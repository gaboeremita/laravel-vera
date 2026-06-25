import { useState, useEffect, useCallback } from 'react';
import { Outlet, Navigate } from 'react-router-dom';
import { api } from '../utils/api.js';
import { useEmotions } from '../hooks/useEmotions.js';
import { useToast } from '../hooks/useToast.js';
import Portrait from '../components/Portrait.jsx';
import Scanlines from '../components/Scanlines.jsx';
import BootSequence from '../components/BootSequence.jsx';
import ToastContainer from '../components/ToastContainer.jsx';

export default function AuthenticatedLayout() {
	const [authState, setAuthState] = useState('checking');
	const [booted, setBooted] = useState(false);
	const [currentEmotion, setCurrentEmotion] = useState('neutral');
	const [conversations, setConversations] = useState([]);

	const { emotionNames, fetchEmotions, getImageUrl, getVideoUrl, unlocked } = useEmotions();
	const { toasts, addToast, removeToast } = useToast();

	useEffect(() => {
		api.get('/api/user')
			.then((res) => {
				if (res.ok) {
					setAuthState('authenticated');
					fetchEmotions();
				} else {
					setAuthState('unauthenticated');
				}
			})
			.catch(() => setAuthState('unauthenticated'));
	}, []);

	const fetchConversations = async () => {
		try {
			const res = await api.get('/api/conversations');
			const data = await res.json();
			setConversations(data);
		} catch {
			addToast('Failed to load conversations', 'error');
		}
	};

	const bootComplete = useCallback(() => {
		setBooted(true);
		fetchConversations();
	}, []);

	if (authState === 'checking') return null;
	if (authState === 'unauthenticated') return <Navigate to="/login" replace />;

	return (
		<div className="w-full h-screen bg-[#0a0a0f] font-mono flex relative overflow-hidden">
			<Scanlines />
			<div className="absolute inset-0 pointer-events-none z-[11] vera-vignette" />

			<div className="w-[35%] min-w-50 max-w-400 shrink-0 border-r border-[#1a1a2e] relative z-5">
				<Portrait
					emotion={currentEmotion}
					authenticated={true}
					getImageUrl={getImageUrl}
					getVideoUrl={getVideoUrl}
				/>
			</div>

			<div className="flex-1 flex flex-col relative z-5 min-w-0">
				{!booted ? (
					<BootSequence onComplete={bootComplete} />
				) : (
					<Outlet context={{
						currentEmotion,
						setCurrentEmotion,
						emotionNames,
						fetchEmotions,
						unlocked,
						addToast,
						conversations,
						setConversations,
						fetchConversations,
					}} />
				)}
			</div>

			<ToastContainer toasts={toasts} onDismiss={removeToast} />
		</div>
	);
}