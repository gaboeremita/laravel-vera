import { useCallback, useEffect, useRef, useState } from 'react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import { stripForSpeech } from '../utils/parsers.js';
import { speakingSeconds } from '../components/world/residentMotion.js';
import { isFar, turnGap } from '../components/world/residentDetail.js';
import { createLineCache, deliverLine, facesPoint, voicesLine } from '../components/world/worldVoice.js';

const TURN_GAP_MS = 5000;
const SPEECH_LINGER_MS = 4000;
const PAUSED_RECHECK_MS = 500;

/**
 * Runs the conversations residents start with each other while the user is
 * in the world: one line at a time from whoever did not speak last, at least
 * five seconds apart, until the server stops it for now or the user stops
 * it. Both residents are busy for as long as it runs; a paused world holds
 * it mid-way.
 */
export function useResidentConversations({ enabled, paused = false, voiceEnabled = false, worldId, sessionId, residents, residentCommands, residentPositions, residentVoices, getPositions, getView, onSpeech, addToast }) {
	const conversationsRef = useRef(new Map());
	const [conversations, setConversations] = useState([]);
	const latestRef = useRef({});
	const pausedRef = useRef(paused);
	const lineCacheRef = useRef(null);

	useEffect(() => {
		pausedRef.current = paused;
	}, [paused]);

	useEffect(() => {
		latestRef.current = { residents, getPositions, getView, onSpeech, addToast, voiceEnabled };
	});

	const publish = useCallback(() => {
		setConversations([...conversationsRef.current.values()].map((conversation) => ({ ...conversation, lines: [...conversation.lines] })));
	}, []);

	const nameOf = (residentId) => latestRef.current.residents?.find((resident) => resident.id === residentId)?.assistant.name ?? 'Someone';

	const speak = useCallback(async (residentId, text, pose = null, { toUser = false } = {}) => {
		latestRef.current.onSpeech(residentId, text);
		const resident = latestRef.current.residents?.find((candidate) => candidate.id === residentId);
		const position = residentPositions.current.get(residentId);
		const user = latestRef.current.getPositions().user;
		const spoken = stripForSpeech(text);
		const commands = residentCommands.current.get(residentId);
		const seconds = await deliverLine({
			voiced: Boolean(resident && position && user && spoken && voicesLine({
				voiceEnabled: latestRef.current.voiceEnabled,
				distance: Math.hypot(position.x - user.x, position.z - user.z),
				facing: facesPoint(latestRef.current.getView?.(), position),
				toUser,
			})),
			synthesize: async () => {
				lineCacheRef.current ??= createLineCache();
				const key = `${resident.assistant.id}:${spoken}`;
				const cached = lineCacheRef.current.get(key);
				if (cached) return cached;
				const response = await api.post(route('voice.synthesize', { assistant: resident.assistant.id }), { text: spoken });
				if (!response.ok) return null;
				const audio = await response.blob();
				lineCacheRef.current.set(key, audio);
				return audio;
			},
			play: (audio) => residentVoices.current.get(residentId)?.(audio),
			gesture: () => { if (pose) void commands?.gesture(pose); },
			estimate: () => speakingSeconds(spoken),
			talk: (duration) => commands?.talk(duration),
			onError: (error) => console.error('[useResidentConversations] could not voice a line', error),
		});
		setTimeout(() => latestRef.current.onSpeech(residentId, null), seconds * 1000 + SPEECH_LINGER_MS);
		return seconds;
	}, [residentPositions, residentVoices, residentCommands]);

	const finish = useCallback((conversationId) => {
		const conversation = conversationsRef.current.get(conversationId);
		if (!conversation) return;
		conversation.status = 'paused';
		conversationsRef.current.delete(conversationId);
		publish();
	}, [publish]);

	const run = useCallback(async (conversation) => {
		const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
		let wait = TURN_GAP_MS;
		while (conversation.status === 'active') {
			await sleep(wait);
			while (pausedRef.current && conversation.status === 'active') await sleep(PAUSED_RECHECK_MS);
			if (conversation.status !== 'active') return;
			let response;
			try {
				const postures = Object.fromEntries(conversation.residentIds.map((residentId) => [residentId, residentCommands.current.get(residentId)?.posture() ?? 'standing']));
				response = await api.post(route('worlds.sessions.conversations.turns.store', { world: worldId, session: sessionId, conversation: conversation.id }), { positions: latestRef.current.getPositions(), postures });
			} catch (error) {
				latestRef.current.addToast(`${conversation.names.join(' and ')} stopped talking: ${error.message}`, 'error');
				finish(conversation.id);
				return;
			}
			const data = await response.json().catch(() => ({}));
			if (response.status === 429) {
				wait = Math.max(1, data.retryIn ?? 1) * 1000;
				continue;
			}
			if (!response.ok) {
				latestRef.current.addToast(`${conversation.names.join(' and ')} stopped talking: ${data.message || `HTTP ${response.status}`}`, 'error');
				finish(conversation.id);
				return;
			}
			let seconds = 0;
			if (data.message) {
				conversation.lines.push({ id: data.message.id, residentId: data.message.residentId, content: data.message.content });
				publish();
				seconds = await speak(data.message.residentId, data.message.content, data.message.pose);
			}
			if (data.status === 'paused') {
				finish(conversation.id);
				return;
			}
			const user = latestRef.current.getPositions().user;
			const farAway = conversation.residentIds.every((residentId) => isFar(residentPositions.current.get(residentId), user));
			wait = seconds * 1000 + turnGap(TURN_GAP_MS, farAway);
		}
	}, [worldId, sessionId, speak, publish, finish, residentCommands, residentPositions]);

	const start = useCallback(async ({ conversationId, starterId, otherId, line, pose = null }) => {
		if (conversationsRef.current.has(conversationId)) return;
		const other = residentCommands.current.get(otherId);
		const starterPosition = residentPositions.current.get(starterId);
		await other?.stop();
		if (starterPosition) other?.faceToward(starterPosition);
		const conversation = {
			id: conversationId,
			residentIds: [starterId, otherId],
			names: [nameOf(starterId), nameOf(otherId)],
			lines: [{ id: `opening-${conversationId}`, residentId: starterId, content: line }],
			status: 'active',
		};
		conversationsRef.current.set(conversationId, conversation);
		publish();
		await speak(starterId, line, pose);
		void run(conversation);
	}, [residentCommands, residentPositions, speak, run, publish]);

	const stop = useCallback(async (conversationId) => {
		if (!conversationsRef.current.has(conversationId)) return;
		finish(conversationId);
		try {
			await api.post(route('worlds.sessions.conversations.pause', { world: worldId, session: sessionId, conversation: conversationId }));
		} catch (error) {
			console.error('[useResidentConversations] could not stop a conversation', error);
		}
	}, [worldId, sessionId, finish]);

	const conversationOf = useCallback((residentId) => [...conversationsRef.current.values()].find((conversation) => conversation.residentIds.includes(residentId)) ?? null, []);
	const isBusy = useCallback((residentId) => conversationOf(residentId) !== null, [conversationOf]);

	useEffect(() => {
		if (enabled) return undefined;
		for (const conversationId of [...conversationsRef.current.keys()]) void stop(conversationId);
		return undefined;
	}, [enabled, stop]);

	useEffect(() => () => {
		for (const conversationId of [...conversationsRef.current.keys()]) void stop(conversationId);
	}, [stop]);

	return { conversations, start, stop, speak, isBusy, conversationOf };
}
