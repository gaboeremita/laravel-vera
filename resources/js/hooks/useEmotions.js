import { useState } from "react";
import { api } from "../utils/api";

export function useEmotions() {
	const [emotions, setEmotions] = useState([]);
	const [emotionsLoaded, setEmotionsLoaded] = useState(false);
	const [unlocked, setUnlocked] = useState(false);

	const fetchEmotions = async (unlock = false) => {
		try {
			const param = unlock ? '?unlocked=true' : '';
			const res = await api.get(`/api/emotions${param}`);
			const data = await res.json();
			setEmotions(data);
			setUnlocked(unlock);
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
		unlocked,
		fetchEmotions,
		getImageUrl,
		getVideoUrl,
	};
}