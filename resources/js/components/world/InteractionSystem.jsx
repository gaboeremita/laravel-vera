import { useEffect, useRef } from 'react';
import { useFrame, useThree } from '@react-three/fiber';

const INTERACTION_DISTANCE = 2.2;

export default function InteractionSystem({ residents, residentPositions, onResidentChange, onInteract, enabled = true }) {
	const { camera } = useThree();
	const nearest = useRef(null);

	useFrame(() => {
		let nextResident = null;
		let nearestDistance = INTERACTION_DISTANCE;
		if (enabled) {
			for (const resident of residents) {
				const position = residentPositions.current.get(resident.id);
				if (!position) continue;
				const distance = camera.position.distanceTo(position);
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
			if (event.code === 'KeyC' && nearest.current) { event.preventDefault(); onInteract(nearest.current); }
		};
		window.addEventListener('keydown', keyDown);
		return () => window.removeEventListener('keydown', keyDown);
	}, [onInteract, enabled]);

	return null;
}
