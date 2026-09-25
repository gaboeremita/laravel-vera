import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import { isTypingTarget } from '../components/world/keyboardFocus.js';
import { actionLine, getUpLine, observationGetUpLine, observationLine } from '../components/world/activityLines.js';
import { spotAvailability } from '../components/world/objectFocus.js';
import { selectOnlookers } from '../components/world/onlookers.js';
import { activityKind, nearestFreeSpot } from '../components/world/playerActivities.js';
import { floorAt, zoneChain } from '../components/world/worldLocation.js';

const ACTIVITY_MS = 3000;
const OCCUPANCY_REFRESH_MS = 500;
const USER_EYE_FOR_SIGHT = 1.2;

function uniqueActivities(object) {
	const seen = new Map();
	for (const spot of object.spots) {
		for (const activity of spot.activities) if (!seen.has(activity.id)) seen.set(activity.id, activity);
	}
	return [...seen.values()];
}

function availabilityText({ free, total, takenBy }) {
	if (total === 1) {
		if (free === 1) return 'FREE';
		return takenBy[0] === 'YOU' ? 'IN USE · YOU' : `TAKEN · ${takenBy[0].toUpperCase()}`;
	}
	if (free > 0) return `${free} OF ${total} FREE`;
	return takenBy.includes('YOU') ? 'IN USE · YOU' : `ALL ${total} TAKEN`;
}

/** A zone's ancestors, or the zone itself with them, then its floor, joined for a context line. */
export function contextLineFor(layout, zone, { withFloor, includeZone = false }) {
	if (!zone) return '';
	const chain = zoneChain(layout, zone);
	const parts = (includeZone ? chain : chain.slice(0, -1)).map((ancestor) => ancestor.name);
	const floor = withFloor ? layout.floors?.find((candidate) => candidate.id === zone.floorId) : null;
	if (floor) parts.push(floor.name);
	return parts.join(' · ');
}

/**
 * The user's side of the world's activities: object and zone cards, starting
 * and leaving activities, and telling the residents who saw it.
 */
export function usePlayerActivities({ world, worldId, sessionId, location, focusedObject, occupiedSpots: occupiedSpotsRef, playerState: playerStateRef, playerCommands: playerCommandsRef, residentPositions: residentPositionsRef, residentCommands: residentCommandsRef, collisionWorldRef, chatResident, actionSender: actionSenderRef, addToast }) {
	const layout = world?.layout;
	const withFloor = (layout?.floors?.length ?? 0) > 1;
	const [card, setCard] = useState(null);
	const [highlightedIndex, setHighlightedIndex] = useState(0);
	const [activity, setActivity] = useState(null);
	const [hudEntries, setHudEntries] = useState([]);
	const [occupancy, setOccupancy] = useState(() => new Map());
	const gettingUp = useRef(false);

	const cardZone = card?.kind === 'zone' ? layout?.zones?.find((zone) => zone.id === card.id) ?? null : null;
	const cardObject = card?.kind === 'object' ? layout?.objects?.find((object) => object.id === card.id) ?? null : null;
	if (card?.kind === 'zone' && location?.zone?.id !== card.id) setCard(null);

	const residentNames = useMemo(() => new Map((world?.residents ?? []).map((resident) => [resident.id, resident.assistant.name])), [world]);
	let cardView = null;
	if (cardObject) {
		const objectZone = layout.zones?.find((zone) => zone.id === cardObject.zoneId) ?? null;
		cardView = {
			kind: 'object',
			title: cardObject.name,
			contextLine: contextLineFor(layout, objectZone, { withFloor, includeZone: true }),
			description: cardObject.description,
			rows: uniqueActivities(cardObject).map((candidate) => {
				const availability = spotAvailability(cardObject, candidate.id, occupancy, residentNames);
				return { id: candidate.id, name: candidate.name, posture: candidate.posture ?? 'standing', availability: availabilityText(availability), taken: availability.free === 0 && !availability.takenBy.includes('YOU') };
			}),
		};
	} else if (cardZone) {
		cardView = {
			kind: 'zone',
			title: cardZone.name,
			contextLine: contextLineFor(layout, cardZone, { withFloor }),
			description: cardZone.description,
			rows: (cardZone.activities ?? []).map((candidate) => ({ id: candidate.id, name: candidate.name, posture: candidate.posture ?? 'standing', availability: null })),
		};
	}

	useEffect(() => {
		if (!card) return undefined;
		const interval = setInterval(() => setOccupancy(new Map(occupiedSpotsRef.current)), OCCUPANCY_REFRESH_MS);
		return () => clearInterval(interval);
	}, [card, occupiedSpotsRef]);

	const showEntry = useCallback((kind, text) => {
		setHudEntries((entries) => [...entries, { id: crypto.randomUUID(), kind, text }]);
	}, []);
	const expireEntry = useCallback((id) => setHudEntries((entries) => entries.filter((entry) => entry.id !== id)), []);

	const deliver = useCallback(({ line, observation }) => {
		showEntry('line', line);
		const foot = playerStateRef.current?.footPosition;
		if (!foot) return;
		const collisionWorld = collisionWorldRef.current;
		const floorOf = (y) => floorAt(layout, y)?.id ?? null;
		const onlookers = selectOnlookers({
			residents: world.residents,
			userEye: { x: foot.x, y: foot.y + USER_EYE_FOR_SIGHT, z: foot.z },
			userFloorId: floorOf(foot.y),
			floorOf,
			residentPose: (residentId) => {
				const position = residentPositionsRef.current.get(residentId);
				const yaw = residentCommandsRef.current.get(residentId)?.state()?.rotation?.y;
				return position && typeof yaw === 'number' ? { position, yaw } : null;
			},
			hasLineOfSight: (from, to) => (collisionWorld ? collisionWorld.hasLineOfSight(from, to) : true),
		});
		for (const resident of onlookers) {
			if (resident.id === chatResident?.id) {
				actionSenderRef.current?.(line);
				continue;
			}
			if (!sessionId) continue;
			const name = resident.assistant.name;
			void (async () => {
				try {
					const response = await api.post(route('worlds.sessions.residents.observations.store', { world: worldId, session: sessionId, resident: resident.id }), { line: observation });
					if (!response.ok) throw new Error(`HTTP ${response.status}`);
				} catch (error) {
					console.error(`[usePlayerActivities] could not tell ${name} what the user did`, error);
					addToast(`Could not tell ${name} what you did (${error.message})`, 'error');
				}
			})();
		}
	}, [showEntry, playerStateRef, collisionWorldRef, layout, world, residentPositionsRef, residentCommandsRef, chatResident, actionSenderRef, sessionId, worldId, addToast]);

	const releaseSpot = useCallback((spotId) => {
		if (spotId && occupiedSpotsRef.current.get(spotId) === 'user') occupiedSpotsRef.current.delete(spotId);
	}, [occupiedSpotsRef]);

	const start = useCallback(async (index) => {
		if (!cardView || !cardView.rows[index]) return;
		if (activity) {
			showEntry('notice', activity.kind === 'resting' ? 'SPACE — GET UP FIRST' : 'FINISH WHAT YOU ARE DOING FIRST');
			return;
		}
		const commands = playerCommandsRef.current;
		if (!commands) return;
		const row = cardView.rows[index];

		if (cardView.kind === 'zone') {
			const zoneActivity = cardZone.activities.find((candidate) => candidate.id === row.id);
			setCard(null);
			commands.setActivity({ activityId: zoneActivity.id });
			setActivity({ key: crypto.randomUUID(), kind: 'zone', activity: zoneActivity, object: null, spot: null, cancelled: false });
			return;
		}

		const chosen = uniqueActivities(cardObject).find((candidate) => candidate.id === row.id);
		const foot = playerStateRef.current?.footPosition ?? cardObject.position;
		const spot = nearestFreeSpot(cardObject, chosen.id, occupiedSpotsRef.current, foot);
		if (!spot) {
			const availability = spotAvailability(cardObject, chosen.id, occupiedSpotsRef.current, residentNames);
			showEntry('notice', availability.total > 1 ? `NOTHING FREE — ALL ${availability.total} ARE TAKEN` : `TAKEN BY ${availability.takenBy[0]?.toUpperCase() ?? 'SOMEONE'}`);
			return;
		}
		setCard(null);
		occupiedSpotsRef.current.set(spot.id, 'user');
		commands.setActivity({ spotId: spot.id, activityId: chosen.id });

		if (activityKind(chosen, false) === 'resting') {
			setActivity({ key: crypto.randomUUID(), kind: 'resting', activity: chosen, object: cardObject, spot, settling: true });
			await commands.settleOnSpot({ spot, posture: chosen.posture });
			setActivity((current) => (current?.spot?.id === spot.id ? { ...current, settling: false } : current));
			deliver({ line: actionLine({ activity: chosen, object: cardObject }), observation: observationLine({ activity: chosen, object: cardObject }) });
			return;
		}

		setActivity({ key: crypto.randomUUID(), kind: 'standing', activity: chosen, object: cardObject, spot, cancelled: false });
		void commands.faceToward(spot.position);
	}, [cardView, activity, playerCommandsRef, cardZone, cardObject, playerStateRef, occupiedSpotsRef, residentNames, showEntry, deliver]);

	const getUp = useCallback(async () => {
		if (activity?.kind !== 'resting' || activity.settling || gettingUp.current) return;
		gettingUp.current = true;
		try {
			await playerCommandsRef.current?.getUp();
			releaseSpot(activity.spot.id);
			playerCommandsRef.current?.setActivity({});
			setActivity(null);
			deliver({ line: getUpLine(activity.object), observation: observationGetUpLine(activity.object) });
		} finally {
			gettingUp.current = false;
		}
	}, [activity, playerCommandsRef, releaseSpot, deliver]);

	const cancel = useCallback(() => {
		if (!activity || activity.kind === 'resting' || activity.cancelled) return;
		releaseSpot(activity.spot?.id);
		playerCommandsRef.current?.setActivity({});
		setActivity({ ...activity, cancelled: true });
	}, [activity, releaseSpot, playerCommandsRef]);

	const complete = useCallback(() => {
		if (!activity || activity.cancelled) return;
		releaseSpot(activity.spot?.id);
		playerCommandsRef.current?.setActivity({});
		deliver({ line: actionLine({ activity: activity.activity, object: activity.object }), observation: observationLine({ activity: activity.activity, object: activity.object }) });
	}, [activity, releaseSpot, playerCommandsRef, deliver]);

	const finish = useCallback(() => setActivity(null), []);

	const openObjectCard = useCallback((objectId) => {
		setOccupancy(new Map(occupiedSpotsRef.current));
		setCard({ kind: 'object', id: objectId });
		setHighlightedIndex(0);
	}, [occupiedSpotsRef]);
	const closeCard = useCallback(() => setCard(null), []);

	useEffect(() => {
		const rowCount = cardView?.rows.length ?? 0;
		const keyDown = (event) => {
			if (isTypingTarget(event.target)) return;
			switch (event.code) {
				case 'KeyE':
					if (card?.kind === 'object') setCard(null);
					else if (focusedObject) openObjectCard(focusedObject.id);
					return;
				case 'KeyG':
					if (card?.kind === 'zone') setCard(null);
					else if (location?.zone) {
						setCard({ kind: 'zone', id: location.zone.id });
						setHighlightedIndex(0);
					}
					return;
				case 'Escape':
					if (card) setCard(null);
					return;
				case 'ArrowDown':
				case 'ArrowUp':
					if (!card) return;
					event.preventDefault();
					if (rowCount > 0) setHighlightedIndex((index) => (index + (event.code === 'ArrowDown' ? 1 : -1) + rowCount) % rowCount);
					return;
				case 'Enter':
				case 'NumpadEnter':
					if (!card) return;
					event.preventDefault();
					if (rowCount > 0) void start(highlightedIndex);
					return;
				default:
			}
		};
		window.addEventListener('keydown', keyDown, true);
		return () => window.removeEventListener('keydown', keyDown, true);
	}, [card, cardView?.rows.length, focusedObject, location, highlightedIndex, start, openObjectCard]);

	const releaseAll = useCallback(() => {
		for (const [spotId, holder] of occupiedSpotsRef.current) if (holder === 'user') occupiedSpotsRef.current.delete(spotId);
	}, [occupiedSpotsRef]);

	useEffect(() => releaseAll, [releaseAll]);

	return {
		cardView,
		cardKey: card ? `${card.kind}:${card.id}` : null,
		cardObjectId: card?.kind === 'object' ? card.id : null,
		highlightedIndex,
		setHighlightedIndex,
		chooseRow: start,
		closeCard,
		activity,
		getUp,
		cancel,
		complete,
		finish,
		hudEntries,
		expireEntry,
		releaseAll,
		activityMs: ACTIVITY_MS,
	};
}
