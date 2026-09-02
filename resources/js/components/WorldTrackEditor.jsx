import { useRef, useState } from 'react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import { FIELD_LABEL, FIELD_INPUT } from '../utils/formFieldStyles.js';

export default function WorldTrackEditor({ worldId, trackOriginalName, addToast }) {
	const inputRef = useRef(null);
	const [currentName, setCurrentName] = useState(trackOriginalName ?? null);
	const [isUploading, setIsUploading] = useState(false);

	const upload = async (file) => {
		setIsUploading(true);
		try {
			const formData = new FormData();
			formData.append('track', file);
			const res = await api.postForm(route('worlds.track.store', { world: worldId }), formData);
			if (!res.ok) {
				const error = await res.json().catch(() => ({}));
				throw new Error(error.message || 'Upload failed');
			}
			setCurrentName(file.name);
			addToast('Track updated', 'success');
		} catch (error) {
			addToast(error.message || 'Failed to upload track', 'error');
		} finally {
			setIsUploading(false);
		}
	};

	const remove = async (event) => {
		event.stopPropagation();
		try {
			const res = await api.delete(route('worlds.track.destroy', { world: worldId }));
			if (!res.ok) {
				const error = await res.json().catch(() => ({}));
				throw new Error(error.message || 'Failed to remove track');
			}
			setCurrentName(null);
			addToast('Track removed', 'success');
		} catch (error) {
			addToast(error.message || 'Failed to remove track', 'error');
		}
	};

	return (
		<div>
			<label className={FIELD_LABEL}>Background Music (.mp3, .wav)</label>
			<div onClick={() => inputRef.current?.click()} className={`${FIELD_INPUT} cursor-pointer flex items-center justify-between gap-3`}>
				<span className="truncate">{isUploading ? 'Uploading...' : (currentName ?? 'No file chosen')}</span>
				{currentName && !isUploading && (
					<button type="button" onClick={remove} className="text-danger text-[0.65rem] tracking-[0.1em] shrink-0 cursor-pointer hover:text-danger transition-colors">
						REMOVE
					</button>
				)}
			</div>
			<input
				ref={inputRef}
				type="file"
				accept="audio/mpeg,audio/wav,.mp3,.wav"
				onChange={(event) => {
					const file = event.target.files?.[0];
					if (file) upload(file);
					event.target.value = '';
				}}
				className="hidden"
			/>
		</div>
	);
}
