import { ArrowLeft, Settings } from 'lucide-react';
import { useNavigate } from 'react-router-dom';

export default function Header({ children, status, actions, counter, hideSettings = false, settingsPath = '/settings', onBack }) {
	const navigate = useNavigate();

	return (
		<div className="px-5 py-3 border-b border-line-1 flex justify-between items-end shrink-0">
			<div className="flex items-end gap-4">
				{onBack && (
					<button
						onClick={onBack}
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
				{!hideSettings && (
					<button
						onClick={() => navigate(settingsPath)}
						className="text-fg-3 hover:text-accent transition-colors cursor-pointer"
					>
						<Settings size={16} />
					</button>
				)}
			</div>
		</div>
	);
}