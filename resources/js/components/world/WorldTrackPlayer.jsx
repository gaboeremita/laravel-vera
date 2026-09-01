import { useEffect, useRef, useState } from 'react';

const FADE_MS = 600;
const RESTART_DELAY_MS = 3000;
const VOLUME_STORAGE_KEY = 'worldTrackVolume';

function readStoredVolume() {
	try {
		const stored = localStorage.getItem(VOLUME_STORAGE_KEY);
		const parsed = stored === null ? null : Number(stored);
		return Number.isFinite(parsed) && parsed >= 0 && parsed <= 1 ? parsed : 0.5;
	} catch {
		return 0.5;
	}
}

function fade(audio, from, to, duration) {
	const steps = 20;
	const stepMs = duration / steps;
	let step = 0;
	audio.volume = from;
	const interval = setInterval(() => {
		step += 1;
		audio.volume = from + ((to - from) * step) / steps;
		if (step >= steps) clearInterval(interval);
	}, stepMs);
	return () => clearInterval(interval);
}

export default function WorldTrackPlayer({ trackUrl, isActive }) {
	const audioRef = useRef(null);
	const [volume, setVolume] = useState(readStoredVolume);
	const [isMuted, setIsMuted] = useState(false);
	const volumeBeforeMute = useRef(volume);

	useEffect(() => {
		const audio = audioRef.current;
		if (!audio || !trackUrl || !isActive) return undefined;

		let cancelFade = null;
		let restartTimeout = null;

		audio.volume = isMuted ? 0 : volume;
		audio.play().catch(() => {});

		const restart = () => {
			cancelFade = fade(audio, audio.volume, 0, FADE_MS);
			restartTimeout = setTimeout(() => {
				audio.currentTime = 0;
				audio.play().catch(() => {});
				cancelFade = fade(audio, 0, isMuted ? 0 : volume, FADE_MS);
			}, FADE_MS + RESTART_DELAY_MS);
		};

		audio.addEventListener('ended', restart);

		return () => {
			audio.removeEventListener('ended', restart);
			if (cancelFade) cancelFade();
			if (restartTimeout) clearTimeout(restartTimeout);
			audio.pause();
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [trackUrl, isActive]);

	useEffect(() => {
		const audio = audioRef.current;
		if (audio) audio.volume = isMuted ? 0 : volume;
	}, [volume, isMuted]);

	useEffect(() => {
		const keyDown = (event) => {
			if (event.key !== 'm' && event.key !== 'M') return;
			if (event.target instanceof HTMLInputElement || event.target instanceof HTMLTextAreaElement) return;
			setIsMuted((wasMuted) => {
				if (!wasMuted) volumeBeforeMute.current = volume;
				return !wasMuted;
			});
		};
		window.addEventListener('keydown', keyDown);
		return () => window.removeEventListener('keydown', keyDown);
	}, [volume]);

	const handleVolumeChange = (event) => {
		const next = Number(event.target.value);
		setVolume(next);
		setIsMuted(false);
		try {
			localStorage.setItem(VOLUME_STORAGE_KEY, String(next));
		} catch {
			/* best-effort persistence only */
		}
	};

	if (!trackUrl) return null;

	return (
		<>
			<audio ref={audioRef} src={trackUrl} preload="auto" />
			<div className="absolute right-5 top-5 z-10 flex items-center gap-2 border border-line-1 bg-bg-0/90 px-3 py-2">
				<button
					type="button"
					onClick={() => setIsMuted((wasMuted) => !wasMuted)}
					className="text-fg-2 text-[0.7rem] tracking-[0.1em] hover:text-fg-1"
					title="Toggle mute (M)"
				>
					{isMuted ? 'UNMUTE' : 'MUTE'}
				</button>
				<input
					type="range"
					min="0"
					max="1"
					step="0.01"
					value={isMuted ? 0 : volume}
					onChange={handleVolumeChange}
					className="w-24 accent-accent"
				/>
			</div>
		</>
	);
}
