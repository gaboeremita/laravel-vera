import { useEffect, useState } from 'react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import Accordion from './common/Accordion.jsx';
import ConfirmationModal from './common/ConfirmationModal.jsx';
import { FIELD_INPUT, FIELD_LABEL } from '../utils/formFieldStyles.js';

const EMPTY_FACT = { topic: '', content: '', disclosure: '', relayResidentIds: [] };

function sameFact(a, b) {
	return a.topic === b.topic && a.content === b.content && a.disclosure === b.disclosure
		&& JSON.stringify([...a.relayResidentIds].sort()) === JSON.stringify([...b.relayResidentIds].sort());
}

function RelayPicker({ candidates, selectedIds, onChange }) {
	if (candidates.length === 0) {
		return <p className="text-fg-3 text-xs border border-dashed border-line-1 px-3 py-3">No other residents in this world yet.</p>;
	}

	const toggle = (id) => onChange(selectedIds.includes(id) ? selectedIds.filter((selected) => selected !== id) : [...selectedIds, id]);

	return (
		<div className="flex flex-wrap gap-2">
			{candidates.map((candidate) => {
				const selected = selectedIds.includes(candidate.id);
				return (
					<button
						key={candidate.id}
						type="button"
						aria-pressed={selected}
						onClick={() => toggle(candidate.id)}
						className={`text-xs px-3 py-1.5 border transition-colors cursor-pointer ${selected ? 'border-accent/60 bg-accent/10 text-accent' : 'border-line-1 text-fg-2 hover:border-accent/40 hover:text-fg-1'}`}
					>
						{candidate.name}
					</button>
				);
			})}
		</div>
	);
}

function FactForm({ fact, relayCandidates, saveLabel, onSave, onCancel }) {
	const [draft, setDraft] = useState(fact);
	const [isSaving, setIsSaving] = useState(false);
	const [error, setError] = useState(null);
	const dirty = !sameFact(draft, fact);
	const complete = draft.topic.trim() !== '' && draft.content.trim() !== '' && draft.disclosure.trim() !== '';
	const disabled = !dirty || !complete || isSaving;

	const save = async () => {
		setIsSaving(true);
		setError(await onSave(draft));
		setIsSaving(false);
	};

	return (
		<div className="space-y-3">
			<div>
				<label className={FIELD_LABEL}>Topic <span className="normal-case text-fg-3">(what it is about, the only part they see until the player knows it)</span></label>
				<input value={draft.topic} maxLength={120} onChange={(event) => setDraft((current) => ({ ...current, topic: event.target.value }))} className={FIELD_INPUT} />
			</div>
			<div>
				<label className={FIELD_LABEL}>Secret</label>
				<textarea value={draft.content} rows={3} onChange={(event) => setDraft((current) => ({ ...current, content: event.target.value }))} className={`${FIELD_INPUT} resize-none`} />
			</div>
			<div>
				<label className={FIELD_LABEL}>When they share it <span className="normal-case text-fg-3">(plain language, e.g. only in the chapel, for 50 credits)</span></label>
				<textarea value={draft.disclosure} rows={2} onChange={(event) => setDraft((current) => ({ ...current, disclosure: event.target.value }))} className={`${FIELD_INPUT} resize-none`} />
			</div>
			<div>
				<p className={FIELD_LABEL}>Can be told by the player <span className="normal-case text-fg-3">(they know the topic and act on it once the player tells them)</span></p>
				<RelayPicker candidates={relayCandidates} selectedIds={draft.relayResidentIds} onChange={(relayResidentIds) => setDraft((current) => ({ ...current, relayResidentIds }))} />
			</div>
			{error && <p className="text-danger text-xs">{error}</p>}
			<div className="flex justify-end gap-3">
				{onCancel && (
					<button type="button" onClick={onCancel} className="text-fg-3 text-[0.7rem] tracking-[0.1em] px-4 py-1.5 cursor-pointer hover:text-fg-1 transition-colors">
						CANCEL
					</button>
				)}
				<button
					type="button"
					onClick={save}
					disabled={disabled}
					className={`text-[0.7rem] tracking-[0.1em] px-4 py-1.5 transition-colors ${disabled ? 'bg-bg-3 text-fg-3 cursor-default' : 'button-success cursor-pointer'}`}
				>
					{isSaving ? 'SAVING...' : saveLabel}
				</button>
			</div>
		</div>
	);
}

async function errorMessage(response, fallback) {
	const body = await response.json().catch(() => ({}));
	return Object.values(body.errors ?? {})[0]?.[0] ?? body.message ?? fallback;
}

/**
 * The facts a resident holds: secrets they share in character, and the other
 * residents who can act on each once the player knows it.
 */
export default function ResidentFactsEditor({ worldId, resident, otherResidents, onFactsChange, addToast }) {
	const [facts, setFacts] = useState([]);
	const [toolsUnsupported, setToolsUnsupported] = useState(null);
	const [isLoading, setIsLoading] = useState(true);
	const [openId, setOpenId] = useState(null);
	const [isAdding, setIsAdding] = useState(false);
	const [deleting, setDeleting] = useState(null);
	const relayCandidates = otherResidents.map((other) => ({ id: other.id, name: other.assistant.name }));

	useEffect(() => {
		const load = async () => {
			try {
				const response = await api.get(route('worlds.residents.facts.index', { world: worldId, resident: resident.id }));
				if (!response.ok) throw new Error();
				const body = await response.json();
				setFacts(body.facts);
				setToolsUnsupported(body.toolsUnsupported);
			} catch { addToast('Failed to load facts', 'error'); } finally { setIsLoading(false); }
		};
		void load();
	}, [worldId, resident.id, addToast]);

	const create = async (draft) => {
		const response = await api.post(route('worlds.residents.facts.store', { world: worldId, resident: resident.id }), draft);
		if (!response.ok) return errorMessage(response, 'Unable to save the fact');
		const saved = await response.json();
		setFacts((current) => [...current, saved].sort((a, b) => a.topic.localeCompare(b.topic)));
		setIsAdding(false);
		onFactsChange?.();
		return null;
	};

	const update = async (fact, draft) => {
		const response = await api.patch(route('worlds.residents.facts.update', { world: worldId, resident: resident.id, fact: fact.id }), draft);
		if (!response.ok) return errorMessage(response, 'Unable to save the fact');
		const saved = await response.json();
		setFacts((current) => current.map((item) => (item.id === saved.id ? saved : item)));
		onFactsChange?.();
		return null;
	};

	const destroy = async (fact) => {
		try {
			const response = await api.delete(route('worlds.residents.facts.destroy', { world: worldId, resident: resident.id, fact: fact.id }));
			if (!response.ok) throw new Error();
			setFacts((current) => current.filter((item) => item.id !== fact.id));
			onFactsChange?.();
		} catch { addToast('Unable to delete the fact', 'error'); }
	};

	if (isLoading) return <p className="text-fg-3 text-xs">Loading facts...</p>;

	return (
		<div className="space-y-2">
			{toolsUnsupported && <p className="text-danger text-xs border border-danger/40 bg-danger/5 px-3 py-2">{toolsUnsupported}</p>}
			{facts.length === 0 && !isAdding && (
				<p className="text-fg-3 text-xs border border-dashed border-line-1 px-3 py-3">Holds no facts. Add a secret they share only when the moment is right.</p>
			)}
			{facts.map((fact) => (
				<Accordion
					key={fact.id}
					title={fact.topic}
					collapsed={openId !== fact.id}
					onToggle={() => setOpenId((current) => (current === fact.id ? null : fact.id))}
					onDelete={() => setDeleting(fact)}
					badge={fact.usage > 0 ? <span className="text-fg-3 text-[0.65rem] tracking-[0.1em]">KNOWN IN {fact.usage} SESSION{fact.usage === 1 ? '' : 'S'}</span> : null}
				>
					<FactForm key={JSON.stringify(fact)} fact={fact} relayCandidates={relayCandidates} saveLabel="SAVE FACT" onSave={(draft) => update(fact, draft)} />
				</Accordion>
			))}
			{isAdding ? (
				<div className="border border-accent/30 bg-bg-1/40 p-3">
					<FactForm fact={EMPTY_FACT} relayCandidates={relayCandidates} saveLabel="ADD FACT" onSave={create} onCancel={() => setIsAdding(false)} />
				</div>
			) : (
				<button type="button" onClick={() => setIsAdding(true)} className="text-info text-[0.65rem] tracking-[0.1em] cursor-pointer hover:text-fg-1 transition-colors">
					+ ADD FACT
				</button>
			)}
			{deleting && (
				<ConfirmationModal
					title="Delete fact"
					message={deleting.usage > 0
						? `Delete "${deleting.topic}"? The player knows it in ${deleting.usage} session${deleting.usage === 1 ? '' : 's'}; it will be forgotten there too.`
						: `Delete "${deleting.topic}"?`}
					options={[{ label: 'DELETE', value: 'confirm' }, { label: 'CANCEL', value: 'cancel', cancel: true }]}
					onSelect={(selected) => { const fact = deleting; setDeleting(null); if (selected === 'confirm') void destroy(fact); }}
				/>
			)}
		</div>
	);
}
