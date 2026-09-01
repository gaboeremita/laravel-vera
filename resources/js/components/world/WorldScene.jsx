import { Canvas } from '@react-three/fiber';
import { useCallback, useMemo, useRef, useState } from 'react';
import FirstPersonController from './FirstPersonController.jsx';
import InteractionSystem from './InteractionSystem.jsx';
import ResidentController from './ResidentController.jsx';
import WorldEnvironment from './WorldEnvironment.jsx';

export default function WorldScene({ world, explorationEnabled, onReady, onError, onResidentChange, onInteract, activePose, initialPosition, onPlayerPositionChange }) {
	const [environment, setEnvironment] = useState(null);
	const residentPositions = useRef(new Map());
	const [playerPosition, setPlayerPosition] = useState([0, 1.6, 4]);
	const handlePositionChange = useCallback((position) => {
		setPlayerPosition(position);
		onPlayerPositionChange?.(position);
	}, [onPlayerPositionChange]);
	const handleReady = useCallback((loadedEnvironment) => {
		setEnvironment(loadedEnvironment);
		onReady();
	}, [onReady]);
	const spawnPosition = useMemo(() => {
		if (!environment) return null;
		if (!initialPosition) return environment.spawnPosition;
		return environment.collisionWorld.restorePlayerPosition(initialPosition, environment.spawnPosition);
	}, [environment, initialPosition]);

	return (
		<Canvas camera={{ position: [0, 1.6, 4], fov: 70, near: 0.01, far: 100 }} className="h-full w-full bg-black">
			<color attach="background" args={['#050913']} />
			<ambientLight intensity={0.7} />
			<directionalLight position={[4, 8, 4]} intensity={2} />
			<WorldEnvironment url={world.environmentUrl} onReady={handleReady} onError={onError} />
			{environment && (
				<>
					<FirstPersonController collisionWorld={environment.collisionWorld} spawnPosition={spawnPosition} enabled={explorationEnabled} onPositionChange={handlePositionChange} />
					{world.residents.map((resident) => <ResidentController key={resident.id} resident={resident} playerPosition={playerPosition} paused={!explorationEnabled} activePose={activePose} collisionWorld={environment.collisionWorld} residentPositions={residentPositions} />)}
					<InteractionSystem residents={world.residents} residentPositions={residentPositions} onResidentChange={onResidentChange} onInteract={onInteract} enabled={explorationEnabled} />
				</>
			)}
		</Canvas>
	);
}
