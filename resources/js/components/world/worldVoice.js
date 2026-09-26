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
