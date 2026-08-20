import { Archive, Image, MessageCircle, MessagesSquare, Mic, ScrollText, Server, Settings } from 'lucide-react';

export function getAssistantMenuItems(assistantId) {
	return [
		{ label: 'Conversations', to: `/assistants/${assistantId}/conversations`, icon: MessagesSquare },
		{ label: 'Prompt', to: `/assistants/${assistantId}/prompt`, icon: ScrollText },
		{ label: 'Providers', to: `/assistants/${assistantId}/providers`, icon: Server },
		{ label: 'Image Gen', to: `/assistants/${assistantId}/image-gen-providers`, icon: Image },
		{ label: 'Voice', to: `/assistants/${assistantId}/voice`, icon: Mic },
		{ label: 'Discord', to: `/assistants/${assistantId}/discord`, icon: MessageCircle },
		{ label: 'Archive', to: `/assistants/${assistantId}/archive`, icon: Archive },
		{ label: 'Settings', to: `/assistants/${assistantId}/settings`, icon: Settings },
	];
}
