import { useState } from 'react';

export default function AgentToolCallsTrace({ toolCalls }) {
	const [isOpen, setIsOpen] = useState(false);

	if (!toolCalls || toolCalls.length === 0) return null;

	return (
		<div className="my-3">
			<button
				onClick={() => setIsOpen(!isOpen)}
				className="flex items-center gap-1.5 text-[0.7rem] tracking-[0.1em] uppercase text-fg-3 hover:text-fg-2 transition-colors cursor-pointer"
			>
				<span className={`inline-block transition-transform duration-200 ${isOpen ? 'rotate-90' : ''}`}>▶</span>
				Used {toolCalls.length} tool{toolCalls.length === 1 ? '' : 's'}: {toolCalls.map((call) => call.name).join(', ')}
			</button>
			{isOpen && (
				<div className="mt-2 pl-4 border-l border-line-1 text-[0.75rem] leading-relaxed text-fg-3 space-y-2">
					{toolCalls.map((call, i) => (
						<div key={i}>
							<div className="text-accent">{call.name}</div>
							<div className="whitespace-pre-wrap">
								{call.error ? `Error: ${call.error}` : JSON.stringify(call.result)}
							</div>
						</div>
					))}
				</div>
			)}
		</div>
	);
}
