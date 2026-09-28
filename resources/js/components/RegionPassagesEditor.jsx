import { useState } from 'react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import Accordion from './common/Accordion.jsx';
import ConfirmationModal from './common/ConfirmationModal.jsx';
import PassageSelect, { passageKey, passageLabel, partnersByPassage } from './PassageSelect.jsx';

export default function RegionPassagesEditor({ worldId, region, regions, spawn, onLinksChange, addToast }) {
	const [collapsed, setCollapsed] = useState(false);
	const [pendingLink, setPendingLink] = useState(null);
	const passages = region.layout?.passages ?? [];
	const partners = partnersByPassage(regions);

	const saveLink = async (passageId, target) => {
		try {
			const params = { world: worldId, region: region.id, passage: passageId };
			const response = target
				? await api.put(route('worlds.regions.passages.link.update', params), { targetRegionId: target.regionId, targetPassageId: target.passageId })
				: await api.delete(route('worlds.regions.passages.link.destroy', params));
			if (!response.ok) throw new Error((await response.json().catch(() => ({}))).message || 'Unable to save the link');
			await onLinksChange();
		} catch (error) { addToast(error.message || 'Unable to save the link', 'error'); }
	};

	const choose = (passageId, target) => {
		const targetPartner = target ? partners.get(passageKey(target.regionId, target.passageId)) : null;
		const replacesOtherLink = targetPartner && !(targetPartner.regionId === region.id && targetPartner.passageId === passageId);
		if (replacesOtherLink) return setPendingLink({ passageId, target, previousPartner: targetPartner });
		void saveLink(passageId, target);
	};

	return (
		<Accordion label="PASSAGES" collapsed={collapsed} onToggle={() => setCollapsed((current) => !current)} actions={<span className="text-fg-3 text-xs">{passages.length}</span>}>
			{passages.length === 0 ? (
				<p className="text-warning text-xs">⚠ This region's environment has no passage markers, so it cannot be reached.</p>
			) : (
				<div className="space-y-3">
					{passages.map((passage) => {
						const partner = partners.get(passageKey(region.id, passage.id)) ?? null;
						const isSpawn = spawn?.regionId === region.id && spawn?.passageId === passage.id;
						return (
							<div key={passage.id} className="grid grid-cols-1 md:grid-cols-[1fr_2fr] gap-2 items-center">
								<p className="text-fg-1 text-sm">{isSpawn && <span className="text-accent" title="Spawn point">★ </span>}{passage.name}{!partner && <span className="text-warning"> ⚠</span>}</p>
								<PassageSelect regions={regions} value={partner} onChange={(target) => choose(passage.id, target)} emptyLabel="— unlinked —" exclude={passageKey(region.id, passage.id)} />
							</div>
						);
					})}
				</div>
			)}
			{pendingLink && (
				<ConfirmationModal
					title="Replace link"
					message={`${passageLabel(regions, pendingLink.target.regionId, pendingLink.target.passageId)} is linked to ${passageLabel(regions, pendingLink.previousPartner.regionId, pendingLink.previousPartner.passageId)}. Link it here instead? That passage will be unlinked.`}
					options={[{ label: 'LINK', value: 'confirm' }, { label: 'CANCEL', value: 'cancel', cancel: true }]}
					onSelect={(selected) => { const link = pendingLink; setPendingLink(null); if (selected === 'confirm') void saveLink(link.passageId, link.target); }}
				/>
			)}
		</Accordion>
	);
}
