import { useState, useRef, useEffect } from "react";
import { Trash2 } from "lucide-react";
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

export default function WorldSessionList({ worldId, sessions, onSelect, onNew, onDelete }) {
	const [activeRow, setActiveRow] = useState(0);
	const [activeColumn, setActiveColumn] = useState("select");
	const [pendingDeleteId, setPendingDeleteId] = useState(null);
	const listRef = useRef(null);

	useEffect(() => {
		listRef.current?.focus();
	}, []);

	const columnResetKey = activeRow >= sessions.length;
	const [wasRowInvalid, setWasRowInvalid] = useState(columnResetKey);
	if (columnResetKey !== wasRowInvalid) {
		setWasRowInvalid(columnResetKey);
		if (columnResetKey) {
			setActiveColumn("select");
		}
	}

	const handleKeyDown = (e) => {
		if (pendingDeleteId) return;

		const total = sessions.length + 1;

		if (e.key === "ArrowUp") {
			e.preventDefault();
			setActiveRow((prev) => (prev - 1 + total) % total);
		} else if (e.key === "ArrowDown") {
			e.preventDefault();
			setActiveRow((prev) => (prev + 1) % total);
		} else if (e.key === "ArrowLeft" || e.key === "ArrowRight") {
			e.preventDefault();
			if (activeRow < sessions.length) {
				const columns = ["select", "delete"];
				const dir = e.key === "ArrowRight" ? 1 : -1;
				const currentIdx = columns.indexOf(activeColumn);
				const nextIdx = (currentIdx + dir + columns.length) % columns.length;
				setActiveColumn(columns[nextIdx]);
			}
		} else if (e.key === "Enter") {
			e.preventDefault();
			if (activeRow < sessions.length) {
				if (activeColumn === "delete") {
					setPendingDeleteId(sessions[activeRow].id);
				} else {
					onSelect(sessions[activeRow].id);
				}
			} else {
				onNew();
			}
		}
	};

	const handleDelete = async (id) => {
		try {
			await api.delete(route('worlds.sessions.destroy', { world: worldId, session: id }));
			setPendingDeleteId(null);
			onDelete(id);
		} catch {
			setPendingDeleteId(null);
		}
	};

	const isRowActive = (i) => activeRow === i;

	return (
		<div
			className="p-6 focus:outline-none relative"
			tabIndex={0}
			onKeyDown={handleKeyDown}
			ref={listRef}
		>
			<div className="text-accent text-[0.8rem] font-bold tracking-[0.15em] uppercase mb-1">
				Select session
			</div>
			<div className="text-[#1a1a2e] text-[0.7rem] mb-4">
				─────────────────────────────────
			</div>

			{sessions.length === 0 && (
				<div className="text-fg-3 text-[0.75rem] mb-4">
					No sessions yet in this world.
				</div>
			)}

			{sessions.map((session, i) => (
				<div
					key={session.id}
					onMouseEnter={() => setActiveRow(i)}
					className={`flex items-center gap-3 px-2 py-1.5 text-[0.8rem] transition-colors duration-150 ${
						isRowActive(i) ? "text-accent" : "text-fg-3"
					}`}
				>
					<span className="w-4 shrink-0">
						{isRowActive(i) ? "›" : " "}
					</span>

					<button
						onClick={() => onSelect(session.id)}
						onMouseEnter={() => setActiveColumn("select")}
						className="flex-1 text-left cursor-pointer min-w-0"
					>
						{i + 1}.{" "}
						<span className={`pb-0.5 border-b-2 transition-all duration-150 ${
							isRowActive(i) && activeColumn === "select"
								? "border-accent"
								: "border-transparent"
						}`}>
							{session.title || "New session"}
						</span>
					</button>

					<span className="text-[0.65rem] shrink-0 text-fg-3">
						{timeAgo(session.updated_at)}
					</span>

					<button
						onClick={() => setPendingDeleteId(session.id)}
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

			<button
				onClick={onNew}
				onMouseEnter={() => {
					setActiveRow(sessions.length);
					setActiveColumn("select");
				}}
				className={`w-full text-left px-2 py-1.5 flex items-center gap-3 text-[0.8rem] cursor-pointer transition-colors duration-150 mt-2 ${
					activeRow === sessions.length
						? "text-success"
						: "text-success/40"
				}`}
			>
				<span className="w-4 shrink-0">
					{activeRow === sessions.length ? "›" : " "}
				</span>
				<span>+{" "}
					<span className={`pb-0.5 border-b-2 transition-all duration-150 ${
						activeRow === sessions.length
							? "border-success"
							: "border-transparent"
					}`}>
						New session
					</span>
				</span>
			</button>

			<div className="text-fg-3 text-[0.65rem] mt-6 tracking-[0.1em]">
				Enter to select · ↑↓ navigate · ←→ select/delete
			</div>

			{pendingDeleteId && (
				<ConfirmationModal
					title="Delete session"
					message="This session and its conversations will be permanently erased. Are you sure?"
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
