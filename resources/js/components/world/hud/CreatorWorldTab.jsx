import { useState } from 'react';
import { api } from '../../../utils/api.js';
import { HudButton, HudChip } from './HudControls.jsx';

const rowClass = 'hud-enter-rise flex items-center gap-3 border border-line-1 px-3 py-2 transition-colors duration-150 hover:border-accent/40';
const rowStyle = { borderRadius: 'calc(var(--hud-radius) / 2)' };

/** Blocking objects to open or close, secrets to reveal or forget, and every activity to use from anywhere. */
export default function CreatorWorldTab({ overview, creator, context }) {
	const [regionId, setRegionId] = useState(context.regionId ?? overview.regions[0]?.id ?? null);
	const region = overview.regions.find((candidate) => candidate.id === regionId) ?? overview.regions[0] ?? null;
	const blockers = overview.regions.flatMap((candidate) => candidate.objects.filter((object) => object.blocks).map((object) => ({ ...object, region: candidate })));

	const setPassable = async (object, passable) => {
		const body = await creator.run(`${passable ? 'opened' : 'closed'} ${object.name}`, () => api.put(context.route('objects.passable.update'), { regionId: object.region.id, objectId: object.id, passable }));
		if (body?.objectState) context.applyObjectState(body.objectState);
		return body;
	};
	const setKnown = async (fact, known) => {
		const body = await creator.run(`${known ? 'revealed' : 'forgot'} ${fact.label}`, () => api.put(context.route('facts.update', { fact: fact.id }), { known }));
		if (body?.learnedFact) context.applyLearnedFacts([body.learnedFact]);
		if (body && !known) context.forgetFact(fact.id);
		return body;
	};
	const runActivity = async (object, activity, response) => {
		const body = await creator.run(`used ${activity.name} at ${object.name}`, () => api.post(context.route('activity-uses.store'), { regionId: region.id, objectId: object.id, activityId: activity.id, response }));
		if (!body) return false;
		context.applyInventory(body.inventory);
		if (body.learnedFacts?.length) context.applyLearnedFacts(body.learnedFacts);
		if (body.objectState) context.applyObjectState(body.objectState);
		return body;
	};

	return (
		<div className="flex flex-col gap-6">
			<div className="grid grid-cols-2 gap-5">
				<div className="flex flex-col gap-1.5">
					<span className="world-hud-label">Blockers</span>
					{blockers.length === 0 && <span className="text-fg-3 text-xs">No object blocks the way anywhere.</span>}
					{blockers.map((object, index) => (
						<button key={`${object.region.id}:${object.id}`} type="button" onClick={() => void setPassable(object, !object.passable)} className={`${rowClass} text-left`} style={{ ...rowStyle, animationDelay: `${Math.min(index, 12) * 40}ms` }}>
							<span className="min-w-0 flex-1 truncate text-sm">{object.name} <span className="world-hud-label text-fg-3">· {object.region.name}</span></span>
							<HudChip value={object.passable} className={object.passable ? 'text-accent' : 'text-danger'}>{object.passable ? 'Open' : 'Locked'}</HudChip>
						</button>
					))}
				</div>
				<div className="flex flex-col gap-1.5">
					<span className="world-hud-label">Secrets</span>
					{overview.facts.length === 0 && <span className="text-fg-3 text-xs">This world has no secrets.</span>}
					{overview.facts.map((fact, index) => (
						<button key={fact.id} type="button" onClick={() => void setKnown(fact, !fact.known)} className={`${rowClass} text-left`} style={{ ...rowStyle, animationDelay: `${Math.min(index, 12) * 40}ms` }}>
							<span className="min-w-0 flex-1 truncate text-sm">{fact.label}</span>
							<HudChip value={fact.known} className={fact.known ? 'text-accent' : 'text-fg-3'}>{fact.known ? 'Revealed' : 'Hidden'}</HudChip>
						</button>
					))}
				</div>
			</div>

			{region && (
				<div className="flex flex-col gap-2 border-t border-line-1 pt-4">
					<div className="flex flex-wrap items-center gap-1.5">
						<span className="world-hud-label mr-1">Objects in</span>
						{overview.regions.map((candidate) => (
							<button key={candidate.id} type="button" onClick={() => setRegionId(candidate.id)} className={`hud-chip cursor-pointer transition-colors ${candidate.id === region.id ? 'text-accent' : 'text-fg-3 hover:text-fg-1'}`}>{candidate.name}</button>
						))}
					</div>
					{region.objects.filter((object) => object.activities.length > 0).length === 0 && <span className="text-fg-3 text-xs">No object in this region has an activity.</span>}
					{region.objects.filter((object) => object.activities.length > 0).map((object) => (
						<div key={object.id} className="flex flex-col gap-1 border border-line-1 px-3 py-2" style={rowStyle}>
							<span className="text-fg-1 text-sm">{object.name}</span>
							{object.activities.map((activity) => (
								<div key={activity.id} className="flex flex-wrap items-center gap-2 pl-2">
									<span className="world-hud-label w-28 truncate">{activity.name}</span>
									{activity.responses.length === 0
										? <HudButton onClick={() => runActivity(object, activity, 0)}>Use</HudButton>
										: activity.responses.map((response) => (
											<HudButton key={response.index} onClick={() => runActivity(object, activity, response.index)} title={response.summary}>
												Use · {response.summary}
											</HudButton>
										))}
								</div>
							))}
						</div>
					))}
				</div>
			)}
		</div>
	);
}
