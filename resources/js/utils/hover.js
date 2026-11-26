import { AnimationClip, QuaternionKeyframeTrack } from 'three';
import { boundPoseRotations } from './boundPose.js';

export const HOVER_HEIGHT = 0.12;
export const HOVER_BOB_HEIGHT = 0.03;
export const HOVER_BOB_SECONDS = 4;

const LEG_BONES = ['leftUpperLeg', 'leftLowerLeg', 'leftFoot', 'leftToes', 'rightUpperLeg', 'rightLowerLeg', 'rightFoot', 'rightToes'];

/** How far above her rest height a hovering body drifts at a moment, bobbing slowly around its hover height. */
export function hoverBob(elapsedSeconds) {
	return Math.sin((elapsedSeconds / HOVER_BOB_SECONDS) * Math.PI * 2) * HOVER_BOB_HEIGHT;
}

export function hoverOffset(elapsedSeconds) {
	return HOVER_HEIGHT + hoverBob(elapsedSeconds);
}

const heldLegs = new WeakMap();

function boundLegRotations(vrm) {
	if (!heldLegs.has(vrm)) heldLegs.set(vrm, boundPoseRotations(vrm, LEG_BONES));
	return heldLegs.get(vrm);
}

/** The clip with its leg tracks replaced by the legs' bound pose, so a hovering body's legs keep the pose they were modelled in. */
export function withHeldLegs(clip, vrm, legRotations = boundLegRotations(vrm)) {
	const legNodes = new Map(LEG_BONES.map((bone) => [vrm.humanoid.getNormalizedBoneNode(bone)?.name, bone]).filter(([node]) => node));
	const tracks = clip.tracks.filter((track) => !legNodes.has(track.name.slice(0, track.name.lastIndexOf('.'))));
	for (const [node, bone] of legNodes) {
		const rotation = legRotations.get(bone);
		if (rotation) tracks.push(new QuaternionKeyframeTrack(`${node}.quaternion`, [0], rotation.toArray()));
	}
	return new AnimationClip(clip.name, clip.duration, tracks);
}
