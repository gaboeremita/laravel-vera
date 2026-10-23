import { useEffect, useState } from 'react';
import { Search, X } from 'lucide-react';
import { api } from '../../../utils/api.js';
import { HudButton, HudChip, HudMeter } from './HudControls.jsx';

const NEAR_METERS = 12;

function initials(name) {
	return name.split(/\s+/).map((part) => part[0]).join('').slice(0, 2).toUpperCase();
}

function Avatar({ resident, large = false }) {
	const size = large ? 'h-14 w-14 text-base' : 'h-9 w-9 text-xs';
	return resident.portraitUrl
		? <img src={resident.portraitUrl} alt="" className={`hud-avatar ${size} shrink-0 border border-accent/50 object-cover`} />
		: <span className={`hud-avatar ${size} flex shrink-0 items-center justify-center border border-accent/50 bg-accent/10 text-accent`}>{initials(resident.name)}</span>;
}

function SendPicker({ regions, onSend, onCancel }) {
	const [regionId, setRegionId] = useState(regions[0]?.id ?? null);
	const region = regions.find((candidate) => candidate.id === Number(regionId));
	const [zoneId, setZoneId] = useState(region?.zones[0]?.id ?? '');
	const selectClass = 'border border-line-2 bg-bg-1 px-2 py-1.5 text-fg-1 text-sm';

	return (
		<div className="hud-enter-rise flex flex-wrap items-center gap-2 border border-line-1 p-3" style={{ borderRadius: 'calc(var(--hud-radius) / 2)' }}>
			<select value={regionId ?? ''} onChange={(event) => { setRegionId(Number(event.target.value)); setZoneId(regions.find((candidate) => candidate.id === Number(event.target.value))?.zones[0]?.id ?? ''); }} className={selectClass} aria-label="Region">
				{regions.map((candidate) => <option key={candidate.id} value={candidate.id}>{candidate.name}</option>)}
			</select>
			<select value={zoneId} onChange={(event) => setZoneId(event.target.value)} className={selectClass} aria-label="Zone">
				{(region?.zones ?? []).map((zone) => <option key={zone.id} value={zone.id}>{zone.name}</option>)}
			</select>
			<HudButton variant="primary" disabled={!zoneId} onClick={() => onSend(Number(regionId), zoneId)}>Send</HudButton>
			<HudButton onClick={onCancel}>Cancel</HudButton>
		</div>
	);
}

/** Every resident of the world: where they are, what they think and feel, and the creator's commands over them. */
export default function CreatorResidentsTab({ overview, creator, context, arrowHandlerRef, openItems }) {
	const [selectedId, setSelectedId] = useState(overview.residents[0]?.id ?? null);
	const [sending, setSending] = useState(false);
	const [query, setQuery] = useState('');
	const regionName = (regionId) => overview.regions.find((region) => region.id === regionId)?.name ?? '';
	const needle = query.trim().toLowerCase();
	const shown = needle === '' ? overview.residents : overview.residents.filter((resident) => [resident.name, regionName(resident.regionId), resident.zoneName ?? ''].some((text) => text.toLowerCase().includes(needle)));
	const index = Math.max(0, shown.findIndex((resident) => resident.id === selectedId));
	const selected = shown[index] ?? overview.residents.find((resident) => resident.id === selectedId) ?? null;
	const worldResident = context.residents.find((resident) => resident.id === selected?.id) ?? null;

	useEffect(() => {
		arrowHandlerRef.current = (step) => {
			const next = shown[Math.min(shown.length - 1, Math.max(0, index + step))];
			if (next) setSelectedId(next.id);
		};
		return () => { arrowHandlerRef.current = null; };
	}, [arrowHandlerRef, shown, index]);

	if (!selected || !worldResident) return <p className="text-fg-3 text-sm">This world has no residents yet.</p>;

	const player = context.getPlayerPosition();
	const distance = (resident) => (player && resident.position && resident.regionId === context.regionId ? Math.hypot(resident.position.x - player.x, resident.position.z - player.z) : null);
	const thought = context.thoughts[selected.id] ?? null;
	const from = { regionId: selected.regionId, zoneName: selected.zoneName };
	const setSentiment = (name, value) => creator.run(`set ${selected.name}'s ${name} to ${value > 0 ? '+' : ''}${value}`, () => api.put(context.route('residents.sentiments.update', { resident: selected.id }), { values: { [name]: value } }));

	return (
		<div className="grid grid-cols-[17rem_1fr] gap-5">
			<div className="flex min-w-0 flex-col gap-1.5">
				<div className="relative mb-1">
					<Search size={14} className="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-fg-3" aria-hidden="true" />
					<input
						value={query}
						onChange={(event) => setQuery(event.target.value)}
						placeholder="Name, region or zone"
						aria-label="Search residents"
						className="w-full border border-line-2 bg-bg-1 py-1.5 pl-8 pr-8 text-fg-1 text-sm outline-none transition-colors focus:border-accent/60"
						style={{ borderRadius: 'calc(var(--hud-radius) / 2)' }}
					/>
					{query && (
						<button type="button" onClick={() => setQuery('')} aria-label="Clear search" className="absolute right-2 top-1/2 -translate-y-1/2 p-0.5 text-fg-3 transition-colors hover:text-accent">
							<X size={14} />
						</button>
					)}
				</div>
				<span className="world-hud-label mb-1">{needle === '' ? `${overview.residents.length} residents · ${overview.regions.length} regions` : `${shown.length} of ${overview.residents.length} residents`}</span>
				{shown.length === 0 && <span className="text-fg-3 text-xs">No resident matches “{query.trim()}”.</span>}
				{shown.map((resident, rowIndex) => {
					const active = resident.id === selected.id;
					const near = distance(resident);
					return (
						<button
							key={resident.id}
							type="button"
							onClick={() => { setSelectedId(resident.id); setSending(false); }}
							className={`hud-enter-rise flex items-center gap-3 border px-3 py-2 text-left transition-[border-color,background-color,transform] duration-150 hover:translate-x-0.5 ${active ? 'world-hud-shimmer border-accent/60 bg-accent/10' : 'border-line-1 hover:border-accent/40'}`}
							style={{ borderRadius: 'calc(var(--hud-radius) / 2)', animationDelay: `${Math.min(rowIndex, 12) * 40}ms` }}
						>
							<Avatar resident={resident} />
							<span className="flex min-w-0 flex-1 flex-col">
								<span className={`truncate text-sm ${active ? 'text-fg-1' : 'text-fg-2'}`}>{resident.name}</span>
								<span className="world-hud-label truncate text-fg-3">{[regionName(resident.regionId), resident.zoneName].filter(Boolean).join(' · ')}</span>
							</span>
							{resident.frozen && <HudChip value="frozen" frost className="text-info">Frozen</HudChip>}
							{!resident.frozen && near !== null && near <= NEAR_METERS && <HudChip value="near" className="text-accent">Near</HudChip>}
						</button>
					);
				})}
			</div>

			<div key={selected.id} className="hud-enter-fade flex min-w-0 flex-col gap-4 border-l border-line-1 pl-5">
				<div className="flex items-center gap-4">
					<Avatar resident={selected} large />
					<div className="flex min-w-0 flex-col">
						<span className="hud-title world-hud-glow truncate text-2xl">{selected.name}</span>
						<span className="world-hud-label">
							{[regionName(selected.regionId), selected.zoneName, distance(selected) !== null ? `${Math.round(distance(selected))} m away` : null].filter(Boolean).join(' · ')}
						</span>
					</div>
				</div>

				<div className="border border-line-1 bg-bg-2/60 px-3 py-2.5" style={{ borderRadius: 'calc(var(--hud-radius) / 2)' }}>
					<span className="world-hud-label text-accent">Thinking</span>
					{thought
						? <p key={thought} className="hud-enter-type mt-1 text-fg-1 text-sm leading-relaxed">{thought}<span className="hud-caret" /></p>
						: <p className="mt-1 text-fg-3 text-sm">No thought right now.</p>}
				</div>

				<div className="flex flex-col gap-2.5">
					<span className="world-hud-label">Feelings toward you</span>
					{overview.sentimentNames.length === 0
						? <span className="text-fg-3 text-xs">This world has no sentiments.</span>
						: overview.sentimentNames.map((name) => <HudMeter key={name} label={name} value={selected.sentiments[name] ?? 0} onChange={(value) => setSentiment(name, value)} />)}
				</div>

				<div className="flex flex-wrap gap-2">
					<HudButton variant="primary" onClick={() => context.summon(worldResident, from)}>Summon</HudButton>
					<HudButton onClick={() => setSending((current) => !current)}>Send to…</HudButton>
					<HudButton onClick={() => context.sendHome(worldResident, from)}>Send home</HudButton>
					<HudButton onClick={() => context.freeze(worldResident, !selected.frozen)}>{selected.frozen ? 'Unfreeze' : 'Freeze'}</HudButton>
					<HudButton onClick={() => openItems({ type: 'resident', residentId: selected.id })}>Inventory</HudButton>
				</div>
				{sending && <SendPicker regions={overview.regions} onCancel={() => setSending(false)} onSend={async (regionId, zoneId) => { const done = await context.send(worldResident, regionId, zoneId, from); if (done) setSending(false); return done; }} />}

				<div className="flex flex-col gap-1">
					<span className="world-hud-label">Recent</span>
					{selected.recentActivities.length === 0 && <span className="text-fg-3 text-xs">Nothing yet this session.</span>}
					{selected.recentActivities.map((activity, activityIndex) => (
						<span key={activityIndex} className="text-fg-2 text-xs">
							<span className="text-fg-3">{activity.verb}</span> {activity.target}{activity.outcome ? <span className="text-fg-3"> — {activity.outcome}</span> : null}
						</span>
					))}
				</div>
			</div>
		</div>
	);
}
