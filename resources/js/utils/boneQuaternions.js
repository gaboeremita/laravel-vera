// vrm.humanoid.humanBones returns the *raw* bones — a different node than
// the *normalized* ones AnimationMixer clips actually target (built via
// getNormalizedBoneNode() in both createVRMAnimationClip and
// mixamoRetargeting.js) and that vrm.update() re-derives raw bones from
// every frame. Reading/writing the raw set here would silently do nothing
// (or get immediately overwritten) — every bone lookup in this file must go
// through getNormalizedBoneNode() to touch the nodes actually being driven.
export function captureBoneQuaternions(vrm) {
	const map = new Map();
	for (const name of Object.keys(vrm.humanoid.humanBones)) {
		const node = vrm.humanoid.getNormalizedBoneNode(name);
		if (node) map.set(name, node.quaternion.clone());
	}
	return map;
}

export function applyBoneQuaternions(vrm, quatMap) {
	for (const [name, quat] of quatMap) {
		const node = vrm.humanoid.getNormalizedBoneNode(name);
		if (node) node.quaternion.copy(quat);
	}
}
