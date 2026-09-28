import { useEffect, useRef } from 'react';
import { useFrame } from '@react-three/fiber';
import { createPassageTrigger } from './passageTrigger.js';

const CHECK_INTERVAL_SECONDS = 0.1;

/** Reports the linked passage the user walks into. */
export default function PassageTracker({ passages, linkedPassageIds, playerState, enabled, onEnter }) {
	const trigger = useRef(null);
	const sinceCheck = useRef(CHECK_INTERVAL_SECONDS);

	useEffect(() => {
		trigger.current = createPassageTrigger(passages, linkedPassageIds);
	}, [passages, linkedPassageIds]);

	useFrame((_, delta) => {
		if (!enabled || !trigger.current || passages.length === 0) return;
		sinceCheck.current += delta;
		if (sinceCheck.current < CHECK_INTERVAL_SECONDS) return;
		sinceCheck.current = 0;
		const foot = playerState.current?.footPosition;
		if (!foot) return;
		const passage = trigger.current.update(foot);
		if (passage) onEnter(passage);
	});

	return null;
}
