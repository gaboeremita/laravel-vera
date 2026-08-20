import { useNavigate, useOutletContext } from 'react-router-dom';
import { AnimatePresence } from 'framer-motion';
import Header from '../components/Header.jsx';
import ProviderAccordion from '../components/ProviderAccordion.jsx';
import ConfirmationModal from '../components/common/ConfirmationModal.jsx';
import useProviders from '../hooks/useProviders.js';

export default function ProvidersPage() {
	const navigate = useNavigate();
	const { addToast, assistantId } = useOutletContext();

	const {
		providers,
		isLoading,
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
		activeModelId,
		selectModel,
	} = useProviders(addToast, assistantId);

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
					<span className="text-fg-2 text-sm tracking-[0.05em]">AI Providers</span>
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
				<span className="text-fg-2 text-sm tracking-[0.05em]">AI Providers</span>
			</Header>

			<div className="flex-1 overflow-y-auto p-5 custom-scrollbar space-y-4">
				<AnimatePresence initial={false}>
					{providers.map((provider, pi) => (
						<ProviderAccordion
							key={provider.id ?? provider.uid}
							provider={provider}
							activeModelId={activeModelId}
							onUpdate={(field, value) => updateProvider(pi, field, value)}
							onSave={() => saveProvider(pi)}
							onDelete={() => setDeleteTarget({ type: 'provider', providerIndex: pi })}
							onAddModel={() => addModel(pi)}
							onUpdateModel={(mi, field, value) => updateModel(pi, mi, field, value)}
							onSaveModel={(mi) => saveModel(pi, mi)}
							onDeleteModel={(mi) => setDeleteTarget({ type: 'model', providerIndex: pi, modelIndex: mi })}
							onSelectModel={(modelId) => selectModel(modelId)}
						/>
					))}
				</AnimatePresence>

				{providers.length === 0 && (
					<div className="text-fg-3 text-sm text-center py-8">
						No providers configured.
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