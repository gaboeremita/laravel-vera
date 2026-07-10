import { useState, useRef, useEffect } from "react";

export default function TerminalModal({ title, message, options, onSelect }) {
	const [activeIndex, setActiveIndex] = useState(0);
	const modalRef = useRef(null);

	// Grab focus when modal opens
	useEffect(() => {
		modalRef.current?.focus();
	}, []);

	const handleKeyDown = (e) => {
		if (e.key === "ArrowLeft" || e.key === "ArrowUp") {
			e.preventDefault();
			setActiveIndex((prev) => (prev - 1 + options.length) % options.length);
		} else if (e.key === "ArrowRight" || e.key === "ArrowDown") {
			e.preventDefault();
			setActiveIndex((prev) => (prev + 1) % options.length);
		} else if (e.key === "Enter") {
			e.preventDefault();
			onSelect(options[activeIndex].value);
		} else if (e.key === "Escape") {
			e.preventDefault();
			// Find the cancel/no option and trigger it
			const cancelOption = options.find(o => o.cancel);
			if (cancelOption) onSelect(cancelOption.value);
		}
	};

	return (
		<div className="absolute inset-0 z-20 flex items-center justify-center bg-black/70">
			<div
				ref={modalRef}
				tabIndex={0}
				onKeyDown={handleKeyDown}
				className="border border-line-1 bg-bg-0 p-6 max-w-sm w-full focus:outline-none"
			>
				<div className="text-danger text-[0.7rem] font-bold tracking-[0.2em] uppercase mb-3">
					{title}
				</div>
				<div className="text-fg-2 text-[0.8rem] mb-5">
					{message}
				</div>
				<div className="flex gap-4">
					{options.map((opt, i) => (
						<button
							key={opt.value}
							onClick={() => onSelect(opt.value)}
							onMouseEnter={() => setActiveIndex(i)}
							className={` text-[0.75rem] px-4 py-1.5 border tracking-[0.1em] cursor-pointer transition-colors ${
								activeIndex === i
									? opt.destructive
										? "border-danger text-danger"
										: "border-accent text-accent"
									: "border-line-1 text-fg-3"
							}`}
						>
							{opt.label}
						</button>
					))}
				</div>
				<div className="text-fg-3 text-[0.6rem] mt-4 tracking-[0.1em]">
					←→ to navigate · Enter to confirm · Esc to cancel
				</div>
			</div>
		</div>
	);
}