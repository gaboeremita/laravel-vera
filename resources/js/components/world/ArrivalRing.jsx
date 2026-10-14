import { useMemo, useRef } from 'react';
import { useFrame } from '@react-three/fiber';
import { AdditiveBlending, Color, DoubleSide } from 'three';
import { themeRgb } from '../../utils/themeColor.js';

const DURATION_SECONDS = 0.6;
const MAX_RADIUS = 1.2;
const HEIGHT_ABOVE_FEET = 0.04;

function Ring({ residentId, residentPositions, onDone }) {
	const mesh = useRef(null);
	const elapsed = useRef(0);
	const color = useMemo(() => new Color(...themeRgb('--accent', '#00ffc8')), []);

	useFrame((_, delta) => {
		const position = residentPositions.current.get(residentId);
		if (!mesh.current || !position) return;
		elapsed.current += delta;
		const progress = Math.min(1, elapsed.current / DURATION_SECONDS);
		const eased = 1 - (1 - progress) ** 3;
		mesh.current.position.set(position.x, position.y + HEIGHT_ABOVE_FEET, position.z);
		mesh.current.scale.setScalar(0.2 + eased * MAX_RADIUS);
		mesh.current.material.opacity = 0.9 * (1 - progress);
		if (progress >= 1) onDone(residentId);
	});

	return (
		<mesh ref={mesh} rotation={[-Math.PI / 2, 0, 0]}>
			<ringGeometry args={[0.82, 1, 48]} />
			<meshBasicMaterial color={color} transparent opacity={0.9} side={DoubleSide} depthWrite={false} blending={AdditiveBlending} />
		</mesh>
	);
}

/** A glowing ring that spreads out at the feet of each resident who just arrived, then fades. */
export default function ArrivalRing({ arrivals, residentPositions, onDone }) {
	return arrivals.map((arrival) => <Ring key={arrival.key} residentId={arrival.residentId} residentPositions={residentPositions} onDone={() => onDone(arrival.key)} />);
}
