import { memo, useCallback, useEffect, useRef, useState } from 'react';

function formatTime(seconds) {
	const mins = Math.floor(seconds / 60);
	const secs = Math.floor(seconds % 60);
	return `${mins}:${secs.toString().padStart(2, '0')}`;
}

function VoiceMessagePlayer({ audioBase64, audioContentType }) {
	const audioRef = useRef(null);
	const blobUrlRef = useRef(null);
	const [isPlaying, setIsPlaying] = useState(false);
	const [currentTime, setCurrentTime] = useState(0);
	const [duration, setDuration] = useState(0);

	useEffect(() => {
		const bytes = Uint8Array.from(atob(audioBase64), (c) => c.charCodeAt(0));
		const blob = new Blob([bytes], { type: audioContentType });
		const url = URL.createObjectURL(blob);
		blobUrlRef.current = url;

		const audio = new Audio(url);
		audioRef.current = audio;

		audio.addEventListener('loadedmetadata', () => setDuration(audio.duration));
		audio.addEventListener('timeupdate', () => setCurrentTime(audio.currentTime));
		audio.addEventListener('ended', () => {
			setIsPlaying(false);
			setCurrentTime(0);
		});

		return () => {
			audio.pause();
			URL.revokeObjectURL(url);
		};
	}, [audioBase64, audioContentType]);

	const togglePlay = useCallback(() => {
		const audio = audioRef.current;
		if (!audio) return;

		if (isPlaying) {
			audio.pause();
			setIsPlaying(false);
		} else {
			audio.play().then(() => setIsPlaying(true)).catch(() => {});
		}
	}, [isPlaying]);

	const handleSeek = useCallback((e) => {
		const audio = audioRef.current;
		if (!audio || !duration) return;

		const rect = e.currentTarget.getBoundingClientRect();
		const ratio = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
		audio.currentTime = ratio * duration;
		setCurrentTime(audio.currentTime);
	}, [duration]);

	const progress = duration > 0 ? (currentTime / duration) * 100 : 0;

	return (
		<div className="flex items-center gap-2.5 my-2 px-3 py-2 border border-line-1 bg-bg-1 max-w-xs">
			<button
				onClick={togglePlay}
				className="shrink-0 w-7 h-7 flex items-center justify-center border border-accent text-accent hover:bg-accent hover:text-bg-1 transition-colors cursor-pointer"
				aria-label={isPlaying ? 'Pause' : 'Play'}
			>
				{isPlaying ? '❚❚' : '▶'}
			</button>

			<div className="flex-1 min-w-0">
				<div
					className="h-1.5 bg-bg-2 cursor-pointer relative"
					onClick={handleSeek}
				>
					<div
						className="absolute inset-y-0 left-0 bg-accent transition-[width] duration-100"
						style={{ width: `${progress}%` }}
					/>
				</div>
			</div>

			<span className="shrink-0 text-fg-3 text-[0.65rem] tracking-[0.05em] tabular-nums">
				{formatTime(currentTime)}/{formatTime(duration)}
			</span>
		</div>
	);
}

export default memo(VoiceMessagePlayer);
