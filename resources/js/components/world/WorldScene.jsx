import { Canvas } from '@react-three/fiber';
import { useCallback, useRef, useState } from 'react';
import FirstPersonController from './FirstPersonController.jsx';
import InteractionSystem from './InteractionSystem.jsx';
import ResidentController from './ResidentController.jsx';
import WorldEnvironment from './WorldEnvironment.jsx';

export default function WorldScene({ world, explorationEnabled, onReady, onError, onResidentChange, onInteract }) {
	const collisionMeshes = useRef([]);
	const [playerPosition, setPlayerPosition] = useState([0, 1.6, 4]);
	const handlePositionChange = useCallback((position) => setPlayerPosition(position), []);

	return (
		<Canvas camera={{ position: [0, 1.6, 4], fov: 70, near: 0.01, far: 100 }} className="h-full w-full bg-black">
			<color attach="background" args={['#050913']} />
			<ambientLight intensity={0.7} />
			<directionalLight position={[4, 8, 4]} intensity={2} />
			<WorldEnvironment url={world.environment_url} collisionMeshes={collisionMeshes} onReady={onReady} onError={onError} />
			<FirstPersonController collisionMeshes={collisionMeshes} enabled={explorationEnabled} onPositionChange={handlePositionChange} />
			{world.residents.map((resident) => <ResidentController key={resident.id} resident={resident} playerPosition={playerPosition} paused={!explorationEnabled} />)}
			<InteractionSystem residents={world.residents} playerPosition={playerPosition} onResidentChange={onResidentChange} onInteract={onInteract} />
		</Canvas>
	);
}
