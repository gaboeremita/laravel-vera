import { useEffect, useRef } from 'react';
import { useFrame, useThree } from '@react-three/fiber';
import { isTypingTarget } from './keyboardFocus.js';
import { PLAYER_EYE_HEIGHT } from './collisionCheck.js';

const INTERACTION_DISTANCE = 4;
const INTERACTION_HEIGHT_DIFFERENCE = 2.5;

export default function InteractionSystem({ residents, residentPositions, playerState, onResidentChange, onInteract, onEndConversation, activeResidentId = null, enabled = true }) {
	const { camera } = useThree();
	const nearest = useRef(null);

	useFrame(() => {
		let nextResident = null;
		let nearestDistance = INTERACTION_DISTANCE;
		const foot = playerState?.current?.footPosition ?? { x: camera.position.x, y: camera.position.y - PLAYER_EYE_HEIGHT, z: camera.position.z };
		if (enabled) {
			for (const resident of residents) {
				const position = residentPositions.current.get(resident.id);
				if (!position) continue;
				const heightDifference = Math.abs(foot.y - position.y);
				if (heightDifference > INTERACTION_HEIGHT_DIFFERENCE) continue;
				const distance = Math.hypot(foot.x - position.x, foot.z - position.z);
				if (distance > nearestDistance) continue;
				nearestDistance = distance;
				nextResident = resident;
			}
		}
		if (nextResident?.id !== nearest.current?.id) {
			nearest.current = nextResident;
			onResidentChange(nextResident);
		}
	});

	useEffect(() => {
		if (!enabled) return;
		const keyDown = (event) => {
			if (event.code !== 'KeyC' || isTypingTarget(event.target)) return;
			if (activeResidentId !== null) { event.preventDefault(); onEndConversation?.(); return; }
			if (nearest.current) { event.preventDefault(); onInteract(nearest.current); }
		};
		window.addEventListener('keydown', keyDown);
		return () => window.removeEventListener('keydown', keyDown);
	}, [onInteract, onEndConversation, activeResidentId, enabled]);

	return null;
}
