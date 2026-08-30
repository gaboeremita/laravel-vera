import { useId, useState } from 'react';
import ConfirmationModal from './common/ConfirmationModal.jsx';

export const EXPRESSION_SUGGESTIONS = ['happy', 'sad', 'angry', 'relaxed', 'surprised', 'neutral', 'blink'];

function toPercentDisplay(blendshapes) {
	return blendshapes.map((b) => (b.weight <= 1 ? { ...b, weight: Math.round(b.weight * 100) } : b));
}

export function BlendshapeRows({ blendshapes, onChange, datalistId }) {
	const updateRow = (index, field, value) => {
		if (field === 'weight') {
			const parsed = Number(value);
			value = Number.isFinite(parsed) ? Math.min(100, Math.max(0, parsed)) : 0;
		}
		onChange(blendshapes.map((b, i) => (i === index ? { ...b, [field]: value } : b)));
	};

	const removeRow = (index) => {
		onChange(blendshapes.filter((_, i) => i !== index));
	};

	const addRow = () => {
		onChange([...blendshapes, { expression: '', weight: 100 }]);
	};

	return (
		<div className="space-y-1.5">
			{blendshapes.map((b, i) => (
				<div key={i} className="flex items-center gap-1.5">
					<input
						type="text"
						list={datalistId}
						value={b.expression}
						onChange={(e) => updateRow(i, 'expression', e.target.value)}
						placeholder="expression"
						className="flex-1 min-w-0 bg-bg-0 border border-line-1 text-accent text-xs px-2 py-1 outline-none focus:border-accent/50 transition-colors"
					/>
					<input
						type="number"
						min={0}
						max={100}
						value={b.weight}
						onChange={(e) => updateRow(i, 'weight', e.target.value)}
						className="w-16 bg-bg-0 border border-line-1 text-accent text-xs px-2 py-1 outline-none focus:border-accent/50 transition-colors"
					/>
					<span className="text-fg-3 text-xs">%</span>
					<button
						onClick={() => removeRow(i)}
						className="text-danger text-xs cursor-pointer hover:text-danger transition-colors shrink-0"
					>
						✕
					</button>
				</div>
			))}
			<button
				onClick={addRow}
				className="text-success text-[0.65rem] tracking-[0.1em] cursor-pointer hover:text-success/80 transition-colors"
			>
				+ ADD EXPRESSION
			</button>
		</div>
	);
}

function EmotionRow({ emotion, onSave, onDelete, canDelete, datalistId }) {
	const [draft, setDraft] = useState(() => toPercentDisplay(emotion.vrm_blendshapes || []));
	const [syncedEmotion, setSyncedEmotion] = useState(emotion);
	const [isSaving, setIsSaving] = useState(false);

	if (syncedEmotion !== emotion) {
		setSyncedEmotion(emotion);
		setDraft(toPercentDisplay(emotion.vrm_blendshapes || []));
	}

	const handleSave = async () => {
		setIsSaving(true);
		try {
			await onSave(draft.filter((b) => b.expression.trim()));
		} finally {
			setIsSaving(false);
		}
	};

	return (
		<div className="border border-line-1 bg-bg-1 p-3 space-y-2">
			<div className="flex items-center justify-between">
				<span className="text-accent text-[0.7rem] tracking-[0.05em]">{emotion.name}</span>
				{canDelete && (
					<button
						onClick={onDelete}
						className="text-danger text-[0.65rem] cursor-pointer hover:text-danger transition-colors"
					>
						✕ DELETE
					</button>
				)}
			</div>
			<BlendshapeRows blendshapes={draft} onChange={setDraft} datalistId={datalistId} />
			<div className="flex justify-end">
				<button
					onClick={handleSave}
					disabled={isSaving}
					className={`px-3 py-1 text-[0.65rem] tracking-[0.1em] transition-colors ${
						isSaving ? 'bg-bg-3 text-fg-3 cursor-default' : 'button-success cursor-pointer'
					}`}
				>
					{isSaving ? 'SAVING...' : 'SAVE'}
				</button>
			</div>
		</div>
	);
}

/**
 * Per-assistant emotion → VRM blendshape mapping editor. Each emotion row
 * holds N {expression, weight%} pairs, saved explicitly rather than on
 * every keystroke.
 *
 * @param {Array} emotions - [{id, name, vrm_blendshapes}]
 * @param {function} onAdd - (name, blendshapes) => void
 * @param {function} onDelete - (emotion) => void
 * @param {function} onUpdateBlendshapes - (emotion, blendshapes) => void
 */
export default function VrmEmotionEditor({ emotions, onAdd, onDelete, onUpdateBlendshapes, label = 'Emotions' }) {
	const datalistId = useId();
	const [isAdding, setIsAdding] = useState(false);
	const [newName, setNewName] = useState('');
	const [newBlendshapes, setNewBlendshapes] = useState([{ expression: '', weight: 100 }]);
	const [deleteTarget, setDeleteTarget] = useState(null);

	const handleAdd = () => {
		const trimmed = newName.trim();
		if (!trimmed) return;

		onAdd(trimmed, newBlendshapes.filter((b) => b.expression.trim()));
		setNewName('');
		setNewBlendshapes([{ expression: '', weight: 100 }]);
		setIsAdding(false);
	};

	const handleCancelAdd = () => {
		setNewName('');
		setNewBlendshapes([{ expression: '', weight: 100 }]);
		setIsAdding(false);
	};

	const handleConfirmDelete = () => {
		if (deleteTarget) {
			onDelete(deleteTarget);
			setDeleteTarget(null);
		}
	};

	return (
		<div className="space-y-3">
			<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block">
				{label} ({emotions.length})
			</label>

			<datalist id={datalistId}>
				{EXPRESSION_SUGGESTIONS.map((name) => (
					<option key={name} value={name} />
				))}
			</datalist>

			<div className="space-y-2">
				{emotions.map((emotion) => (
					<EmotionRow
						key={emotion.id}
						emotion={emotion}
						canDelete={emotion.name !== 'default'}
						onSave={(blendshapes) => onUpdateBlendshapes(emotion, blendshapes)}
						onDelete={() => setDeleteTarget(emotion)}
						datalistId={datalistId}
					/>
				))}

				{!isAdding ? (
					<button
						onClick={() => setIsAdding(true)}
						className="w-full border border-dashed border-line-1 py-2 flex items-center justify-center text-success text-[0.7rem] tracking-[0.1em] cursor-pointer hover:border-success/50 hover:bg-bg-1 transition-colors"
					>
						+ ADD EMOTION
					</button>
				) : (
					<div className="border border-line-1 bg-bg-1 p-3 space-y-2">
						<input
							type="text"
							value={newName}
							onChange={(e) => setNewName(e.target.value)}
							className="w-full bg-bg-0 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
							placeholder="e.g. annoyed"
							autoFocus
						/>
						<BlendshapeRows blendshapes={newBlendshapes} onChange={setNewBlendshapes} datalistId={datalistId} />
						<div className="flex justify-end gap-2">
							<button
								onClick={handleCancelAdd}
								className="px-3 py-1 text-[0.65rem] tracking-[0.1em] border border-line-1 text-fg-3 cursor-pointer hover:text-fg-1 transition-colors"
							>
								CANCEL
							</button>
							<button
								onClick={handleAdd}
								disabled={!newName.trim()}
								className={`px-3 py-1 text-[0.65rem] tracking-[0.1em] transition-colors ${
									newName.trim() ? 'button-success cursor-pointer' : 'bg-bg-3 text-fg-3 cursor-default'
								}`}
							>
								ADD
							</button>
						</div>
					</div>
				)}
			</div>

			{deleteTarget && (
				<ConfirmationModal
					title="Delete emotion"
					message={`Delete emotion "${deleteTarget.name}"?`}
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
		</div>
	);
}
