import { useCallback, useEffect, useState } from 'react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import Accordion from './common/Accordion.jsx';
import ConfirmationModal from './common/ConfirmationModal.jsx';
import QuestEditor from './QuestEditor.jsx';
import useQuestOptions from '../hooks/useQuestOptions.js';

const CHIP = 'text-[0.6rem] tracking-[0.1em] border px-1.5 py-0.5';

function QuestRow({ worldId, quest, options, campaigns, onChanged, addToast }) {
	const [collapsed, setCollapsed] = useState(true);
	const [confirmingDelete, setConfirmingDelete] = useState(false);
	const campaign = campaigns.find((candidate) => candidate.id === quest.campaignId);
	const beatCount = quest.definition.beats?.length ?? 0;

	const remove = async () => {
		setConfirmingDelete(false);
		try {
			const response = await api.delete(route('worlds.quests.destroy', { world: worldId, quest: quest.id }));
			if (!response.ok) throw new Error((await response.json().catch(() => ({}))).message);
			addToast(`${quest.title} deleted`, 'success');
			await onChanged();
		} catch (error) { addToast(error.message || 'Failed to delete the quest', 'error'); }
	};

	return (
		<Accordion
			title={quest.title}
			collapsed={collapsed}
			onToggle={() => setCollapsed((current) => !current)}
			onDelete={() => setConfirmingDelete(true)}
			badge={
				<span className="flex flex-wrap items-center gap-1.5">
					{campaign && <span className={`${CHIP} border-accent/40 text-accent`}>{campaign.title.toUpperCase()}</span>}
					<span className={`${CHIP} border-line-1 text-fg-3`}>{beatCount} BEAT{beatCount === 1 ? '' : 'S'}</span>
					{quest.problems.length > 0 && <span className={`${CHIP} border-warning/50 text-warning`}>⚠ {quest.problems.length} PROBLEM{quest.problems.length === 1 ? '' : 'S'}</span>}
				</span>
			}
		>
			{quest.problems.length > 0 && (
				<div className="border border-warning/40 bg-warning/5 px-3 py-2 space-y-0.5">
					{quest.problems.map((problem) => <p key={problem} className="text-warning text-xs">{problem}</p>)}
				</div>
			)}
			<QuestEditor worldId={worldId} quest={quest} options={options} campaigns={campaigns} onSaved={onChanged} addToast={addToast} />
			{confirmingDelete && (
				<ConfirmationModal
					title="Delete quest"
					message={quest.sessionCount > 0
						? `Delete "${quest.title}"? It is in ${quest.sessionCount} session${quest.sessionCount === 1 ? '' : 's'}; its progress, log and endings there will be removed.`
						: `Delete "${quest.title}"?`}
					options={[{ label: 'DELETE', value: 'confirm', destructive: true }, { label: 'CANCEL', value: 'cancel', cancel: true }]}
					onSelect={(selected) => { if (selected === 'confirm') void remove(); else setConfirmingDelete(false); }}
				/>
			)}
		</Accordion>
	);
}

/** The world's quests: each opens in the quest editor, with the problems the world has since introduced. */
export default function QuestsEditor({ worldId, campaigns = [], worldVersion = 0, onQuestsChange, addToast }) {
	const [collapsed, setCollapsed] = useState(true);
	const [adding, setAdding] = useState(false);
	const [quests, setQuests] = useState([]);
	const { options, reload: reloadOptions } = useQuestOptions(worldId, addToast, worldVersion);

	const fetchQuests = useCallback(async () => {
		const response = await api.get(route('worlds.quests.index', { world: worldId }));
		if (!response.ok) throw new Error();
		return response.json();
	}, [worldId]);

	useEffect(() => {
		let active = true;
		const load = async () => {
			try {
				const loaded = await fetchQuests();
				if (active) setQuests(loaded);
			} catch {
				addToast('Failed to load quests', 'error');
			}
		};
		void load();
		return () => { active = false; };
	}, [fetchQuests, campaigns, worldVersion, addToast]);

	const reload = useCallback(async () => {
		try {
			setQuests(await fetchQuests());
			await reloadOptions();
			await onQuestsChange?.();
		} catch {
			addToast('Failed to load quests', 'error');
		}
	}, [fetchQuests, reloadOptions, onQuestsChange, addToast]);

	return (
		<Accordion label="QUESTS" collapsed={collapsed} onToggle={() => setCollapsed((current) => !current)} actions={<span className="text-fg-3 text-xs">{quests.length} QUEST{quests.length === 1 ? '' : 'S'}</span>}>
			<div className="space-y-2">
				{quests.length === 0 && !adding && (
					<div className="border border-dashed border-line-1 px-4 py-6 text-center">
						<p className="text-fg-2 text-sm">No quests yet</p>
						<p className="text-fg-3 text-xs mt-1">Quests give the player beats to move through and end with an ending judged against your rubric.</p>
					</div>
				)}
				{quests.map((quest) => (
					<QuestRow key={quest.id} worldId={worldId} quest={quest} options={options} campaigns={campaigns} onChanged={reload} addToast={addToast} />
				))}
				{adding ? (
					<div className="border border-accent/40 bg-accent/5 p-4 space-y-3 hud-enter-fade">
						<p className="text-accent text-[0.65rem] tracking-[0.15em]">NEW QUEST</p>
						<QuestEditor worldId={worldId} quest={null} options={options} campaigns={campaigns} onCancel={() => setAdding(false)} onSaved={async () => { setAdding(false); await reload(); }} addToast={addToast} />
					</div>
				) : (
					<button type="button" onClick={() => setAdding(true)} className="w-full border border-dashed border-line-1 px-3 py-2 text-[0.7rem] tracking-[0.1em] text-fg-3 hover:text-accent hover:border-accent/50 transition-colors cursor-pointer">
						+ NEW QUEST
					</button>
				)}
			</div>
		</Accordion>
	);
}
