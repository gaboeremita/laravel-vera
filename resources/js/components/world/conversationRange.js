export const CONVERSATION_WARNING_DISTANCE = 8;
export const CONVERSATION_END_DISTANCE = 12;

export function conversationRangeState(distance) {
	if (distance >= CONVERSATION_END_DISTANCE) return 'ended';
	if (distance >= CONVERSATION_WARNING_DISTANCE) return 'warning';
	return 'ok';
}
