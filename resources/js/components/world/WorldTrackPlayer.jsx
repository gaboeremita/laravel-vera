import { useEffect, useRef, useState } from 'react';
import { musicVolume } from './musicVolume.js';
import { isTypingTarget } from './keyboardFocus.js';

const FADE_MS = 600;
const DUCK_FADE_MS = 250;
const DUCK_RELEASE_MS = 1000;
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

/**
 * The world's music. The slider follows loudness rather than raw volume, and
 * the music drops while a resident speaks (`voiceUntil`, a timestamp in ms)
 * so her voice carries over it.
 */
export default function WorldTrackPlayer({ trackUrl, isActive, voiceUntil = 0 }) {
	const audioRef = useRef(null);
	const [volume, setVolume] = useState(readStoredVolume);
	const [isMuted, setIsMuted] = useState(false);
	const [releasedFor, setReleasedFor] = useState(voiceUntil);
	const ducked = voiceUntil !== releasedFor;
	const volumeBeforeMute = useRef(volume);
	const target = musicVolume({ slider: volume, muted: isMuted, ducked });
	const targetRef = useRef(target);
	const cancelTargetFade = useRef(null);

	useEffect(() => {
		const audio = audioRef.current;
		if (!audio || !trackUrl || !isActive) return undefined;

		let cancelFade = null;
		let restartTimeout = null;

		audio.volume = targetRef.current;
		audio.play().catch(() => {});

		const restart = () => {
			cancelFade = fade(audio, audio.volume, 0, FADE_MS);
			restartTimeout = setTimeout(() => {
				audio.currentTime = 0;
				audio.play().catch(() => {});
				cancelFade = fade(audio, 0, targetRef.current, FADE_MS);
			}, FADE_MS + RESTART_DELAY_MS);
		};

		audio.addEventListener('ended', restart);

		return () => {
			audio.removeEventListener('ended', restart);
			if (cancelFade) cancelFade();
			if (restartTimeout) clearTimeout(restartTimeout);
			audio.pause();
		};
	}, [trackUrl, isActive]);

	useEffect(() => {
		targetRef.current = target;
		const audio = audioRef.current;
		if (!audio) return;
		cancelTargetFade.current?.();
		cancelTargetFade.current = fade(audio, audio.volume, target, DUCK_FADE_MS);
	}, [target]);

	useEffect(() => () => cancelTargetFade.current?.(), []);

	useEffect(() => {
		const remaining = voiceUntil - Date.now();
		const timer = setTimeout(() => setReleasedFor(voiceUntil), remaining <= 0 ? 0 : remaining + DUCK_RELEASE_MS);
		return () => clearTimeout(timer);
	}, [voiceUntil]);

	useEffect(() => {
		const keyDown = (event) => {
			if (event.code !== 'KeyN' || isTypingTarget(event.target)) return;
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
					title="Toggle mute (N)"
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
