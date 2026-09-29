import { useRef, useState } from 'react';
import { Play, X } from 'lucide-react';
import { playSound } from '../utils/soundCache.js';
import { FIELD_LABEL } from '../utils/formFieldStyles.js';

/** An optional sound: pick a file, hear it, or remove it. `sound` is { url, hash } or, before upload, { file, previewUrl }. */
export default function SoundUploadField({ label = 'Sound', hint = 'optional, plays when the player gets it', sound, isUploading, onPick, onRemove, onError }) {
	const inputRef = useRef(null);
	const [isPlaying, setIsPlaying] = useState(false);

	const play = async () => {
		setIsPlaying(true);
		try {
			if (sound.previewUrl) await new Audio(sound.previewUrl).play();
			else await playSound(sound);
		} catch {
			onError?.('Unable to play this sound');
		} finally {
			setTimeout(() => setIsPlaying(false), 400);
		}
	};

	return (
		<div className="space-y-2">
			<label className={FIELD_LABEL}>{label} <span className="normal-case text-fg-3">({hint})</span></label>
			<div className="flex items-center gap-2">
				{sound ? (
					<>
						<button type="button" onClick={play} aria-label="Play sound" className={`flex h-9 w-9 items-center justify-center border transition-colors cursor-pointer ${isPlaying ? 'border-accent bg-accent/15 text-accent' : 'border-line-1 text-fg-2 hover:border-accent/50 hover:text-accent'}`}>
							<Play size={14} />
						</button>
						<span className="text-fg-3 text-xs truncate max-w-40">{sound.file?.name ?? 'Sound set'}</span>
						<button type="button" onClick={onRemove} aria-label="Remove sound" className="text-fg-3 hover:text-danger transition-colors cursor-pointer"><X size={14} /></button>
					</>
				) : (
					<button type="button" onClick={() => inputRef.current?.click()} disabled={isUploading} className="border border-dashed border-line-1 px-3 py-2 text-[0.65rem] tracking-[0.1em] text-fg-3 hover:text-accent hover:border-accent/50 transition-colors cursor-pointer disabled:opacity-50">
						{isUploading ? 'UPLOADING...' : 'CHOOSE A SOUND'}
					</button>
				)}
			</div>
			<input
				ref={inputRef}
				type="file"
				accept="audio/*"
				className="hidden"
				onChange={(event) => {
					const file = event.target.files?.[0];
					if (file) onPick(file);
					event.target.value = '';
				}}
			/>
		</div>
	);
}
