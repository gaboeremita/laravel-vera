/**
 * Reading an activity's responses on the player's side. The server decides
 * which response runs; these only guess it from what the client knows, to
 * show the price and what changes hands before the player commits.
 */

function holdsItem(inventory, itemId, atLeast = 1) {
	const held = (inventory?.items ?? []).find((item) => item.itemId === itemId);
	return !!held && (held.quantity === null || held.quantity >= atLeast);
}

/** Whether the condition may hold: leaves the client cannot read count as met. */
export function mayHold(condition, { inventory, objectState }) {
	if (!condition) return true;
	const [kind] = Object.keys(condition);
	const value = condition[kind];
	switch (kind) {
		case 'all': return value.every((child) => mayHold(child, { inventory, objectState }));
		case 'any': return value.some((child) => mayHold(child, { inventory, objectState }));
		case 'not': return !definitelyHolds(value, { inventory, objectState });
		case 'has': return holdsItem(inventory, value.item, value.atLeast ?? 1);
		case 'credits': return inventory?.credits === null || (inventory?.credits ?? 0) >= (value.atLeast ?? 0);
		case 'objectState': return !!objectState?.[value];
		default: return true;
	}
}

function definitelyHolds(condition, known) {
	if (!condition) return true;
	const [kind] = Object.keys(condition);
	const value = condition[kind];
	switch (kind) {
		case 'all': return value.every((child) => definitelyHolds(child, known));
		case 'any': return value.some((child) => definitelyHolds(child, known));
		case 'not': return !mayHold(value, known);
		case 'has':
		case 'credits':
		case 'objectState': return mayHold(condition, known);
		default: return false;
	}
}

/** The first response that may run, or null when none can. */
export function likelyResponse(terms, known) {
	return (terms?.responses ?? []).find((response) => mayHold(response.condition, known)) ?? null;
}

function effectsOf(response, type) {
	return (response?.effects ?? []).filter((effect) => effect.type === type);
}

/** The credits the response charges the player. */
export function responsePrice(response) {
	return effectsOf(response, 'takeCredits').reduce((total, effect) => total + effect.amount, 0);
}

/** The items the response takes from the player, with names and pictures from the terms. */
export function responseTakes(terms, response) {
	return effectsOf(response, 'takeItems').flatMap((effect) => effect.items).map((entry) => ({ ...entry, name: terms.items?.[entry.itemId]?.name ?? 'Item', cardImageUrl: terms.items?.[entry.itemId]?.cardImageUrl ?? null }));
}

/** The items the response hands the player, with names and pictures from the terms. */
export function responseGives(terms, response) {
	return effectsOf(response, 'giveItems').flatMap((effect) => effect.items).map((entry) => ({ ...entry, name: terms.items?.[entry.itemId]?.name ?? 'Item', cardImageUrl: terms.items?.[entry.itemId]?.cardImageUrl ?? null }));
}

/** Whether any response hands the player the item. */
export function givesItem(terms, itemId) {
	return (terms?.responses ?? []).some((response) => responseGives(terms, response).some((entry) => entry.itemId === itemId));
}

/** The items the first response's condition asks the player to hold, as it or among the conditions that must all be met. */
export function requiredItemIds(terms) {
	const condition = terms?.responses?.[0]?.condition;
	if (!condition) return [];
	const leaves = 'all' in condition ? condition.all : [condition];
	return leaves.filter((leaf) => leaf && 'has' in leaf).map((leaf) => leaf.has.item);
}

/** Whether a response asks the narrator, so the player says or does something first. */
export function needsAttempt(terms) {
	const asksNarrator = (condition) => {
		if (!condition) return false;
		const [kind] = Object.keys(condition);
		if (kind === 'narrator') return true;
		if (kind === 'all' || kind === 'any') return condition[kind].some(asksNarrator);
		return false;
	};
	return (terms?.responses ?? []).some((response) => asksNarrator(response.condition));
}

function opens(terms) {
	return (terms.responses ?? []).some((response) => (response.effects ?? []).some((effect) => effect.type === 'makePassable'));
}

/** The objects that start out blocking the player: those with a response that makes them passable. */
export function blockingObjectIds(activityTerms) {
	return new Set((activityTerms ?? []).filter(opens).map((terms) => terms.objectId));
}

/** The activity of the object with a response that makes it passable, or null when it has none. */
export function openingActivityId(activityTerms, objectId) {
	return (activityTerms ?? []).find((terms) => terms.objectId === objectId && opens(terms))?.activityId ?? null;
}
