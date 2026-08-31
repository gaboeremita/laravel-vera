import { useEffect, useMemo } from 'react';

const INTERACTION_DISTANCE = 2.2;

export default function InteractionSystem({ residents, playerPosition, onResidentChange, onInteract }) {
	const nearest = useMemo(() => residents
		.map((resident) => ({ resident, distance: Math.hypot(playerPosition[0] - resident.position.x, playerPosition[1] - resident.position.y, playerPosition[2] - resident.position.z) }))
		.filter(({ distance }) => distance <= INTERACTION_DISTANCE)
		.sort((left, right) => left.distance - right.distance)[0]?.resident ?? null, [playerPosition, residents]);

	useEffect(() => {
		onResidentChange(nearest);
		const keyDown = (event) => {
			if (event.code === 'KeyC' && nearest) { event.preventDefault(); onInteract(nearest); }
		};
		window.addEventListener('keydown', keyDown);
		return () => window.removeEventListener('keydown', keyDown);
	}, [nearest, onInteract, onResidentChange]);

	return null;
}
