import { FIELD_INPUT } from '../utils/formFieldStyles.js';

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

export default function PassageSelect({ regions, value, onChange, emptyLabel, exclude = null, disabled = false }) {
	const partners = partnersByPassage(regions);
	const selected = value ? passageKey(value.regionId, value.passageId) : '';

	const select = (key) => {
		if (key === '') return onChange(null);
		const separator = key.indexOf(':');
		onChange({ regionId: Number(key.slice(0, separator)), passageId: key.slice(separator + 1) });
	};

	return (
		<select value={selected} onChange={(event) => select(event.target.value)} className={FIELD_INPUT} disabled={disabled}>
			<option value="">{emptyLabel}</option>
			{regions.filter((region) => region.passages.length > 0).map((region) => (
				<optgroup key={region.id} label={region.name.toUpperCase()}>
					{region.passages.map((passage) => {
						const key = passageKey(region.id, passage.id);
						const partner = partners.get(key);
						const partnerNote = partner && key !== selected ? ` (↔ ${passageLabel(regions, partner.regionId, partner.passageId)})` : '';
						return <option key={key} value={key} disabled={key === exclude}>{`${region.name} – ${passage.name}${partnerNote}`}</option>;
					})}
				</optgroup>
			))}
		</select>
	);
}
