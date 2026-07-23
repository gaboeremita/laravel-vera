import { useState } from 'react';
import { useNavigate, useOutletContext } from 'react-router-dom';
import { AnimatePresence } from 'framer-motion';
import Header from '../components/Header.jsx';
import VoiceProviderAccordion from '../components/VoiceProviderAccordion.jsx';
import useVoiceProviders from '../hooks/useVoiceProviders.js';

export default function VoicePage() {
	const navigate = useNavigate();
	const { addToast, assistantId } = useOutletContext();
	const [collapsedProviders, setCollapsedProviders] = useState({});

	const {
		providers,
		isLoading,
		activeModelId,
		activeVoice,
		chooseVoice,
		deactivateModel,
	} = useVoiceProviders(addToast, assistantId);

	if (isLoading) {
		return (
			<>
				<Header settingsPath={`/assistants/${assistantId}/settings`}
					status={{ label: 'LOADING', color: 'text-warning', dot: '●', blink: true }}
					actions={
						<button onClick={() => navigate(-1)} className="button-primary">
							← PREVIOUS PAGE
						</button>
					}
				>
					<span className="text-fg-2 text-sm tracking-[0.05em]">Voice</span>
				</Header>
				<div className="flex-1 p-5">
					<span className="text-fg-3 text-sm cursor-effect">Loading...</span>
				</div>
			</>
		);
	}

	return (
		<>
			<Header settingsPath={`/assistants/${assistantId}/settings`}
				status={{ label: 'WAITING', color: 'text-info', dot: '●', blink: false }}
				counter={`PROVIDERS: ${providers.length}`}
				actions={
					<button onClick={() => navigate(-1)} className="button-primary">
						← PREVIOUS PAGE
					</button>
				}
			>
				<span className="text-fg-2 text-sm tracking-[0.05em]">Voice</span>
			</Header>

			<div className="flex-1 overflow-y-auto p-5 custom-scrollbar space-y-4">
				<AnimatePresence initial={false}>
					{providers.map((provider) => (
						<VoiceProviderAccordion
							key={provider.id}
							provider={provider}
							collapsed={collapsedProviders[provider.id] ?? true}
							onToggle={() =>
								setCollapsedProviders((prev) => ({ ...prev, [provider.id]: !(prev[provider.id] ?? true) }))
							}
							activeModelId={activeModelId}
							activeVoice={activeVoice}
							onChooseVoice={chooseVoice}
							onDeactivateModel={deactivateModel}
							addToast={addToast}
						/>
					))}
				</AnimatePresence>

				{providers.length === 0 && (
					<div className="text-fg-3 text-sm text-center py-8">
						No voice providers configured.
					</div>
				)}
			</div>
		</>
	);
}
