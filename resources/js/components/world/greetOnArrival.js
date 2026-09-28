/**
 * The resident who opens a conversation when a session begins: the first one
 * in the region who greets on arrival, while the session has no conversation yet.
 */
export function greetingResident(residents, session) {
	if (!session || session.hasConversations) return null;
	return residents.find((resident) => resident.behaviorSettings?.greetOnArrival === true) ?? null;
}
