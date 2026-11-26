import { AdditiveBlending, BufferAttribute, BufferGeometry, CanvasTexture, Euler, Points, PointsMaterial, Quaternion, Sprite, SpriteMaterial, Vector3 } from 'three';

export const ORBIT_SECONDS = 40;
export const FLOAT_HEIGHT = 0.04;
export const FLOAT_SECONDS = 5;
export const SWAY_ANGLE = 0.06;
export const SWAY_SECONDS = 10;
const SWAY_LAG = 0.7;
const SPARKLE_COUNT = 70;
const FAINT_SPARKLE_COUNT = 18;
const FAINT_SPARKLE_OPACITY = 0.45;
export const SPARKLE_COLOR = 0x8fe9ff;
export const FAKE_SPARKLE_COLOR = 0xffc46b;
const SPARKLE_RISE = 0.18;
const SPARKLE_TOP = 1.95;
const GLOW_CENTRE = new Vector3(0, 1.05, 0);
const GLOW_OPACITY = 0.5;
const HAZE_OPACITY = 0.18;
const HAZE_OFFSET = 0.45;
export const GLOW_BREATH_SECONDS = 9;
const UP = new Vector3(0, 1, 0);
const towardCamera = new Vector3();

/** How a bone moves on its own from its name: `orbit_…` turns about its own axis, `float_…` bobs along it, `sway_…` bends in a wave that runs down its chain. */
export function ornamentMotion(name) {
	if (name.startsWith('orbit_')) return 'orbit';
	if (name.startsWith('float_')) return 'float';
	if (name.startsWith('sway_')) return 'sway';
	return null;
}

/**
 * The bones under root that move on their own, each with its rest pose. An orbiting or floating chain moves once, at its
 * outermost bone; every bone of a swaying chain bends, `depth` links below the chain's first bone.
 */
export function findOrnaments(root) {
	const ornaments = [];
	const found = new Map();
	let chains = 0;
	root.traverse((node) => {
		const motion = node.isBone ? ornamentMotion(node.name) : null;
		const chained = Boolean(motion) && node.parent?.isBone && ornamentMotion(node.parent.name) === motion;
		if (!motion || (chained && motion !== 'sway')) return;
		const parent = chained ? found.get(node.parent) : null;
		const ornament = { node, motion, phase: parent ? parent.phase : chains++, depth: parent ? parent.depth + 1 : 0, restQuaternion: node.quaternion.clone(), restPosition: node.position.clone() };
		found.set(node, ornament);
		ornaments.push(ornament);
	});
	return ornaments;
}

/**
 * The turn (radians), lift (metres) and swing (radians about the bone's own x and z) an ornament has at a moment;
 * neighbours turn opposite ways and bob out of step, and a swaying bone bends a little after the one above it.
 */
export function ornamentPose(motion, phase, elapsedSeconds, depth = 0) {
	if (motion === 'orbit') {
		return { turn: (phase % 2 === 0 ? 1 : -1) * (elapsedSeconds / (ORBIT_SECONDS + phase * 3)) * Math.PI * 2, lift: 0, swing: [0, 0] };
	}
	if (motion === 'sway') {
		const lag = depth * SWAY_LAG;
		return {
			turn: 0,
			lift: 0,
			swing: [
				SWAY_ANGLE * Math.sin((elapsedSeconds / (SWAY_SECONDS + phase * 0.4)) * Math.PI * 2 - lag + phase * 1.3),
				0.6 * SWAY_ANGLE * Math.sin((elapsedSeconds / (SWAY_SECONDS * 0.73)) * Math.PI * 2 - lag + phase * 2.1),
			],
		};
	}
	return { turn: 0, lift: Math.sin((elapsedSeconds / FLOAT_SECONDS) * Math.PI * 2 + phase) * FLOAT_HEIGHT, swing: [0, 0] };
}

const turnQuaternion = new Quaternion();
const swingRotation = new Euler();
const axis = new Vector3();

export function moveOrnaments(ornaments, elapsedSeconds) {
	for (const { node, motion, phase, depth, restQuaternion, restPosition } of ornaments) {
		const { turn, lift, swing } = ornamentPose(motion, phase, elapsedSeconds, depth);
		node.quaternion.copy(restQuaternion).multiply(turnQuaternion.setFromAxisAngle(UP, turn));
		if (motion === 'sway') node.quaternion.multiply(turnQuaternion.setFromEuler(swingRotation.set(swing[0], 0, swing[1])));
		axis.copy(UP).applyQuaternion(restQuaternion);
		node.position.copy(restPosition).addScaledVector(axis, lift);
	}
}

/** How far through its slow breath the glow is at a moment: 0 dim and small, 1 bright and large. */
export function glowBreath(elapsedSeconds) {
	return 0.5 - 0.5 * Math.cos((elapsedSeconds / GLOW_BREATH_SECONDS) * Math.PI * 2);
}

/** A hologram's brightness at a moment, around 1: two slow waves and, now and then, a brief drop. */
export function shimmerLevel(elapsedSeconds, phase) {
	const wave = 0.18 * Math.sin(elapsedSeconds * 1.3 + phase) + 0.08 * Math.sin(elapsedSeconds * 3.7 + phase * 2);
	const tick = Math.sin((Math.floor(elapsedSeconds * 6) + phase) * 12.9898) * 43758.5453;
	const flicker = tick - Math.floor(tick) > 0.97 ? 0.35 : 0;
	return 1 + wave - flicker;
}

function glowTexture() {
	const canvas = document.createElement('canvas');
	canvas.width = canvas.height = 128;
	const context = canvas.getContext('2d');
	const gradient = context.createRadialGradient(64, 64, 0, 64, 64, 64);
	gradient.addColorStop(0, 'rgba(120, 220, 255, 0.9)');
	gradient.addColorStop(0.4, 'rgba(60, 170, 255, 0.35)');
	gradient.addColorStop(1, 'rgba(0, 80, 160, 0)');
	context.fillStyle = gradient;
	context.fillRect(0, 0, 128, 128);
	return new CanvasTexture(canvas);
}

/** Sparkles rising around a body; rise() moves them up by one frame. */
function risingSparkles(count, color = SPARKLE_COLOR) {
	const positions = new Float32Array(count * 3);
	const speeds = new Float32Array(count);
	for (let index = 0; index < count; index += 1) {
		const angle = Math.random() * Math.PI * 2;
		const radius = 0.2 + Math.random() * 0.35;
		positions.set([Math.cos(angle) * radius, Math.random() * SPARKLE_TOP, Math.sin(angle) * radius], index * 3);
		speeds[index] = SPARKLE_RISE * (0.5 + Math.random());
	}
	const geometry = new BufferGeometry();
	geometry.setAttribute('position', new BufferAttribute(positions, 3));
	const sparkleMaterial = new PointsMaterial({ color, size: 0.025, transparent: true, opacity: 0.8, blending: AdditiveBlending, depthWrite: false });
	const sparkles = new Points(geometry, sparkleMaterial);
	sparkles.frustumCulled = false;

	return {
		sparkles,
		geometry,
		sparkleMaterial,
		rise(delta) {
			for (let index = 0; index < count; index += 1) {
				let y = positions[index * 3 + 1] + speeds[index] * delta;
				if (y > SPARKLE_TOP) y -= SPARKLE_TOP;
				positions[index * 3 + 1] = y;
			}
			geometry.attributes.position.needsUpdate = true;
		},
	};
}

/** A few faint sparkles rising around a loaded VRM, with its materials left as they are. */
export function createFaintShimmer(vrm, color) {
	const { sparkles, geometry, sparkleMaterial, rise } = risingSparkles(FAINT_SPARKLE_COUNT, color);
	vrm.scene.add(sparkles);

	return {
		update(elapsedSeconds, delta) {
			rise(delta);
			sparkleMaterial.opacity = FAINT_SPARKLE_OPACITY * (0.7 + 0.3 * Math.sin(elapsedSeconds * 1.7));
		},
		dispose() {
			vrm.scene.remove(sparkles);
			geometry.dispose();
			sparkleMaterial.dispose();
		},
	};
}

/**
 * Hologram effects on a loaded VRM: its MToon materials pulse and flicker, cyan sparkles rise around it, a soft glow
 * sits behind it and a fainter haze lies over it on the camera's side, both slowly dimming and shrinking, then
 * brightening and growing. update() runs each frame before the VRM's own update; dispose() puts the materials back.
 */
export function createHologram(vrm) {
	const materials = [];
	vrm.scene.traverse((node) => {
		for (const material of [node.material].flat()) {
			if (material?.isMToonMaterial && !materials.some((entry) => entry.material === material)) {
				materials.push({ material, emissiveIntensity: material.emissiveIntensity, opacity: material.opacity, phase: materials.length * 1.7 });
			}
		}
	});

	const { sparkles, geometry, sparkleMaterial, rise } = risingSparkles(SPARKLE_COUNT);

	const glowMap = glowTexture();
	const glowMaterial = new SpriteMaterial({ map: glowMap, transparent: true, opacity: GLOW_OPACITY, blending: AdditiveBlending, depthWrite: false });
	const glow = new Sprite(glowMaterial);
	glow.position.copy(GLOW_CENTRE);
	const glowSize = [1.9, 2.8];

	// A wider, fainter haze sits between the body and the camera, so the glow also lies over her and not only behind.
	const hazeMaterial = new SpriteMaterial({ map: glowMap, transparent: true, opacity: HAZE_OPACITY, blending: AdditiveBlending, depthWrite: false });
	const haze = new Sprite(hazeMaterial);
	const hazeSize = [2.4, 3.0];
	haze.renderOrder = 10;
	haze.onBeforeRender = (renderer, scene, camera) => {
		vrm.scene.worldToLocal(camera.getWorldPosition(towardCamera));
		haze.position.copy(GLOW_CENTRE).addScaledVector(towardCamera.sub(GLOW_CENTRE).normalize(), HAZE_OFFSET);
		haze.updateMatrixWorld();
	};

	vrm.scene.add(sparkles, glow, haze);

	return {
		update(elapsedSeconds, delta) {
			for (const entry of materials) {
				const level = shimmerLevel(elapsedSeconds, entry.phase);
				entry.material.emissiveIntensity = entry.emissiveIntensity * level;
				if (entry.material.transparent) entry.material.opacity = Math.min(1, entry.opacity * (0.9 + 0.1 * level));
			}
			rise(delta);
			sparkleMaterial.opacity = 0.55 + 0.25 * Math.sin(elapsedSeconds * 2.1);
			const breath = glowBreath(elapsedSeconds);
			const strength = (0.7 + 0.3 * breath) * shimmerLevel(elapsedSeconds, 0.5);
			const size = 0.9 + 0.15 * breath;
			glowMaterial.opacity = GLOW_OPACITY * strength;
			hazeMaterial.opacity = HAZE_OPACITY * strength;
			glow.scale.set(glowSize[0] * size, glowSize[1] * size, 1);
			haze.scale.set(hazeSize[0] * size, hazeSize[1] * size, 1);
		},
		dispose() {
			for (const { material, emissiveIntensity, opacity } of materials) {
				material.emissiveIntensity = emissiveIntensity;
				material.opacity = opacity;
			}
			vrm.scene.remove(sparkles, glow, haze);
			geometry.dispose();
			sparkleMaterial.dispose();
			glowMaterial.dispose();
			hazeMaterial.dispose();
			glowMap.dispose();
		},
	};
}
