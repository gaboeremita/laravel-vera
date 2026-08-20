import { useState } from 'react';
import { useNavigate, useOutletContext } from 'react-router-dom';
import { AnimatePresence } from 'framer-motion';
import Header from '../components/Header.jsx';
import VoiceProviderAccordion from '../components/VoiceProviderAccordion.jsx';
import ConfirmationModal from '../components/common/ConfirmationModal.jsx';
import useVoiceProviders from '../hooks/useVoiceProviders.js';
import { getAssistantMenuItems } from '../utils/assistantMenu.jsx';

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
		deleteTarget,
		setDeleteTarget,
		addProvider,
		updateProvider,
		saveProvider,
		deleteProvider,
		addModel,
		updateModel,
		saveModel,
		deleteModel,
	} = useVoiceProviders(addToast, assistantId);

	if (isLoading) {
		return (
			<>
				<Header settingsPath={`/assistants/${assistantId}/settings`}
					menuItems={getAssistantMenuItems(assistantId)}
					status={{ label: 'LOADING', color: 'text-warning', dot: '●', blink: true }}
					onBack={() => navigate(-1)}
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
					menuItems={getAssistantMenuItems(assistantId)}
				status={{ label: 'WAITING', color: 'text-info', dot: '●', blink: false }}
				counter={`PROVIDERS: ${providers.length}`}
				onBack={() => navigate(-1)}
			>
				<span className="text-fg-2 text-sm tracking-[0.05em]">Voice</span>
			</Header>

			<div className="flex-1 overflow-y-auto p-5 custom-scrollbar space-y-4">
				<AnimatePresence initial={false}>
					{providers.map((provider, pi) => {
						const key = provider.id ?? provider.uid;
						return (
							<VoiceProviderAccordion
								key={key}
								provider={provider}
								collapsed={collapsedProviders[key] ?? true}
								onToggle={() =>
									setCollapsedProviders((prev) => ({ ...prev, [key]: !(prev[key] ?? true) }))
								}
								activeModelId={activeModelId}
								activeVoice={activeVoice}
								onChooseVoice={chooseVoice}
								onDeactivateModel={deactivateModel}
								onUpdate={(field, value) => updateProvider(pi, field, value)}
								onSave={() => saveProvider(pi)}
								onDelete={() => setDeleteTarget({ type: 'provider', providerIndex: pi })}
								onAddModel={() => addModel(pi)}
								onUpdateModel={(mi, field, value) => updateModel(pi, mi, field, value)}
								onSaveModel={(mi) => saveModel(pi, mi)}
								onDeleteModel={(mi) => setDeleteTarget({ type: 'model', providerIndex: pi, modelIndex: mi })}
								addToast={addToast}
							/>
						);
					})}
				</AnimatePresence>

				{providers.length === 0 && (
					<div className="text-fg-3 text-sm text-center py-8">
						No voice providers configured.
					</div>
				)}
			</div>

			<div className="px-5 py-3 border-t border-line-1 shrink-0">
				<button
					onClick={addProvider}
					className="w-full text-[0.75rem] tracking-[0.1em] py-3 transition-colors border border-dashed border-line-1 text-success cursor-pointer hover:border-success/50 hover:bg-bg-1"
				>
					+ ADD PROVIDER
				</button>
			</div>

			{deleteTarget && (
				<ConfirmationModal
					title={`Delete ${deleteTarget.type}`}
					message={
						deleteTarget.type === 'provider'
							? `Delete provider "${providers[deleteTarget.providerIndex]?.name || 'Untitled'}"? This will also delete all its models.`
							: `Delete model "${providers[deleteTarget?.providerIndex]?.models[deleteTarget?.modelIndex]?.name || 'Untitled'}"?`
					}
					options={[
						{ label: 'DELETE', value: 'confirm', destructive: true },
						{ label: 'CANCEL', value: 'cancel', cancel: true },
					]}
					onSelect={(value) => {
						if (value === 'confirm') {
							deleteTarget.type === 'provider' ? deleteProvider() : deleteModel();
						} else {
							setDeleteTarget(null);
						}
					}}
				/>
			)}
		</>
	);
}
