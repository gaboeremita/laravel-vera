import { useRef } from 'react';
import { useFrame, useThree } from '@react-three/fiber';
import { Vector3 } from 'three';
import { anchorPoint, isWithinReach, objectsWithin, pickFocus } from './objectFocus.js';
import { floorAt } from './worldLocation.js';

const LABEL_HEIGHT = 1.1;
const NEARBY_RADIUS = 6;
const NEARBY_INTERVAL_SECONDS = 0.25;

/**
 * Reports the object in front of the user and keeps its floating label placed
 * over it. The label is positioned by writing its style directly each frame.
 */
export default function FocusTracker({ layout, playerState, enabled = true, labelRef, onFocusChange, onNearbyChange, watchedObjectId = null, onWatchedOutOfReach }) {
	const { camera, size } = useThree();
	const focused = useRef(null);
	const nearbyKey = useRef('');
	const sinceNearby = useRef(NEARBY_INTERVAL_SECONDS);
	const forward = useRef(new Vector3());
	const anchor = useRef(new Vector3());

	useFrame((_, delta) => {
		const foot = playerState.current?.footPosition;
		if (!foot || !layout?.objects?.length) return;
		const floorId = floorAt(layout, foot.y)?.id ?? null;

		camera.getWorldDirection(forward.current);
		const next = enabled ? pickFocus({ layout, foot, forward: { x: forward.current.x, z: forward.current.z }, floorId }) : null;
		if (next?.id !== focused.current?.id) {
			focused.current = next;
			onFocusChange(next);
		}

		if (watchedObjectId) {
			const watched = layout.objects.find((object) => object.id === watchedObjectId);
			if (!watched || !isWithinReach(layout, watched, foot, floorId)) onWatchedOutOfReach?.();
		}

		sinceNearby.current += delta;
		if (sinceNearby.current >= NEARBY_INTERVAL_SECONDS) {
			sinceNearby.current = 0;
			const ids = enabled ? objectsWithin(layout, foot, floorId, NEARBY_RADIUS) : [];
			const key = ids.join('|');
			if (key !== nearbyKey.current) {
				nearbyKey.current = key;
				onNearbyChange(ids);
			}
		}

		const label = labelRef.current;
		if (!label) return;
		if (!next) {
			label.style.opacity = '0';
			return;
		}
		const point = anchorPoint(next, foot);
		anchor.current.set(point.x, point.y + LABEL_HEIGHT, point.z).project(camera);
		if (anchor.current.z > 1) {
			label.style.opacity = '0';
			return;
		}
		const x = (anchor.current.x * 0.5 + 0.5) * size.width;
		const y = (-anchor.current.y * 0.5 + 0.5) * size.height;
		label.style.transform = `translate(${x}px, ${y}px) translate(-50%, -100%)`;
		label.style.opacity = '1';
	});

	return null;
}
