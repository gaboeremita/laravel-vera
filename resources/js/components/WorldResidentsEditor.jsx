import { useEffect, useMemo, useState } from 'react';
import { route } from 'ziggy-js';
import { Square, SquareCheck } from 'lucide-react';
import { api } from '../utils/api.js';
import Accordion from './common/Accordion.jsx';
import { parseZoneAccess } from './world/zoneAccess.js';
import { behaviorSettingsText, parseBehaviorSettings } from './world/behaviorSettings.js';

const DEFAULT_PLACEMENT = { position: { x: 0, y: 0, z: 0 }, rotation: { x: 0, y: 0, z: 0 }, behavior: 'stationary', behaviorSettings: null, openingMessage: '', customPrompt: '', zoneAccess: null };
const ZONE_ACCESS_EXAMPLE = '{ "tags": ["deprecated"], "zones": ["mona-house"] }';
const BEHAVIOR_SETTINGS_EXAMPLE = '{ "homeSpot": { "spotId": "toll-booth-stool", "activityId": "man-the-toll-booth" } }';
const FIELD_LABEL = 'text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1';
const FIELD_INPUT = 'w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors';
const GROUP_INDENT = 'ml-4 border-l border-line-1 pl-3 space-y-2';

function blurOnWheel(event) { event.target.blur(); }

function isEligible(candidate) {
	return candidate.portrait_type === 'avatar3d' && !!candidate.vrm_url;
}

function radiansToDegrees(radians) {
	return Math.round((radians * 180) / Math.PI * 100) / 100;
}

function zoneAccessText(zoneAccess) {
	const tags = zoneAccess?.tags ?? [];
	const zones = zoneAccess?.zones ?? [];
	return tags.length === 0 && zones.length === 0 ? '' : JSON.stringify({ tags, zones });
}

function toDraft(placement) {
	return {
		position: placement.position,
		facing: radiansToDegrees(placement.rotation?.y ?? 0),
		behavior: placement.behavior,
		behaviorSettings: behaviorSettingsText(placement.behaviorSettings),
		openingMessage: placement.openingMessage ?? '',
		customPrompt: placement.customPrompt ?? '',
		zoneAccess: zoneAccessText(placement.zoneAccess),
	};
}

function toPlacement({ facing, zoneAccess, behaviorSettings, ...draft }) {
	return { ...draft, rotation: { x: 0, y: (facing * Math.PI) / 180, z: 0 }, zoneAccess: parseZoneAccess(zoneAccess).zoneAccess, behaviorSettings: parseBehaviorSettings(behaviorSettings).behaviorSettings };
}

function isDirty(draft, resident) {
	return draft.behavior !== resident.behavior
		|| draft.behaviorSettings !== behaviorSettingsText(resident.behaviorSettings)
		|| draft.facing !== radiansToDegrees(resident.rotation?.y ?? 0)
		|| draft.position.x !== resident.position.x
		|| draft.position.y !== resident.position.y
		|| draft.position.z !== resident.position.z
		|| draft.openingMessage !== (resident.openingMessage ?? '')
		|| draft.customPrompt !== (resident.customPrompt ?? '')
		|| draft.zoneAccess !== zoneAccessText(resident.zoneAccess);
}

function ResidentRow({ candidate, resident, privateZones, onAdd, onRemove, onUpdate }) {
	const [collapsed, setCollapsed] = useState(true);
	const [draft, setDraft] = useState(toDraft(resident ?? DEFAULT_PLACEMENT));
	const [isSaving, setIsSaving] = useState(false);

	if (!resident) {
		return (
			<div
				role="button"
				tabIndex={0}
				onClick={() => onAdd(candidate)}
				onKeyDown={(event) => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); onAdd(candidate); } }}
				className="group w-full flex items-center justify-between border border-line-1 p-3 text-left transition-colors hover:border-accent/50 hover:bg-bg-1 cursor-pointer"
			>
				<p className="text-fg-1 text-sm">{candidate.name}</p>
				<Square size={18} className="text-fg-3 transition-colors group-hover:text-success" />
			</div>
		);
	}

	const dirty = isDirty(draft, resident);
	const zoneAccessError = parseZoneAccess(draft.zoneAccess).error;
	const behaviorSettingsError = parseBehaviorSettings(draft.behaviorSettings).error;
	const invalid = zoneAccessError !== null || behaviorSettingsError !== null;
	const save = async () => {
		setIsSaving(true);
		await onUpdate(candidate, toPlacement(draft));
		setIsSaving(false);
	};

	return (
		<Accordion
			title={candidate.name}
			collapsed={collapsed}
			onToggle={() => setCollapsed((current) => !current)}
			actions={<button type="button" onClick={() => onRemove(resident)} aria-label="Remove resident" className="text-success cursor-pointer hover:text-danger transition-colors"><SquareCheck size={18} /></button>}
			className="bg-success/5"
		>
			<div>
				<p className={FIELD_LABEL}>Spawn Position <span className="normal-case text-fg-3">(from the room origin)</span></p>
				<div className="grid grid-cols-3 gap-2">
					{['x', 'y', 'z'].map((axis) => (
						<div key={axis}>
							<label className={FIELD_LABEL}>{axis.toUpperCase()}</label>
							<input
								type="number"
								value={draft.position[axis]}
								onWheel={blurOnWheel}
								onChange={(event) => setDraft((current) => ({ ...current, position: { ...current.position, [axis]: Number(event.target.value) } }))}
								className={FIELD_INPUT}
							/>
						</div>
					))}
				</div>
			</div>
			<div className="grid grid-cols-3 gap-2">
				<div>
					<label className={FIELD_LABEL}>Facing <span className="normal-case text-fg-3">(degrees)</span></label>
					<input
						type="number"
						value={draft.facing}
						onWheel={blurOnWheel}
						onChange={(event) => setDraft((current) => ({ ...current, facing: Number(event.target.value) }))}
						className={FIELD_INPUT}
					/>
				</div>
			</div>
			<div>
				<label className={FIELD_LABEL}>Behavior</label>
				<select
					value={draft.behavior}
					onChange={(event) => setDraft((current) => ({ ...current, behavior: event.target.value }))}
					className={FIELD_INPUT}
				>
					<option value="stationary">Stationary</option>
					<option value="roam">Roam</option>
					<option value="autonomous">Autonomous</option>
					<option value="route">Route</option>
				</select>
			</div>
			<div>
				<label className={FIELD_LABEL}>Behavior Settings <span className="normal-case text-fg-3">(JSON: homeSpot, route stops, the area she keeps to, decisionSeconds)</span></label>
				<textarea
					value={draft.behaviorSettings}
					onChange={(event) => setDraft((current) => ({ ...current, behaviorSettings: event.target.value }))}
					placeholder={BEHAVIOR_SETTINGS_EXAMPLE}
					rows={3}
					spellCheck={false}
					className={`${FIELD_INPUT} resize-none font-mono`}
				/>
				{behaviorSettingsError && <p className="text-danger text-xs mt-1">{behaviorSettingsError}</p>}
			</div>
			<div>
				<label className={FIELD_LABEL}>Opening Message <span className="normal-case text-fg-3">(overrides the default greeting, only in this world)</span></label>
				<textarea
					value={draft.openingMessage}
					onChange={(event) => setDraft((current) => ({ ...current, openingMessage: event.target.value }))}
					rows={2}
					className={`${FIELD_INPUT} resize-none`}
				/>
			</div>
			<div>
				<label className={FIELD_LABEL}>Custom Prompt <span className="normal-case text-fg-3">(added on top of this world's own context, only for this resident)</span></label>
				<textarea
					value={draft.customPrompt}
					onChange={(event) => setDraft((current) => ({ ...current, customPrompt: event.target.value }))}
					rows={3}
					className={`${FIELD_INPUT} resize-none`}
				/>
			</div>
			<div>
				<label className={FIELD_LABEL}>Zone Access <span className="normal-case text-fg-3">(JSON: the groups she belongs to and the private places she may enter on her own)</span></label>
				<textarea
					value={draft.zoneAccess}
					onChange={(event) => setDraft((current) => ({ ...current, zoneAccess: event.target.value }))}
					placeholder={ZONE_ACCESS_EXAMPLE}
					rows={2}
					spellCheck={false}
					className={`${FIELD_INPUT} resize-none font-mono`}
				/>
				{zoneAccessError && <p className="text-danger text-xs mt-1">{zoneAccessError}</p>}
				{privateZones.length > 0 && (
					<p className="text-fg-3 text-xs mt-1">
						Private places: {privateZones.map((zone) => `${zone.id}${zone.accessTags?.length ? ` (${zone.accessTags.join(', ')})` : ''}${zone.secret ? ' [secret]' : ''}`).join(', ')}
					</p>
				)}
			</div>
			<div className="flex justify-end">
				<button
					type="button"
					onClick={save}
					disabled={!dirty || isSaving || invalid}
					className={`text-[0.7rem] tracking-[0.1em] px-4 py-1.5 transition-colors ${
						!dirty || isSaving || invalid ? 'bg-bg-3 text-fg-3 cursor-default' : 'button-success cursor-pointer'
					}`}
				>
					{isSaving ? 'SAVING...' : 'SAVE'}
				</button>
			</div>
		</Accordion>
	);
}

function KindList({ label, candidates, residentsByAssistantId, privateZones, onAdd, onRemove, onUpdate }) {
	const rows = candidates.filter((candidate) => isEligible(candidate) || residentsByAssistantId.has(candidate.id));
	if (rows.length === 0) return null;

	return (
		<div className="space-y-2">
			<p className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase">{label}</p>
			<div className={GROUP_INDENT}>
				{rows.map((candidate) => (
					<ResidentRow
						key={candidate.id}
						candidate={candidate}
						resident={residentsByAssistantId.get(candidate.id) ?? null}
						privateZones={privateZones}
						onAdd={onAdd}
						onRemove={onRemove}
						onUpdate={onUpdate}
					/>
				))}
			</div>
		</div>
	);
}

export default function WorldResidentsEditor({ world, onWorldChange, addToast }) {
	const [assistantCandidates, setAssistantCandidates] = useState([]);
	const [npcCandidates, setNpcCandidates] = useState([]);
	const [collapsed, setCollapsed] = useState(false);
	const [isLoading, setIsLoading] = useState(true);
	const residentsByAssistantId = useMemo(() => new Map(world.residents.map((resident) => [resident.assistant.id, resident])), [world.residents]);
	const privateZones = useMemo(() => (world.layout?.zones ?? []).filter((zone) => zone.private), [world.layout]);

	useEffect(() => {
		const load = async () => {
			try {
				const [assistantsResponse, npcsResponse] = await Promise.all([api.get(route('assistants.index')), api.get(route('npcs.index'))]);
				if (!assistantsResponse.ok || !npcsResponse.ok) throw new Error();
				const [assistants, npcs] = await Promise.all([assistantsResponse.json(), npcsResponse.json()]);
				setAssistantCandidates(assistants);
				setNpcCandidates(npcs);
			} catch { addToast('Failed to load eligible residents', 'error'); } finally { setIsLoading(false); }
		};
		void load();
	}, [addToast]);

	const updateResident = async (assistant, placement) => {
		if (!world.id) {
			const resident = { id: `staged-${assistant.id}`, assistant, position: placement.position, rotation: placement.rotation, behavior: placement.behavior, behaviorSettings: placement.behaviorSettings, openingMessage: placement.openingMessage, customPrompt: placement.customPrompt, zoneAccess: placement.zoneAccess };
			onWorldChange((current) => ({ ...current, residents: [...current.residents.filter((item) => item.assistant.id !== assistant.id), resident] }));
			return;
		}

		try {
			const response = await api.put(route('worlds.residents.upsert', { world: world.id, assistant: assistant.id }), placement);
			if (!response.ok) throw new Error((await response.json().catch(() => ({}))).message || 'Unable to save resident');
			const resident = await response.json();
			onWorldChange((current) => ({ ...current, residents: [...current.residents.filter((item) => item.assistant.id !== assistant.id), resident] }));
		} catch (error) { addToast(error.message || 'Unable to save resident', 'error'); }
	};

	const removeResident = async (resident) => {
		if (!world.id) {
			onWorldChange((current) => ({ ...current, residents: current.residents.filter((item) => item.id !== resident.id) }));
			return;
		}

		try {
			const response = await api.delete(route('worlds.residents.destroy', { world: world.id, assistant: resident.assistant.id }));
			if (!response.ok) throw new Error();
			onWorldChange((current) => ({ ...current, residents: current.residents.filter((item) => item.id !== resident.id) }));
		} catch { addToast('Unable to remove resident', 'error'); }
	};

	return (
		<Accordion
			label="RESIDENTS"
			collapsed={collapsed}
			onToggle={() => setCollapsed((current) => !current)}
			actions={<span className="text-fg-3 text-xs">{world.residents.length} RESIDENT{world.residents.length === 1 ? '' : 'S'}</span>}
		>
			<div className="space-y-4">
				{isLoading ? (
					<p className="text-fg-3 text-xs">Loading eligible characters...</p>
				) : (
					<>
						<KindList label="Assistants" candidates={assistantCandidates} residentsByAssistantId={residentsByAssistantId} privateZones={privateZones} onAdd={(candidate) => updateResident(candidate, DEFAULT_PLACEMENT)} onRemove={removeResident} onUpdate={updateResident} />
						<KindList label="NPCs" candidates={npcCandidates} residentsByAssistantId={residentsByAssistantId} privateZones={privateZones} onAdd={(candidate) => updateResident(candidate, DEFAULT_PLACEMENT)} onRemove={removeResident} onUpdate={updateResident} />
					</>
				)}
			</div>
		</Accordion>
	);
}
