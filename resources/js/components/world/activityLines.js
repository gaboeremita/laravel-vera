const IRREGULAR = { have: 'has', be: 'is', do: 'does', go: 'goes' };

function thirdPerson(verb) {
	const lower = verb.toLowerCase();
	if (IRREGULAR[lower]) return IRREGULAR[lower];
	if (/(s|sh|ch|x|z|o)$/.test(lower)) return `${lower}es`;
	if (/[^aeiou]y$/.test(lower)) return `${lower.slice(0, -1)}ies`;
	return `${lower}s`;
}

function lowerFirst(text) {
	return text.charAt(0).toLowerCase() + text.slice(1);
}

function placeSuffix(activityName, object) {
	if (!object) return '';
	const activityText = activityName.toLowerCase();
	const namesObject = object.name.toLowerCase().split(/\s+/).some((word) => word.length >= 4 && activityText.includes(word));
	return namesObject ? '' : ` at the ${object.name.toLowerCase()}`;
}

/** The user's activity in the third person, for the resident they are talking to. */
export function actionLine({ activity, object = null }) {
	const [verb, ...rest] = activity.name.split(' ');
	return `*${[thirdPerson(verb), ...rest].join(' ')}${placeSuffix(activity.name, object)}*`;
}

export function getUpLine(object) {
	return `*gets up from the ${object.name.toLowerCase()}*`;
}

/** The user's activity as seen by a resident who was not addressed, in her own voice. */
export function observationLine({ activity, object = null }) {
	return `*I see the user ${lowerFirst(activity.name)}${placeSuffix(activity.name, object)}*`;
}

export function observationGetUpLine(object) {
	return `*I see the user get up from the ${object.name.toLowerCase()}*`;
}

export function joinLines(lines) {
	return lines.join(' ');
}
