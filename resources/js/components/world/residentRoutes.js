const DEFAULT_PAUSE_SECONDS = 5;

/** What she does at a stop of her route: walk to a point, take a spot for its activity, or walk to a place or thing. */
export function routeAction(stop) {
	if (stop.point) return { verb: 'go_to_point', point: stop.point };
	if (stop.activity) return { verb: 'use', target: stop.target, activity: stop.activity };
	return { verb: 'go_to', target: stop.target };
}

/** How long she stays at a stop before heading to the next one, in milliseconds. */
export function routePause(stop) {
	return (stop.pause ?? DEFAULT_PAUSE_SECONDS) * 1000;
}

/** The stop after this one; the route loops back to its first stop. */
export function nextStopIndex(index, route) {
	return route.length === 0 ? 0 : (index + 1) % route.length;
}
