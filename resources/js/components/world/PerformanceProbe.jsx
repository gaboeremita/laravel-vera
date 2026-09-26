import { useRef } from 'react';
import { useFrame, useThree } from '@react-three/fiber';
import { summarizeResidents } from './residentDetail.js';

const SAMPLE_SECONDS = 0.5;

/** Samples frame rate, renderer counts and resident detail levels into statsRef for the performance overlay. */
export default function PerformanceProbe({ statsRef, residentDetails }) {
	const { gl } = useThree();
	const sample = useRef({ frames: 0, elapsed: 0 });

	useFrame((_, delta) => {
		const current = sample.current;
		current.frames += 1;
		current.elapsed += delta;
		if (current.elapsed < SAMPLE_SECONDS) return;
		statsRef.current = {
			fps: Math.round(current.frames / current.elapsed),
			drawCalls: gl.info.render.calls,
			triangles: gl.info.render.triangles,
			geometries: gl.info.memory.geometries,
			textures: gl.info.memory.textures,
			...summarizeResidents(residentDetails.current),
		};
		current.frames = 0;
		current.elapsed = 0;
	});

	return null;
}
