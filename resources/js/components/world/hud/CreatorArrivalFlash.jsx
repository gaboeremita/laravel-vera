/** A short accent flash over the whole view after the creator goes somewhere; a new key plays it again. */
export default function CreatorArrivalFlash({ flashKey }) {
	if (flashKey === null) return null;
	return <div key={flashKey} className="hud-flash pointer-events-none absolute inset-0 z-30 bg-accent" />;
}
