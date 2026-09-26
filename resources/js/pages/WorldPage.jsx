import { useCallback, useEffect, useRef, useState } from 'react';
import { useNavigate, useOutletContext, useParams, useSearchParams } from 'react-router-dom';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import WorldScene from '../components/world/WorldScene.jsx';
import WorldChat from '../components/world/WorldChat.jsx';
import WorldTrackPlayer from '../components/world/WorldTrackPlayer.jsx';
import { PLAYER_EYE_HEIGHT } from '../components/world/collisionCheck.js';
import { CONVERSATION_END_DISTANCE, conversationRangeState } from '../components/world/conversationRange.js';
import { isTypingTarget } from '../components/world/keyboardFocus.js';
import { RESIDENT_BUSY, executeAction } from '../components/world/residentActions.js';
import { speakingSeconds } from '../components/world/residentMotion.js';
import { stripForSpeech } from '../utils/parsers.js';
import { useResidentAgency } from '../hooks/useResidentAgency.js';
import { useResidentConversations } from '../hooks/useResidentConversations.js';
import ConversationObserverPanel from '../components/world/ConversationObserverPanel.jsx';
import { finishActivity, startActivity } from '../components/world/activityLog.js';
import OffscreenIndicator from '../components/world/OffscreenIndicator.jsx';
import WorldMap from '../components/world/WorldMap.jsx';
import ZoneTitleCard from '../components/world/hud/ZoneTitleCard.jsx';
import LocationReadout from '../components/world/hud/LocationReadout.jsx';
import SwimOverlay from '../components/world/hud/SwimOverlay.jsx';
import PostureHint from '../components/world/hud/PostureHint.jsx';
import FocusPrompt from '../components/world/hud/FocusPrompt.jsx';
import InspectCard from '../components/world/hud/InspectCard.jsx';
import ActivityProgress from '../components/world/hud/ActivityProgress.jsx';
import ActionLine from '../components/world/hud/ActionLine.jsx';
import ControlsLegend from '../components/world/hud/ControlsLegend.jsx';
import PauseOverlay from '../components/world/hud/PauseOverlay.jsx';
import { contextLineFor, usePlayerActivities } from '../hooks/usePlayerActivities.js';
import { fullSpotIds, releaseAllSpots, stackedSpots } from '../components/world/spotOccupancy.js';
import { readVoiceEnabled, storeVoiceEnabled } from '../components/world/worldVoice.js';

const INVITE_MS = 30000;
const LISTEN_DISTANCE = 12;
const NEARBY_CONVERSATION_CHECK_MS = 500;

export default function WorldPage() {
	const { worldId } = useParams();
	const [searchParams] = useSearchParams();
	const sessionId = searchParams.get('session');
	const navigate = useNavigate();
	const { addToast, setHidePortrait } = useOutletContext();
	const [world, setWorld] = useState(null);
	const [session, setSession] = useState(null);
	const [status, setStatus] = useState('loading');
	const [nearbyResident, setNearbyResident] = useState(null);
	const [chatResident, setChatResident] = useState(null);
	const [activePose, setActivePose] = useState(null);
	const [thoughts, setThoughts] = useState({});
	const [speech, setSpeech] = useState({});
	const [invite, setInvite] = useState(null);
	const [observed, setObserved] = useState(null);
	const [nearbyConversation, setNearbyConversation] = useState(null);
	const [paused, setPaused] = useState(false);
	const [voiceEnabled, setVoiceEnabled] = useState(readVoiceEnabled);
	const [voiceUntil, setVoiceUntil] = useState(0);
	const pausedRef = useRef(false);
	const chatResidentRef = useRef(null);
	const userClaimRef = useRef(null);
	const residentClaimsRef = useRef(new Map());
	const latestPosition = useRef(null);
	const residentPositions = useRef(new Map());
	const residentVoices = useRef(new Map());
	const playerView = useRef(null);
	const offscreenIndicator = useRef(null);
	const navigation = useRef(null);
	const residentCommands = useRef(new Map());
	const occupiedSpots = useRef(new Map());
	const [floorMaps, setFloorMaps] = useState([]);
	const [mapExpanded, setMapExpanded] = useState(false);
	const [conversationRange, setConversationRange] = useState('ok');
	const playerState = useRef(null);
	const playerCommands = useRef(null);
	const collisionWorldRef = useRef(null);
	const actionSender = useRef(null);
	const focusLabelRef = useRef(null);
	const [location, setLocation] = useState(null);
	const [titleCard, setTitleCard] = useState(null);
	const [pendingTitleCard, setPendingTitleCard] = useState(null);
	const [movement, setMovement] = useState('walking');
	const [focusedObject, setFocusedObject] = useState(null);
	const [nearbyObjectIds, setNearbyObjectIds] = useState([]);

	useEffect(() => {
		setHidePortrait(true);
		return () => setHidePortrait(false);
	}, [setHidePortrait]);

	useEffect(() => {
		const load = async () => {
			try {
				const response = await api.get(route('worlds.show', { world: worldId }));
				if (!response.ok) throw new Error('World unavailable');
				const data = await response.json();
				if (!data.environmentUrl) throw new Error('This world has no environment asset.');
				let selectedSession = null;

				if (sessionId) {
					const sessionsResponse = await api.get(route('worlds.sessions.index', { world: worldId }));
					if (!sessionsResponse.ok) throw new Error('World session unavailable');
					const sessions = await sessionsResponse.json();
					selectedSession = sessions.find((item) => String(item.id) === String(sessionId)) ?? null;
					if (!selectedSession) throw new Error('World session unavailable');
				}

				setSession(selectedSession);
				setWorld(data);
				setStatus('entering');
			} catch (error) { addToast(error.message || 'Failed to load world', 'error'); setStatus('error'); }
		};
		void load();
	}, [addToast, worldId, sessionId]);

	const persistPosition = useCallback(async () => {
		if (!sessionId || !latestPosition.current) return;
		const [x, y, z] = latestPosition.current;
		const response = await api.put(route('worlds.sessions.position.update', { world: worldId, session: sessionId }), { position: { x, y, z } });
		if (response.status === 404) navigate(`/worlds/${worldId}/sessions`);
	}, [worldId, sessionId, navigate]);

	useEffect(() => {
		latestPosition.current = null;
	}, [sessionId]);

	useEffect(() => {
		if (!sessionId) return undefined;
		const interval = setInterval(persistPosition, 10000);
		return () => {
			clearInterval(interval);
			void persistPosition();
		};
	}, [sessionId, persistPosition]);

	const persistResidentStates = useCallback(async () => {
		if (!sessionId) return;
		await Promise.all([...residentCommands.current].map(async ([residentId, commands]) => {
			try {
				const response = await api.put(route('worlds.sessions.residents.state.update', { world: worldId, session: sessionId, resident: residentId }), commands.state());
				if (!response.ok) throw new Error(`HTTP ${response.status}`);
			} catch (error) {
				console.error(`[WorldPage] could not save resident ${residentId}'s state`, error);
			}
		}));
	}, [worldId, sessionId]);

	useEffect(() => {
		if (!sessionId) return undefined;
		const interval = setInterval(persistResidentStates, 10000);
		return () => {
			clearInterval(interval);
			void persistResidentStates();
		};
	}, [sessionId, persistResidentStates]);

	const exit = useCallback(() => {
		releaseAllSpots(occupiedSpots.current, 'user');
		persistPosition();
		void persistResidentStates();
		navigate(`/worlds/${worldId}/sessions`);
	}, [navigate, persistPosition, persistResidentStates, worldId]);
	useEffect(() => {
		pausedRef.current = paused;
		if (paused && document.activeElement instanceof HTMLElement) document.activeElement.blur();
	}, [paused]);

	// Registered before any other world key handler, so while paused P is
	// the only world key that does anything.
	useEffect(() => {
		const keyDown = (event) => {
			if (isTypingTarget(event.target)) return;
			if (event.code === 'KeyP') {
				event.stopImmediatePropagation();
				if (!event.repeat) setPaused((current) => !current);
				return;
			}
			if (pausedRef.current) event.stopImmediatePropagation();
		};
		window.addEventListener('keydown', keyDown, true);
		return () => window.removeEventListener('keydown', keyDown, true);
	}, []);

	useEffect(() => {
		chatResidentRef.current = chatResident;
	}, [chatResident]);

	// The user is busy with whoever they chat with, or with the one resident
	// on her way to talk to them or waiting for an answer.
	const getUserBusyWith = useCallback(() => chatResidentRef.current?.id ?? userClaimRef.current, []);
	const claimUser = useCallback((residentId) => {
		const busyWith = getUserBusyWith();
		if (busyWith !== null && busyWith !== residentId) return false;
		userClaimRef.current = residentId;
		return true;
	}, [getUserBusyWith]);
	const releaseUser = useCallback((residentId) => {
		if (userClaimRef.current === residentId) userClaimRef.current = null;
	}, []);

	const openChat = useCallback((resident) => {
		setChatResident(resident);
		setInvite((current) => (current?.residentId === resident.id ? null : current));
	}, []);
	const closeChat = useCallback(() => setChatResident(null), []);
	const handlePlayerPositionChange = useCallback((position) => { latestPosition.current = position; }, []);
	const getPositions = useCallback(() => {
		const residents = {};
		for (const [residentId, position] of residentPositions.current) residents[residentId] = { x: position.x, y: position.y, z: position.z };
		const foot = playerState.current?.footPosition;
		if (foot) return { user: { x: foot.x, y: foot.y, z: foot.z }, residents };
		const eye = latestPosition.current;
		return eye ? { user: { x: eye[0], y: eye[1] - PLAYER_EYE_HEIGHT, z: eye[2] }, residents } : { residents };
	}, []);
	const getUserState = useCallback(() => {
		const state = playerState.current;
		return state ? { posture: state.posture, spotId: state.spotId, activityId: state.activityId } : null;
	}, []);
	const getOccupiedSpots = useCallback((residentId) => fullSpotIds(occupiedSpots.current, world?.layout, residentId), [world?.layout]);
	const getStackedSpots = useCallback(() => stackedSpots(occupiedSpots.current), []);
	const handleWorldReady = useCallback(() => setStatus('ready'), []);
	const handleWorldError = useCallback((error) => {
		addToast(error?.message || 'Failed to initialize world', 'error');
		setStatus('error');
	}, [addToast]);

	useEffect(() => {
		if (!chatResident) return undefined;
		let frame = null;
		let lastState = null;
		const checkRange = () => {
			const eye = latestPosition.current;
			const residentPosition = residentPositions.current.get(chatResident.id);
			if (eye && residentPosition) {
				const state = conversationRangeState(Math.hypot(eye[0] - residentPosition.x, eye[2] - residentPosition.z));
				if (state !== lastState) {
					lastState = state;
					setConversationRange(state);
				}
				if (state === 'ended') {
					addToast(`${chatResident.assistant.name} is out of talking range (more than ${CONVERSATION_END_DISTANCE} m away)`, 'info');
					setConversationRange('ok');
					closeChat();
					return;
				}
			}
			frame = requestAnimationFrame(checkRange);
		};
		frame = requestAnimationFrame(checkRange);
		return () => cancelAnimationFrame(frame);
	}, [chatResident, closeChat, addToast]);

	useEffect(() => {
		const keyDown = (event) => {
			if (event.code !== 'KeyM' || isTypingTarget(event.target)) return;
			setMapExpanded((expanded) => !expanded);
		};
		window.addEventListener('keydown', keyDown);
		return () => window.removeEventListener('keydown', keyDown);
	}, []);

	const toggleVoice = useCallback(() => {
		setVoiceEnabled((enabled) => {
			storeVoiceEnabled(!enabled);
			return !enabled;
		});
	}, []);

	useEffect(() => {
		const keyDown = (event) => {
			if (event.code !== 'KeyO' || event.repeat || isTypingTarget(event.target)) return;
			toggleVoice();
		};
		window.addEventListener('keydown', keyDown);
		return () => window.removeEventListener('keydown', keyDown);
	}, [toggleVoice]);

	const hasMultipleFloors = (world?.layout?.floors?.length ?? 0) > 1;
	const handleLocationChange = useCallback(({ floor, zone, zoneChain, announce }) => {
		setLocation({ floor, zone, zoneChain });
		if (!announce || !zone) return;
		const card = { key: crypto.randomUUID(), zoneName: zone.name, contextLine: contextLineFor(world.layout, zone, { withFloor: hasMultipleFloors }) };
		if (mapExpanded) setPendingTitleCard(card);
		else setTitleCard(card);
	}, [world, hasMultipleFloors, mapExpanded]);

	if (!mapExpanded && pendingTitleCard) {
		setTitleCard(pendingTitleCard);
		setPendingTitleCard(null);
	}

	const getResidentPosture = useCallback((residentId) => residentCommands.current.get(residentId)?.posture() ?? 'standing', []);
	const getResidentState = useCallback((residentId) => residentCommands.current.get(residentId)?.bodyState() ?? null, []);

	const getFollowTarget = useCallback(() => {
		const view = playerView.current;
		if (!view) return null;
		return { x: view.x + Math.sin(view.yaw), y: view.y, z: view.z + Math.cos(view.yaw) };
	}, []);

	const runResidentAction = useCallback(async (resident, action, { reason = null, narration = null, fromUser = true } = {}) => {
		const log = { worldId, sessionId, residentId: resident.id };
		const name = resident.assistant.name;
		const record = async (entry) => {
			if (!sessionId) return null;
			try {
				return await startActivity(log, { ...entry, position: residentPositions.current.get(resident.id) });
			} catch (error) {
				console.error(`[WorldPage] could not record ${name}'s ${entry.verb} action`, error);
				addToast(`Could not record ${name}'s action (${error.message}), so she won't remember it`, 'error');
				return null;
			}
		};
		const report = async (activityId, result) => {
			if (!activityId) return;
			try {
				await finishActivity(log, activityId, result);
			} catch (error) {
				console.error(`[WorldPage] could not report the outcome of ${name}'s action`, error);
				addToast(`Could not save how ${name}'s action went (${error.message})`, 'error');
			}
		};

		const activityId = await record({ verb: action.verb, target: action.target ?? null, activity: action.activity ?? null, reason, narration });
		const result = await executeAction(action, {
			commands: residentCommands.current.get(resident.id),
			layout: world?.layout,
			getFollowTarget,
			fromUser,
			zoneAccess: resident.zoneAccess,
			residentId: resident.id,
			occupiedSpots: occupiedSpots.current,
			onStepStart: (step, index, total) => record({ verb: step.verb, target: step.verb === 'do' ? step.description : step.target, activity: step.activity, reason: `step ${index + 1} of ${total} of ${action.target}` }),
			onStepEnd: (step, index, stepResult, stepActivityId) => report(stepActivityId, stepResult),
		});
		if (result.outcome === 'failed') {
			const attempted = action.verb === 'plan' ? action.target : action.target ? `${action.verb.replace('_', ' ')} ${action.target}` : action.verb;
			console.error(`[WorldPage] ${name} could not ${attempted}: ${result.reason}`);
			addToast(`${name} couldn't ${attempted}: ${result.reason}`, 'error');
		}
		await report(activityId, result);
	}, [worldId, sessionId, world, getFollowTarget, addToast]);

	const handleChatAction = useCallback((action, narration) => {
		if (chatResident) void runResidentAction(chatResident, action, { narration });
	}, [chatResident, runResidentAction]);

	useEffect(() => {
		if (!chatResident) return undefined;
		const keyDown = (event) => {
			if (isTypingTarget(event.target)) return;
			if (event.code === 'KeyF') void runResidentAction(chatResident, { verb: 'follow' }, { reason: 'direct control' });
			if (event.code === 'KeyX') void runResidentAction(chatResident, { verb: 'stop' }, { reason: 'direct control' });
		};
		window.addEventListener('keydown', keyDown);
		return () => window.removeEventListener('keydown', keyDown);
	}, [chatResident, runResidentAction]);

	const handleResidentVoice = useCallback((seconds) => {
		setVoiceUntil((current) => Math.max(current, Date.now() + seconds * 1000));
	}, []);

	const playResidentVoice = useCallback(async (audioBlob) => {
		const playPositional = chatResident && residentVoices.current.get(chatResident.id);
		const duration = playPositional ? await playPositional(audioBlob) : null;
		if (duration !== null) return duration;
		const url = URL.createObjectURL(audioBlob);
		const player = new Audio(url);
		player.addEventListener('ended', () => URL.revokeObjectURL(url), { once: true });
		await player.play();
		const seconds = Number.isFinite(player.duration) ? player.duration : 0;
		handleResidentVoice(seconds);
		return seconds;
	}, [chatResident, handleResidentVoice]);

	const handleSilentReply = useCallback((text) => {
		if (chatResident) residentCommands.current.get(chatResident.id)?.talk(speakingSeconds(stripForSpeech(text)));
	}, [chatResident]);

	const activeSession = sessionId && String(session?.id) === String(sessionId) ? session : null;
	const handleThought = useCallback((residentId, line) => setThoughts((current) => ({ ...current, [residentId]: line })), []);
	const handleSpeech = useCallback((residentId, line) => {
		setSpeech((current) => ({ ...current, [residentId]: line }));
		if (line) setThoughts((current) => ({ ...current, [residentId]: null }));
	}, []);
	const getResidentPosition = useCallback((residentId) => residentPositions.current.get(residentId) ?? null, []);
	const residentConversations = useResidentConversations({
		enabled: status === 'ready',
		paused,
		voiceEnabled,
		worldId,
		sessionId,
		residents: world?.residents,
		residentCommands,
		residentPositions,
		residentVoices,
		getPositions,
		onSpeech: handleSpeech,
		addToast,
	});
	const { speak: speakAloud, start: startResidentConversation, stop: stopResidentConversation, isBusy: isInResidentConversation, conversationOf: residentConversationOf } = residentConversations;

	// A resident is busy while she talks with someone, is on her way to talk
	// to someone, or has someone on the way to talk to her. Claims map the
	// resident being walked to onto the one walking over.
	const isResidentBusy = useCallback((residentId) => isInResidentConversation(residentId)
		|| residentClaimsRef.current.has(residentId)
		|| [...residentClaimsRef.current.values()].includes(residentId), [isInResidentConversation]);
	const claimTarget = useCallback((claimerId, target) => {
		if (target === 'user') return claimUser(claimerId);
		const targetId = Number(target);
		if (residentClaimsRef.current.get(targetId) === claimerId) return true;
		if (isResidentBusy(targetId)) return false;
		residentClaimsRef.current.set(targetId, claimerId);
		return true;
	}, [claimUser, isResidentBusy]);
	const releaseTarget = useCallback((claimerId, target) => {
		if (target === 'user') {
			releaseUser(claimerId);
			return;
		}
		const targetId = Number(target);
		if (residentClaimsRef.current.get(targetId) === claimerId) residentClaimsRef.current.delete(targetId);
	}, [releaseUser]);
	const getBusyResidents = useCallback(() => {
		const busy = new Map();
		for (const conversation of residentConversations.conversations) {
			const [first, second] = conversation.residentIds;
			busy.set(first, second);
			busy.set(second, first);
		}
		for (const [targetId, claimerId] of residentClaimsRef.current) {
			busy.set(targetId, claimerId);
			busy.set(claimerId, targetId);
		}
		return [...busy].map(([id, talkingWith]) => ({ id, talkingWith }));
	}, [residentConversations.conversations]);
	const handleSpeak = useCallback(async (residentId, action) => {
		const resident = world?.residents?.find((candidate) => candidate.id === residentId);
		if (!resident) return false;
		try {
			if (action.target === 'user') {
				const response = await api.post(route('worlds.sessions.residents.observations.store', { world: worldId, session: sessionId, resident: residentId }), { line: action.line, expression: action.expression ?? null });
				if (!response.ok) throw new Error(`HTTP ${response.status}`);
				setInvite({ residentId, name: resident.assistant.name, key: Date.now() });
				await speakAloud(residentId, action.line, action.pose ?? null);
				return true;
			}
			const otherId = Number(action.target);
			const response = await api.post(route('worlds.sessions.residents.conversations.store', { world: worldId, session: sessionId, resident: residentId }), { with: otherId, line: action.line, expression: action.expression ?? null });
			if (response.status === 409) return RESIDENT_BUSY;
			if (!response.ok) throw new Error(`HTTP ${response.status}`);
			const { conversationId } = await response.json();
			await startResidentConversation({ conversationId, starterId: residentId, otherId, line: action.line, pose: action.pose ?? null });
			return true;
		} catch (error) {
			console.error(`[WorldPage] ${resident.assistant.name} could not start talking`, error);
			addToast(`${resident.assistant.name} couldn't start talking (${error.message})`, 'error');
			return false;
		}
	}, [world, worldId, sessionId, speakAloud, startResidentConversation, addToast]);
	useResidentAgency({
		enabled: status === 'ready',
		worldId,
		sessionId,
		residents: world?.residents,
		layout: world?.layout,
		chatResidentId: chatResident?.id ?? null,
		residentCommands,
		occupiedSpots,
		getPositions,
		getFollowTarget,
		getResidentPosition,
		getUserState,
		getUserBusyWith,
		getBusyResidents,
		claimTarget,
		releaseTarget,
		isBusy: isResidentBusy,
		isPaused: () => pausedRef.current,
		onSpeak: handleSpeak,
		onThought: handleThought,
		addToast,
	});

	useEffect(() => {
		if (!chatResident) return;
		const conversation = residentConversationOf(chatResident.id);
		if (conversation) void stopResidentConversation(conversation.id);
		releaseUser(chatResident.id);
	}, [chatResident, residentConversationOf, stopResidentConversation, releaseUser]);

	useEffect(() => {
		if (!invite) return undefined;
		const timer = setTimeout(() => {
			setInvite((current) => (current?.key === invite.key ? null : current));
			releaseUser(invite.residentId);
		}, INVITE_MS);
		return () => clearTimeout(timer);
	}, [invite, releaseUser]);

	useEffect(() => {
		const interval = setInterval(() => {
			const user = getPositions().user;
			const distanceTo = (conversation) => Math.min(...conversation.residentIds.map((residentId) => {
				const position = residentPositions.current.get(residentId);
				return position ? Math.hypot(position.x - user.x, position.z - user.z) : Infinity;
			}));
			const near = user
				? residentConversations.conversations
					.map((conversation) => ({ conversation, distance: distanceTo(conversation) }))
					.filter(({ distance }) => distance <= LISTEN_DISTANCE)
					.sort((a, b) => a.distance - b.distance)[0]?.conversation ?? null
				: null;
			setNearbyConversation((current) => (current?.id === near?.id ? current : near ? { id: near.id, names: near.names } : null));
		}, NEARBY_CONVERSATION_CHECK_MS);
		return () => clearInterval(interval);
	}, [getPositions, residentConversations.conversations]);

	useEffect(() => {
		const keyDown = (event) => {
			if (event.code !== 'KeyL' || event.repeat || isTypingTarget(event.target)) return;
			if (observed) setObserved(null);
			else if (nearbyConversation && !chatResident) setObserved(nearbyConversation);
		};
		window.addEventListener('keydown', keyDown);
		return () => window.removeEventListener('keydown', keyDown);
	}, [observed, nearbyConversation, chatResident]);

	const observedConversation = observed ? residentConversations.conversations.find((conversation) => conversation.id === observed.id) ?? null : null;
	const residentNames = new Map((world?.residents ?? []).map((resident) => [resident.id, resident.assistant.name]));
	const isRequestedSessionLoaded = sessionId ? activeSession !== null : session === null;
	const player = usePlayerActivities({
		world,
		worldId,
		sessionId,
		location,
		focusedObject,
		occupiedSpots,
		playerState,
		playerCommands,
		residentPositions,
		residentCommands,
		collisionWorldRef,
		chatResident,
		actionSender,
		addToast,
	});
	const hasZones = (world?.layout?.zones?.length ?? 0) > 0;
	const readoutText = location?.zone
		? [location.zone.name, hasMultipleFloors ? location.floor?.name : null].filter(Boolean).join(' · ')
		: world?.name;
	const [lastCardKey, setLastCardKey] = useState(null);
	if (player.cardKey && player.cardKey !== lastCardKey) setLastCardKey(player.cardKey);
	const postureHint = player.activity?.kind === 'resting' ? 'SPACE — GET UP' : movement === 'crouching' ? 'Q — STAND UP' : null;

	if (status === 'error') return <div className="flex h-full items-center justify-center bg-bg-0"><button className="button-primary" onClick={exit}>RETURN TO WORLDS</button></div>;
	if (!world || !isRequestedSessionLoaded) return <div className="flex h-full items-center justify-center bg-bg-0 text-fg-3 text-sm tracking-[0.1em]">LOADING WORLD...</div>;

	return (
		<div className="flex h-full min-h-0 overflow-hidden bg-black">
			<div className="relative flex-1 min-w-0">
				{observed && !chatResident && (
					<div className="absolute left-5 top-16 bottom-5 z-20 w-[min(26rem,40%)] min-w-72">
						<ConversationObserverPanel key={observed.id} worldId={worldId} sessionId={sessionId} conversationId={observed.id} names={observed.names} liveLines={observedConversation?.lines ?? []} residentNames={residentNames} active={observedConversation !== null} onStop={() => void stopResidentConversation(observed.id)} onClose={() => setObserved(null)} />
					</div>
				)}
				{invite && !chatResident && (
					<div className="absolute left-1/2 top-5 z-20 -translate-x-1/2 border border-accent bg-bg-0/90 px-4 py-2 text-accent text-[0.7rem] tracking-[0.1em]">
						{invite.name.toUpperCase()} IS TALKING TO YOU — C TO ANSWER
					</div>
				)}
				{chatResident && (
					<div className="absolute left-5 top-16 bottom-5 z-20 w-[min(26rem,40%)] min-w-72">
						<WorldChat world={world} resident={chatResident} onClose={closeChat} addToast={addToast} onPoseTrigger={setActivePose} worldSessionId={sessionId} getPositions={getPositions} getResidentPosture={getResidentPosture} getResidentState={getResidentState} getUserState={getUserState} getOccupiedSpots={getOccupiedSpots} getStackedSpots={getStackedSpots} onVoiceAudio={playResidentVoice} onSilentReply={handleSilentReply} onAction={handleChatAction} actionSender={actionSender} />
					</div>
				)}
				{chatResident && conversationRange === 'warning' && (
					<div className="absolute left-1/2 top-5 z-20 -translate-x-1/2 border border-line-1 bg-bg-0/90 px-4 py-2 text-fg-2 text-[0.7rem] tracking-[0.1em]">
						YOU ARE WALKING AWAY FROM {chatResident.assistant.name.toUpperCase()}
					</div>
				)}
				<WorldTrackPlayer trackUrl={world.trackUrl} isActive={status === 'ready' && !paused} voiceUntil={voiceUntil} />
				<WorldScene key={`${world.id}:${world.environmentUrl}:${sessionId ?? 'default'}`} world={world} explorationEnabled={status === 'ready' && !paused} paused={paused} onResidentVoice={handleResidentVoice} onReady={handleWorldReady} onError={handleWorldError} onResidentChange={setNearbyResident} onInteract={openChat} activePose={activePose} initialPosition={activeSession?.position} onPlayerPositionChange={handlePlayerPositionChange} residentPositions={residentPositions} residentVoices={residentVoices} activeResidentId={chatResident?.id ?? null} onEndConversation={closeChat} playerView={playerView} offscreenIndicator={offscreenIndicator} onFloorMaps={setFloorMaps} navigation={navigation} residentCommands={residentCommands} occupiedSpots={occupiedSpots} residentStates={activeSession?.residentStates ?? {}} thoughts={thoughts} speech={speech} playerState={playerState} playerCommands={playerCommands} collisionWorldRef={collisionWorldRef} onMovementChange={setMovement} onGetUpIntent={player.getUp} onMoveIntent={player.cancel} onLocationChange={handleLocationChange} focusLabelRef={focusLabelRef} focusedObjectId={focusedObject?.id ?? null} nearbyObjectIds={nearbyObjectIds} onFocusChange={setFocusedObject} onNearbyChange={setNearbyObjectIds} watchedObjectId={player.cardObjectId} onWatchedOutOfReach={player.closeCard} />
				{status === 'ready' && <WorldMap layout={world.layout} floorMaps={floorMaps} playerView={playerView} residents={world.residents} residentPositions={residentPositions} activeResidentId={chatResident?.id ?? null} expanded={mapExpanded} onClose={() => setMapExpanded(false)} header={<div className="flex flex-col items-end gap-1.5"><ControlsLegend hasZones={hasZones} />{hasZones && readoutText && <LocationReadout text={readoutText} />}</div>} />}
				{status === 'ready' && (
					<>
						<SwimOverlay active={movement === 'swimming'} />
						<FocusPrompt object={focusedObject} labelRef={focusLabelRef} hidden={player.cardView !== null} />
						{titleCard && <ZoneTitleCard key={titleCard.key} zoneName={titleCard.zoneName} contextLine={titleCard.contextLine} onDone={() => setTitleCard(null)} />}
						<InspectCard key={player.cardKey ?? lastCardKey ?? 'closed'} card={player.cardView} highlightedIndex={player.highlightedIndex} onHighlight={player.setHighlightedIndex} onChoose={(index) => void player.chooseRow(index)} />
						{player.activity && player.activity.kind !== 'resting' && (
							<ActivityProgress key={player.activity.key} activityName={player.activity.activity.name} durationMs={player.activityMs} cancelled={player.activity.cancelled} paused={paused} onComplete={player.complete} onFinished={player.finish} />
						)}
						<div className="pointer-events-none absolute bottom-6 left-1/2 z-20 flex -translate-x-1/2 flex-col items-center gap-3">
							<ActionLine entries={player.hudEntries} onExpire={player.expireEntry} />
							<PostureHint hint={postureHint} />
						</div>
					</>
				)}
				{chatResident && <OffscreenIndicator ref={offscreenIndicator} name={chatResident.assistant.name} />}
				<PauseOverlay paused={paused} onResume={() => setPaused(false)} onExit={exit} />
				<div className={`absolute inset-0 z-10 flex items-center justify-center overflow-hidden transition-opacity duration-700 ${status !== 'ready' ? 'opacity-100' : 'opacity-0 pointer-events-none'}`}>
					{world.cardImageUrl && (
						<img src={world.cardImageUrl} alt="" className="absolute inset-0 w-full h-full object-cover object-top scale-105 blur-sm brightness-[0.35]" />
					)}
					<div className="absolute inset-0 bg-bg-0/60" />
					<span className="relative text-fg-2 text-sm tracking-[0.12em] animate-fade-in">INITIALIZING {world.name.toUpperCase()}...</span>
				</div>
				<div className="absolute left-5 top-5 z-10 flex items-center gap-3">
					<button type="button" onClick={exit} className="border border-line-1 bg-bg-0/90 px-3 py-2 text-fg-2 text-[0.7rem] tracking-[0.1em] hover:text-fg-1">EXIT WORLD</button>
					{status === 'ready' && <button type="button" onClick={() => setPaused(true)} className="border border-line-1 bg-bg-0/90 px-3 py-2 text-fg-2 text-[0.7rem] tracking-[0.1em] hover:text-fg-1">P — PAUSE</button>}
					{status === 'ready' && <button type="button" onClick={toggleVoice} aria-pressed={voiceEnabled} className="border border-line-1 bg-bg-0/90 px-3 py-2 text-fg-2 text-[0.7rem] tracking-[0.1em] hover:text-fg-1">O — VOICE {voiceEnabled ? 'ON' : 'OFF'}</button>}
					{chatResident && (
						<>
							<button type="button" onClick={() => void runResidentAction(chatResident, { verb: 'follow' }, { reason: 'direct control' })} className="border border-line-1 bg-bg-0/90 px-3 py-2 text-fg-2 text-[0.7rem] tracking-[0.1em] hover:text-fg-1">F — FOLLOW ME</button>
							<button type="button" onClick={() => void runResidentAction(chatResident, { verb: 'stop' }, { reason: 'direct control' })} className="border border-line-1 bg-bg-0/90 px-3 py-2 text-fg-2 text-[0.7rem] tracking-[0.1em] hover:text-fg-1">X — STOP</button>
						</>
					)}
					{nearbyResident && !chatResident && <button type="button" onClick={() => openChat(nearbyResident)} className="button-primary text-[0.7rem]">C — CHAT WITH {nearbyResident.assistant.name.toUpperCase()}</button>}
					{nearbyConversation && !chatResident && !observed && <button type="button" onClick={() => setObserved(nearbyConversation)} className="border border-line-1 bg-bg-0/90 px-3 py-2 text-fg-2 text-[0.7rem] tracking-[0.1em] hover:text-fg-1">L — LISTEN IN ON {nearbyConversation.names.join(' & ').toUpperCase()}</button>}
				</div>
			</div>
		</div>
	);
}
