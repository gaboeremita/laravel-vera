import { useEffect } from 'react';
import echo from '../echo.js';

export default function useVideoGenerationNotices(userId, addToast, navigate) {
	useEffect(() => {
		if (!userId) return;

		const showNotice = (data) => {
			const action = {
				label: 'OPEN',
				onClick: () => navigate(`/assistants/${data.assistantId}/conversations/${data.conversationId}`),
			};

			if (data.status === 'completed') {
				addToast(`${data.assistantName}'s video is ready`, 'success', { action });
			} else {
				addToast(`${data.assistantName}'s video failed: ${data.failureReason || 'unknown error'}`, 'error', { action });
			}
		};

		const channelName = `user.${userId}`;
		const channel = echo.private(channelName);
		channel.listen('.video-generation.finished', showNotice);

		return () => {
			channel.stopListening('.video-generation.finished', showNotice);
			echo.leave(channelName);
		};
	}, [userId, addToast, navigate]);
}
