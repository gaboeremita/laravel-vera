import { useState, useEffect } from 'react';
import { Outlet, useParams, useOutletContext } from 'react-router-dom';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import { useTheme } from '../contexts/ThemeContext.jsx';

export default function AssistantLayout() {
	const { assistantId } = useParams();
	const parentContext = useOutletContext();
	const { setTheme, setAvailableThemes } = useTheme();
	const [conversations, setConversations] = useState([]);
	const [assistantName, setAssistantName] = useState('');
	const [archiveId, setArchiveId] = useState(null);

	const fetchConversations = async () => {
		try {
			const res = await api.get(route('conversations.index', { assistant: assistantId }));
			const data = await res.json();
			setConversations(data);
		} catch {
			parentContext.addToast('Failed to load conversations', 'error');
		}
	};

	useEffect(() => {
		parentContext.setActiveAssistantId(Number(assistantId));
		fetchConversations();
		parentContext.fetchEmotions(Number(assistantId));

		api.get(route('assistants.show', { id: assistantId }))
			.then((res) => res.json())
			.then((data) => {
				setAssistantName(data.name);
				setArchiveId(data.archive_id ?? null);
			})
			.catch(() => {});

		api.get(route('settings.show', { assistant: assistantId }))
			.then((res) => res.json())
			.then((data) => {
				setAvailableThemes(data.available_themes);
				setTheme(data.selected_theme);
			})
			.catch(() => {});
	}, [assistantId]);

	return (
		<Outlet
			context={{
				...parentContext,
				assistantId: Number(assistantId),
				assistantName,
				archiveId,
				conversations,
				setConversations,
				fetchConversations,
			}}
		/>
	);
}