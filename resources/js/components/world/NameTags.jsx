import { useEffect, useRef } from 'react';
import { useFrame, useThree } from '@react-three/fiber';
import { CanvasTexture, SRGBColorSpace, Sprite, SpriteMaterial, Vector3 } from 'three';

const TAG_HEIGHT_ABOVE_FEET = 2.05;
const SCREEN_SCALE = 0.045;
const MIN_WORLD_HEIGHT = 0.12;
const TEXTURE_WIDTH = 512;
const TEXTURE_HEIGHT = 96;
const ASPECT = TEXTURE_WIDTH / TEXTURE_HEIGHT;

function themeColor(variable, fallback) {
	const value = getComputedStyle(document.documentElement).getPropertyValue(variable).trim();
	return value || fallback;
}

function makeTagTexture(name, highlighted) {
	const canvas = document.createElement('canvas');
	canvas.width = TEXTURE_WIDTH;
	canvas.height = TEXTURE_HEIGHT;
	const context = canvas.getContext('2d');
	context.font = '600 44px sans-serif';
	const textWidth = Math.min(context.measureText(name).width, TEXTURE_WIDTH - 48);
	const boxWidth = textWidth + 48;
	const left = (TEXTURE_WIDTH - boxWidth) / 2;

	if (highlighted) {
		context.shadowColor = themeColor('--accent', '#7dd3fc');
		context.shadowBlur = 18;
	}
	context.fillStyle = highlighted ? themeColor('--accent', '#7dd3fc') : 'rgba(0, 0, 0, 0.6)';
	context.beginPath();
	context.roundRect(left, 14, boxWidth, TEXTURE_HEIGHT - 28, 34);
	context.fill();
	context.shadowBlur = 0;
	context.fillStyle = highlighted ? themeColor('--accent-fg', '#000000') : '#ffffff';
	context.textAlign = 'center';
	context.textBaseline = 'middle';
	context.fillText(name, TEXTURE_WIDTH / 2, TEXTURE_HEIGHT / 2, TEXTURE_WIDTH - 48);

	const texture = new CanvasTexture(canvas);
	texture.colorSpace = SRGBColorSpace;
	return texture;
}

export default function NameTags({ residents, residentPositions, activeResidentId }) {
	const { camera, scene, size } = useThree();
	const tags = useRef(new Map());
	const worldPoint = useRef(new Vector3());
	const screenPoint = useRef(new Vector3());

	useFrame(() => {
		const visible = [];
		for (const [residentId, tag] of tags.current) {
			const position = residentPositions.current.get(residentId);
			tag.sprite.visible = Boolean(position);
			if (!position) continue;
			const texture = residentId === activeResidentId ? tag.textures.highlighted : tag.textures.normal;
			if (tag.sprite.material.map !== texture) tag.sprite.material.map = texture;
			worldPoint.current.set(position.x, position.y + TAG_HEIGHT_ABOVE_FEET, position.z);
			const worldHeight = Math.max(camera.position.distanceTo(worldPoint.current) * SCREEN_SCALE, MIN_WORLD_HEIGHT);
			tag.sprite.scale.set(worldHeight * ASPECT, worldHeight, 1);
			visible.push({ tag, base: worldPoint.current.clone(), worldHeight, distance: camera.position.distanceTo(worldPoint.current) });
		}

		visible.sort((a, b) => a.distance - b.distance);
		const placed = [];
		for (const entry of visible) {
			const offset = entry.base.clone();
			for (let attempt = 0; attempt < visible.length; attempt++) {
				screenPoint.current.copy(offset).project(camera);
				const x = ((screenPoint.current.x + 1) / 2) * size.width;
				const y = ((1 - screenPoint.current.y) / 2) * size.height;
				const pixelHeight = (entry.worldHeight / (2 * entry.distance * Math.tan((camera.fov * Math.PI) / 360))) * size.height;
				const overlapping = placed.some((other) => Math.abs(other.x - x) < pixelHeight * ASPECT * 0.8 && Math.abs(other.y - y) < pixelHeight);
				if (!overlapping) {
					placed.push({ x, y });
					break;
				}
				offset.y += entry.worldHeight * 1.1;
			}
			entry.tag.sprite.position.copy(offset);
		}
	});

	useEffect(() => {
		const created = new Map();
		for (const resident of residents) {
			const textures = { normal: makeTagTexture(resident.assistant.name, false), highlighted: makeTagTexture(resident.assistant.name, true) };
			const sprite = new Sprite(new SpriteMaterial({ map: textures.normal, depthTest: false, depthWrite: false, transparent: true }));
			sprite.renderOrder = 1000;
			sprite.visible = false;
			scene.add(sprite);
			created.set(resident.id, { sprite, textures });
		}
		tags.current = created;
		return () => {
			for (const { sprite, textures } of created.values()) {
				scene.remove(sprite);
				sprite.material.dispose();
				textures.normal.dispose();
				textures.highlighted.dispose();
			}
		};
	}, [residents, scene]);

	return null;
}
