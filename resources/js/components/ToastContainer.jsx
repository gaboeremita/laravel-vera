export default function ToastContainer({ toasts, onDismiss }) {
	if (toasts.length === 0) return null;

	return (
		<div className="fixed bottom-4 right-4 z-50 flex flex-col gap-2 max-w-sm">
			{toasts.map((toast) => (
				<div
					key={toast.id}
					className={`flex items-start gap-3 px-4 py-3 font-mono text-[0.75rem] border bg-[#0a0a0f]/95 backdrop-blur-sm ${
						toast.type === "error"
							? "border-vera-red text-vera-red"
							: "border-vera-cyan text-vera-cyan"
					}`}
				>
                    <span className="shrink-0 mt-0.5 tracking-[0.15em] uppercase text-[0.6rem] font-bold">
                        {toast.type === "error" ? "ERR" : "SYS"}
                    </span>

					<span className="flex-1 text-[#c8c8d8] leading-relaxed">
                        {toast.message}
                    </span>

					<button
						onClick={() => onDismiss(toast.id)}
						className={`shrink-0 cursor-pointer text-[0.7rem] transition-colors ${
							toast.type === "error"
								? "text-vera-red/50 hover:text-vera-red"
								: "text-vera-cyan/50 hover:text-vera-cyan"
						}`}
					>
						✕
					</button>
				</div>
			))}
		</div>
	);
}