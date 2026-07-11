import { useState } from "react";
import { route } from 'ziggy-js';
import { api } from "../utils/api";

export function useEmotions() {
	const [emotions, setEmotions] = useState([]);
	const [emotionsLoaded, setEmotionsLoaded] = useState(false);

	const fetchEmotions = async () => {
		try {
			const res = await api.get(route('emotions.index'));
			const data = await res.json();
			setEmotions(data);
			setEmotionsLoaded(true);
		} catch {
			setEmotionsLoaded(true);
		}
	};

	const emotionNames = emotions.map((e) => e.name);

	const getImageUrl = (name) => {
		return emotions.find((e) => e.name === name)?.image_url || null;
	};

	const getVideoUrl = (name) => {
		return emotions.find((e) => e.name === name)?.video_url || null;
	};

	return {
		emotions,
		emotionNames,
		emotionsLoaded,
		fetchEmotions,
		getImageUrl,
		getVideoUrl,
	};
}