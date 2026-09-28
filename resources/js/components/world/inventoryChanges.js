const byId = (inventory) => new Map((inventory?.items ?? []).map((item) => [item.itemId, item]));
const signed = (delta) => (delta > 0 ? `+${delta}` : `${delta}`);

/** The toast lines for what changed between two player inventories: credits first, then items by name. */
export function inventoryChanges(before, after) {
	if (!before || !after) return [];
	const lines = [];
	const creditDelta = (after.credits ?? 0) - (before.credits ?? 0);
	if (creditDelta !== 0) lines.push(`${signed(creditDelta)} credits`);

	const was = byId(before);
	const now = byId(after);
	const ids = [...new Set([...was.keys(), ...now.keys()])];
	ids
		.map((id) => ({ name: (now.get(id) ?? was.get(id)).name, delta: (now.get(id)?.quantity ?? 0) - (was.get(id)?.quantity ?? 0) }))
		.filter(({ delta }) => delta !== 0)
		.sort((a, b) => a.name.localeCompare(b.name))
		.forEach(({ name, delta }) => lines.push(`${signed(delta)} ${name}`));
	return lines;
}

/** A quantity or balance as the HUD shows it: ∞ when unlimited. */
export function formatAmount(amount) {
	return amount === null || amount === undefined ? '∞' : amount.toLocaleString();
}

/** The server's signed `changes` as short lines, e.g. ["-5 credits", "+1 Drink"]. */
export function changeLines(changes) {
	if (!changes) return [];
	const lines = changes.credits ? [`${signed(changes.credits)} credits`] : [];
	return [...lines, ...(changes.items ?? []).map((change) => `${signed(change.delta)} ${change.name}`)];
}
