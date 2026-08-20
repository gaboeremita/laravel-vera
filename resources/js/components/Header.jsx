import { useEffect, useRef, useState } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import { ArrowLeft, Menu, Settings } from 'lucide-react';
import { useLocation, useNavigate } from 'react-router-dom';

function HeaderMenu({ items }) {
	const navigate = useNavigate();
	const location = useLocation();
	const [open, setOpen] = useState(false);
	const containerRef = useRef(null);

	useEffect(() => {
		if (!open) {
			return;
		}

		const handlePointerDown = (event) => {
			if (containerRef.current && !containerRef.current.contains(event.target)) {
				setOpen(false);
			}
		};

		const handleKeyDown = (event) => {
			if (event.key === 'Escape') {
				setOpen(false);
			}
		};

		document.addEventListener('mousedown', handlePointerDown);
		document.addEventListener('keydown', handleKeyDown);

		return () => {
			document.removeEventListener('mousedown', handlePointerDown);
			document.removeEventListener('keydown', handleKeyDown);
		};
	}, [open]);

	return (
		<div className="relative" ref={containerRef}>
			<button
				type="button"
				onClick={() => setOpen((prev) => !prev)}
				aria-label="Menu"
				aria-expanded={open}
				className="text-fg-3 hover:text-accent transition-colors cursor-pointer"
			>
				<Menu size={16} />
			</button>
			<AnimatePresence>
				{open && (
					<motion.div
						initial={{ opacity: 0, y: -4 }}
						animate={{ opacity: 1, y: 0 }}
						exit={{ opacity: 0, y: -4 }}
						transition={{ duration: 0.15, ease: 'easeOut' }}
						className="absolute right-0 top-full mt-2 w-48 border border-line-1 bg-bg-0/95 backdrop-blur-sm z-50"
					>
						{items.map(({ label, to, icon: Icon }) => {
							const isCurrent = location.pathname === to;

							return (
								<button
									key={to}
									type="button"
									disabled={isCurrent}
									aria-current={isCurrent ? 'page' : undefined}
									onClick={() => {
										setOpen(false);
										navigate(to);
									}}
									className={`w-full flex items-center gap-3 px-4 py-2.5 text-[0.7rem] tracking-[0.1em] uppercase transition-colors ${
										isCurrent
											? 'text-accent bg-bg-1 cursor-default'
											: 'text-fg-3 hover:bg-bg-1 hover:text-accent cursor-pointer'
									}`}
								>
									<Icon size={14} />
									{label}
									{isCurrent && (
										<span className="ml-0 w-1.5 h-1.5 rounded-full bg-accent shrink-0" />
									)}
								</button>
							);
						})}
					</motion.div>
				)}
			</AnimatePresence>
		</div>
	);
}

export default function Header({ children, status, actions, counter, hideSettings = false, settingsPath = '/settings', menuItems, onBack, align = 'end' }) {
	const navigate = useNavigate();
	const alignClass = align === 'center' ? 'items-center' : 'items-end';

	return (
		<div className={`px-5 py-3 border-b border-line-1 flex justify-between ${alignClass} shrink-0`}>
			<div className="flex items-center gap-4">
				{onBack && (
					<button
						type="button"
						onClick={onBack}
						aria-label="Back"
						className="text-fg-3 hover:text-accent transition-colors cursor-pointer"
					>
						<ArrowLeft size={16} />
					</button>
				)}
				{children}
			</div>
			<div className="flex items-center gap-6">
				{actions}
				{status && (
					<div className="text-right">
						<div className={`text-[0.75rem] tracking-[0.15em] ${status.color}`}>
							<span className={status.blink ? 'cursor-effect' : ''}>{status.dot}</span> {status.label}
						</div>
						{counter && (
							<div className="text-fg-2 text-[0.65rem] mt-0.5">
								{counter}
							</div>
						)}
					</div>
				)}
				{menuItems?.length > 0 ? (
					<HeaderMenu items={menuItems} />
				) : (
					!hideSettings && (
						<button
							onClick={() => navigate(settingsPath)}
							className="text-fg-3 hover:text-accent transition-colors cursor-pointer"
						>
							<Settings size={16} />
						</button>
					)
				)}
			</div>
		</div>
	);
}
