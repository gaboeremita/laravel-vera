import { useEffect, useMemo, useRef } from 'react';
import { useFrame } from '@react-three/fiber';
import { BufferGeometry, CanvasTexture, Color, Float32BufferAttribute, LineBasicMaterial, LineLoop, SRGBColorSpace, Sprite, SpriteMaterial } from 'three';
import { themeColor, themeRgb } from '../../utils/themeColor.js';
import { outlineCentroid } from './zoneLanding.js';

const HEIGHT_ABOVE_FLOOR = 0.05;
const DRAW_SECONDS = 0.6;
const LABEL_HEIGHT = 0.5;
const LABEL_ABOVE_FLOOR = 0.6;

function labelTexture(name) {
	const canvas = document.createElement('canvas');
	canvas.width = 512;
	canvas.height = 96;
	const context = canvas.getContext('2d');
	context.font = `600 40px ${themeColor('--font-display', 'sans-serif')}`;
	context.fillStyle = themeColor('--accent', '#00ffc8');
	context.textAlign = 'center';
	context.textBaseline = 'middle';
	context.fillText(name.toUpperCase(), 256, 48, 480);
	const texture = new CanvasTexture(canvas);
	texture.colorSpace = SRGBColorSpace;
	return texture;
}

/**
 * Every zone of the region drawn on the ground: its outline, drawn along its
 * length when shown, and its name floating at its middle.
 */
export default function ZoneOverlay({ layout }) {
	const elapsed = useRef(0);
	const overlayRef = useRef(null);
	const reducedMotion = useMemo(() => window.matchMedia('(prefers-reduced-motion: reduce)').matches, []);
	const color = useMemo(() => new Color(...themeRgb('--accent', '#00ffc8')), []);
	const zones = useMemo(() => (layout?.zones ?? []).filter((zone) => (zone.outline ?? []).length >= 3).map((zone) => {
		const points = zone.outline.flatMap(([x, z]) => [x, zone.minY + HEIGHT_ABOVE_FLOOR, z]);
		const geometry = new BufferGeometry();
		geometry.setAttribute('position', new Float32BufferAttribute(points, 3));
		geometry.setDrawRange(0, 0);
		const line = new LineLoop(geometry, new LineBasicMaterial({ color, transparent: true, opacity: 0.85, depthTest: false }));
		line.renderOrder = 10;
		line.userData.vertexCount = zone.outline.length;
		const middle = outlineCentroid(zone.outline);
		const texture = labelTexture(zone.name);
		const label = new Sprite(new SpriteMaterial({ map: texture, transparent: true, opacity: 0, depthTest: false }));
		label.position.set(middle.x, zone.minY + LABEL_ABOVE_FLOOR, middle.z);
		label.scale.set(LABEL_HEIGHT * (512 / 96), LABEL_HEIGHT, 1);
		label.renderOrder = 11;
		return { id: zone.id, line, label, texture };
	}), [layout, color]);

	useEffect(() => () => {
		for (const zone of zones) {
			zone.line.geometry.dispose();
			zone.line.material.dispose();
			zone.label.material.dispose();
			zone.texture.dispose();
		}
	}, [zones]);

	useFrame((_, delta) => {
		if (elapsed.current >= DRAW_SECONDS) return;
		elapsed.current = Math.min(DRAW_SECONDS, elapsed.current + (reducedMotion ? DRAW_SECONDS : delta));
		const progress = elapsed.current / DRAW_SECONDS;
		for (const group of overlayRef.current?.children ?? []) {
			const [line, label] = group.children;
			line.geometry.setDrawRange(0, Math.max(2, Math.ceil(line.userData.vertexCount * progress)));
			label.material.opacity = progress;
		}
	});

	return (
		<group ref={overlayRef}>
			{zones.map((zone) => (
				<group key={zone.id}>
					<primitive object={zone.line} />
					<primitive object={zone.label} />
				</group>
			))}
		</group>
	);
}
