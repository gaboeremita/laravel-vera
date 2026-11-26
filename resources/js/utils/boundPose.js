import { Matrix4, Quaternion, Vector3 } from 'three';

const position = new Vector3();
const scale = new Vector3();

/**
 * Each named humanoid bone's normalized rotation that puts its raw bone where the VRM's skin was bound, so the model
 * keeps the pose it was made in. three-vrm sets raw = P⁻¹·n·P·B, P being the raw parent's rest world rotation and B the
 * raw bone's rest local rotation, so n = P·r·B⁻¹·P⁻¹ for the bound local rotation r. Bones the VRM lacks are left out.
 *
 * @param {import('@pixiv/three-vrm').VRM} vrm
 * @param {string[]} bones
 * @returns {Map<string, Quaternion>}
 */
export function boundPoseRotations(vrm, bones) {
	const sceneInverse = new Matrix4().copy(vrm.scene.matrixWorld).invert();
	const bound = new Map();
	vrm.scene.traverse((node) => {
		if (!node.isSkinnedMesh) return;
		node.skeleton.bones.forEach((bone, index) => {
			if (!bound.has(bone)) bound.set(bone, new Matrix4().copy(node.bindMatrix).multiply(new Matrix4().copy(node.skeleton.boneInverses[index]).invert()));
		});
	});
	const boundRotation = (node) => {
		const rotation = new Quaternion();
		(bound.get(node) ?? new Matrix4().multiplyMatrices(sceneInverse, node.matrixWorld)).decompose(position, rotation, scale);
		return rotation;
	};

	const rig = vrm.humanoid._normalizedHumanBones;
	const rotations = new Map();
	for (const name of bones) {
		const raw = vrm.humanoid.getRawBoneNode(name);
		const parentRest = rig?._parentWorldRotations?.[name];
		if (!raw?.parent || !parentRest) continue;
		const wanted = boundRotation(raw.parent).invert().multiply(boundRotation(raw));
		rotations.set(name, parentRest.clone().multiply(wanted).multiply(rig._boneRotations[name].clone().invert()).multiply(parentRest.clone().invert()));
	}
	return rotations;
}
