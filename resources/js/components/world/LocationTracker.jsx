import { useEffect, useRef } from 'react';
import { useFrame } from '@react-three/fiber';
import { createCrossingTracker, floorAt, zoneAt, zoneChain } from './worldLocation.js';

const CHECK_INTERVAL_SECONDS = 0.15;
const CROSSING_DEBOUNCE_MS = 2000;

/** Reports the user's zone and floor whenever either changes. */
export default function LocationTracker({ layout, playerState, onLocationChange }) {
	const tracker = useRef(null);
	const sinceCheck = useRef(CHECK_INTERVAL_SECONDS);
	const lastFloorId = useRef(undefined);
	const hasZones = (layout?.zones?.length ?? 0) > 0;

	useEffect(() => {
		tracker.current = createCrossingTracker({ debounceMs: CROSSING_DEBOUNCE_MS });
		lastFloorId.current = undefined;
	}, [layout]);

	useFrame((_, delta) => {
		if (!hasZones || !tracker.current) return;
		sinceCheck.current += delta;
		if (sinceCheck.current < CHECK_INTERVAL_SECONDS) return;
		sinceCheck.current = 0;
		const foot = playerState.current?.footPosition;
		if (!foot) return;
		const floor = floorAt(layout, foot.y);
		const zone = zoneAt(layout, foot);
		const crossing = tracker.current.update(zone?.id ?? null, performance.now());
		const floorChanged = (floor?.id ?? null) !== lastFloorId.current;
		if (!crossing.changed && !floorChanged) return;
		lastFloorId.current = floor?.id ?? null;
		onLocationChange({ floor, zone, zoneChain: zone ? zoneChain(layout, zone) : [], announce: crossing.announce });
	});

	return null;
}
