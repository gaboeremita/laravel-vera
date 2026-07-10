export default function ToastContainer({ toasts, onDismiss }) {
	if (toasts.length === 0) return null;

	return (
		<div className="fixed bottom-4 right-4 z-50 flex flex-col gap-2 max-w-sm">
			{toasts.map((toast) => (
				<div
					key={toast.id}
					className={`flex items-start gap-3 px-4 py-3  text-[0.75rem] border bg-bg-0/95 backdrop-blur-sm ${
						toast.type === "error"
							? "border-danger text-danger"
							: "border-accent text-accent"
					}`}
				>
                    <span className="shrink-0 mt-0.5 tracking-[0.15em] uppercase text-[0.6rem] font-bold">
                        {toast.type === "error" ? "ERR" : "SYS"}
                    </span>

					<span className="flex-1 text-fg-1 leading-relaxed">
                        {toast.message}
                    </span>

					<button
						onClick={() => onDismiss(toast.id)}
						className={`shrink-0 cursor-pointer text-[0.7rem] transition-colors ${
							toast.type === "error"
								? "text-danger/50 hover:text-danger"
								: "text-accent/50 hover:text-accent"
						}`}
					>
						✕
					</button>
				</div>
			))}
		</div>
	);
}