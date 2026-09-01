import { useState } from 'react';
import { useNavigate, useOutletContext } from 'react-router-dom';
import Header from '../components/Header.jsx';
import WorldCard from '../components/WorldCard.jsx';
import ConfirmationModal from '../components/common/ConfirmationModal.jsx';
import useWorlds from '../hooks/useWorlds.js';

export default function WorldsPage() {
	const navigate = useNavigate();
	const { addToast } = useOutletContext();
	const { worlds, isLoading, deleteWorld } = useWorlds(addToast);
	const [pendingDeleteId, setPendingDeleteId] = useState(null);
	const pendingDeleteWorld = worlds.find((w) => w.id === pendingDeleteId);

	return (
		<>
			<Header hideSettings onBack={() => navigate('/')} status={{ label: isLoading ? 'LOADING' : 'WAITING', color: isLoading ? 'text-warning' : 'text-info', dot: '●', blink: isLoading }} counter={!isLoading ? `WORLDS: ${worlds.length}` : null}>
				<span className="text-fg-2 text-lg tracking-[0.05em]">Worlds</span>
			</Header>
			<div className="relative flex-1 overflow-y-auto p-5 custom-scrollbar">
				<div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
					{worlds.map((world) => <WorldCard key={world.id} world={world} onEdit={() => navigate(`/worlds/${world.id}/edit`)} onEnter={() => navigate(`/worlds/${world.id}/sessions`)} onDelete={() => setPendingDeleteId(world.id)} />)}
					<button type="button" onClick={() => navigate('/worlds/create')} className="border border-dashed border-line-1 flex items-center justify-center min-h-48 text-success text-[0.75rem] tracking-[0.1em] cursor-pointer hover:border-success/50 hover:bg-bg-1 transition-colors">+ ADD WORLD</button>
				</div>
				{pendingDeleteWorld && (
					<ConfirmationModal
						title="Delete world"
						message={`Delete "${pendingDeleteWorld.name}"? Its environment asset, sessions, and resident placements will be removed. Assistants and NPCs will not be affected.`}
						options={[
							{ label: 'DELETE', value: 'confirm', destructive: true },
							{ label: 'CANCEL', value: 'cancel', cancel: true },
						]}
						onSelect={(value) => {
							if (value === 'confirm') deleteWorld(pendingDeleteId);
							setPendingDeleteId(null);
						}}
					/>
				)}
			</div>
		</>
	);
}
