import { useState, useEffect, useRef } from 'react';
import { api } from '../utils/api.js';
import { route } from 'ziggy-js';

const POLL_INTERVAL_MS = 5000;

export default function useConversationMemory(addToast, assistantId, conversationId) {
	const [memory, setMemory] = useState('');
	const [pendingCount, setPendingCount] = useState(0);
	const [isLoading, setIsLoading] = useState(true);
	const [isSaving, setIsSaving] = useState(false);
	const [isSummarizing, setIsSummarizing] = useState(false);
	const [isLocked, setIsLocked] = useState(false);
	const [isUnlocking, setIsUnlocking] = useState(false);
	const [autoSummarizeEnabled, setAutoSummarizeEnabled] = useState(false);
	const [isTogglingAutoSummarize, setIsTogglingAutoSummarize] = useState(false);
	const pollRef = useRef(null);

	const applyShowData = (data) => {
		setMemory(data.long_term_memory ?? '');
		setPendingCount(data.pending_count ?? 0);
		setIsLocked(!!data.is_summarizing);
		setAutoSummarizeEnabled(!!data.auto_summarize_enabled);
		return !!data.is_summarizing;
	};

	const fetchStatus = async () => {
		const res = await api.get(route('memory.show', { assistant: assistantId, id: conversationId }));
		return await res.json();
	};

	useEffect(() => {
		const load = async () => {
			try {
				const data = await fetchStatus();
				applyShowData(data);
			} catch (e) {
				addToast('Failed to load memory', 'error');
			} finally {
				setIsLoading(false);
			}
		};
		void load();

		return () => {
			if (pollRef.current) clearInterval(pollRef.current);
		};
	}, [assistantId, conversationId]);

	// Poll while a background summarization is in progress, since it can be
	// triggered automatically (not just from this page) and may still be
	// running when the user comes back to this page later.
	useEffect(() => {
		if (!isLocked) {
			if (pollRef.current) {
				clearInterval(pollRef.current);
				pollRef.current = null;
			}
			return;
		}

		pollRef.current = setInterval(async () => {
			try {
				const data = await fetchStatus();
				const stillLocked = applyShowData(data);
				if (!stillLocked) {
					addToast('Memory summarized', 'success');
				}
			} catch (e) {
				// Keep polling silently — a transient failure shouldn't spam the user.
			}
		}, POLL_INTERVAL_MS);

		return () => clearInterval(pollRef.current);
	}, [isLocked]);

	const save = async () => {
		setIsSaving(true);
		try {
			const res = await api.put(route('memory.update', { assistant: assistantId, id: conversationId }), {
				long_term_memory: memory,
			});

			if (res.status === 409) {
				addToast('Memory is being summarized in the background — try again shortly', 'error');
				return;
			}

			const data = await res.json();
			setMemory(data.long_term_memory ?? '');
			addToast('Memory saved', 'success');
		} catch (e) {
			addToast('Failed to save memory', 'error');
		} finally {
			setIsSaving(false);
		}
	};

	const summarize = async (mode) => {
		setIsSummarizing(true);
		try {
			const res = await api.post(route('memory.summarize', { assistant: assistantId, id: conversationId }), { mode });
			const data = await res.json();

			if (data.queued) {
				setIsLocked(true);
				addToast('Summarizing in the background — this may take a moment to appear', 'success');
			} else if (data.already_summarizing) {
				setIsLocked(true);
				addToast('Already summarizing in the background', 'error');
			} else {
				addToast('Nothing new to summarize', 'success');
			}
		} catch (e) {
			addToast('Failed to queue summarization', 'error');
		} finally {
			setIsSummarizing(false);
		}
	};

	const summarizeSinceLast = () => summarize('since_last');
	const summarizeAsFarAsPossible = () => summarize('full');

	const forceUnlock = async () => {
		setIsUnlocking(true);
		try {
			const res = await api.post(route('memory.unlock', { assistant: assistantId, id: conversationId }));
			const data = await res.json();
			setMemory(data.long_term_memory ?? '');
			setIsLocked(false);
			addToast('Lock cleared', 'success');
		} catch (e) {
			addToast('Failed to clear lock', 'error');
		} finally {
			setIsUnlocking(false);
		}
	};

	const toggleAutoSummarize = async () => {
		const next = !autoSummarizeEnabled;
		setIsTogglingAutoSummarize(true);
		try {
			const res = await api.put(route('memory.update', { assistant: assistantId, id: conversationId }), {
				auto_summarize_enabled: next,
			});

			if (res.status === 409) {
				addToast('Memory is being summarized in the background — try again shortly', 'error');
				return;
			}

			const data = await res.json();
			setAutoSummarizeEnabled(!!data.auto_summarize_enabled);
		} catch (e) {
			addToast('Failed to update setting', 'error');
		} finally {
			setIsTogglingAutoSummarize(false);
		}
	};

	return {
		memory,
		setMemory,
		pendingCount,
		isLoading,
		isSaving,
		isSummarizing,
		isLocked,
		isUnlocking,
		autoSummarizeEnabled,
		isTogglingAutoSummarize,
		save,
		summarizeSinceLast,
		summarizeAsFarAsPossible,
		forceUnlock,
		toggleAutoSummarize,
	};
}
