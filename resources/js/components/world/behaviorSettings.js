const KEYS = ['radius', 'homeSpot', 'route', 'area', 'decisionSeconds'];

const isObject = (value) => value !== null && typeof value === 'object' && !Array.isArray(value);
const isName = (value) => typeof value === 'string' && value.trim() !== '';

function routeError(route) {
	if (!Array.isArray(route) || route.length < 2) return '"route" must be a list of at least two stops';
	for (const [index, stop] of route.entries()) {
		if (!isObject(stop)) return `Stop ${index + 1} must be an object`;
		if (!isName(stop.target) && !isObject(stop.point)) return `Stop ${index + 1} needs a "target" or a "point"`;
		if (stop.pause !== undefined && typeof stop.pause !== 'number') return `Stop ${index + 1}: "pause" must be a number of seconds`;
	}
	return null;
}

/** The behavior settings JSON typed into the resident editor, or an error saying what is wrong with it. */
export function parseBehaviorSettings(text) {
	if (text.trim() === '') return { behaviorSettings: null, error: null };
	let value;
	try {
		value = JSON.parse(text);
	} catch {
		return { behaviorSettings: null, error: 'Not valid JSON' };
	}
	if (!isObject(value)) return { behaviorSettings: null, error: 'Must be an object' };
	const unknownKey = Object.keys(value).find((key) => !KEYS.includes(key));
	if (unknownKey) return { behaviorSettings: null, error: `Unknown key "${unknownKey}"; allowed: ${KEYS.join(', ')}` };
	if (value.homeSpot !== undefined && !(isObject(value.homeSpot) && isName(value.homeSpot.spotId) && isName(value.homeSpot.activityId))) {
		return { behaviorSettings: null, error: '"homeSpot" needs a "spotId" and an "activityId"' };
	}
	if (value.route !== undefined) {
		const error = routeError(value.route);
		if (error) return { behaviorSettings: null, error };
	}
	if (value.area !== undefined && !(Array.isArray(value.area) && value.area.every(isName))) {
		return { behaviorSettings: null, error: '"area" must be a list of zone ids' };
	}
	const pace = value.decisionSeconds;
	if (pace !== undefined && !(isObject(pace) && typeof pace.min === 'number' && typeof pace.max === 'number' && pace.min <= pace.max)) {
		return { behaviorSettings: null, error: '"decisionSeconds" needs a "min" and a "max", in seconds' };
	}
	return { behaviorSettings: value, error: null };
}

export function behaviorSettingsText(behaviorSettings) {
	return behaviorSettings && Object.keys(behaviorSettings).length > 0 ? JSON.stringify(behaviorSettings) : '';
}
