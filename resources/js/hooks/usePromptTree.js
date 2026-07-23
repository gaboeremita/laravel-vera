import { useState } from 'react';

/**
 * Generic prompt-tree editing state, extracted from usePrompt.js.
 * Same tree operations, but persistence is left to the caller via onSave —
 * used for any JSON prompt tree that isn't the assistant's own prompt.
 */
export default function usePromptTree(initialValue, onSave, addToast) {
	const [sections, setSections] = useState(
		initialValue && Object.keys(initialValue).length > 0 ? initialValue : null
	);
	const [isSaving, setIsSaving] = useState(false);

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

	const renameKey = (path, newKey) => {
		setSections((prev) => {
			const updated = structuredClone(prev);
			let node = updated;

			for (let i = 0; i < path.length - 1; i++) {
				node = node[path[i]];
			}

			const oldKey = path[path.length - 1];
			if (oldKey === newKey) return prev;

			const rebuilt = {};
			for (const [k, v] of Object.entries(node)) {
				rebuilt[k === oldKey ? newKey : k] = v;
			}

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
			await onSave(sections ?? {});
			addToast?.('Prompt saved', 'success');
		} catch (e) {
			addToast?.(e.message || 'Failed to save prompt', 'error');
		} finally {
			setIsSaving(false);
		}
	};

	const saveFromJson = async (json) => {
		let parsed;
		try {
			parsed = JSON.parse(json);
		} catch {
			addToast?.('Invalid JSON', 'error');
			return;
		}

		setIsSaving(true);

		try {
			await onSave(parsed);
			setSections(parsed && Object.keys(parsed).length > 0 ? parsed : null);
			addToast?.('Prompt saved', 'success');
		} catch (e) {
			addToast?.(e.message || 'Failed to save prompt', 'error');
		} finally {
			setIsSaving(false);
		}
	};

	const destroy = async () => {
		try {
			await onSave(null);
			setSections(null);
			addToast?.('Prompt cleared', 'success');
		} catch (e) {
			addToast?.(e.message || 'Failed to clear prompt', 'error');
		}
	};

	return {
		sections,
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
