import { AnimationClip, QuaternionKeyframeTrack, Quaternion, Vector3, VectorKeyframeTrack } from 'three';

/**
 * Mixamo rig bone name -> VRM humanoid bone name. Ported from the reference
 * mapping used in @pixiv/three-vrm's own Mixamo-animation loading example
 * (community-verified via vrm-mixamo-retargeter, MIT licensed). Scoped to
 * Mixamo's specific bone-naming convention — an .fbx exported from a
 * different rigging pipeline will not retarget correctly against this map.
 */
export const MIXAMO_VRM_BONE_MAP = {
	mixamorigHips: 'hips',
	mixamorigSpine: 'spine',
	mixamorigSpine1: 'chest',
	mixamorigSpine2: 'upperChest',
	mixamorigNeck: 'neck',
	mixamorigHead: 'head',
	mixamorigLeftShoulder: 'leftShoulder',
	mixamorigLeftArm: 'leftUpperArm',
	mixamorigLeftForeArm: 'leftLowerArm',
	mixamorigLeftHand: 'leftHand',
	mixamorigLeftHandThumb1: 'leftThumbMetacarpal',
	mixamorigLeftHandThumb2: 'leftThumbProximal',
	mixamorigLeftHandThumb3: 'leftThumbDistal',
	mixamorigLeftHandIndex1: 'leftIndexProximal',
	mixamorigLeftHandIndex2: 'leftIndexIntermediate',
	mixamorigLeftHandIndex3: 'leftIndexDistal',
	mixamorigLeftHandMiddle1: 'leftMiddleProximal',
	mixamorigLeftHandMiddle2: 'leftMiddleIntermediate',
	mixamorigLeftHandMiddle3: 'leftMiddleDistal',
	mixamorigLeftHandRing1: 'leftRingProximal',
	mixamorigLeftHandRing2: 'leftRingIntermediate',
	mixamorigLeftHandRing3: 'leftRingDistal',
	mixamorigLeftHandPinky1: 'leftLittleProximal',
	mixamorigLeftHandPinky2: 'leftLittleIntermediate',
	mixamorigLeftHandPinky3: 'leftLittleDistal',
	mixamorigRightShoulder: 'rightShoulder',
	mixamorigRightArm: 'rightUpperArm',
	mixamorigRightForeArm: 'rightLowerArm',
	mixamorigRightHand: 'rightHand',
	mixamorigRightHandPinky1: 'rightLittleProximal',
	mixamorigRightHandPinky2: 'rightLittleIntermediate',
	mixamorigRightHandPinky3: 'rightLittleDistal',
	mixamorigRightHandRing1: 'rightRingProximal',
	mixamorigRightHandRing2: 'rightRingIntermediate',
	mixamorigRightHandRing3: 'rightRingDistal',
	mixamorigRightHandMiddle1: 'rightMiddleProximal',
	mixamorigRightHandMiddle2: 'rightMiddleIntermediate',
	mixamorigRightHandMiddle3: 'rightMiddleDistal',
	mixamorigRightHandIndex1: 'rightIndexProximal',
	mixamorigRightHandIndex2: 'rightIndexIntermediate',
	mixamorigRightHandIndex3: 'rightIndexDistal',
	mixamorigRightHandThumb1: 'rightThumbMetacarpal',
	mixamorigRightHandThumb2: 'rightThumbProximal',
	mixamorigRightHandThumb3: 'rightThumbDistal',
	mixamorigLeftUpLeg: 'leftUpperLeg',
	mixamorigLeftLeg: 'leftLowerLeg',
	mixamorigLeftFoot: 'leftFoot',
	mixamorigLeftToeBase: 'leftToes',
	mixamorigRightUpLeg: 'rightUpperLeg',
	mixamorigRightLeg: 'rightLowerLeg',
	mixamorigRightFoot: 'rightFoot',
	mixamorigRightToeBase: 'rightToes',
};

/**
 * The part of retargeting that depends only on the FBX: every mapped bone's
 * rotation re-expressed against the rig's rest pose, and the hips height the
 * motion was recorded at. One base clip serves every VRM, and it holds only
 * plain numbers and typed arrays, so it can be stored between sessions.
 * Returns null when the clip or the hips height can't be found.
 *
 * @param {import('three').Group} fbxAsset - loaded FBX object containing the Mixamo animation
 * @param {string} clipName - animation clip name inside the FBX (Mixamo default: "mixamo.com")
 * @returns {{ duration: number, motionHipsHeight: number, tracks: Array<{ bone: string, property: string, kind: 'quaternion' | 'vector', times: Float32Array, values: Float32Array }> } | null}
 */
export function mixamoBaseClip(fbxAsset, clipName = 'mixamo.com') {
	// Falls back to the first embedded clip rather than requiring the exact
	// name "mixamo.com" — that literal name only holds for an unmodified
	// Mixamo export; FBX files re-exported or converted through other tools
	// (e.g. Blender) commonly rename or prefix the clip.
	const clip = AnimationClip.findByName(fbxAsset.animations, clipName) ?? fbxAsset.animations[0];
	if (!clip) {
		console.warn('[mixamoRetargeting] no animation clip found in FBX asset');
		return null;
	}

	const motionHipsHeight = fbxAsset.getObjectByName('mixamorigHips')?.position.y;
	if (!motionHipsHeight) {
		console.warn('[mixamoRetargeting] failed to calculate hips height scaling — likely not a Mixamo-rigged FBX');
		return null;
	}

	const restRotationInverse = new Quaternion();
	const parentRestWorldRotation = new Quaternion();
	const quatA = new Quaternion();
	const tracks = [];

	for (const track of clip.tracks) {
		const [bone, property] = track.name.split('.');
		if (!MIXAMO_VRM_BONE_MAP[bone]) continue;

		if (track instanceof QuaternionKeyframeTrack) {
			const mixamoRigNode = fbxAsset.getObjectByName(bone);
			mixamoRigNode?.getWorldQuaternion(restRotationInverse).invert();
			mixamoRigNode?.parent?.getWorldQuaternion(parentRestWorldRotation);
			const values = new Float32Array(track.values.length);
			for (let i = 0; i < track.values.length; i += 4) {
				quatA.fromArray(track.values, i);
				// parent's rest-pose world rotation * track rotation * inverse of rest-pose world rotation
				quatA.premultiply(parentRestWorldRotation).multiply(restRotationInverse);
				quatA.toArray(values, i);
			}
			tracks.push({ bone, property, kind: 'quaternion', times: Float32Array.from(track.times), values });
		} else if (track instanceof VectorKeyframeTrack) {
			tracks.push({ bone, property, kind: 'vector', times: Float32Array.from(track.times), values: Float32Array.from(track.values) });
		}
	}

	return { duration: clip.duration, motionHipsHeight, tracks };
}

const FINGER_BONE = /Thumb|Index|Middle|Ring|Little/;

/**
 * Fits a base clip to one VRM: its bones' node names, VRM 0.x's mirrored
 * axes and its hips height. Returns null when the VRM's hips can't be found.
 *
 * @param {ReturnType<typeof mixamoBaseClip>} base
 * @param {import('@pixiv/three-vrm').VRM} vrm - target VRM
 * @returns {import('three').AnimationClip | null}
 */
export function retargetBaseClip(base, vrm) {
	const vec3 = new Vector3();
	const vrmHipsY = vrm.humanoid?.getNormalizedBoneNode('hips')?.getWorldPosition(vec3).y;
	const vrmRootY = vrm.scene.getWorldPosition(vec3).y;
	if (!vrmHipsY) {
		console.warn('[mixamoRetargeting] failed to calculate hips height scaling — the VRM has no hips');
		return null;
	}

	const hipsPositionScale = Math.abs(vrmHipsY - vrmRootY) / base.motionHipsHeight;
	const isVrm0 = vrm.meta?.metaVersion === '0';
	const tracks = [];

	for (const track of base.tracks) {
		const vrmBoneName = MIXAMO_VRM_BONE_MAP[track.bone];
		const vrmNodeName = vrm.humanoid?.getNormalizedBoneNode(vrmBoneName)?.name;
		if (!vrmNodeName) {
			// Fingers are optional in a VRM humanoid; a model without them just keeps its hands still.
			if (!FINGER_BONE.test(vrmBoneName)) console.warn(`[mixamoRetargeting] VRM bone "${vrmBoneName}" not found in humanoid for Mixamo bone "${track.bone}"`);
			continue;
		}
		const name = `${vrmNodeName}.${track.property}`;
		if (track.kind === 'quaternion') {
			tracks.push(new QuaternionKeyframeTrack(name, track.times, track.values.map((v, i) => (isVrm0 && i % 2 === 0 ? -v : v))));
		} else {
			tracks.push(new VectorKeyframeTrack(name, track.times, track.values.map((v, i) => (isVrm0 && i % 3 !== 1 ? -v : v) * hipsPositionScale)));
		}
	}

	return new AnimationClip('mixamoRetargeted', base.duration, tracks);
}

/**
 * Retargets a Mixamo FBX animation clip onto a VRM's normalized humanoid
 * bones. Returns null (rather than throwing) when the clip or hips-height
 * scaling can't be resolved, so a non-Mixamo .fbx fails as a load error
 * instead of crashing avatar rendering.
 *
 * @param {import('three').Group} fbxAsset - loaded FBX object containing the Mixamo animation
 * @param {import('@pixiv/three-vrm').VRM} vrm - target VRM
 * @param {string} clipName - animation clip name inside the FBX (Mixamo default: "mixamo.com")
 * @returns {import('three').AnimationClip | null}
 */
export function retargetMixamoAnimation(fbxAsset, vrm, clipName = 'mixamo.com') {
	const base = mixamoBaseClip(fbxAsset, clipName);
	return base ? retargetBaseClip(base, vrm) : null;
}
