import { useEffect, useState } from 'react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';

export default function useWorlds(addToast) {
	const [worlds, setWorlds] = useState([]);
	const [isLoading, setIsLoading] = useState(true);

	useEffect(() => {
		const load = async () => {
			try {
				const response = await api.get(route('worlds.index'));
				if (!response.ok) throw new Error();
				setWorlds(await response.json());
			} catch {
				addToast('Failed to load worlds', 'error');
			} finally {
				setIsLoading(false);
			}
		};
		void load();
	}, [addToast]);

	return { worlds, isLoading };
}
