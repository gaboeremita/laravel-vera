import { useCallback, useEffect, useState } from 'react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';

const LOG_LENGTH = 20;

async function readBody(response) {
	if (response.status === 204) return {};
	try {
		return await response.json();
	} catch {
		return {};
	}
}

/**
 * The Creator menu's state in a world session: whether it is open and
 * unlocked, the overview of the world it shows, and the log of changes made
 * from it. `run` sends one change, logs it and refreshes the overview.
 */
export default function useCreatorMenu({ worldId, sessionId, initiallyUnlocked, addToast }) {
	const [open, setOpen] = useState(false);
	const [unlockedHere, setUnlockedHere] = useState(false);
	const [overview, setOverview] = useState(null);
	const [refreshKey, setRefreshKey] = useState(0);
	const [log, setLog] = useState([]);
	const unlocked = initiallyUnlocked || unlockedHere;

	useEffect(() => {
		if (!open || !unlocked || !sessionId) return undefined;
		let cancelled = false;
		const load = async () => {
			try {
				const response = await api.get(route('worlds.sessions.creator.show', { world: worldId, session: sessionId }));
				const body = await readBody(response);
				if (!response.ok) throw new Error(body.message || 'Unable to load the Creator menu');
				if (!cancelled) setOverview(body);
			} catch (error) {
				if (!cancelled) addToast(error.message, 'error');
			}
		};
		void load();
		return () => {
			cancelled = true;
		};
	}, [open, unlocked, refreshKey, worldId, sessionId, addToast]);

	const toggle = useCallback(() => setOpen((current) => !current), []);
	const close = useCallback(() => setOpen(false), []);
	const refresh = useCallback(() => setRefreshKey((current) => current + 1), []);

	const unlock = useCallback(async (password) => {
		const response = await api.post(route('worlds.sessions.creator.unlock', { world: worldId, session: sessionId }), { password });
		const body = await readBody(response);
		if (!response.ok) return { ok: false, message: body.message || body.errors?.password?.[0] || 'Unable to unlock the Creator menu' };
		setUnlockedHere(true);
		return { ok: true, message: null };
	}, [worldId, sessionId]);

	const addLogLine = useCallback((text) => {
		setLog((current) => [{ id: `${Date.now()}-${Math.random()}`, text }, ...current].slice(0, LOG_LENGTH));
	}, []);

	/** Sends a change; resolves to the response body, or to false after toasting why it failed. */
	const run = useCallback(async (text, sendRequest) => {
		try {
			const response = await sendRequest();
			const body = await readBody(response);
			if (!response.ok) throw new Error(body.message || `Unable to ${text}`);
			addLogLine(text);
			refresh();
			return body;
		} catch (error) {
			addToast(error.message, 'error');
			return false;
		}
	}, [addLogLine, refresh, addToast]);

	return { open, toggle, close, unlocked, unlock, overview, refresh, log, addLogLine, run };
}
