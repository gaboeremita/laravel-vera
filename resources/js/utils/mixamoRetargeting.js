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
	const clip = AnimationClip.findByName(fbxAsset.animations, clipName);
	if (!clip) {
		console.warn(`[mixamoRetargeting] animation clip "${clipName}" not found in FBX asset`);
		return null;
	}

	const tracks = [];
	const restRotationInverse = new Quaternion();
	const parentRestWorldRotation = new Quaternion();
	const quatA = new Quaternion();
	const vec3 = new Vector3();

	const motionHipsHeight = fbxAsset.getObjectByName('mixamorigHips')?.position.y;
	const vrmHipsY = vrm.humanoid?.getNormalizedBoneNode('hips')?.getWorldPosition(vec3).y;
	const vrmRootY = vrm.scene.getWorldPosition(vec3).y;

	if (!vrmHipsY || !motionHipsHeight) {
		console.warn('[mixamoRetargeting] failed to calculate hips height scaling — likely not a Mixamo-rigged FBX');
		return null;
	}

	const vrmHipsHeight = Math.abs(vrmHipsY - vrmRootY);
	const hipsPositionScale = vrmHipsHeight / motionHipsHeight;

	clip.tracks.forEach((track) => {
		const [mixamoRigName, propertyName] = track.name.split('.');
		const vrmBoneName = MIXAMO_VRM_BONE_MAP[mixamoRigName];
		const vrmNodeName = vrm.humanoid?.getNormalizedBoneNode(vrmBoneName)?.name;
		const mixamoRigNode = fbxAsset.getObjectByName(mixamoRigName);

		if (!vrmNodeName) {
			if (vrmBoneName) {
				console.warn(`[mixamoRetargeting] VRM bone "${vrmBoneName}" not found in humanoid for Mixamo bone "${mixamoRigName}"`);
			}
			return;
		}

		mixamoRigNode?.getWorldQuaternion(restRotationInverse).invert();
		mixamoRigNode?.parent?.getWorldQuaternion(parentRestWorldRotation);

		if (track instanceof QuaternionKeyframeTrack) {
			for (let i = 0; i < track.values.length; i += 4) {
				const flatQuaternion = track.values.slice(i, i + 4);
				quatA.fromArray(flatQuaternion);

				// parent's rest-pose world rotation * track rotation * inverse of rest-pose world rotation
				quatA.premultiply(parentRestWorldRotation).multiply(restRotationInverse);

				quatA.toArray(flatQuaternion);
				flatQuaternion.forEach((v, index) => {
					track.values[index + i] = v;
				});
			}

			tracks.push(
				new QuaternionKeyframeTrack(
					`${vrmNodeName}.${propertyName}`,
					track.times,
					track.values.map((v, i) => (vrm.meta?.metaVersion === '0' && i % 2 === 0 ? -v : v))
				)
			);
		} else if (track instanceof VectorKeyframeTrack) {
			const value = track.values.map((v, i) => (vrm.meta?.metaVersion === '0' && i % 3 !== 1 ? -v : v) * hipsPositionScale);
			tracks.push(new VectorKeyframeTrack(`${vrmNodeName}.${propertyName}`, track.times, value));
		}
	});

	return new AnimationClip('mixamoRetargeted', clip.duration, tracks);
}
