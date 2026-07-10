import {useState, useEffect, useRef} from "react";

export default function Portrait({ emotion, authenticated, getImageUrl, getVideoUrl }) {
	const [playingVideo, setPlayingVideo] = useState(false);
	const canvasRef = useRef(null);
	const imgRef = useRef(null);

	const src = getImageUrl(emotion) || getImageUrl('neutral');
	const videoSrc = getVideoUrl(emotion);

	useEffect(() => {
		if (!authenticated && imgRef.current && canvasRef.current) {
			const img = imgRef.current;
			const canvas = canvasRef.current;
			const ctx = canvas.getContext('2d');
			canvas.width = 16;
			canvas.height = 24;

			const draw = () => {
				ctx.filter = 'brightness(0.15)';
				ctx.drawImage(img, 0, 0, 16, 24);
			};

			if (img.complete) {
				draw();
			} else {
				img.onload = draw;
			}
		}
	}, [authenticated]);

	useEffect(() => {
		if (videoSrc && emotion === "neutral" && authenticated) {
			setPlayingVideo(true);
		}
	}, [emotion, authenticated, videoSrc]);

	if (!authenticated) {
		return (
			<div className="relative w-full h-full overflow-hidden bg-bg-0 flex items-center justify-center">
				<img
					ref={imgRef}
					src={src}
					className="hidden"
					crossOrigin="anonymous"
				/>
				<canvas
					ref={canvasRef}
					className="absolute inset-0 w-full h-full"
					style={{ imageRendering: 'pixelated' }}
				/>
				<div className="absolute inset-0 pointer-events-none portrait-overlay" />
				<div className="relative z-10 text-center px-4">
					<div className="text-danger font-bold text-lg tracking-[0.2em] uppercase ">
						Please log in
					</div>
					<div className="text-danger font-bold text-sm tracking-[0.15em] uppercase  mt-1">
						to access VERA
					</div>
				</div>
			</div>
		);
	}

	if (playingVideo && videoSrc) {
		return (
			<div className="relative w-full h-full overflow-hidden portrait-bg">
				<video
					src={videoSrc}
					autoPlay
					muted
					playsInline
					onEnded={() => setPlayingVideo(false)}
					className="w-full h-full object-cover object-top"
				/>
				<div className="absolute inset-0 pointer-events-none portrait-overlay" />
			</div>
		);
	}

	return (
		<div className="relative w-full h-full overflow-hidden portrait-bg">
			<img
				src={src}
				alt={`VERA - ${emotion}`}
				className="w-full h-full object-cover object-top transition-opacity duration-300"
			/>
			<div className="absolute inset-0 pointer-events-none portrait-overlay" />
			<div className="absolute bottom-3 left-3 bg-black/60 px-2.5 py-1 text-[0.6rem] tracking-[0.15em] text-accent uppercase ">
				mood: {emotion}
			</div>
		</div>
	);
}