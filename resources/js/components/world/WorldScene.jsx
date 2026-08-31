import { Canvas } from '@react-three/fiber';
import { useCallback, useRef, useState } from 'react';
import { Box3 } from 'three';
import FirstPersonController from './FirstPersonController.jsx';
import InteractionSystem from './InteractionSystem.jsx';
import ResidentController from './ResidentController.jsx';
import WorldEnvironment from './WorldEnvironment.jsx';

export default function WorldScene({ world, explorationEnabled, onReady, onError, onResidentChange, onInteract, activePose }) {
	const collisionMeshes = useRef([]);
	const worldBounds = useRef(new Box3());
	const environmentScene = useRef(null);
	const [playerPosition, setPlayerPosition] = useState([0, 1.6, 4]);
	const handlePositionChange = useCallback((position) => setPlayerPosition(position), []);

	return (
		<Canvas camera={{ position: [0, 1.6, 4], fov: 70, near: 0.01, far: 100 }} className="h-full w-full bg-black">
			<color attach="background" args={['#050913']} />
			<ambientLight intensity={0.7} />
			<directionalLight position={[4, 8, 4]} intensity={2} />
			<WorldEnvironment url={world.environmentUrl} collisionMeshes={collisionMeshes} worldBounds={worldBounds} environmentScene={environmentScene} onReady={onReady} onError={onError} />
			<FirstPersonController collisionMeshes={collisionMeshes} environmentScene={environmentScene} worldBounds={worldBounds} enabled={explorationEnabled} onPositionChange={handlePositionChange} />
			{world.residents.map((resident) => <ResidentController key={resident.id} resident={resident} playerPosition={playerPosition} paused={!explorationEnabled} activePose={activePose} worldBounds={worldBounds} collisionMeshes={collisionMeshes} environmentScene={environmentScene} />)}
			<InteractionSystem residents={world.residents} playerPosition={playerPosition} onResidentChange={onResidentChange} onInteract={onInteract} enabled={explorationEnabled} />
		</Canvas>
	);
}
