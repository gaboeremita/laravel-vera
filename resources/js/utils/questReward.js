/** What a quest reward handed over, as one line: each item with its quantity, then the credits. Empty when nothing was given. */
export function rewardSummary(reward) {
	if (!reward) return '';
	const parts = (reward.items ?? []).map((item) => (item.quantity > 1 ? `${item.name} ×${item.quantity}` : item.name));
	if (reward.credits > 0) parts.push(`${reward.credits} CR`);
	return parts.join(' · ');
}
