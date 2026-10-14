import { useEffect, useRef } from 'react';
import { useFrame, useThree } from '@react-three/fiber';
import { PerspectiveCamera } from 'three';
import { BACKDROP_LAYER } from './drawDistance.js';

const BACKDROP_NEAR = 0.1;

function createBackdropCamera() {
	const camera = new PerspectiveCamera();
	camera.layers.set(BACKDROP_LAYER);
	camera.matrixAutoUpdate = false;
	camera.matrixWorldAutoUpdate = false;
	return camera;
}

/**
 * Draws the backdrop first through a camera that reaches it from anywhere in
 * the world, then clears depth and draws the world on top, so the sky and
 * far silhouettes never fall past the world camera's reach.
 */
export default function BackdropRenderer({ far }) {
	const get = useThree((state) => state.get);
	const backdropCameraRef = useRef(null);

	useEffect(() => {
		const { gl } = get();
		const autoClear = gl.autoClear;
		const autoReset = gl.info.autoReset;
		gl.autoClear = false;
		gl.info.autoReset = false;
		return () => {
			gl.autoClear = autoClear;
			gl.info.autoReset = autoReset;
		};
	}, [get]);

	useFrame(({ gl, scene, camera }) => {
		backdropCameraRef.current ??= createBackdropCamera();
		const backdropCamera = backdropCameraRef.current;
		camera.updateMatrixWorld();
		if (backdropCamera.fov !== camera.fov || backdropCamera.aspect !== camera.aspect || backdropCamera.far !== far) {
			backdropCamera.fov = camera.fov;
			backdropCamera.aspect = camera.aspect;
			backdropCamera.near = BACKDROP_NEAR;
			backdropCamera.far = far;
			backdropCamera.updateProjectionMatrix();
		}
		backdropCamera.matrixWorld.copy(camera.matrixWorld);
		backdropCamera.matrixWorldInverse.copy(camera.matrixWorldInverse);

		gl.info.reset();
		gl.clear();
		gl.render(scene, backdropCamera);
		gl.clearDepth();
		// A colour background clears the screen on every render, even with autoClear off, so the world pass would erase the backdrop.
		const background = scene.background;
		scene.background = null;
		gl.render(scene, camera);
		scene.background = background;
	}, 1);

	return null;
}
