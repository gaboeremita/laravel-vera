import { useState, useEffect } from 'react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';

export default function usePrompt(assistantId, addToast) {
	const [sections, setSections] = useState(null);
	const [isLoading, setIsLoading] = useState(true);
	const [isSaving, setIsSaving] = useState(false);

	useEffect(() => {
		const load = async () => {
			try {
				const res = await api.get(route('prompt.show', { assistant: assistantId }));
				const data = await res.json();

				// null or empty means no prompt exists yet
				setSections(data && Object.keys(data).length > 0 ? data : null);
			} catch {
				addToast('Failed to load prompt', 'error');
			} finally {
				setIsLoading(false);
			}
		};
		void load();
	}, [assistantId]);

	/**
	 * Set a value at a given path in the tree.
	 * Path is an array of keys, e.g. ['appearance', 'general']
	 */
	const setValueAtPath = (path, value) => {
		setSections((prev) => {
			const updated = structuredClone(prev);
			let node = updated;

			for (let i = 0; i < path.length - 1; i++) {
				node = node[path[i]];
			}

			node[path[path.length - 1]] = value;
			return updated;
		});
	};

	/**
	 * Add a new key at a given path.
	 * parentPath is [] for top-level, or ['appearance'] for a sub-key inside appearance.
	 */
	const addKey = (parentPath, key, type) => {
		const defaultValues = {
			string: '',
			list: [''],
			object: {},
		};

		setSections((prev) => {
			const updated = structuredClone(prev ?? {});
			let node = updated;

			for (const segment of parentPath) {
				node = node[segment];
			}

			node[key] = defaultValues[type];
			return updated;
		});
	};

	/** Remove a key at a given path. */
	const removeKey = (path) => {
		setSections((prev) => {
			const updated = structuredClone(prev);
			let node = updated;

			for (let i = 0; i < path.length - 1; i++) {
				node = node[path[i]];
			}

			delete node[path[path.length - 1]];
			return updated;
		});
	};

	/** Rename a key without losing its value or position. */
	const renameKey = (path, newKey) => {
		setSections((prev) => {
			const updated = structuredClone(prev);
			let node = updated;

			for (let i = 0; i < path.length - 1; i++) {
				node = node[path[i]];
			}

			const oldKey = path[path.length - 1];
			if (oldKey === newKey) return prev;

			// Rebuild to preserve key order
			const rebuilt = {};
			for (const [k, v] of Object.entries(node)) {
				if (k === oldKey) {
					rebuilt[newKey] = v;
				} else {
					rebuilt[k] = v;
				}
			}

			// Replace the parent's contents
			if (path.length === 1) {
				return rebuilt;
			}

			let parent = updated;
			for (let i = 0; i < path.length - 2; i++) {
				parent = parent[path[i]];
			}
			parent[path[path.length - 2]] = rebuilt;

			return updated;
		});
	};

	/** Add an item to a sequential array at the given path. */
	const addListItem = (path) => {
		setSections((prev) => {
			const updated = structuredClone(prev);
			let node = updated;

			for (const segment of path) {
				node = node[segment];
			}

			node.push('');
			return updated;
		});
	};

	/** Remove an item from a sequential array by index. */
	const removeListItem = (path, index) => {
		setSections((prev) => {
			const updated = structuredClone(prev);
			let node = updated;

			for (const segment of path) {
				node = node[segment];
			}

			node.splice(index, 1);
			return updated;
		});
	};

	/** Update a single item in a sequential array. */
	const updateListItem = (path, index, value) => {
		setSections((prev) => {
			const updated = structuredClone(prev);
			let node = updated;

			for (const segment of path) {
				node = node[segment];
			}

			node[index] = value;
			return updated;
		});
	};

	const save = async () => {
		setIsSaving(true);

		try {
			const isCreate = sections === null || Object.keys(sections).length === 0;
			const payload = { prompt: sections ?? {} };

			const res = isCreate
				? await api.post(route('prompt.store', { assistant: assistantId }), payload)
				: await api.put(route('prompt.update', { assistant: assistantId }), payload);

			if (!res.ok) {
				const error = await res.json().catch(() => ({}));
				throw new Error(error.message || 'Save failed');
			}

			addToast('Prompt saved', 'success');
		} catch (e) {
			addToast(e.message || 'Failed to save prompt', 'error');
		} finally {
			setIsSaving(false);
		}
	};

	const saveFromJson = async (json) => {
		let parsed;
		try {
			parsed = JSON.parse(json);
		} catch {
			addToast('Invalid JSON', 'error');
			return;
		}

		setIsSaving(true);

		try {
			const isCreate = sections === null || Object.keys(sections).length === 0;
			const payload = { prompt: parsed };

			const res = isCreate
				? await api.post(route('prompt.store', { assistant: assistantId }), payload)
				: await api.put(route('prompt.update', { assistant: assistantId }), payload);

			if (!res.ok) {
				const error = await res.json().catch(() => ({}));
				throw new Error(error.message || 'Save failed');
			}

			setSections(parsed);
			addToast('Prompt saved', 'success');
		} catch (e) {
			addToast(e.message || 'Failed to save prompt', 'error');
		} finally {
			setIsSaving(false);
		}
	};

	const destroy = async () => {
		try {
			const res = await api.delete(route('prompt.destroy', { assistant: assistantId }));
			if (!res.ok) throw new Error('Delete failed');

			setSections(null);
			addToast('Prompt deleted', 'success');
		} catch {
			addToast('Failed to delete prompt', 'error');
		}
	};

	return {
		sections,
		isLoading,
		isSaving,
		setValueAtPath,
		addKey,
		removeKey,
		renameKey,
		addListItem,
		removeListItem,
		updateListItem,
		save,
		saveFromJson,
		destroy,
	};
}