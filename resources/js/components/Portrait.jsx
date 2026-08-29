import {useState} from "react";
import veraAvatar from "../../images/vera-avatar.png";
import VrmAvatar from "./VrmAvatar.jsx";

export default function Portrait({ emotion, authenticated, hasAssistant = true, getImageUrl, getVideoUrl, getVrmBlendshapes, poseBlendshapes = [], poseAnimationUrl = null, portraitType = 'image', vrmUrl = null, assistantId = null, conversationId = null }) {
	const [playingVideo, setPlayingVideo] = useState(false);

	const src = getImageUrl(emotion) || getImageUrl('default');
	const videoSrc = getVideoUrl(emotion);

	// Starting video playback is triggered by emotion/auth/video changing,
	// computed here instead of in an effect so it applies before the next
	// paint rather than one render later.
	const triggerKey = `${emotion}|${authenticated}|${videoSrc ?? ''}`;
	const [lastTriggerKey, setLastTriggerKey] = useState(triggerKey);
	if (triggerKey !== lastTriggerKey) {
		setLastTriggerKey(triggerKey);
		if (videoSrc && emotion === "default" && authenticated) {
			setPlayingVideo(true);
		}
	}

	if (authenticated && !hasAssistant) {
		return (
			<div className="relative w-full h-full overflow-hidden bg-bg-0 flex items-center justify-center">
				<div className="absolute inset-0 bg-gradient-to-b from-accent/5 via-bg-0 to-accent/10" />
				<div className="portrait-nebula">
					<span className="nebula-blob nebula-blob-1" />
					<span className="nebula-blob nebula-blob-2" />
					<span className="nebula-blob nebula-blob-3" />
				</div>
				<div className="absolute inset-0 pointer-events-none portrait-overlay" />
				<div className="vera-avatar relative z-10 w-2/3 aspect-square">
					<img src={veraAvatar} alt="vera" className="w-full h-full object-contain" />
					<img src={veraAvatar} alt="" aria-hidden="true" className="depth absolute inset-0 w-full h-full object-contain" />
				</div>
			</div>
		);
	}

	if (!authenticated) {
		return (
			<div className="relative w-full h-full overflow-hidden bg-bg-0 flex items-center justify-center">
				<div className="absolute inset-0 bg-gradient-to-b from-accent/5 via-bg-0 to-accent/10" />
				<div className="portrait-nebula">
					<span className="nebula-blob nebula-blob-1" />
					<span className="nebula-blob nebula-blob-2" />
					<span className="nebula-blob nebula-blob-3" />
				</div>
				<div className="vera-avatar-idle relative z-10 w-2/3 aspect-square">
					<img src={veraAvatar} alt="vera" className="w-full h-full object-contain" />
					<img src={veraAvatar} alt="" aria-hidden="true" className="depth absolute inset-0 w-full h-full object-contain" />
				</div>
			</div>
		);
	}

	if (portraitType === 'avatar3d') {
		if (vrmUrl) {
			return (
				<div className="relative w-full h-full overflow-hidden portrait-bg">
					<VrmAvatar
					vrmUrl={vrmUrl}
					emotion={emotion}
					blendshapes={getVrmBlendshapes ? getVrmBlendshapes(emotion) : []}
					poseBlendshapes={poseBlendshapes}
					poseAnimationUrl={poseAnimationUrl}
					assistantId={assistantId}
					conversationId={conversationId}
				/>
					<div className="absolute inset-0 pointer-events-none portrait-overlay" />
					<div className="absolute bottom-3 left-3 bg-black/60 px-2.5 py-1 text-[0.6rem] tracking-[0.15em] text-accent uppercase">
						mood: {emotion}
					</div>
				</div>
			);
		}

		return (
			<div className="relative w-full h-full overflow-hidden bg-bg-0 flex items-center justify-center">
				<div className="absolute inset-0 bg-gradient-to-b from-accent/5 via-bg-0 to-accent/10" />
				<div className="vera-avatar relative z-10 w-2/3 aspect-square">
					<img src={veraAvatar} alt="vera" className="w-full h-full object-contain" />
					<img src={veraAvatar} alt="" aria-hidden="true" className="depth absolute inset-0 w-full h-full object-contain" />
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
				alt={emotion}
				onError={(e) => { e.currentTarget.src = veraAvatar; }}
				className="w-full h-full object-cover object-top transition-opacity duration-300"
			/>
			<div className="absolute inset-0 pointer-events-none portrait-overlay" />
			<div className="absolute bottom-3 left-3 bg-black/60 px-2.5 py-1 text-[0.6rem] tracking-[0.15em] text-accent uppercase ">
				mood: {emotion}
			</div>
		</div>
	);
}
