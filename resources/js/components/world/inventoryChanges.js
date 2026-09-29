const byId = (inventory) => new Map((inventory?.items ?? []).map((item) => [item.itemId, item]));
const signed = (delta) => (delta > 0 ? `+${delta}` : `${delta}`);

/**
 * What changed between two player inventories, credits first, then items by
 * name, each with the item's image and sound.
 */
export function inventoryChangeEntries(before, after) {
	if (!before || !after) return [];
	const entries = [];
	const creditDelta = (after.credits ?? 0) - (before.credits ?? 0);
	if (creditDelta !== 0) entries.push({ text: `${signed(creditDelta)} credits`, delta: creditDelta, imageUrl: null, sound: null });

	const was = byId(before);
	const now = byId(after);
	const ids = [...new Set([...was.keys(), ...now.keys()])];
	ids
		.map((id) => {
			const item = now.get(id) ?? was.get(id);
			return { item, delta: (now.get(id)?.quantity ?? 0) - (was.get(id)?.quantity ?? 0) };
		})
		.filter(({ delta }) => delta !== 0)
		.sort((a, b) => a.item.name.localeCompare(b.item.name))
		.forEach(({ item, delta }) => entries.push({
			text: `${signed(delta)} ${item.name}`,
			delta,
			imageUrl: item.cardImageUrl ?? null,
			sound: item.soundUrl && item.soundHash ? { url: item.soundUrl, hash: item.soundHash } : null,
		}));
	return entries;
}

/** The toast lines for what changed between two player inventories. */
export function inventoryChanges(before, after) {
	return inventoryChangeEntries(before, after).map((entry) => entry.text);
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

/** The line the player sends when they pick one of a vendor's goods: "*asks for an order of tacos al pastor*". */
export function askForLine(item) {
	const name = item.name.charAt(0).toLowerCase() + item.name.slice(1);
	return `*asks for ${/^[aeiou]/i.test(name) ? 'an' : 'a'} ${name}*`;
}
