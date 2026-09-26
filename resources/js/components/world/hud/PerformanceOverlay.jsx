import { useEffect, useState } from 'react';

const REFRESH_MS = 500;

export default function PerformanceOverlay({ statsRef }) {
	const [stats, setStats] = useState(null);

	useEffect(() => {
		const timer = setInterval(() => setStats(statsRef.current), REFRESH_MS);
		return () => clearInterval(timer);
	}, [statsRef]);

	if (!stats) return null;
	const rows = [
		['FPS', stats.fps],
		['DRAW CALLS', stats.drawCalls],
		['TRIANGLES', stats.triangles.toLocaleString()],
		['GEOMETRIES', stats.geometries],
		['TEXTURES', stats.textures],
		['RESIDENTS LOADED', stats.loaded],
		['ANIMATED', stats.animated],
		['HAIR PHYSICS', stats.springBones],
	];

	return (
		<div className="world-hud-panel hud-enter-fade pointer-events-none flex flex-col gap-0.5 px-3 py-2">
			{rows.map(([label, value]) => (
				<div key={label} className="flex justify-between gap-6">
					<span className="world-hud-label">{label}</span>
					<span className="text-fg-1 text-[0.7rem] tabular-nums">{value}</span>
				</div>
			))}
		</div>
	);
}
