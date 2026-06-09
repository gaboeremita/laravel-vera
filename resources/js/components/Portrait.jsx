import { useState, useEffect } from "react";

export default function Portrait({ emotion, images }) {
	const [playingVideo, setPlayingVideo] = useState(false);

	useEffect(() => {
		if (emotion === "neutral") {
			setPlayingVideo(true);
		}
	}, [emotion]);

	if (playingVideo) {
		return (
			<div className="relative w-full h-full overflow-hidden vera-portrait-bg">
				<video
					src="/videos/vera/neutral_intro.mp4"
					autoPlay
					muted
					playsInline
					onEnded={() => setPlayingVideo(false)}
					className="w-full h-full object-cover object-top"
				/>
				<div className="absolute inset-0 pointer-events-none vera-portrait-scanlines" />
			</div>
		);
	}

	const src = images[emotion] || images.neutral;

	return (
		<div className="relative w-full h-full overflow-hidden vera-portrait-bg">
			<img
				src={src}
				alt={`VERA - ${emotion}`}
				className="w-full h-full object-cover object-top transition-opacity duration-300"
			/>
			<div className="absolute inset-0 pointer-events-none vera-portrait-scanlines" />
			<div className="absolute bottom-3 left-3 bg-black/60 px-2.5 py-1 text-[0.6rem] tracking-[0.15em] text-vera-cyan uppercase font-mono">
				mood: {emotion}
			</div>
		</div>
	);
}