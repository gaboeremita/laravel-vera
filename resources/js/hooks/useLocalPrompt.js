import { useState } from 'react';

/**
 * Prompt tree state management without API persistence.
 * Used by CreateAssistantPage where no assistant ID exists yet.
 * Same interface as usePrompt so PromptEditor works with either.
 */
export default function useLocalPrompt(initialSections = null) {
	const [sections, setSections] = useState(initialSections);
	const isSaving = false;
	const isLoading = false;

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
		const defaultValues = { string: '', list: [''], object: {} };
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

			if (path.length === 1) return rebuilt;

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
			for (const segment of path) node = node[segment];
			node.push('');
			return updated;
		});
	};

	const removeListItem = (path, index) => {
		setSections((prev) => {
			const updated = structuredClone(prev);
			let node = updated;
			for (const segment of path) node = node[segment];
			node.splice(index, 1);
			return updated;
		});
	};

	const updateListItem = (path, index, value) => {
		setSections((prev) => {
			const updated = structuredClone(prev);
			let node = updated;
			for (const segment of path) node = node[segment];
			node[index] = value;
			return updated;
		});
	};

	// No-ops to match usePrompt interface
	const save = () => {};
	const destroy = null;

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
		destroy,
	};
}