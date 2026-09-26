import { FBXLoader } from 'three/addons/loaders/FBXLoader.js';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { VRMAnimationLoaderPlugin, createVRMAnimationClip } from '@pixiv/three-vrm-animation';
import { mixamoBaseClip, retargetBaseClip } from './mixamoRetargeting.js';
import { STORES, readCached, writeCached } from './worldCache.js';

const baseClips = new Map();
const vrmAnimations = new Map();
const readStored = (url) => readCached(STORES.poseClips, url);
const writeStored = (url, base) => writeCached(STORES.poseClips, url, base);

/**
 * The model-independent part of a Mixamo FBX pose, parsed once per file per
 * session and kept between visits, keyed by the file's URL (a replaced file
 * gets a new URL).
 */
export function mixamoBaseClipFor(url, { load = (fileUrl) => new FBXLoader().loadAsync(fileUrl), read = readStored, write = writeStored } = {}) {
	if (!baseClips.has(url)) {
		const pending = (async () => {
			const stored = await read(url);
			if (stored) return stored;
			const base = mixamoBaseClip(await load(url));
			if (base) void write(url, base);
			return base;
		})();
		pending.catch(() => baseClips.delete(url));
		baseClips.set(url, pending);
	}
	return baseClips.get(url);
}

export function clearPoseClipCache() {
	baseClips.clear();
	vrmAnimations.clear();
}

/** A pose's animation clip fitted to one VRM, from a Mixamo FBX or a VRM animation file. */
export async function loadPoseClip(url, vrm) {
	if (url.toLowerCase().endsWith('.fbx')) {
		const base = await mixamoBaseClipFor(url);
		return base ? retargetBaseClip(base, vrm) : null;
	}

	if (!vrmAnimations.has(url)) {
		const loader = new GLTFLoader();
		loader.register((parser) => new VRMAnimationLoaderPlugin(parser));
		const pending = loader.loadAsync(url).then((gltf) => gltf.userData.vrmAnimations?.[0] ?? null);
		pending.catch(() => vrmAnimations.delete(url));
		vrmAnimations.set(url, pending);
	}
	const vrmAnimation = await vrmAnimations.get(url);
	return vrmAnimation ? createVRMAnimationClip(vrmAnimation, vrm) : null;
}
