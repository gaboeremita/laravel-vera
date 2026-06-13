import { useState, useRef, useEffect } from "react";

// Format timestamp into relative time (e.g. "2m ago", "3d ago")
function timeAgo(dateString) {
	const now = new Date();
	const date = new Date(dateString);
	const seconds = Math.floor((now - date) / 1000);

	if (seconds < 60) return "just now";
	if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
	if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
	return `${Math.floor(seconds / 86400)}d ago`;
}

export default function ConversationList({ conversations, onSelect, onNew }) {
	const [activeIndex, setActiveIndex] = useState(0);
	const listRef = useRef(null);

	const handleKeyDown = (e) => {
		// Total items = conversations + the "new conversation" option
		const total = conversations.length + 1;

		if (e.key === "ArrowUp") {
			e.preventDefault();
			setActiveIndex((prev) => (prev - 1 + total) % total);
		} else if (e.key === "ArrowDown") {
			e.preventDefault();
			setActiveIndex((prev) => (prev + 1) % total);
		} else if (e.key === "Enter") {
			e.preventDefault();
			if (activeIndex < conversations.length) {
				onSelect(conversations[activeIndex].id);
			} else {
				onNew();
			}
		}
	};

	// Focus the list on mount so keyboard navigation works immediately
	useEffect(() => {
		listRef.current?.focus();
	}, []);
	
	return (
		<div
			className="p-6 focus:outline-none"
			tabIndex={0}
			onKeyDown={handleKeyDown}
			ref={listRef}
		>
			<div className="text-vera-cyan text-[0.8rem] font-bold tracking-[0.15em] uppercase mb-1">
				Select conversation
			</div>
			<div className="text-[#1a1a2e] text-[0.7rem] mb-4">
				─────────────────────────────────
			</div>

			{conversations.map((conv, i) => (
				<button
					key={conv.id}
					onClick={() => onSelect(conv.id)}
					onMouseEnter={() => setActiveIndex(i)}
					className={`w-full text-left px-2 py-1.5 flex items-center gap-3 text-[0.8rem] font-mono cursor-pointer transition-colors ${
						activeIndex === i
							? "text-vera-cyan"
							: "text-[#555568]"
					}`}
				>
                    <span className="w-4 shrink-0">
                        {activeIndex === i ? "›" : " "}
                    </span>
					<span className="w-5 shrink-0">{i + 1}.</span>
					<span className="truncate flex-1">
                        {conv.title || "Untitled"}
                    </span>
					<span className="text-[0.65rem] text-[#303045] shrink-0">
                        {timeAgo(conv.updated_at)}
                    </span>
				</button>
			))}

			{/* New conversation option */}
			<button
				onClick={onNew}
				onMouseEnter={() => setActiveIndex(conversations.length)}
				className={`w-full text-left px-2 py-1.5 flex items-center gap-3 text-[0.8rem] font-mono cursor-pointer transition-colors mt-2 ${
					activeIndex === conversations.length
						? "text-vera-red"
						: "text-[#555568]"
				}`}
			>
                <span className="w-4 shrink-0">
                    {activeIndex === conversations.length ? "›" : " "}
                </span>
				<span>+ New conversation</span>
			</button>

			<div className="text-[#303045] text-[0.65rem] mt-6 tracking-[0.1em]">
				Enter to select · ↑↓ to navigate
			</div>
		</div>
	);
}