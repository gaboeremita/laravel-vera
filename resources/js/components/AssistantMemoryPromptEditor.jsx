import { useState, useEffect } from 'react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import usePromptTree from '../hooks/usePromptTree.js';
import PromptTreeEditor from './PromptTreeEditor.jsx';

// Only mounts once initialValue is available, so usePromptTree's internal
// state (fixed at first render) starts from real data instead of null.
function Editor({ initialValue, assistantId, addToast }) {
	const promptTree = usePromptTree(
		initialValue,
		async (sections) => {
			const res = await api.put(route('memory-prompt.update', { assistant: assistantId }), {
				memory_prompt: sections,
			});
			if (!res.ok) throw new Error('Failed to save memory prompt');
		},
		addToast
	);

	return <PromptTreeEditor promptTree={promptTree} />;
}

export default function AssistantMemoryPromptEditor({ assistantId, addToast }) {
	const [initialValue, setInitialValue] = useState(null);
	const [isLoading, setIsLoading] = useState(true);

	useEffect(() => {
		let cancelled = false;
		setIsLoading(true);
		setInitialValue(null);

		const load = async () => {
			try {
				const res = await api.get(route('memory-prompt.show', { assistant: assistantId }));
				if (!res.ok) throw new Error('Failed to load memory prompt');
				const data = await res.json();
				if (!cancelled) setInitialValue(data);
			} catch (e) {
				if (!cancelled) addToast('Failed to load memory prompt', 'error');
			} finally {
				if (!cancelled) setIsLoading(false);
			}
		};
		void load();

		return () => {
			cancelled = true;
		};
	}, [assistantId]);

	if (isLoading) {
		return <span className="text-fg-3 text-sm cursor-effect">Loading...</span>;
	}

	return <Editor key={assistantId} initialValue={initialValue} assistantId={assistantId} addToast={addToast} />;
}
