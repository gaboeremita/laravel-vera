export function passageKey(regionId, passageId) {
	return `${regionId}:${passageId}`;
}

export function passageLabel(regions, regionId, passageId) {
	const region = regions.find((candidate) => candidate.id === regionId);
	const passage = region?.passages.find((candidate) => candidate.id === passageId);
	return region && passage ? `${region.name} – ${passage.name}` : passageId;
}

export function partnersByPassage(regions) {
	const partners = new Map();
	for (const region of regions) {
		for (const link of region.links ?? []) partners.set(passageKey(region.id, link.passageId), { regionId: link.targetRegionId, passageId: link.targetPassageId });
	}
	return partners;
}
