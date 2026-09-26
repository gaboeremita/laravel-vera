/** The zone access JSON typed into the resident editor, or an error saying what is wrong with it. */
export function parseZoneAccess(text) {
	if (text.trim() === '') return { zoneAccess: null, error: null };
	let value;
	try {
		value = JSON.parse(text);
	} catch {
		return { zoneAccess: null, error: 'Not valid JSON' };
	}
	if (value === null || typeof value !== 'object' || Array.isArray(value)) return { zoneAccess: null, error: 'Must be an object with "tags" and "zones"' };
	const unknownKeys = Object.keys(value).filter((key) => key !== 'tags' && key !== 'zones');
	if (unknownKeys.length > 0) return { zoneAccess: null, error: `Unknown key "${unknownKeys[0]}"; only "tags" and "zones" are allowed` };
	for (const key of ['tags', 'zones']) {
		const list = value[key] ?? [];
		if (!Array.isArray(list) || list.some((item) => typeof item !== 'string' || item.trim() === '')) return { zoneAccess: null, error: `"${key}" must be a list of names` };
	}
	return { zoneAccess: { tags: value.tags ?? [], zones: value.zones ?? [] }, error: null };
}

/**
 * Whether a resident may go into a zone on her own. Access granted to a zone
 * covers every zone inside it; a private zone that nothing at or above it
 * opened for her keeps her out. Mirrors ApplyResidentZoneAccess on the server.
 */
export function canEnterZone(layout, zoneId, zoneAccess) {
	const zonesById = new Map((layout?.zones ?? []).map((zone) => [zone.id, zone]));
	const chain = [];
	const seen = new Set();
	for (let id = zoneId; id != null && zonesById.has(id) && !seen.has(id); id = zonesById.get(id).parentId ?? null) {
		seen.add(id);
		chain.unshift(zonesById.get(id));
	}

	const tags = new Set((zoneAccess?.tags ?? []).map((tag) => tag.toLowerCase()));
	const zoneIds = new Set(zoneAccess?.zones ?? []);
	let granted = false;
	for (const zone of chain) {
		granted = granted || zoneIds.has(zone.id) || (zone.accessTags ?? []).some((tag) => tags.has(tag.toLowerCase()));
		if (zone.private && !granted) return false;
	}
	return true;
}
