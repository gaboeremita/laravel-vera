import { useEffect, useState } from 'react';
import { useNavigate, useOutletContext } from 'react-router-dom';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import Header from '../components/Header.jsx';
import veraAvatar from '../../images/vera-avatar.png';

export default function NpcsPage() {
	const navigate = useNavigate();
	const { addToast } = useOutletContext();
	const [npcs, setNpcs] = useState([]);
	const [isLoading, setIsLoading] = useState(true);

	useEffect(() => {
		const load = async () => {
			try {
				const response = await api.get(route('npcs.index'));
				if (!response.ok) throw new Error();
				setNpcs(await response.json());
			} catch { addToast('Failed to load NPCs', 'error'); } finally { setIsLoading(false); }
		};
		void load();
	}, [addToast]);

	const remove = async (npc) => {
		if (!globalThis.confirm(`Delete NPC "${npc.name}"? Its resident placements will be removed, but worlds and other assistants will remain.`)) return;
		try {
			const response = await api.delete(route('npcs.destroy', { npc: npc.id }));
			if (!response.ok) throw new Error();
			setNpcs((current) => current.filter(({ id }) => id !== npc.id));
			addToast('NPC deleted', 'success');
		} catch { addToast('Failed to delete NPC', 'error'); }
	};

	return <><Header hideSettings onBack={() => navigate('/')} status={{ label: isLoading ? 'LOADING' : 'WAITING', color: isLoading ? 'text-warning' : 'text-info', dot: '●', blink: isLoading }} counter={!isLoading ? `NPCS: ${npcs.length}` : null}><span className="text-fg-2 text-lg tracking-[0.05em]">NPCs</span></Header><div className="flex-1 overflow-y-auto p-5 custom-scrollbar"><div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">{npcs.map((npc) => <div key={npc.id} className="border border-line-1 bg-bg-1 flex flex-col"><div className="flex gap-4 p-4 flex-1"><div className="w-24 h-24 shrink-0 border border-line-1 overflow-hidden"><img src={npc.image_url || veraAvatar} alt={npc.name} onError={(e) => { e.currentTarget.src = veraAvatar; }} className="w-full h-full object-cover object-top" /></div><div className="flex-1 min-w-0"><p className="text-accent text-sm tracking-[0.05em]">{npc.name}</p><p className="text-fg-3 text-xs mt-2 line-clamp-3">{npc.description || 'No description'}</p></div></div><div className="border-t border-line-1 px-4 py-3 flex items-center gap-3"><button type="button" onClick={() => navigate(`/npcs/${npc.id}/edit`)} className="text-fg-3 text-[0.7rem] tracking-[0.1em] hover:text-fg-1">EDIT</button><button type="button" onClick={() => remove(npc)} className="text-danger text-[0.7rem] tracking-[0.1em] hover:text-danger">DELETE</button></div></div>)}<button type="button" onClick={() => navigate('/npcs/create')} className="border border-dashed border-line-1 flex items-center justify-center min-h-48 text-success text-[0.75rem] tracking-[0.1em] cursor-pointer hover:border-success/50 hover:bg-bg-1 transition-colors">+ ADD NPC</button></div></div></>;
}
