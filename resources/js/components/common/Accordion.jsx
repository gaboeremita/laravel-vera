import { motion, AnimatePresence } from 'framer-motion';

export default function Accordion({ label, title, collapsed, onToggle, onDelete, badge, actions, children }) {
	return (
		<div className="border border-line-1">
			{/* Header */}
			<div
				onClick={onToggle}
				className="w-full px-4 py-3 flex items-center justify-between cursor-pointer hover:bg-bg-1 transition-colors"
			>
				<div className="flex items-center gap-3">
					<motion.span
						animate={{ rotate: collapsed ? 0 : 90 }}
						transition={{ duration: 0.2 }}
						className="text-fg-3 text-xs"
					>
						▶
					</motion.span>
					<span className="text-fg-3 text-[0.65rem] tracking-[0.15em]">
                        {label}
                    </span>
					<span className="text-accent text-sm truncate max-w-md">
                        {collapsed ? (title || 'Untitled') : ''}
                    </span>
					{badge}
				</div>
				<div className="flex items-center gap-3" onClick={(e) => e.stopPropagation()}>
					{actions}
					{onDelete && (
						<button
							onClick={onDelete}
							className="text-danger text-[0.7rem] tracking-[0.1em] cursor-pointer hover:text-danger transition-colors"
						>
							DELETE
						</button>
					)}
				</div>
			</div>

			{/* Body */}
			<AnimatePresence initial={false}>
				{!collapsed && (
					<motion.div
						initial={{ height: 0, opacity: 0 }}
						animate={{ height: 'auto', opacity: 1 }}
						exit={{ height: 0, opacity: 0 }}
						transition={{ duration: 0.2, ease: 'easeOut' }}
						style={{ overflow: 'hidden' }}
					>
						<div className="pl-8 pr-4 pb-4 space-y-3">
							{children}
						</div>
					</motion.div>
				)}
			</AnimatePresence>
		</div>
	);
}