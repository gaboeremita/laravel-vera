import { useEffect, useState } from 'react';
import { floorForHeight, projectToMap, spreadLabels, zonesOnFloor } from './worldMapProjection.js';

const SNAPSHOT_INTERVAL_MS = 100;
const MINIMAP_WIDTH = 220;
const LABEL_SPACING = 14;

function zoneCentroid(outline) {
	const sum = outline.reduce((total, [x, z]) => ({ x: total.x + x, z: total.z + z }), { x: 0, z: 0 });
	return { x: sum.x / outline.length, z: sum.z / outline.length };
}

export default function WorldMap({ layout, floorMaps, playerView, residents, residentPositions, activeResidentId, expanded, onClose }) {
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

	if (!snapshot || floorMaps.length === 0) return null;

	const shownFloorId = manualFloorId ?? snapshot.playerFloorId;
	const map = floorMaps.find((floorMap) => floorMap.floorId === shownFloorId) ?? floorMaps[0];
	const aspect = (map.bounds.maxZ - map.bounds.minZ) / (map.bounds.maxX - map.bounds.minX);
	const width = expanded ? Math.min(window.innerWidth * 0.8, (window.innerHeight * 0.8) / aspect) : MINIMAP_WIDTH;
	const size = { width, height: width * aspect };
	const project = (point) => projectToMap(point, map.bounds, size);

	const residentMarkers = spreadLabels(snapshot.residents.map((resident) => {
		const floor = floors.length ? floorForHeight(layout, resident.y) : null;
		const onShownFloor = floors.length === 0 || (floor?.id ?? null) === map.floorId;
		return { ...resident, ...project(resident), floorName: floor?.name ?? null, onShownFloor };
	}), LABEL_SPACING);
	const player = snapshot.player && (floors.length === 0 || snapshot.playerFloorId === map.floorId) ? { ...project(snapshot.player), yaw: snapshot.player.yaw } : null;
	const zoneLabels = expanded ? zonesOnFloor(layout, map.floorId).filter((zone) => !zone.parentId).map((zone) => ({ id: zone.id, name: zone.name, ...project(zoneCentroid(zone.outline)) })) : [];

	const mapView = (
		<div className="relative overflow-hidden border border-line-1 bg-black" style={size}>
			<img src={map.url} alt="" className="absolute inset-0 h-full w-full opacity-80" draggable={false} />
			{zoneLabels.map((zone) => (
				<span key={zone.id} className="absolute -translate-x-1/2 -translate-y-1/2 whitespace-nowrap text-fg-2 text-[0.6rem] tracking-[0.1em]" style={{ left: zone.x, top: zone.y }}>{zone.name.toUpperCase()}</span>
			))}
			{residentMarkers.map((marker) => {
				const active = marker.id === activeResidentId;
				return (
					<div key={marker.id} className={marker.onShownFloor ? '' : 'opacity-40'}>
						<span className={`absolute h-2.5 w-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full ${active ? 'bg-accent ring-2 ring-accent/50' : 'bg-white'}`} style={{ left: marker.x, top: marker.y }} />
						<span className={`absolute -translate-x-1/2 translate-y-1.5 whitespace-nowrap px-1 text-[0.6rem] tracking-[0.06em] ${active ? 'bg-accent text-accent-fg' : 'bg-black/60 text-white'}`} style={{ left: marker.labelX, top: marker.labelY }}>
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
				<button key={floor.id} type="button" onClick={() => setManualFloorId(floor.id)} className={`border px-2 py-1 text-[0.6rem] tracking-[0.1em] cursor-pointer ${floor.id === map.floorId ? 'border-accent text-accent' : 'border-line-1 text-fg-3 hover:text-fg-1'}`}>
					{floor.name.toUpperCase()}
				</button>
			))}
		</div>
	);

	if (!expanded) {
		return (
			<div className="absolute bottom-5 right-5 z-10 flex flex-col items-end gap-1">
				{floorButtons}
				{mapView}
				<span className="text-fg-3 text-[0.55rem] tracking-[0.1em]">M — FULL MAP</span>
			</div>
		);
	}

	return (
		<div className="absolute inset-0 z-30 flex flex-col items-center justify-center gap-3 bg-black/70" onClick={onClose}>
			<div className="flex flex-col items-center gap-3" onClick={(event) => event.stopPropagation()}>
				{floorButtons}
				{mapView}
				<span className="text-fg-3 text-[0.6rem] tracking-[0.1em]">M OR CLICK OUTSIDE TO CLOSE</span>
			</div>
		</div>
	);
}
