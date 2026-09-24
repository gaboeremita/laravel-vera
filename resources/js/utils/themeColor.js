export function themeColor(variable, fallback) {
	const value = getComputedStyle(document.documentElement).getPropertyValue(variable).trim();
	return value || fallback;
}

let probe = null;

/**
 * A theme colour as sRGB components from 0 to 1. Themes write colours as
 * oklch() or hex, which three.js cannot parse, so a canvas resolves them.
 */
export function themeRgb(variable, fallback) {
	probe ??= document.createElement('canvas').getContext('2d', { willReadFrequently: true });
	probe.clearRect(0, 0, 1, 1);
	probe.fillStyle = fallback;
	probe.fillStyle = themeColor(variable, fallback);
	probe.fillRect(0, 0, 1, 1);
	const [red, green, blue] = probe.getImageData(0, 0, 1, 1).data;
	return [red / 255, green / 255, blue / 255];
}
