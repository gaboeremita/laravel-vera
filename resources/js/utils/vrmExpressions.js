const EXPRESSION_MAP = {
	default: [],
	neutral: [],
	happy: [{ expression: 'happy', weight: 0.8 }],
	sad: [{ expression: 'sad', weight: 0.7 }],
	annoyed: [{ expression: 'angry', weight: 0.4 }],
	flustered: [
		{ expression: 'surprised', weight: 0.3 },
		{ expression: 'happy', weight: 0.2 },
	],
	seduced: [{ expression: 'relaxed', weight: 0.5 }],
	surprised: [{ expression: 'surprised', weight: 0.8 }],
	angry: [{ expression: 'angry', weight: 0.9 }],
	relaxed: [{ expression: 'relaxed', weight: 0.7 }],
};

export function getBlendshapeTargets(emotionTag) {
	return EXPRESSION_MAP[emotionTag] ?? [];
}
