import Header from '../components/Header.jsx';

export default function LorebookPage() {
	return (
		<>
			<Header>
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