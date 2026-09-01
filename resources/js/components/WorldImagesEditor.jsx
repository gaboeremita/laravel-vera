import { useRef, useState } from 'react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';

function ImageUploadField({ label, hint, previewUrl, isUploading, onUpload }) {
	const inputRef = useRef(null);

	return (
		<div className="space-y-2">
			<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block">
				{label} <span className="text-fg-3 normal-case">({hint})</span>
			</label>
			<div
				onClick={() => inputRef.current?.click()}
				className="w-32 h-32 border border-dashed border-line-1 flex items-center justify-center cursor-pointer hover:border-accent/50 transition-colors overflow-hidden"
			>
				{previewUrl ? (
					<img src={previewUrl} alt={label} className="w-full h-full object-cover object-top" />
				) : (
					<span className="text-fg-3 text-[0.65rem] tracking-[0.1em] text-center px-2">
						{isUploading ? 'UPLOADING...' : 'CLICK TO SELECT'}
					</span>
				)}
			</div>
			<input
				ref={inputRef}
				type="file"
				accept="image/*"
				onChange={(event) => {
					const file = event.target.files?.[0];
					if (file) onUpload(file);
					event.target.value = '';
				}}
				className="hidden"
			/>
		</div>
	);
}

export default function WorldImagesEditor({ worldId, cardImageUrl, portraitImageUrl, addToast }) {
	const [cardPreview, setCardPreview] = useState(cardImageUrl ?? null);
	const [portraitPreview, setPortraitPreview] = useState(portraitImageUrl ?? null);
	const [isUploadingCard, setIsUploadingCard] = useState(false);
	const [isUploadingPortrait, setIsUploadingPortrait] = useState(false);

	const upload = async (routeName, file, setPreview, setIsUploading, label) => {
		setIsUploading(true);
		try {
			const formData = new FormData();
			formData.append('image', file);
			const res = await api.postForm(route(routeName, { world: worldId }), formData);
			if (!res.ok) {
				const error = await res.json().catch(() => ({}));
				throw new Error(error.message || 'Upload failed');
			}
			setPreview(URL.createObjectURL(file));
			addToast(`${label} image updated`, 'success');
		} catch (error) {
			addToast(error.message || `Failed to upload ${label.toLowerCase()} image`, 'error');
		} finally {
			setIsUploading(false);
		}
	};

	return (
		<div className="flex flex-wrap gap-6">
			<ImageUploadField
				label="Card Image"
				hint="shown in the worlds menu"
				previewUrl={cardPreview}
				isUploading={isUploadingCard}
				onUpload={(file) => upload('worlds.image.card.store', file, setCardPreview, setIsUploadingCard, 'Card')}
			/>
			<ImageUploadField
				label="Portrait Image"
				hint="shown while browsing this world's sessions"
				previewUrl={portraitPreview}
				isUploading={isUploadingPortrait}
				onUpload={(file) => upload('worlds.image.portrait.store', file, setPortraitPreview, setIsUploadingPortrait, 'Portrait')}
			/>
		</div>
	);
}
