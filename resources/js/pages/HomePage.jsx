import { useNavigate } from 'react-router-dom';
import Header from '../components/Header.jsx';

export default function HomePage() {
	const navigate = useNavigate();

	return (
		<>
			<Header hideSettings>
				<span className="text-fg-2 text-lg tracking-[0.05em]">Home</span>
			</Header>

			<div className="flex-1 overflow-y-auto p-5 custom-scrollbar">
				<div className="mb-6">
					<h1 className="text-fg-1 text-lg font-semibold tracking-[0.02em]">Welcome to VERA</h1>
					<p className="text-fg-3 text-sm mt-1">Pick up with an assistant, step into a world, or manage the NPCs who live there.</p>
				</div>

				<div className="grid grid-cols-1 gap-4 md:grid-cols-3">
					<button type="button" onClick={() => navigate('/assistants')} className="border border-line-1 bg-bg-1 p-5 text-left transition-colors hover:border-accent/50">
						<p className="text-accent text-sm tracking-[0.08em]">ASSISTANTS</p>
						<p className="mt-2 text-fg-3 text-xs">Create and manage AI companions with configurable prompts, providers, and portraits.</p>
					</button>
					<button type="button" onClick={() => navigate('/worlds')} className="border border-line-1 bg-bg-1 p-5 text-left transition-colors hover:border-accent/50">
						<p className="text-accent text-sm tracking-[0.08em]">WORLDS</p>
						<p className="mt-2 text-fg-3 text-xs">Create and configure explorable 3D spaces, residents, context prompts, and room environments.</p>
					</button>
					<button type="button" onClick={() => navigate('/npcs')} className="border border-line-1 bg-bg-1 p-5 text-left transition-colors hover:border-accent/50">
						<p className="text-accent text-sm tracking-[0.08em]">NPCS</p>
						<p className="mt-2 text-fg-3 text-xs">Manage assistant-backed NPCs with the same model, animation, prompt, and archive tools.</p>
					</button>
				</div>
			</div>
		</>
	);
}
