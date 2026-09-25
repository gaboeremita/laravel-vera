/** How far the music drops while a resident speaks. */
export const DUCKED_LEVEL = 0.25;

/**
 * The audio volume for a slider position. Loudness is heard roughly on a
 * log scale, so the slider is cubed to make every part of it matter.
 */
export function perceivedVolume(slider) {
	return Math.min(1, Math.max(0, slider)) ** 3;
}

export function musicVolume({ slider, muted, ducked }) {
	if (muted) return 0;
	return perceivedVolume(slider) * (ducked ? DUCKED_LEVEL : 1);
}
