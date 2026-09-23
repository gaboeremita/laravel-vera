import { useRef } from 'react';
import { useFrame, useThree } from '@react-three/fiber';
import { Vector3 } from 'three';
import { ChevronRight } from 'lucide-react';

const EDGE_MARGIN = 0.88;
const TARGET_HEIGHT = 1.2;

/**
 * Lives inside the canvas and moves the HTML indicator directly each frame,
 * so a per-frame position never goes through React state.
 */
export function OffscreenIndicatorTracker({ residentPositions, activeResidentId, indicatorRef }) {
	const { camera } = useThree();
	const point = useRef(new Vector3());
	const viewPoint = useRef(new Vector3());

	useFrame(() => {
		const element = indicatorRef.current;
		if (!element) return;
		const position = activeResidentId !== null ? residentPositions.current.get(activeResidentId) : null;
		if (!position) {
			element.style.display = 'none';
			return;
		}

		point.current.set(position.x, position.y + TARGET_HEIGHT, position.z);
		viewPoint.current.copy(point.current).applyMatrix4(camera.matrixWorldInverse);
		const behind = viewPoint.current.z > 0;
		point.current.project(camera);
		let x = point.current.x;
		let y = point.current.y;
		if (!behind && Math.abs(x) <= 1 && Math.abs(y) <= 1) {
			element.style.display = 'none';
			return;
		}
		if (behind) {
			x = -x;
			y = -y;
		}

		const angle = Math.atan2(y, x);
		const reach = EDGE_MARGIN / Math.max(Math.abs(Math.cos(angle)), Math.abs(Math.sin(angle)));
		const screenX = (Math.cos(angle) * reach + 1) / 2;
		const screenY = (1 - Math.sin(angle) * reach) / 2;
		element.style.display = 'flex';
		element.style.left = `${screenX * 100}%`;
		element.style.top = `${screenY * 100}%`;
		element.firstChild.style.transform = `rotate(${-angle}rad)`;
	});

	return null;
}

export default function OffscreenIndicator({ ref, name }) {
	return (
		<div ref={ref} className="pointer-events-none absolute z-20 -translate-x-1/2 -translate-y-1/2 flex-col items-center gap-1" style={{ display: 'none' }}>
			<div className="flex h-9 w-9 items-center justify-center rounded-full bg-accent text-accent-fg shadow-lg">
				<ChevronRight size={20} />
			</div>
			<span className="bg-bg-0/90 px-2 py-0.5 text-accent text-[0.6rem] tracking-[0.1em]">{name.toUpperCase()}</span>
		</div>
	);
}
