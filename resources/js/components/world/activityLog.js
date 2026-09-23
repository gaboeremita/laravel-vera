import { route } from 'ziggy-js';
import { api } from '../../utils/api.js';

/**
 * Records that a resident started an action and resolves with the activity id.
 * Throws with the HTTP status when the server refuses it.
 */
export async function startActivity({ worldId, sessionId, residentId }, { verb, target = null, activity = null, reason = null, source = 'requested', position = null }) {
	const response = await api.post(route('worlds.sessions.residents.activities.store', { world: worldId, session: sessionId, resident: residentId }), {
		verb,
		target,
		activity,
		reason,
		source,
		position: position ? { x: position.x, y: position.y, z: position.z } : null,
	});
	if (!response.ok) throw new Error(`HTTP ${response.status}`);
	return (await response.json()).id;
}

/** Records how an action a resident started turned out. */
export async function finishActivity({ worldId, sessionId, residentId }, activityId, { outcome, reason }) {
	const response = await api.patch(route('worlds.sessions.residents.activities.update', { world: worldId, session: sessionId, resident: residentId, activity: activityId }), { outcome, reason });
	if (!response.ok) throw new Error(`HTTP ${response.status}`);
}
