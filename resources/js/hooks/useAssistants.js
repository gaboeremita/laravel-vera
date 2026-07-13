import { useState, useEffect } from 'react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';

export default function useAssistants(addToast) {
	const [assistants, setAssistants] = useState([]);
	const [isLoading, setIsLoading] = useState(true);

	useEffect(() => {
		const load = async () => {
			try {
				const res = await api.get(route('assistants.index'));
				if (!res.ok) throw new Error('Failed to load assistants');
				const data = await res.json();
				setAssistants(data);
			} catch(e) {
				console.error('Failed to load assistants:', e);
				addToast('Failed to load assistants', 'error');
			} finally {
				setIsLoading(false);
			}
		};
		void load();
	}, []);

	const deleteAssistant = async (id) => {
		try {
			const res = await api.delete(route('assistants.destroy', { id }));
			if (!res.ok) throw new Error('Delete failed');
			setAssistants((prev) => prev.filter((a) => a.id !== id));
			addToast('Assistant deleted', 'success');
		} catch {
			addToast('Failed to delete assistant', 'error');
		}
	};

	return {
		assistants,
		isLoading,
		deleteAssistant,
	};
}