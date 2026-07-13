import { useState, useRef, useEffect } from "react";
import { Trash2, Pencil } from "lucide-react";
import { route } from 'ziggy-js';
import ConfirmationModal from "./common/ConfirmationModal.jsx";
import { api } from "../utils/api";

function timeAgo(dateString) {
	const now = new Date();
	const date = new Date(dateString);
	const seconds = Math.floor((now - date) / 1000);

	if (seconds < 60) return "just now";
	if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
	if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
	return `${Math.floor(seconds / 86400)}d ago`;
}

// Animated underline indicator
function ActiveUnderline({ color = "accent" }) {
	return (
		<span
			className={`absolute bottom-0 left-0 right-0 h-[1px] bg-${color} animate-underline-in`}
		/>
	);
}

export default function ConversationList({ assistantId, conversations, onSelect, onNew, onDelete, onRename }) {
	const [activeRow, setActiveRow] = useState(0);
	const [activeColumn, setActiveColumn] = useState("select");
	const [pendingDeleteId, setPendingDeleteId] = useState(null);
	const [editingId, setEditingId] = useState(null);
	const [editingTitle, setEditingTitle] = useState("");
	const listRef = useRef(null);
	const editInputRef = useRef(null);

	useEffect(() => {
		listRef.current?.focus();
	}, []);

	useEffect(() => {
		if (activeRow >= conversations.length) {
			setActiveColumn("select");
		}
	}, [activeRow, conversations.length]);

	const handleKeyDown = (e) => {
		if (pendingDeleteId) return;

		const total = conversations.length + 1;

		if (e.key === "ArrowUp") {
			e.preventDefault();
			setActiveRow((prev) => (prev - 1 + total) % total);
		} else if (e.key === "ArrowDown") {
			e.preventDefault();
			setActiveRow((prev) => (prev + 1) % total);
		} else if (e.key === "ArrowLeft" || e.key === "ArrowRight") {
			e.preventDefault();
			if (activeRow < conversations.length) {
				const columns = ["select", "edit", "delete"];
				const dir = e.key === "ArrowRight" ? 1 : -1;
				const currentIdx = columns.indexOf(activeColumn);
				const nextIdx = (currentIdx + dir + columns.length) % columns.length;
				setActiveColumn(columns[nextIdx]);
			}
		} else if (e.key === "Enter") {
			e.preventDefault();
			if (activeRow < conversations.length) {
				if (activeColumn === "delete") {
					setPendingDeleteId(conversations[activeRow].id);
				} else if (activeColumn === "edit") {
					startEditing(conversations[activeRow]);
				} else {
					onSelect(conversations[activeRow].id);
				}
			} else {
				onNew();
			}
		}
	};

	const handleDelete = async (id) => {
		try {
			await api.delete(route('conversations.destroy', { assistant: assistantId, id }));
			setPendingDeleteId(null);
			onDelete(id);
		} catch {
			setPendingDeleteId(null);
		}
	};

	// Enter inline edit mode for a conversation
	const startEditing = (conv) => {
		setEditingId(conv.id);
		setEditingTitle(conv.title || "");
	};

	// Cancel editing and refocus the list
	const cancelEditing = () => {
		setEditingId(null);
		setEditingTitle("");
		// Return focus to the list for keyboard nav
		setTimeout(() => listRef.current?.focus(), 0);
	};

	// Save the edited title
	const saveEditing = () => {
		const trimmed = editingTitle.trim();
		if (trimmed && editingId) {
			onRename(editingId, trimmed);
		}
		cancelEditing();
	};

	// Focus the edit input when editing begins
	useEffect(() => {
		if (editingId && editInputRef.current) {
			editInputRef.current.focus();
			editInputRef.current.select();
		}
	}, [editingId]);

	const isRowActive = (i) => activeRow === i;

	return (
		<div
			className="p-6 focus:outline-none relative"
			tabIndex={0}
			onKeyDown={handleKeyDown}
			ref={listRef}
		>
			<div className="text-accent text-[0.8rem] font-bold tracking-[0.15em] uppercase mb-1">
				Select conversation
			</div>
			<div className="text-[#1a1a2e] text-[0.7rem] mb-4">
				─────────────────────────────────
			</div>

			{conversations.map((conv, i) => (
				<div
					key={conv.id}
					onMouseEnter={() => setActiveRow(i)}
					className={`flex items-center gap-3 px-2 py-1.5 text-[0.8rem]  transition-colors duration-150 ${
						isRowActive(i) ? "text-accent" : "text-fg-3"
					}`}
				>
                <span className="w-4 shrink-0">
                    {isRowActive(i) ? "›" : " "}
                </span>

					{/* Conversation title — inline editable */}
					{editingId === conv.id ? (
						<input
							ref={editInputRef}
							type="text"
							value={editingTitle}
							onChange={(e) => setEditingTitle(e.target.value)}
							onKeyDown={(e) => {
								if (e.key === "Enter") {
									e.preventDefault();
									saveEditing();
								} else if (e.key === "Escape") {
									e.preventDefault();
									cancelEditing();
								}
								e.stopPropagation();
							}}
							onBlur={saveEditing}
							className="flex-1 bg-transparent border-b border-accent text-accent  text-[0.8rem] outline-none caret-accent"
							maxLength={100}
						/>
					) : (
						<button
							onClick={() => onSelect(conv.id)}
							onMouseEnter={() => setActiveColumn("select")}
							className="flex-1 text-left cursor-pointer min-w-0"
						>
							{i + 1}.{" "}
							<span className={`pb-0.5 border-b-2 transition-all duration-150 ${
								isRowActive(i) && activeColumn === "select"
									? "border-accent"
									: "border-transparent"
							}`}>
								{conv.title || "Untitled"}
							</span>
						</button>
					)}

					{/* Edit — pencil next to name */}
					<button
						onClick={() => startEditing(conv)}
						onMouseEnter={() => setActiveColumn("edit")}
						className={`shrink-0 cursor-pointer pb-0.5 border-b-2 transition-all duration-150 ${
							isRowActive(i) && activeColumn === "edit"
								? "text-accent border-accent"
								: isRowActive(i)
									? "text-accent/50 border-transparent"
									: "text-accent/20 border-transparent"
						}`}
					>
						<Pencil size={14} />
					</button>

					{/* Timestamp */}
					<span className={`text-[0.65rem] shrink-0 transition-colors duration-150 ${
						isRowActive(i) ? "text-fg-3" : "text-fg-3"
					}`}>
                    {timeAgo(conv.updated_at)}
                </span>

					{/* Delete action */}
					<button
						onClick={() => setPendingDeleteId(conv.id)}
						onMouseEnter={() => setActiveColumn("delete")}
						className={`shrink-0 cursor-pointer pb-0.5 border-b-2 transition-all duration-150 ${
							isRowActive(i) && activeColumn === "delete"
								? "text-danger border-danger"
								: isRowActive(i)
									? "text-danger border-transparent"
									: "text-danger/30 border-transparent"
						}`}
					>
						<Trash2 size={14} />
					</button>
				</div>
			))}

			{/* New conversation option */}
			<button
				onClick={onNew}
				onMouseEnter={() => {
					setActiveRow(conversations.length);
					setActiveColumn("select");
				}}
				className={`w-full text-left px-2 py-1.5 flex items-center gap-3 text-[0.8rem]  cursor-pointer transition-colors duration-150 mt-2 ${
					activeRow === conversations.length
						? "text-success"
						: "text-success/40"
				}`}
			>
            <span className="w-4 shrink-0">
                {activeRow === conversations.length ? "›" : " "}
            </span>
				<span>+{" "}
					<span className={`pb-0.5 border-b-2 transition-all duration-150 ${
						activeRow === conversations.length
							? "border-success"
							: "border-transparent"
					}`}>
                    New conversation
                </span>
            </span>
			</button>

			<div className="text-fg-3 text-[0.65rem] mt-6 tracking-[0.1em]">
				Enter to select · ↑↓ navigate · ←→ select/rename/delete
			</div>

			{/* Delete confirmation modal */}
			{pendingDeleteId && (
				<ConfirmationModal
					title="Delete conversation"
					message="This conversation will be permanently erased from The Bridge. Are you sure?"
					options={[
						{ label: "DELETE", value: "confirm", destructive: true },
						{ label: "CANCEL", value: "cancel", cancel: true },
					]}
					onSelect={(value) => {
						if (value === "confirm") handleDelete(pendingDeleteId);
						else setPendingDeleteId(null);
					}}
				/>
			)}
		</div>
	);
}