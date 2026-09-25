import { useEffect, useMemo, useRef, useState } from 'react';
import { useFrame } from '@react-three/fiber';
import { AdditiveBlending, CanvasTexture, CylinderGeometry, DoubleSide, PlaneGeometry, ShaderMaterial, SpriteMaterial } from 'three';
import { themeRgb } from '../../utils/themeColor.js';
import { getGroundHeight } from './groundHeight.js';
import { RESTING_POSTURES } from './playerPostures.js';
import { isCompact } from './objectFocus.js';
import { hasRoomFor } from './spotOccupancy.js';

const FADE_SECONDS = 0.3;
const RING_SIZE = 0.9;
const COLUMN_HEIGHT = 2.4;
const COLUMN_RADIUS = 0.32;
const DOT_HEIGHT = 1.1;
const SPOT_DOT_HEIGHT = 0.8;
const DOT_SIZE = 0.16;

const RING_VERTEX = `
varying vec2 vUv;
void main() {
	vUv = uv;
	gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
}`;

const RING_FRAGMENT = `
uniform vec3 uColor;
uniform vec3 uTakenColor;
uniform float uTime;
uniform float uFade;
uniform float uTaken;
varying vec2 vUv;
void main() {
	vec2 p = (vUv - 0.5) * 2.0;
	float r = length(p);
	float angle = atan(p.y, p.x);
	float pulse = 0.65 + 0.35 * sin(uTime * 2.6);
	float ring = smoothstep(0.74, 0.8, r) * (1.0 - smoothstep(0.84, 0.9, r));
	float halo = exp(-pow((r - 0.82) * 7.0, 2.0)) * 0.55;
	float band = smoothstep(0.5, 0.53, r) * (1.0 - smoothstep(0.57, 0.6, r));
	float dash = step(0.45, fract((angle + uTime * 0.6) / 0.5236));
	float core = (1.0 - smoothstep(0.0, 0.5, r)) * 0.12 * pulse;
	float alpha = (ring * pulse + halo * pulse + band * dash * 0.7 + core) * uFade * mix(1.0, 0.45, uTaken);
	gl_FragColor = vec4(mix(uColor, uTakenColor, uTaken), alpha);
}`;

const COLUMN_FRAGMENT = `
uniform vec3 uColor;
uniform float uTime;
uniform float uFade;
varying vec2 vUv;
float hash(vec2 p) { return fract(sin(dot(p, vec2(12.9898, 78.233))) * 43758.5453); }
void main() {
	float fall = pow(1.0 - vUv.y, 1.6);
	float veil = fall * 0.28;
	vec2 cell = vec2(floor(vUv.x * 28.0), floor(vUv.y * 10.0 - uTime * 0.9));
	float mote = step(0.965, hash(cell)) * fall;
	float alpha = (veil + mote * 0.9) * uFade;
	gl_FragColor = vec4(uColor, alpha);
}`;

function makeDotTexture() {
	const canvas = document.createElement('canvas');
	canvas.width = 64;
	canvas.height = 64;
	const context = canvas.getContext('2d');
	const gradient = context.createRadialGradient(32, 32, 0, 32, 32, 32);
	gradient.addColorStop(0, 'rgba(255,255,255,1)');
	gradient.addColorStop(0.35, 'rgba(255,255,255,0.55)');
	gradient.addColorStop(1, 'rgba(255,255,255,0)');
	context.fillStyle = gradient;
	context.fillRect(0, 0, 64, 64);
	return new CanvasTexture(canvas);
}

function useReducedMotion() {
	const reduced = useRef(false);
	useEffect(() => {
		const query = window.matchMedia('(prefers-reduced-motion: reduce)');
		reduced.current = query.matches;
		const change = (event) => { reduced.current = event.matches; };
		query.addEventListener('change', change);
		return () => query.removeEventListener('change', change);
	}, []);
	return reduced;
}

function isRestingSpot(spot) {
	return spot.activities.some((activity) => RESTING_POSTURES.includes(activity.posture));
}

function ringPosition(spot, collisionWorld) {
	if (isRestingSpot(spot)) return [spot.position.x, spot.position.y + 0.03, spot.position.z];
	const { x, y, z } = spot.approach;
	const ground = collisionWorld ? getGroundHeight(x, z, collisionWorld.octree, y - 2, y + 0.3) : null;
	return [x, (ground ?? y) + 0.03, z];
}

function ObjectBeacon({ object, visible, colors, occupiedSpots, collisionWorld, reducedMotion, onFaded }) {
	const groupRef = useRef(null);
	const fade = useRef(0);
	const time = useRef(0);
	const ringGeometry = useMemo(() => new PlaneGeometry(RING_SIZE, RING_SIZE), []);
	const columnGeometry = useMemo(() => new CylinderGeometry(COLUMN_RADIUS, COLUMN_RADIUS, COLUMN_HEIGHT, 32, 1, true), []);
	const ringMaterials = useMemo(() => object.spots.map(() => new ShaderMaterial({
		uniforms: { uColor: { value: colors.accent }, uTakenColor: { value: colors.warning }, uTime: { value: 0 }, uFade: { value: 0 }, uTaken: { value: 0 } },
		vertexShader: RING_VERTEX,
		fragmentShader: RING_FRAGMENT,
		transparent: true,
		depthWrite: false,
		blending: AdditiveBlending,
		side: DoubleSide,
	})), [object, colors]);
	const columnMaterial = useMemo(() => new ShaderMaterial({
		uniforms: { uColor: { value: colors.accent }, uTime: { value: 0 }, uFade: { value: 0 } },
		vertexShader: RING_VERTEX,
		fragmentShader: COLUMN_FRAGMENT,
		transparent: true,
		depthWrite: false,
		blending: AdditiveBlending,
		side: DoubleSide,
	}), [colors]);
	const ringPositions = useMemo(() => object.spots.map((spot) => ringPosition(spot, collisionWorld)), [object, collisionWorld]);
	const showColumn = useMemo(() => isCompact(object), [object]);

	useEffect(() => () => {
		ringGeometry.dispose();
		columnGeometry.dispose();
	}, [ringGeometry, columnGeometry]);
	useEffect(() => () => ringMaterials.forEach((material) => material.dispose()), [ringMaterials]);
	useEffect(() => () => columnMaterial.dispose(), [columnMaterial]);

	useFrame((_, delta) => {
		if (!reducedMotion.current) time.current += delta;
		const target = visible ? 1 : 0;
		const stepSize = delta / FADE_SECONDS;
		fade.current = target > fade.current ? Math.min(target, fade.current + stepSize) : Math.max(target, fade.current - stepSize);
		for (const mesh of groupRef.current?.children ?? []) {
			const { uniforms } = mesh.material;
			uniforms.uTime.value = time.current;
			uniforms.uFade.value = fade.current;
			const { spot } = mesh.userData;
			if (spot) uniforms.uTaken.value = hasRoomFor(occupiedSpots.current, spot, 'user') ? 0 : 1;
		}
		if (!visible && fade.current === 0) onFaded(object.id);
	});

	return (
		<group ref={groupRef}>
			{object.spots.map((spot, index) => (
				<mesh key={spot.id} userData={{ spot }} geometry={ringGeometry} material={ringMaterials[index]} position={ringPositions[index]} rotation={[-Math.PI / 2, 0, 0]} renderOrder={900} />
			))}
			{showColumn && <mesh geometry={columnGeometry} material={columnMaterial} position={[object.position.x, object.position.y + COLUMN_HEIGHT / 2, object.position.z]} renderOrder={899} />}
		</group>
	);
}

function NearbyDot({ object, material }) {
	const points = isCompact(object)
		? [[object.position.x, object.position.y + DOT_HEIGHT, object.position.z]]
		: object.spots.map((spot) => [spot.position.x, spot.position.y + SPOT_DOT_HEIGHT, spot.position.z]);
	return points.map((point) => <sprite key={point.join(':')} material={material} position={point} scale={[DOT_SIZE, DOT_SIZE, 1]} renderOrder={898} />);
}

/**
 * Objects are marker points with no mesh of their own, so interactivity is
 * drawn at the points: rings on the focused object's spots, a light column at
 * the object, and a faint breathing dot at every other object nearby.
 */
export default function SpotBeacons({ layout, focusedObjectId, nearbyIds, occupiedSpots, collisionWorld }) {
	const reducedMotion = useReducedMotion();
	const [previousFocus, setPreviousFocus] = useState(focusedObjectId);
	const [leaving, setLeaving] = useState([]);
	const colors = useMemo(() => ({ accent: themeRgb('--accent', '#7dd3fc'), warning: themeRgb('--warning', '#fbbf24') }), []);
	const dotTexture = useMemo(() => makeDotTexture(), []);
	const dotMaterial = useMemo(() => new SpriteMaterial({ map: dotTexture, color: `rgb(${colors.accent.map((channel) => Math.round(channel * 255)).join(',')})`, transparent: true, depthWrite: false, blending: AdditiveBlending, opacity: 0.4 }), [dotTexture, colors]);
	const breath = useRef(0);
	const dotsRef = useRef(null);

	if (previousFocus !== focusedObjectId) {
		setPreviousFocus(focusedObjectId);
		setLeaving((current) => [...current.filter((id) => id !== focusedObjectId && id !== previousFocus), ...(previousFocus ? [previousFocus] : [])].filter((id) => id !== focusedObjectId));
	}

	useEffect(() => () => {
		dotMaterial.dispose();
		dotTexture.dispose();
	}, [dotMaterial, dotTexture]);

	useFrame((_, delta) => {
		if (!reducedMotion.current) breath.current += delta;
		const material = dotsRef.current?.children[0]?.material;
		if (material) material.opacity = reducedMotion.current ? 0.35 : 0.22 + 0.2 * (0.5 + 0.5 * Math.sin(breath.current * 2.6));
	});

	const objectsById = useMemo(() => new Map((layout?.objects ?? []).map((object) => [object.id, object])), [layout]);
	const shown = [...leaving.map((id) => ({ id, visible: false })), ...(focusedObjectId ? [{ id: focusedObjectId, visible: true }] : [])];
	const handleFaded = (id) => setLeaving((current) => current.filter((leavingId) => leavingId !== id));

	return (
		<>
			<group ref={dotsRef}>
				{nearbyIds.filter((id) => id !== focusedObjectId && objectsById.has(id)).map((id) => <NearbyDot key={id} object={objectsById.get(id)} material={dotMaterial} />)}
			</group>
			{shown.filter(({ id }) => objectsById.has(id)).map(({ id, visible }) => (
				<ObjectBeacon key={id} object={objectsById.get(id)} visible={visible} colors={colors} occupiedSpots={occupiedSpots} collisionWorld={collisionWorld} reducedMotion={reducedMotion} onFaded={handleFaded} />
			))}
		</>
	);
}
