import { useState } from 'react';
import PoseEditor from './PoseEditor.jsx';
import DefaultPoseEditor from './DefaultPoseEditor.jsx';
import WorldMotionPoseEditor from './WorldMotionPoseEditor.jsx';
import { isWorldMotionPose, POSTURES } from './world/worldMotionPoses.js';

const postureOf = (pose) => pose.posture ?? 'standing';

/**
 * Poses grouped by the posture they are made for. Each posture has its own
 * default pose, held while she is in it. Walking and greeting are standing
 * motion poses; swimming has its own, for moving and resting at the side.
 *
 * @param {Array} poses - [{id, name, posture, vrm_blendshapes, animation_url}]
 * @param {function} onAdd - (name, blendshapes, file, posture) => void
 * @param {function} onDelete - (pose) => void
 * @param {function} onUpdatePose - (pose, name, blendshapes, posture) => void
 * @param {function} onUploadAnimation - (pose, file) => void
 * @param {function} onDeleteAnimation - (pose) => void
 * @param {function} onUpdateDefaultBlendshapes - (blendshapes, posture) => void
 * @param {function} onUploadDefaultAnimation - (file, posture) => void
 * @param {function} onDeleteDefaultAnimation - (posture) => void
 */
export default function PosturePoseSections({ poses, onAdd, onDelete, onUpdatePose, onUploadAnimation, onDeleteAnimation, onUpdateDefaultBlendshapes, onUploadDefaultAnimation, onDeleteDefaultAnimation }) {
	const [posture, setPosture] = useState('standing');
	const posturePoses = poses.filter((pose) => postureOf(pose) === posture);
	const defaultPose = posturePoses.find((pose) => pose.name === 'default') || { name: 'default', posture, vrm_blendshapes: null, animation_url: null, animation_original_name: null };
	const label = POSTURES.find(({ key }) => key === posture).label;

	return (
		<div className="space-y-4">
			<div className="flex flex-wrap gap-2">
				{POSTURES.map(({ key, label: postureLabel }) => {
					const count = poses.filter((pose) => postureOf(pose) === key && pose.name !== 'default').length;
					return (
						<button
							key={key}
							type="button"
							onClick={() => setPosture(key)}
							aria-pressed={posture === key}
							className={`px-3 py-1 text-[0.65rem] tracking-[0.1em] uppercase border transition-colors cursor-pointer ${
								posture === key ? 'border-accent text-accent bg-bg-1' : 'border-line-1 text-fg-3 hover:border-fg-3'
							}`}
						>
							{postureLabel} ({count})
						</button>
					);
				})}
			</div>

			<DefaultPoseEditor
				key={posture}
				pose={defaultPose}
				hint={posture === 'standing' ? 'used when nothing else is triggered — optional' : `held while ${posture}; the standing default is used when this is empty — optional`}
				onUpdateBlendshapes={(blendshapes) => onUpdateDefaultBlendshapes(blendshapes, posture)}
				onUploadAnimation={(file) => onUploadDefaultAnimation(file, posture)}
				onDeleteAnimation={() => onDeleteDefaultAnimation(posture)}
			/>

			{(posture === 'standing' || posture === 'swimming') && (
				<WorldMotionPoseEditor
					key={`motion-${posture}`}
					posture={posture}
					poses={posturePoses}
					onAdd={(name, blendshapes, file) => onAdd(name, blendshapes, file, posture)}
					onUpdateBlendshapes={(pose, name, blendshapes) => onUpdatePose(pose, name, blendshapes, posture)}
					onUploadAnimation={onUploadAnimation}
					onDeleteAnimation={onDeleteAnimation}
				/>
			)}

			<PoseEditor
				label={`${label} Poses`}
				poses={posturePoses.filter((pose) => pose.name !== 'default' && !isWorldMotionPose(pose))}
				onAdd={(name, blendshapes, file) => onAdd(name, blendshapes, file, posture)}
				onDelete={onDelete}
				onUpdateBlendshapes={onUpdatePose}
				onUploadAnimation={onUploadAnimation}
				onDeleteAnimation={onDeleteAnimation}
			/>
		</div>
	);
}
