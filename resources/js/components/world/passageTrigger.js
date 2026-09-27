const VERTICAL_REACH = 2;

function isInside(passage, foot) {
	const dx = foot.x - passage.position.x;
	const dz = foot.z - passage.position.z;
	return Math.abs(foot.y - passage.position.y) <= VERTICAL_REACH && dx * dx + dz * dz <= passage.radius * passage.radius;
}

/**
 * Reports a linked passage when the player walks into it. The player arrives
 * standing inside a passage's area, so a passage only fires after the player
 * has been outside it at least once.
 */
export function createPassageTrigger(passages, linkedPassageIds) {
	const inside = new Map();

	return {
		update(foot) {
			let entered = null;
			for (const passage of passages) {
				const now = isInside(passage, foot);
				const before = inside.has(passage.id) ? inside.get(passage.id) : now;
				inside.set(passage.id, now);
				if (now && !before && linkedPassageIds.has(passage.id)) entered = passage;
			}
			return entered;
		},
	};
}
