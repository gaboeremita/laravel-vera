import { useState } from 'react';
import { projectToMap } from '../worldMapProjection.js';
import { outlineCentroid } from '../zoneLanding.js';
import { HudButton } from './HudControls.jsx';

const MAP_WIDTH = 400;
const MAP_HEIGHT = 300;
const MAP_PADDING = 2;

function mapBounds(zones) {
	const points = zones.flatMap((zone) => zone.outline);
	if (points.length === 0) return null;
	const xs = points.map(([x]) => x);
	const zs = points.map(([, z]) => z);
	return { minX: Math.min(...xs) - MAP_PADDING, maxX: Math.max(...xs) + MAP_PADDING, minZ: Math.min(...zs) - MAP_PADDING, maxZ: Math.max(...zs) + MAP_PADDING };
}

/** The zones in nesting order, each with how deep it sits. */
function zoneTree(zones) {
	const byParent = new Map();
	zones.forEach((zone) => byParent.set(zone.parentId ?? null, [...(byParent.get(zone.parentId ?? null) ?? []), zone]));
	const known = new Set(zones.map((zone) => zone.id));
	const ordered = [];
	const walk = (parentId, depth) => (byParent.get(parentId) ?? []).forEach((zone) => {
		ordered.push({ zone, depth });
		walk(zone.id, depth + 1);
	});
	walk(null, 0);
	zones.filter((zone) => zone.parentId && !known.has(zone.parentId)).forEach((zone) => ordered.push({ zone, depth: 0 }));
	return ordered;
}

function ZoneMap({ zones, residents, playerPosition, highlighted, onHighlight, onPick, pulse }) {
	const bounds = mapBounds(zones);
	if (!bounds) return <div className="flex aspect-[4/3] items-center justify-center border border-line-1 text-fg-3 text-xs">This region has no zones to draw.</div>;
	const size = { width: MAP_WIDTH, height: MAP_HEIGHT };
	const project = (x, z) => projectToMap({ x, z }, bounds, size);
	const polygon = (outline) => outline.map(([x, z]) => { const point = project(x, z); return `${point.x},${point.y}`; }).join(' ');

	return (
		<svg viewBox={`0 0 ${MAP_WIDTH} ${MAP_HEIGHT}`} className="aspect-[4/3] w-full border border-line-1 bg-bg-1/60" style={{ borderRadius: 'calc(var(--hud-radius) / 2)' }} onMouseLeave={() => onHighlight(null)}>
			{zones.map((zone) => {
				const lit = highlighted === zone.id;
				return (
					<polygon
						key={zone.id}
						points={polygon(zone.outline)}
						onMouseEnter={() => onHighlight(zone.id)}
						onClick={() => onPick(zone)}
						className="cursor-pointer transition-[fill,stroke] duration-150"
						style={{ fill: `color-mix(in oklab, var(--accent) ${lit ? 26 : 8}%, transparent)`, stroke: lit ? 'var(--accent)' : 'color-mix(in oklab, var(--accent) 50%, transparent)', strokeWidth: 1, filter: lit ? 'drop-shadow(0 0 calc(6px * var(--hud-glow)) var(--accent))' : 'none' }}
					/>
				);
			})}
			{zones.map((zone) => {
				const middle = outlineCentroid(zone.outline);
				const point = project(middle.x, middle.z);
				return <text key={`${zone.id}-label`} x={point.x} y={point.y} textAnchor="middle" className="pointer-events-none fill-fg-2 text-[9px] uppercase">{zone.name}</text>;
			})}
			{residents.map((resident) => {
				const point = project(resident.position.x, resident.position.z);
				return <circle key={resident.id} cx={point.x} cy={point.y} r={3} className="fill-fg-2"><title>{resident.name}</title></circle>;
			})}
			{playerPosition && (() => {
				const point = project(playerPosition.x, playerPosition.z);
				return <circle cx={point.x} cy={point.y} r={4} className="hud-dot-pulse fill-accent" />;
			})()}
			{pulse && (() => {
				const point = project(pulse.x, pulse.z);
				return <circle key={pulse.key} cx={point.x} cy={point.y} r={40} className="hud-pulse-ring fill-none stroke-accent" style={{ transformBox: 'fill-box', transformOrigin: 'center', strokeWidth: 2 }} />;
			})()}
		</svg>
	);
}

/** Every region with its zones as a list and a map, its passages and the world spawn; picking one goes there. */
export default function CreatorGoToTab({ overview, context }) {
	const [regionId, setRegionId] = useState(context.regionId ?? overview.regions[0]?.id ?? null);
	const [highlighted, setHighlighted] = useState(null);
	const [pulse, setPulse] = useState(null);
	const region = overview.regions.find((candidate) => candidate.id === regionId) ?? overview.regions[0];
	if (!region) return <p className="text-fg-3 text-sm">This world has no regions yet.</p>;

	const residentsHere = overview.residents.filter((resident) => resident.regionId === region.id && resident.position);
	const countIn = (zone) => overview.residents.filter((resident) => resident.regionId === region.id && resident.zoneName === zone.name).length;
	const highlightedZone = region.zones.find((zone) => zone.id === highlighted) ?? null;
	const goToZone = async (zone) => {
		const middle = outlineCentroid(zone.outline);
		setPulse((current) => ({ key: (current?.key ?? 0) + 1, ...middle }));
		return context.goTo({ regionId: region.id, zoneId: zone.id });
	};

	return (
		<div className="grid grid-cols-[15rem_1fr] gap-5">
			<div className="flex min-w-0 flex-col gap-4">
				<div className="flex flex-wrap gap-1.5">
					{overview.regions.map((candidate) => (
						<button key={candidate.id} type="button" onClick={() => { setRegionId(candidate.id); setHighlighted(null); }} className={`hud-chip cursor-pointer transition-colors ${candidate.id === region.id ? 'text-accent' : 'text-fg-3 hover:text-fg-1'}`}>
							{candidate.name}{candidate.id === context.regionId ? ' ·' : ''}
						</button>
					))}
				</div>
				<div className="flex flex-col gap-0.5">
					{zoneTree(region.zones).map(({ zone, depth }, index) => (
						<button
							key={zone.id}
							type="button"
							onMouseEnter={() => setHighlighted(zone.id)}
							onClick={() => void goToZone(zone)}
							className={`hud-enter-rise flex items-center justify-between border-l px-2 py-1.5 text-left text-sm transition-colors duration-150 ${highlighted === zone.id ? 'border-accent bg-accent/10 text-fg-1' : 'border-line-1 text-fg-2 hover:text-fg-1'}`}
							style={{ marginLeft: `${depth}rem`, animationDelay: `${Math.min(index, 12) * 30}ms` }}
						>
							<span className="truncate">{zone.name}</span>
							{countIn(zone) > 0 && <span className="text-fg-3 text-xs tabular-nums">{countIn(zone)}</span>}
						</button>
					))}
					{region.zones.length === 0 && <span className="text-fg-3 text-xs">No zones in this region.</span>}
				</div>
				<div className="flex flex-col gap-1 border-t border-line-1 pt-3">
					<span className="world-hud-label">Passages</span>
					{region.passages.map((passage) => (
						<button key={passage.id} type="button" onClick={() => void context.goTo({ regionId: region.id, passageId: passage.id })} className="border-l border-line-1 px-2 py-1.5 text-left text-fg-2 text-sm transition-colors hover:border-accent hover:text-fg-1">
							{passage.name}
						</button>
					))}
					{overview.spawn.regionId && <HudButton className="mt-2 self-start" onClick={() => context.goTo({ spawn: true })}>Go to world spawn</HudButton>}
				</div>
			</div>
			<div className="flex min-w-0 flex-col gap-3">
				<ZoneMap zones={region.zones} residents={residentsHere} playerPosition={region.id === context.regionId ? context.getPlayerPosition() : null} highlighted={highlighted} onHighlight={setHighlighted} onPick={(zone) => void goToZone(zone)} pulse={pulse} />
				<div className="flex items-center justify-between gap-3">
					<span className="world-hud-label">Hover a zone · click to go · <span className="text-accent">●</span> you</span>
					<HudButton variant="primary" disabled={!highlightedZone} onClick={() => goToZone(highlightedZone)}>
						{highlightedZone ? `Go to ${highlightedZone.name}` : 'Go to'}
					</HudButton>
				</div>
			</div>
		</div>
	);
}
