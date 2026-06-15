import { useState, useCallback } from "react";

let toastId = 0;

export function useToast() {
	const [toasts, setToasts] = useState([]);

	const addToast = useCallback((message, type = "error") => {
		const id = ++toastId;
		setToasts((prev) => [...prev, { id, message, type }]);
		return id;
	}, []);

	const removeToast = useCallback((id) => {
		setToasts((prev) => prev.filter((t) => t.id !== id));
	}, []);

	return { toasts, addToast, removeToast };
}