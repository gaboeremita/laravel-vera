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

const LYING_POSTURES = ['lying', 'reclining'];

function placeSuffix(activity, object) {
	if (!object) return '';
	const activityText = activity.name.toLowerCase();
	const namesObject = object.name.toLowerCase().split(/\s+/).some((word) => word.length >= 4 && activityText.includes(word));
	if (namesObject) return '';
	return ` ${LYING_POSTURES.includes(activity.posture) ? 'on' : 'at'} the ${object.name.toLowerCase()}`;
}

function onTopSuffix(onTopOf) {
	return onTopOf ? ` on top of ${onTopOf}` : '';
}

/**
 * The user's activity in the third person, for the resident they are talking
 * to. `onTopOf` names whoever the user lies on in a shared spot, or "you".
 */
export function actionLine({ activity, object = null, onTopOf = null }) {
	const [verb, ...rest] = activity.name.split(' ');
	return `*${[thirdPerson(verb), ...rest].join(' ')}${onTopSuffix(onTopOf)}${placeSuffix(activity, object)}*`;
}

export function getUpLine(object, offOf = null) {
	return offOf ? `*gets up off ${offOf}*` : `*gets up from the ${object.name.toLowerCase()}*`;
}

/** The user's activity as seen by a resident who was not addressed, in her own voice. */
export function observationLine({ activity, object = null, onTopOf = null }) {
	return `*I see the user ${lowerFirst(activity.name)}${onTopSuffix(onTopOf)}${placeSuffix(activity, object)}*`;
}

export function observationGetUpLine(object, offOf = null) {
	return offOf ? `*I see the user get up off ${offOf}*` : `*I see the user get up from the ${object.name.toLowerCase()}*`;
}

export function joinLines(lines) {
	return lines.join(' ');
}
