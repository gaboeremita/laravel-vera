const EXPRESSION_MAP = {
	default: [],
	neutral: [],
	happy: [{ expression: 'happy', weight: 1.0 }],
	// Kept below 1.0 — a milder version of `happy`, not a distinct blendshape.
	content: [{ expression: 'happy', weight: 0.4 }],
	sad: [{ expression: 'sad', weight: 1.0 }],
	// Kept below 1.0 — a milder version of `angry`, not a distinct blendshape.
	annoyed: [{ expression: 'angry', weight: 0.4 }],
	flustered: [
		{ expression: 'surprised', weight: 0.3 },
		{ expression: 'happy', weight: 0.2 },
	],
	seduced: [{ expression: 'relaxed', weight: 1.0 }],
	surprised: [{ expression: 'surprised', weight: 1.0 }],
	angry: [{ expression: 'angry', weight: 1.0 }],
	relaxed: [{ expression: 'relaxed', weight: 1.0 }],
};

export function getBlendshapeTargets(emotionTag) {
	return EXPRESSION_MAP[emotionTag] ?? [];
}
