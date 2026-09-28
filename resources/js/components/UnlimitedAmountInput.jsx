import { FIELD_INPUT } from '../utils/formFieldStyles.js';

function blurOnWheel(event) { event.target.blur(); }

/** A whole-number field that can be switched to unlimited (null), shown as ∞. */
export default function UnlimitedAmountInput({ value, onChange, allowUnlimited, min = 0, ariaLabel }) {
	const unlimited = value === null;
	return (
		<div className="flex items-stretch">
			<input
				type="number"
				min={min}
				value={unlimited ? '' : value}
				placeholder={unlimited ? '∞' : ''}
				disabled={unlimited}
				aria-label={ariaLabel}
				onWheel={blurOnWheel}
				onChange={(event) => onChange(Math.max(min, Math.floor(Number(event.target.value) || 0)))}
				className={`${FIELD_INPUT} ${unlimited ? 'placeholder:text-accent placeholder:text-lg opacity-80' : ''}`}
			/>
			{allowUnlimited && (
				<button
					type="button"
					onClick={() => onChange(unlimited ? min : null)}
					title={unlimited ? 'Set an amount' : 'Unlimited'}
					aria-pressed={unlimited}
					className={`px-3 border border-l-0 text-base transition-colors cursor-pointer ${unlimited ? 'border-accent/60 bg-accent/10 text-accent' : 'border-line-1 text-fg-3 hover:text-fg-1'}`}
				>
					∞
				</button>
			)}
		</div>
	);
}
