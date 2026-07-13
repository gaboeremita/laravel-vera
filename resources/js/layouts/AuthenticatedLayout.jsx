import { useState, useEffect, useCallback } from 'react';
import { Outlet, Navigate } from 'react-router-dom';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import { useEmotions } from '../hooks/useEmotions.js';
import { useToast } from '../hooks/useToast.js';
import Portrait from '../components/Portrait.jsx';
import Scanlines from '../components/Scanlines.jsx';
import BootSequence from '../components/BootSequence.jsx';
import ToastContainer from '../components/ToastContainer.jsx';

export default function AuthenticatedLayout() {
	const [authState, setAuthState] = useState('checking');
	const [booted, setBooted] = useState(() => {
		return sessionStorage.getItem('vera-booted') === 'true';
	});
	const [currentEmotion, setCurrentEmotion] = useState('default');
	const [activeAssistantId, setActiveAssistantId] = useState(null);

	const { emotionNames, fetchEmotions, getImageUrl, getVideoUrl, unlocked } = useEmotions();
	const { toasts, addToast, removeToast } = useToast();

	useEffect(() => {
		api.get(route('user.show'))
			.then((res) => {
				if (res.ok) {
					setAuthState('authenticated');
				} else {
					setAuthState('unauthenticated');
				}
			})
			.catch(() => setAuthState('unauthenticated'));
	}, []);

	const bootComplete = useCallback(() => {
		setBooted(true);
		sessionStorage.setItem('vera-booted', 'true');
	}, []);

	if (authState === 'checking') return null;
	if (authState === 'unauthenticated') return <Navigate to="/login" replace />;

	return (
		<div className="w-full h-screen bg-bg-0  flex relative overflow-hidden">
			<Scanlines />
			<div className="absolute inset-0 pointer-events-none z-[11] viewport-ambient" />

			<div className="w-[35%] min-w-50 max-w-400 shrink-0 border-r border-line-1 relative z-5">
				<Portrait
					emotion={currentEmotion}
					authenticated={true}
					hasAssistant={!!activeAssistantId}
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
						activeAssistantId,
						setActiveAssistantId,
					}} />
				)}
			</div>

			<ToastContainer toasts={toasts} onDismiss={removeToast} />
		</div>
	);
}