import { useEffect, useRef } from 'react';
import { useFrame, useThree } from '@react-three/fiber';
import { isTypingTarget } from './keyboardFocus.js';
import { PLAYER_EYE_HEIGHT } from './collisionCheck.js';
import { chooseTalkTarget } from './talkTarget.js';

export default function InteractionSystem({ residents, residentPositions, playerState, onResidentChange, onInteract, onEndConversation, activeResidentId = null, enabled = true, pickedResidentId = null, onPickedOutOfRange }) {
	const { camera } = useThree();
	const nearest = useRef(null);

	useFrame(() => {
		const foot = playerState?.current?.footPosition ?? { x: camera.position.x, y: camera.position.y - PLAYER_EYE_HEIGHT, z: camera.position.z };
		const nextResident = enabled ? chooseTalkTarget(residents, residentPositions.current, foot, pickedResidentId) : null;
		if (pickedResidentId !== null && nextResident?.id !== pickedResidentId) onPickedOutOfRange?.();
		if (nextResident?.id !== nearest.current?.id) {
			nearest.current = nextResident;
			onResidentChange(nextResident);
		}
	});

	useEffect(() => {
		if (!enabled) return;
		const keyDown = (event) => {
			if (isTypingTarget(event.target) || event.metaKey || event.ctrlKey || event.altKey) return;
			if (activeResidentId !== null) {
				if (event.code === 'Escape') { event.preventDefault(); onEndConversation?.(); }
				return;
			}
			if (event.code === 'KeyC' && nearest.current) { event.preventDefault(); onInteract(nearest.current); }
		};
		window.addEventListener('keydown', keyDown);
		return () => window.removeEventListener('keydown', keyDown);
	}, [onInteract, onEndConversation, activeResidentId, enabled]);

	return null;
}
