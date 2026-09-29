import { useEffect, useState } from 'react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import Accordion from './common/Accordion.jsx';
import ConfirmationModal from './common/ConfirmationModal.jsx';
import RubricEditor, { FieldErrors } from './RubricEditor.jsx';
import { FIELD_INPUT, FIELD_LABEL } from '../utils/formFieldStyles.js';

const NEW_CAMPAIGN = { key: '', title: '', definition: { description: '', rubric: { guidance: '', dimensions: [{ name: '', description: '' }], tiers: [] } }, questIds: [] };

function toDraft(campaign) {
	return campaign ? { key: campaign.key, title: campaign.title, definition: { ...NEW_CAMPAIGN.definition, ...campaign.definition }, questIds: campaign.questIds } : NEW_CAMPAIGN;
}

function CampaignFields({ worldId, campaign, quests, onSaved, onCancel, addToast }) {
	const [draft, setDraft] = useState(() => toDraft(campaign));
	const [previous, setPrevious] = useState(campaign);
	const [errors, setErrors] = useState({});
	const [isSaving, setIsSaving] = useState(false);

	if (previous !== campaign) {
		setPrevious(campaign);
		setDraft(toDraft(campaign));
		setErrors({});
	}

	const update = (field, value) => setDraft((current) => ({ ...current, [field]: value }));
	const setDefinition = (field, value) => setDraft((current) => ({ ...current, definition: { ...current.definition, [field]: value } }));
	const errorsAt = (path, nested = false) => Object.entries(errors)
		.filter(([key]) => key === `definition.${path}` || (nested && key.startsWith(`definition.${path}.`)))
		.flatMap(([, messages]) => messages);
	const toggleQuest = (questId) => update('questIds', draft.questIds.includes(questId) ? draft.questIds.filter((id) => id !== questId) : [...draft.questIds, questId]);
	const canSave = draft.key.trim() !== '' && draft.title.trim() !== '' && !isSaving;

	const save = async () => {
		setIsSaving(true);
		try {
			const payload = { ...draft, key: draft.key.trim(), title: draft.title.trim() };
			const response = campaign
				? await api.patch(route('worlds.campaigns.update', { world: worldId, campaign: campaign.id }), payload)
				: await api.post(route('worlds.campaigns.store', { world: worldId }), payload);
			const body = await response.json();
			if (response.status === 422) {
				setErrors(body.errors ?? {});
				throw new Error(body.message);
			}
			if (!response.ok) throw new Error(body.message);
			setErrors({});
			addToast(`${body.title} saved`, 'success');
			await onSaved();
		} catch (error) { addToast(error.message || 'Failed to save the campaign', 'error'); } finally { setIsSaving(false); }
	};

	return (
		<div className="space-y-4">
			<div className="grid grid-cols-1 md:grid-cols-[1fr_16rem] gap-3">
				<div>
					<label className={FIELD_LABEL}>Title</label>
					<input value={draft.title} onChange={(event) => update('title', event.target.value)} placeholder="The River" className={`${FIELD_INPUT} placeholder:text-fg-3/60`} />
					<FieldErrors messages={errors.title} />
				</div>
				<div>
					<label className={FIELD_LABEL}>Key <span className="normal-case text-fg-3">(what quests name)</span></label>
					<input value={draft.key} onChange={(event) => update('key', event.target.value)} placeholder="the-river" className={`${FIELD_INPUT} placeholder:text-fg-3/60`} />
					<FieldErrors messages={errors.key} />
				</div>
			</div>
			<div>
				<label className={FIELD_LABEL}>Description</label>
				<textarea value={draft.definition.description ?? ''} onChange={(event) => setDefinition('description', event.target.value)} rows={2} placeholder="The river floods, and the valley decides whom to blame." className={`${FIELD_INPUT} resize-none placeholder:text-fg-3/60`} />
			</div>
			<div>
				<p className={FIELD_LABEL}>Quests</p>
				<div className="flex flex-wrap gap-1.5">
					{quests.length === 0 && <span className="text-fg-3 text-xs italic">Write quests first.</span>}
					{quests.map((quest) => {
						const selected = draft.questIds.includes(quest.id);
						const elsewhere = !selected && quest.campaignId !== null && quest.campaignId !== campaign?.id;
						return (
							<button key={quest.id} type="button" disabled={elsewhere} onClick={() => toggleQuest(quest.id)} title={elsewhere ? 'Already in another campaign' : undefined} className={`text-xs px-2 py-1 border transition-colors ${selected ? 'border-accent text-accent bg-accent/10 cursor-pointer' : elsewhere ? 'border-line-1 text-fg-3/50 cursor-default' : 'border-line-1 text-fg-3 hover:border-fg-3 cursor-pointer'}`}>
								{quest.title}
							</button>
						);
					})}
				</div>
				<FieldErrors messages={Object.entries(errors).filter(([key]) => key.startsWith('questIds')).flatMap(([, messages]) => messages)} />
			</div>
			<p className="text-fg-3 text-[0.65rem] tracking-[0.15em] flex items-center gap-3 pt-2"><span>ENDING</span><span className="h-px flex-1 bg-line-1" /></p>
			<RubricEditor value={draft.definition.rubric} onChange={(value) => setDefinition('rubric', value)} errorsAt={errorsAt} />
			<div className="flex justify-end gap-3">
				{onCancel && <button type="button" onClick={onCancel} className="text-fg-3 text-[0.7rem] tracking-[0.1em] px-4 py-1.5 cursor-pointer hover:text-fg-1 transition-colors">CANCEL</button>}
				<button type="button" onClick={save} disabled={!canSave} className={`text-[0.7rem] tracking-[0.1em] px-4 py-1.5 transition-colors ${canSave ? 'button-success cursor-pointer' : 'bg-bg-3 text-fg-3 cursor-default'}`}>
					{isSaving ? 'SAVING...' : campaign ? 'SAVE CAMPAIGN' : 'CREATE CAMPAIGN'}
				</button>
			</div>
		</div>
	);
}

function CampaignRow({ worldId, campaign, quests, onChanged, addToast }) {
	const [collapsed, setCollapsed] = useState(true);
	const [confirmingDelete, setConfirmingDelete] = useState(false);

	const remove = async () => {
		setConfirmingDelete(false);
		try {
			const response = await api.delete(route('worlds.campaigns.destroy', { world: worldId, campaign: campaign.id }));
			if (!response.ok) throw new Error((await response.json().catch(() => ({}))).message);
			addToast(`${campaign.title} deleted`, 'success');
			await onChanged();
		} catch (error) { addToast(error.message || 'Failed to delete the campaign', 'error'); }
	};

	return (
		<Accordion
			title={campaign.title}
			collapsed={collapsed}
			onToggle={() => setCollapsed((current) => !current)}
			onDelete={() => setConfirmingDelete(true)}
			badge={<span className="text-[0.6rem] tracking-[0.1em] border border-line-1 text-fg-3 px-1.5 py-0.5">{campaign.questIds.length} QUEST{campaign.questIds.length === 1 ? '' : 'S'}</span>}
		>
			<CampaignFields worldId={worldId} campaign={campaign} quests={quests} onSaved={onChanged} addToast={addToast} />
			{confirmingDelete && (
				<ConfirmationModal
					title="Delete campaign"
					message={`Delete "${campaign.title}"? Its quests stay, outside any campaign, and its endings in sessions are removed.`}
					options={[{ label: 'DELETE', value: 'confirm', destructive: true }, { label: 'CANCEL', value: 'cancel', cancel: true }]}
					onSelect={(selected) => { if (selected === 'confirm') void remove(); else setConfirmingDelete(false); }}
				/>
			)}
		</Accordion>
	);
}

/** The world's campaigns: named groups of quests with an ending of their own. */
export default function CampaignsEditor({ worldId, campaigns, questsVersion, onChanged, addToast }) {
	const [collapsed, setCollapsed] = useState(true);
	const [adding, setAdding] = useState(false);
	const [quests, setQuests] = useState([]);

	useEffect(() => {
		let active = true;
		const load = async () => {
			try {
				const response = await api.get(route('worlds.quests.index', { world: worldId }));
				if (!response.ok) throw new Error();
				const loaded = await response.json();
				if (active) setQuests(loaded);
			} catch {
				addToast('Failed to load quests', 'error');
			}
		};
		void load();
		return () => { active = false; };
	}, [worldId, questsVersion, campaigns, addToast]);

	return (
		<Accordion label="CAMPAIGNS" collapsed={collapsed} onToggle={() => setCollapsed((current) => !current)} actions={<span className="text-fg-3 text-xs">{campaigns.length} CAMPAIGN{campaigns.length === 1 ? '' : 'S'}</span>}>
			<div className="space-y-2">
				{campaigns.length === 0 && !adding && (
					<div className="border border-dashed border-line-1 px-4 py-6 text-center">
						<p className="text-fg-2 text-sm">No campaigns yet</p>
						<p className="text-fg-3 text-xs mt-1">A campaign groups quests into one story, with an ending of its own once they are all over.</p>
					</div>
				)}
				{campaigns.map((campaign) => (
					<CampaignRow key={campaign.id} worldId={worldId} campaign={campaign} quests={quests} onChanged={onChanged} addToast={addToast} />
				))}
				{adding ? (
					<div className="border border-accent/40 bg-accent/5 p-4 space-y-3 hud-enter-fade">
						<p className="text-accent text-[0.65rem] tracking-[0.15em]">NEW CAMPAIGN</p>
						<CampaignFields worldId={worldId} campaign={null} quests={quests} onCancel={() => setAdding(false)} onSaved={async () => { setAdding(false); await onChanged(); }} addToast={addToast} />
					</div>
				) : (
					<button type="button" onClick={() => setAdding(true)} className="w-full border border-dashed border-line-1 px-3 py-2 text-[0.7rem] tracking-[0.1em] text-fg-3 hover:text-accent hover:border-accent/50 transition-colors cursor-pointer">
						+ NEW CAMPAIGN
					</button>
				)}
			</div>
		</Accordion>
	);
}
