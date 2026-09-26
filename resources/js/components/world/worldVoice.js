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

export function voicesLine({ voiceEnabled, distance }) {
	return voiceEnabled && distance <= EARSHOT;
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
