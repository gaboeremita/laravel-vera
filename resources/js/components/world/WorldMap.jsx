import { useEffect, useState } from 'react';
import { floorForHeight, labelledZones, minimapWindow, projectToMap, spreadLabels, spreadWideLabels } from './worldMapProjection.js';

const SNAPSHOT_INTERVAL_MS = 100;
const MINIMAP_WIDTH = 220;
const LABEL_SPACING = 14;
const ZONE_LABEL_HEIGHT = 22;
const ZONE_LABEL_CHARACTER_WIDTH = 9;
const ZONE_LABEL_PADDING = 16;
const MINIMAP_ZONE_LABEL_HEIGHT = 16;
const MINIMAP_ZONE_LABEL_CHARACTER_WIDTH = 6;
const MINIMAP_ZONE_LABEL_PADDING = 10;

function zoneCentroid(outline) {
	const sum = outline.reduce((total, [x, z]) => ({ x: total.x + x, z: total.z + z }), { x: 0, z: 0 });
	return { x: sum.x / outline.length, z: sum.z / outline.length };
}

export default function WorldMap({ layout, floorMaps, playerView, residents, residentPositions, activeResidentId, expanded, onClose, header = null }) {
	const [snapshot, setSnapshot] = useState(null);
	const [manualFloorId, setManualFloorId] = useState(null);
	const floors = layout?.floors ?? [];

	useEffect(() => {
		let frame = null;
		let lastTick = 0;
		let lastPlayerFloorId;
		const tick = (time) => {
			if (time - lastTick >= SNAPSHOT_INTERVAL_MS) {
				lastTick = time;
				const view = playerView.current;
				const playerFloorId = view ? floorForHeight(layout, view.y)?.id ?? null : null;
				if (lastPlayerFloorId !== undefined && playerFloorId !== lastPlayerFloorId) setManualFloorId(null);
				lastPlayerFloorId = playerFloorId;
				setSnapshot({
					player: view ? { ...view } : null,
					playerFloorId,
					residents: residents.flatMap((resident) => {
						const position = residentPositions.current.get(resident.id);
						return position ? [{ id: resident.id, name: resident.assistant.name, x: position.x, y: position.y, z: position.z }] : [];
					}),
				});
			}
			frame = requestAnimationFrame(tick);
		};
		frame = requestAnimationFrame(tick);
		return () => cancelAnimationFrame(frame);
	}, [layout, playerView, residents, residentPositions]);

	if (!snapshot || floorMaps.length === 0) {
		return header && !expanded ? <div className="absolute bottom-5 right-5 z-10 flex flex-col items-end gap-1">{header}</div> : null;
	}

	const shownFloorId = manualFloorId ?? snapshot.playerFloorId;
	const map = floorMaps.find((floorMap) => floorMap.floorId === shownFloorId) ?? floorMaps[0];
	const shown = expanded ? map.bounds : minimapWindow(map.bounds, snapshot.player);
	const aspect = (shown.maxZ - shown.minZ) / (shown.maxX - shown.minX);
	const width = expanded ? Math.min(window.innerWidth * 0.8, (window.innerHeight * 0.8) / aspect) : MINIMAP_WIDTH;
	const size = { width, height: width * aspect };
	const project = (point) => projectToMap(point, shown, size);
	const imageStart = project({ x: map.bounds.minX, z: map.bounds.minZ });
	const imageEnd = project({ x: map.bounds.maxX, z: map.bounds.maxZ });
	const imageBox = { left: imageStart.x, top: imageStart.y, width: imageEnd.x - imageStart.x, height: imageEnd.y - imageStart.y };
	const inShown = (point) => point.x >= shown.minX && point.x <= shown.maxX && point.z >= shown.minZ && point.z <= shown.maxZ;

	const residentMarkers = spreadLabels(snapshot.residents.map((resident) => {
		const floor = floors.length ? floorForHeight(layout, resident.y) : null;
		const onShownFloor = floors.length === 0 || (floor?.id ?? null) === map.floorId;
		return { ...resident, ...project(resident), floorName: floor?.name ?? null, onShownFloor };
	}), LABEL_SPACING);
	const player = snapshot.player && (floors.length === 0 || snapshot.playerFloorId === map.floorId) ? { ...project(snapshot.player), yaw: snapshot.player.yaw } : null;
	const characterWidth = expanded ? ZONE_LABEL_CHARACTER_WIDTH : MINIMAP_ZONE_LABEL_CHARACTER_WIDTH;
	const labelPadding = expanded ? ZONE_LABEL_PADDING : MINIMAP_ZONE_LABEL_PADDING;
	const zoneLabels = spreadWideLabels(labelledZones(layout, map.floorId)
		.map((zone) => ({ zone, centroid: zoneCentroid(zone.outline) }))
		.filter(({ centroid }) => expanded || inShown(centroid))
		.map(({ zone, centroid }) => ({ id: zone.id, name: zone.name, width: zone.name.length * characterWidth + labelPadding, ...project(centroid) })), expanded ? ZONE_LABEL_HEIGHT : MINIMAP_ZONE_LABEL_HEIGHT);

	const mapView = (
		<div className="relative overflow-hidden border border-line-1 bg-black" style={size}>
			<img src={map.url} alt="" className="absolute max-w-none opacity-80" style={imageBox} draggable={false} />
			{zoneLabels.map((zone) => (
				<span key={zone.id} className={`world-hud-panel world-hud-label absolute -translate-x-1/2 -translate-y-1/2 whitespace-nowrap text-fg-1 ${expanded ? 'px-2 py-0.5 text-[0.7rem]' : 'px-1 text-[0.5rem]'}`} style={{ left: zone.labelX, top: zone.labelY }}>{zone.name.toUpperCase()}</span>
			))}
			{residentMarkers.map((marker) => {
				const active = marker.id === activeResidentId;
				return (
					<div key={marker.id} className={marker.onShownFloor ? '' : 'opacity-40'}>
						<span className={`absolute h-2.5 w-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full ${active ? 'bg-accent ring-2 ring-accent/50' : 'bg-bg-0 ring-2 ring-fg-1'}`} style={{ left: marker.x, top: marker.y }} />
						<span className={`world-hud-panel world-hud-label absolute -translate-x-1/2 translate-y-1.5 whitespace-nowrap px-1.5 text-[0.6rem] ${active ? 'border-accent text-accent' : 'text-fg-1'}`} style={{ left: marker.labelX, top: marker.labelY }}>
							{marker.onShownFloor || !marker.floorName ? marker.name : `${marker.name} · ${marker.floorName}`}
						</span>
					</div>
				);
			})}
			{player && (
				<span className="absolute h-0 w-0 -translate-x-1/2 -translate-y-1/2 border-x-[6px] border-b-[12px] border-x-transparent border-b-accent" style={{ left: player.x, top: player.y, transform: `translate(-50%, -50%) rotate(${-player.yaw}rad)` }} />
			)}
		</div>
	);

	const floorButtons = floors.length > 1 && (
		<div className="flex gap-1">
			{floors.map((floor) => (
				<button key={floor.id} type="button" onClick={() => setManualFloorId(floor.id)} className={`border px-2 py-1 text-[0.6rem] hud-text tracking-[calc(var(--hud-label-tracking)*0.625)] cursor-pointer ${floor.id === map.floorId ? 'border-accent text-accent' : 'border-line-1 text-fg-3 hover:text-fg-1'}`}>
					{floor.name.toUpperCase()}
				</button>
			))}
		</div>
	);

	if (!expanded) {
		return (
			<div className="absolute bottom-5 right-5 z-10 flex flex-col items-end gap-1">
				{header}
				{floorButtons}
				{mapView}
				<span className="text-fg-3 text-[0.55rem] hud-text tracking-[calc(var(--hud-label-tracking)*0.625)]">M — FULL MAP</span>
			</div>
		);
	}

	return (
		<div className="absolute inset-0 z-30 flex flex-col items-center justify-center gap-3 bg-black/70" onClick={onClose}>
			<div className="flex flex-col items-center gap-3" onClick={(event) => event.stopPropagation()}>
				{floorButtons}
				{mapView}
				<span className="text-fg-3 text-[0.6rem] hud-text tracking-[calc(var(--hud-label-tracking)*0.625)]">M OR CLICK OUTSIDE TO CLOSE</span>
			</div>
		</div>
	);
}
