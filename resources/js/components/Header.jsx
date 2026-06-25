export default function Header({ children, status, actions, counter }) {
	return (
		<div className="px-5 py-3 border-b border-[#1a1a2e] flex justify-between items-end shrink-0">
			<div className="flex items-end gap-4">
				<div>
                    <span className="text-vera-red text-[0.6rem] tracking-[0.25em] font-bold">
                        MODEL VR-09
                    </span>
					<div className="text-vera-cyan text-xl font-bold tracking-[0.15em] mt-0.5 vera-glow leading-none">
						V E R A
					</div>
				</div>
				{children}
			</div>
			<div className="flex items-center gap-6">
				{actions}
				{status && (
					<div className="text-right">
						<div className={`text-[0.75rem] tracking-[0.15em] ${status.color}`}>
							<span className={status.blink ? 'vera-cursor' : ''}>{status.dot}</span> {status.label}
						</div>
						{counter && (
							<div className="text-[#7070a0] text-[0.65rem] mt-0.5">
								{counter}
							</div>
						)}
					</div>
				)}
			</div>
		</div>
	);
}