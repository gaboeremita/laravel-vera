import Header from '../components/Header.jsx';
import { useNavigate } from 'react-router-dom';

export default function LorebookPage() {
	const navigate = useNavigate();

	return (
		<>
			<Header
				status={{ label: 'READY', color: 'text-green-400', dot: '●', blink: false }}
				actions={
					<button
						onClick={() => navigate('/conversations')}
						className="bg-indigo-500/15 border border-indigo-400 text-indigo-400 hover:bg-indigo-500/25 text-[0.75rem] tracking-[0.1em] font-mono cursor-pointer transition-colors px-4 py-1.5"
					>
						← CONVERSATIONS
					</button>
				}
			>
				<span className="text-[#7070a0] text-sm tracking-[0.05em]">
					Lorebook
				</span>
			</Header>

			<div className="flex-1 overflow-y-auto p-5">
				<p className="text-[#555568] text-sm">Lorebook coming soon.</p>
			</div>
		</>
	);
}