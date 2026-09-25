import { useEffect, useRef } from 'react';
import { useFrame, useThree } from '@react-three/fiber';
import { CanvasTexture, SRGBColorSpace, Sprite, SpriteMaterial, Vector3 } from 'three';

const BUBBLE_HEIGHT_ABOVE_FEET = 2.45;
const SCREEN_SCALE = 0.23;
const MIN_WORLD_HEIGHT = 0.6;
const TEXTURE_WIDTH = 1536;
const FONT_SIZE = 84;
const LINE_HEIGHT = 102;
const PADDING = 52;
const MAX_LINES = 3;
const FONT = `700 ${FONT_SIZE}px sans-serif`;

function wrapLines(context, text, maxWidth) {
	const lines = [];
	let line = '';
	for (const word of text.split(/\s+/)) {
		const candidate = line ? `${line} ${word}` : word;
		if (context.measureText(candidate).width > maxWidth && line) {
			lines.push(line);
			line = word;
		} else {
			line = candidate;
		}
	}
	if (line) lines.push(line);
	if (lines.length > MAX_LINES) {
		lines.length = MAX_LINES;
		lines[MAX_LINES - 1] = `${lines[MAX_LINES - 1]}…`;
	}
	return lines;
}

function makeBubbleTexture(text, variant) {
	const canvas = document.createElement('canvas');
	const context = canvas.getContext('2d');
	context.font = FONT;
	const lines = wrapLines(context, text, TEXTURE_WIDTH - PADDING * 2);
	canvas.width = TEXTURE_WIDTH;
	canvas.height = lines.length * LINE_HEIGHT + PADDING * 2 + 48;

	const speech = variant === 'speech';
	context.fillStyle = speech ? 'rgba(17, 17, 17, 0.92)' : 'rgba(255, 255, 255, 0.95)';
	context.beginPath();
	context.roundRect(8, 8, TEXTURE_WIDTH - 16, canvas.height - 64, 56);
	context.fill();
	context.beginPath();
	if (speech) {
		context.moveTo(TEXTURE_WIDTH / 2 - 40, canvas.height - 57);
		context.lineTo(TEXTURE_WIDTH / 2 + 40, canvas.height - 57);
		context.lineTo(TEXTURE_WIDTH / 2, canvas.height - 4);
		context.closePath();
	} else {
		context.arc(TEXTURE_WIDTH / 2 - 48, canvas.height - 36, 20, 0, Math.PI * 2);
		context.arc(TEXTURE_WIDTH / 2 - 88, canvas.height - 12, 10, 0, Math.PI * 2);
	}
	context.fill();

	context.font = FONT;
	context.fillStyle = speech ? '#ffffff' : '#111111';
	context.textAlign = 'center';
	context.textBaseline = 'middle';
	lines.forEach((line, index) => context.fillText(line, TEXTURE_WIDTH / 2, PADDING + LINE_HEIGHT * index + LINE_HEIGHT / 2));

	const texture = new CanvasTexture(canvas);
	texture.colorSpace = SRGBColorSpace;
	return { texture, aspect: canvas.width / canvas.height };
}

/**
 * A line above each resident's head: by default the reason-and-action line of
 * her self-chosen step while it runs, or with the speech variant what she
 * says out loud.
 */
export default function ThoughtBubble({ thoughts, residentPositions, variant = 'thought' }) {
	const { camera, scene } = useThree();
	const bubbles = useRef(new Map());
	const worldPoint = useRef(new Vector3());

	useFrame(() => {
		for (const [residentId, bubble] of bubbles.current) {
			const position = residentPositions.current.get(residentId);
			bubble.sprite.visible = Boolean(position);
			if (!position) continue;
			worldPoint.current.set(position.x, position.y + BUBBLE_HEIGHT_ABOVE_FEET, position.z);
			const worldWidth = Math.max(camera.position.distanceTo(worldPoint.current) * SCREEN_SCALE, MIN_WORLD_HEIGHT) * 2;
			const worldHeight = worldWidth / bubble.aspect;
			bubble.sprite.scale.set(worldWidth, worldHeight, 1);
			bubble.sprite.position.set(worldPoint.current.x, worldPoint.current.y + worldHeight / 2, worldPoint.current.z);
		}
	});

	useEffect(() => {
		const created = new Map();
		for (const [residentId, line] of Object.entries(thoughts)) {
			if (!line) continue;
			const { texture, aspect } = makeBubbleTexture(line, variant);
			const sprite = new Sprite(new SpriteMaterial({ map: texture, depthTest: false, depthWrite: false, transparent: true }));
			sprite.renderOrder = 1001;
			sprite.visible = false;
			scene.add(sprite);
			created.set(Number(residentId), { sprite, texture, aspect });
		}
		bubbles.current = created;
		return () => {
			for (const { sprite, texture } of created.values()) {
				scene.remove(sprite);
				sprite.material.dispose();
				texture.dispose();
			}
		};
	}, [thoughts, scene, variant]);

	return null;
}
