import { useId, useState } from 'react';
import { BlendshapeRows, EXPRESSION_SUGGESTIONS } from './VrmEmotionEditor.jsx';
import { AnimationFileControl } from './PoseEditor.jsx';

/**
 * The assistant's default pose — a name-locked, undeletable pose always
 * named "default", the 3D-avatar equivalent of the image-mode "Default
 * Image". Shown as its own dedicated block (not part of the general Poses
 * list, which filters it out), matching how Default Image sits outside the
 * general Emotions grid. Left unconfigured (no blendshapes, no animation),
 * the avatar falls back to its existing hardcoded idle/neutral state.
 *
 * @param {{name: string, vrm_blendshapes: Array, animation_url: ?string, animation_original_name: ?string}} pose
 * @param {function} onUpdateBlendshapes - (blendshapes) => void
 * @param {function} onUploadAnimation - (file) => void
 * @param {function} onDeleteAnimation - () => void
 */
export default function DefaultPoseEditor({ pose, onUpdateBlendshapes, onUploadAnimation, onDeleteAnimation }) {
	const datalistId = useId();
	const [expanded, setExpanded] = useState(false);
	const [draft, setDraft] = useState(() => (pose.vrm_blendshapes || []).map((b) => (b.weight <= 1 ? { ...b, weight: Math.round(b.weight * 100) } : b)));
	const [syncedBlendshapes, setSyncedBlendshapes] = useState(pose.vrm_blendshapes);
	const [isSaving, setIsSaving] = useState(false);

	if (syncedBlendshapes !== pose.vrm_blendshapes) {
		setSyncedBlendshapes(pose.vrm_blendshapes);
		setDraft((pose.vrm_blendshapes || []).map((b) => (b.weight <= 1 ? { ...b, weight: Math.round(b.weight * 100) } : b)));
	}

	const handleSave = async () => {
		setIsSaving(true);
		try {
			await onUpdateBlendshapes(draft.filter((b) => b.expression.trim()));
		} finally {
			setIsSaving(false);
		}
	};

	return (
		<div className="space-y-2">
			<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block">
				Default Pose <span className="text-fg-3 normal-case">(used when nothing else is triggered — optional)</span>
			</label>

			<datalist id={datalistId}>
				{EXPRESSION_SUGGESTIONS.map((name) => (
					<option key={name} value={name} />
				))}
			</datalist>

			<div className="border border-line-1 bg-bg-1">
				<button
					type="button"
					onClick={() => setExpanded((prev) => !prev)}
					aria-expanded={expanded}
					className="w-full flex items-center gap-2 p-3 cursor-pointer text-left"
				>
					<span className={`text-fg-3 text-[0.6rem] transition-transform shrink-0 ${expanded ? 'rotate-90' : ''}`}>▸</span>
					<span className="flex-1 min-w-0 truncate text-accent text-[0.7rem] tracking-[0.05em]">default</span>
				</button>

				{expanded && (
					<div className="p-3 pt-0 space-y-2">
						<BlendshapeRows blendshapes={draft} onChange={setDraft} datalistId={datalistId} />
						<div className="flex justify-end">
							<button
								onClick={handleSave}
								disabled={isSaving}
								className={`px-3 py-1 text-[0.65rem] tracking-[0.1em] transition-colors ${
									isSaving ? 'bg-bg-3 text-fg-3 cursor-default' : 'button-success cursor-pointer'
								}`}
							>
								{isSaving ? 'SAVING...' : 'SAVE'}
							</button>
						</div>
						<AnimationFileControl pose={pose} onUploadAnimation={(_, file) => onUploadAnimation(file)} onDeleteAnimation={() => onDeleteAnimation()} />
					</div>
				)}
			</div>
		</div>
	);
}
