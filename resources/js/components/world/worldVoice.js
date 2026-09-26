const VOICE_STORAGE_KEY = 'worldVoiceEnabled';

/** How close the user has to be to a resident to hear her line voiced. */
export const EARSHOT = 12;

/** Whether the user turned world voices on; off until they do, since every voiced line costs credits. */
export function readVoiceEnabled(storage = globalThis.localStorage) {
	try {
		return storage?.getItem(VOICE_STORAGE_KEY) === 'on';
	} catch {
		return false;
	}
}

export function storeVoiceEnabled(enabled, storage = globalThis.localStorage) {
	try {
		storage?.setItem(VOICE_STORAGE_KEY, enabled ? 'on' : 'off');
	} catch {
		// Private windows and blocked storage keep the choice for this visit only.
	}
}

const FACING_COS = Math.cos(Math.PI / 3);
const CACHED_LINES = 30;

/** Whether the user is looking toward a point: within 60° of where they face. A missing view counts as facing it. */
export function facesPoint(view, point) {
	if (!view || !point) return true;
	const dx = point.x - view.x;
	const dz = point.z - view.z;
	const length = Math.hypot(dx, dz);
	if (length < 0.001) return true;
	return (-Math.sin(view.yaw) * dx - Math.cos(view.yaw) * dz) / length >= FACING_COS;
}

/**
 * A line is voiced with voices on, within earshot, and when the user is
 * facing the speaker or the line is said to them.
 */
export function voicesLine({ voiceEnabled, distance, facing = true, toUser = false }) {
	return voiceEnabled && distance <= EARSHOT && (facing || toUser);
}

/** Recently voiced lines by speaker and text, so a repeated line is played again without paying for it twice. */
export function createLineCache(limit = CACHED_LINES) {
	const lines = new Map();
	return {
		get(key) {
			if (!lines.has(key)) return null;
			const audio = lines.get(key);
			lines.delete(key);
			lines.set(key, audio);
			return audio;
		},
		set(key, audio) {
			lines.delete(key);
			lines.set(key, audio);
			if (lines.size > limit) lines.delete(lines.keys().next().value);
		},
	};
}

/**
 * Says a line: voiced when it can be, otherwise talked for its reading time.
 * The gesture starts together with the voice, once the audio has arrived, so
 * it plays while she speaks.
 *
 * @returns {Promise<number>} how long she speaks, in seconds
 */
export async function deliverLine({ voiced, synthesize, play, gesture, estimate, talk, onError = () => {} }) {
	let gestured = false;
	const gestureOnce = () => {
		if (gestured) return;
		gestured = true;
		gesture();
	};
	let seconds = 0;
	if (voiced) {
		try {
			const audio = await synthesize();
			if (audio) {
				gestureOnce();
				seconds = (await play(audio)) ?? 0;
			}
		} catch (error) {
			onError(error);
		}
	}
	if (seconds === 0) {
		gestureOnce();
		seconds = estimate();
		talk(seconds);
	}
	return seconds;
}
