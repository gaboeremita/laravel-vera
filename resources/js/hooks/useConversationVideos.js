import { useEffect } from 'react';
import echo from '../echo.js';

export default function useConversationVideos(conversationId, onVideoUpdated) {
	useEffect(() => {
		if (!conversationId) return;

		const channelName = `conversation.${conversationId}`;
		const channel = echo.private(channelName);
		channel.listen('.video-generation.updated', onVideoUpdated);

		// Only this listener is removed: echo.leave() would also unsubscribe the other
		// hooks listening on the same conversation channel, such as useAvatarBackground.
		return () => {
			channel.stopListening('.video-generation.updated', onVideoUpdated);
		};
	}, [conversationId, onVideoUpdated]);
}
