import { useEffect, useRef } from 'react';
import { executeAction } from '../components/world/residentActions.js';
import { nextStopIndex, routeAction, routePause } from '../components/world/residentRoutes.js';

const STAGGER_MS = 1500;
const RECHECK_MS = 3000;
const FAILED_STOP_WAIT_MS = 8000;

/**
 * Walks the world's route residents through their stops in a loop, pausing
 * at each one. A resident stops where she is while the user talks to her or
 * while she is in a conversation, and picks her route up again afterwards;
 * nobody walks while the world is paused or the page is hidden.
 */
export function useResidentRoutes({ enabled, residents, layout, chatResidentId, residentCommands, occupiedSpots, isBusy, isPaused }) {
	const chatResidentRef = useRef(chatResidentId);
	const walkingRef = useRef(new Set());
	const latestRef = useRef(null);

	useEffect(() => {
		latestRef.current = { residents, layout, isBusy, isPaused };
	});

	useEffect(() => {
		chatResidentRef.current = chatResidentId;
		if (chatResidentId !== null && walkingRef.current.has(chatResidentId)) {
			void residentCommands.current.get(chatResidentId)?.stop();
		}
	}, [chatResidentId, residentCommands]);

	const routeIds = (residents ?? []).filter((resident) => resident.behavior === 'route').map((resident) => resident.id).join(',');

	useEffect(() => {
		if (!enabled || !routeIds) return undefined;
		let cancelled = false;
		const timers = new Set();
		const walking = walkingRef.current;
		const commandsByResident = residentCommands.current;
		const latest = () => latestRef.current;
		const sleep = (ms) => new Promise((resolve) => {
			const timer = setTimeout(() => {
				timers.delete(timer);
				resolve();
			}, ms);
			timers.add(timer);
		});
		const held = (residentId) => document.visibilityState === 'hidden' || chatResidentRef.current === residentId || latest().isBusy?.(residentId) || latest().isPaused?.();

		const walk = async (resident, index) => {
			const route = resident.behaviorSettings?.route ?? [];
			if (route.length === 0) return;
			let stopIndex = 0;
			await sleep(index * STAGGER_MS);
			while (!cancelled) {
				const commands = residentCommands.current.get(resident.id);
				if (!commands || held(resident.id)) {
					await sleep(RECHECK_MS);
					continue;
				}
				const stop = route[stopIndex];
				const action = routeAction(stop);
				walking.add(resident.id);
				const result = action.verb === 'go_to_point'
					? await commands.goTo(action.point)
					: await executeAction(action, {
						commands,
						layout: latest().layout,
						fromUser: false,
						zoneAccess: resident.zoneAccess,
						area: resident.behaviorSettings?.area ?? [],
						residentId: resident.id,
						occupiedSpots: occupiedSpots.current,
					});
				walking.delete(resident.id);
				if (cancelled) return;
				if (result.outcome === 'interrupted') {
					await sleep(RECHECK_MS);
					continue;
				}
				if (result.outcome === 'failed') {
					console.warn(`[useResidentRoutes] ${resident.assistant.name} could not reach stop ${stopIndex + 1}: ${result.reason}`);
				}
				await sleep(result.outcome === 'failed' ? FAILED_STOP_WAIT_MS : routePause(stop));
				stopIndex = nextStopIndex(stopIndex, route);
			}
		};

		latest().residents.filter((resident) => resident.behavior === 'route').forEach((resident, index) => { void walk(resident, index); });

		return () => {
			cancelled = true;
			for (const timer of timers) clearTimeout(timer);
			for (const residentId of walking) void commandsByResident.get(residentId)?.stop();
		};
	}, [enabled, routeIds, residentCommands, occupiedSpots]);
}
