import { useId, useState } from 'react';
import { AnimationFileControl } from './PoseEditor.jsx';
import { BlendshapeRows, EXPRESSION_SUGGESTIONS } from './VrmEmotionEditor.jsx';
import { findWorldMotionPose, WORLD_MOTION_POSES } from './world/worldMotionPoses.js';

function MotionPoseCard({ definition, pose, onAdd, onUpdateBlendshapes, onUploadAnimation, onDeleteAnimation, datalistId }) {
	const [draft, setDraft] = useState(() => (pose?.vrm_blendshapes || []).map((blendshape) => (blendshape.weight <= 1 ? { ...blendshape, weight: Math.round(blendshape.weight * 100) } : blendshape)));
	const [syncedPose, setSyncedPose] = useState(pose);
	const [isSaving, setIsSaving] = useState(false);
	if (syncedPose !== pose) {
		setSyncedPose(pose);
		setDraft((pose?.vrm_blendshapes || []).map((blendshape) => (blendshape.weight <= 1 ? { ...blendshape, weight: Math.round(blendshape.weight * 100) } : blendshape)));
	}
	const motionPose = pose ?? { name: definition.name, animation_url: null, animation_original_name: null };
	const expressions = draft.filter((blendshape) => blendshape.expression.trim());
	const saveExpressions = async () => {
		setIsSaving(true);
		try {
			if (pose) await onUpdateBlendshapes(pose, pose.name, expressions);
			else await onAdd(definition.name, expressions, null);
		} finally {
			setIsSaving(false);
		}
	};

	return (
		<div className="border border-line-1 bg-bg-1 p-3 space-y-3">
			<div>
				<p className="text-accent text-[0.7rem] tracking-[0.05em]">{definition.label}</p>
				<p className="mt-1 text-fg-3 text-xs">{definition.description}</p>
			</div>
			<BlendshapeRows blendshapes={draft} onChange={setDraft} datalistId={datalistId} />
			<div className="flex justify-end">
				<button onClick={saveExpressions} disabled={isSaving} className={`px-3 py-1 text-[0.65rem] tracking-[0.1em] transition-colors ${isSaving ? 'bg-bg-3 text-fg-3 cursor-default' : 'button-success cursor-pointer'}`}>
					{isSaving ? 'SAVING...' : 'SAVE EXPRESSIONS'}
				</button>
			</div>
			<AnimationFileControl
				pose={motionPose}
				onUploadAnimation={(_, file) => pose ? onUploadAnimation(pose, file) : onAdd(definition.name, expressions, file)}
				onDeleteAnimation={() => pose && onDeleteAnimation(pose)}
			/>
		</div>
	);
}

/**
 * Optional motion clips used only when this avatar is placed as a resident
 * in a world. The standard pose editor remains available for conversation
 * actions and facial expressions.
 */
export default function WorldMotionPoseEditor({ posture = 'standing', poses, onAdd, onUpdateBlendshapes, onUploadAnimation, onDeleteAnimation }) {
	const datalistId = useId();
	return (
		<div className="space-y-3">
			<div>
				<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block">World Motion Poses</label>
				<p className="mt-1 text-fg-3 text-xs">Optional. Used only while this avatar is a world resident.</p>
			</div>
			<datalist id={datalistId}>
				{EXPRESSION_SUGGESTIONS.map((name) => <option key={name} value={name} />)}
			</datalist>
			<div className="grid grid-cols-1 gap-2 md:grid-cols-2">
				{WORLD_MOTION_POSES.filter((definition) => definition.posture === posture).map((definition) => {
					const pose = findWorldMotionPose(poses, definition.key);

					return (
						<MotionPoseCard key={definition.key} definition={definition} pose={pose} onAdd={onAdd} onUpdateBlendshapes={onUpdateBlendshapes} onUploadAnimation={onUploadAnimation} onDeleteAnimation={onDeleteAnimation} datalistId={datalistId} />
					);
				})}
			</div>
		</div>
	);
}
