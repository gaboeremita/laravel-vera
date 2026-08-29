import { useState } from "react";
import { route } from 'ziggy-js';
import { api } from "../utils/api";

export function useEmotions() {
	const [emotions, setEmotions] = useState([]);
	const [emotionsLoaded, setEmotionsLoaded] = useState(false);
	const [portraitType, setPortraitType] = useState('image');
	const [vrmUrl, setVrmUrl] = useState(null);

	const fetchEmotions = async (assistantId) => {
		try {
			const res = await api.get(route('emotions.index', { assistant: assistantId }));
			const data = await res.json();
			setEmotions(data.emotions ?? []);
			setPortraitType(data.portrait_type ?? 'image');
			setVrmUrl(data.vrm_url ?? null);
			setEmotionsLoaded(true);
		} catch {
			// silent fail
		}
	};

	const emotionNames = emotions.map((e) => e.name);

	const getImageUrl = (name) => {
		return emotions.find((e) => e.name === name)?.image_url || null;
	};

	const getVideoUrl = (name) => {
		return emotions.find((e) => e.name === name)?.video_url || null;
	};

	const getVrmBlendshapes = (name) => {
		return emotions.find((e) => e.name === name)?.vrm_blendshapes || [];
	};

	return {
		emotions,
		emotionNames,
		emotionsLoaded,
		fetchEmotions,
		getImageUrl,
		getVideoUrl,
		getVrmBlendshapes,
		portraitType,
		vrmUrl,
	};
}
