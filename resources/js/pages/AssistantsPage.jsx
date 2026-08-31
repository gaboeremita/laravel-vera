import { useState, useEffect } from 'react';
import { useNavigate, useOutletContext } from 'react-router-dom';
import Header from '../components/Header.jsx';
import ConfirmationModal from '../components/common/ConfirmationModal.jsx';
import useAssistants from '../hooks/useAssistants.js';
import veraAvatar from '../../images/vera-avatar.png';

export default function AssistantsPage() {
	const navigate = useNavigate();
	const { addToast } = useOutletContext();
	const { assistants, isLoading, deleteAssistant } = useAssistants(addToast);
	const [deleteTarget, setDeleteTarget] = useState(null);
	const [now, setNow] = useState(() => Date.now());

	useEffect(() => {
		const interval = setInterval(() => setNow(Date.now()), 60000);
		return () => clearInterval(interval);
	}, []);

	const handleConfirmDelete = async () => {
		if (deleteTarget) {
			await deleteAssistant(deleteTarget.id);
			setDeleteTarget(null);
		}
	};

	const formatTimeAgo = (dateString) => {
		if (!dateString) return 'No activity';
		const diff = now - new Date(dateString).getTime();
		const minutes = Math.floor(diff / 60000);
		if (minutes < 1) return 'Just now';
		if (minutes < 60) return `${minutes} min ago`;
		const hours = Math.floor(minutes / 60);
		if (hours < 24) return `${hours}h ago`;
		const days = Math.floor(hours / 24);
		return `${days}d ago`;
	};

	return (
		<>
			<Header
				hideSettings
				onBack={() => navigate('/')}
				status={{
					label: isLoading ? 'LOADING' : 'WAITING',
					color: isLoading ? 'text-warning' : 'text-info',
					dot: '●',
					blink: isLoading,
				}}
				counter={!isLoading ? `ASSISTANTS: ${assistants.length}` : null}
			>
				<span className="text-fg-2 text-lg tracking-[0.05em]">Assistants</span>
			</Header>

			<div className="flex-1 overflow-y-auto p-5 custom-scrollbar">
				{isLoading ? (
					<span className="text-fg-3 text-sm cursor-effect">Loading...</span>
				) : (
					<>
					<div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
						{/* Assistant cards */}
						{assistants.map((assistant) => (
							<div
								key={assistant.id}
								className="border border-line-1 bg-bg-1 flex flex-col overflow-hidden rounded-md"
							>
								{/* Card header with avatar and info */}
								<div className="flex gap-4 p-4">
									{/* Avatar */}
									<div className="w-24 h-24 shrink-0 border border-line-1 overflow-hidden">
										<img
											src={assistant.image_url || veraAvatar}
											alt={assistant.name}
											onError={(e) => { e.currentTarget.src = veraAvatar; }}
											className="w-full h-full object-cover object-top"
										/>
									</div>

									{/* Info */}
									<div className="flex-1 min-w-0">
										<h3 className="text-accent text-sm tracking-[0.05em] font-medium truncate">
											{assistant.name}
										</h3>
										<p className="text-fg-3 text-xs mt-1 line-clamp-3">
											{assistant.description || 'No description'}
										</p>
									</div>
								</div>

								{/* Stats */}
								<div className="px-4 pb-3 flex gap-4 text-fg-3 text-[0.65rem] tracking-[0.05em]">
									<span>⏱ {formatTimeAgo(assistant.last_activity)}</span>
									<span>💬 {assistant.conversations_count} conversations</span>
								</div>

								{/* Actions */}
								<div className="border-t border-line-1 px-4 py-3 flex items-center justify-between mt-auto">
									<div className="flex gap-2">
										<button
											onClick={() => navigate(`/assistants/${assistant.id}/edit`)}
											className="text-fg-3 text-[0.7rem] tracking-[0.1em] cursor-pointer hover:text-fg-1 transition-colors"
										>
											EDIT
										</button>
										<button
											onClick={() => setDeleteTarget(assistant)}
											className="text-danger text-[0.7rem] tracking-[0.1em] cursor-pointer hover:text-danger transition-colors"
										>
											DELETE
										</button>
									</div>
									<button
										onClick={() => navigate(`/assistants/${assistant.id}/conversations`)}
										className="button-primary text-[0.7rem]"
									>
										SELECT
									</button>
								</div>
							</div>
						))}

						{/* Add assistant card */}
						<button
							onClick={() => navigate('/assistants/create')}
							className="border border-dashed border-line-1 flex items-center justify-center min-h-48 text-success text-[0.75rem] tracking-[0.1em] cursor-pointer hover:border-success/50 hover:bg-bg-1 transition-colors"
						>
							+ ADD ASSISTANT
						</button>
					</div>
					</>
				)}
			</div>

			{deleteTarget && (
				<ConfirmationModal
					title="Delete assistant"
					message={`Delete "${deleteTarget.name}"? All conversations and data will be lost.`}
					options={[
						{ label: 'DELETE', value: 'confirm', destructive: true },
						{ label: 'CANCEL', value: 'cancel', cancel: true },
					]}
					onSelect={(value) => {
						if (value === 'confirm') handleConfirmDelete();
						else setDeleteTarget(null);
					}}
				/>
			)}
		</>
	);
}
