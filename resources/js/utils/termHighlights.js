/** Split text into plain and underlined parts from [start, length] ranges. */
export function underlinedParts(text, ranges) {
	const sorted = [...(ranges ?? [])].sort((a, b) => a[0] - b[0]);
	const parts = [];
	let position = 0;

	for (const [start, length] of sorted) {
		if (start < position) continue;
		if (start > position) parts.push({ text: text.slice(position, start), underlined: false });
		parts.push({ text: text.slice(start, start + length), underlined: true });
		position = start + length;
	}

	if (position < text.length) parts.push({ text: text.slice(position), underlined: false });

	return parts;
}
