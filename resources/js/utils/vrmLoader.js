import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { KTX2Loader } from 'three/addons/loaders/KTX2Loader.js';
import { MeshoptDecoder } from 'three/addons/libs/meshopt_decoder.module.js';
import { VRMLoaderPlugin } from '@pixiv/three-vrm';

// The Basis transcoder that ships with three.js, copied into public/ so the
// browser can fetch it next to its wasm.
const TRANSCODER_PATH = '/basis/';

const ktx2Loaders = new WeakMap();

function ktx2LoaderFor(renderer) {
	if (!ktx2Loaders.has(renderer)) {
		ktx2Loaders.set(renderer, new KTX2Loader().setTranscoderPath(TRANSCODER_PATH).detectSupport(renderer));
	}
	return ktx2Loaders.get(renderer);
}

/** A glTF loader able to read KTX2-compressed textures and meshopt-compressed geometry on this renderer. */
export function createGltfLoader(renderer) {
	const loader = new GLTFLoader();
	loader.setKTX2Loader(ktx2LoaderFor(renderer));
	loader.setMeshoptDecoder(MeshoptDecoder);
	return loader;
}

/** A glTF loader for VRM models, with the same compressed formats. */
export function createVrmLoader(renderer) {
	const loader = createGltfLoader(renderer);
	loader.register((parser) => new VRMLoaderPlugin(parser));
	return loader;
}
