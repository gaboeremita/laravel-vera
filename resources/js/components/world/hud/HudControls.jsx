import { useEffect, useRef, useState } from 'react';

const SETTLE_MS = 450;
const METER_CELLS = 20;
const METER_LIMIT = 10;
const METER_STEP_MS = 20;
const COUNTER_MS = 400;

/**
 * A HUD button whose click may return a promise: it shows a busy sweep while
 * the promise runs, then a success ring or a failure shake.
 */
export function HudButton({ variant = 'default', onClick, disabled = false, className = '', children, ...props }) {
	const [state, setState] = useState('idle');
	const timer = useRef(null);
	useEffect(() => () => clearTimeout(timer.current), []);

	const settle = (outcome) => {
		setState(outcome);
		clearTimeout(timer.current);
		timer.current = setTimeout(() => setState('idle'), SETTLE_MS);
	};
	const handleClick = async (event) => {
		if (state === 'busy') return;
		const result = onClick?.(event);
		if (!(result instanceof Promise)) return;
		setState('busy');
		try {
			settle((await result) === false ? 'fail' : 'success');
		} catch (error) {
			console.error('[HudButton] action failed', error);
			settle('fail');
		}
	};

	const variantClass = variant === 'primary' ? 'hud-primary' : variant === 'danger' ? 'hud-danger' : '';
	const stateClass = { busy: 'hud-busy', success: 'hud-success', fail: 'hud-fail' }[state] ?? '';
	return (
		<button type="button" disabled={disabled} onClick={handleClick} className={`hud-button ${variantClass} ${stateClass} ${className}`} {...props}>
			{children}
		</button>
	);
}

export function HudSwitch({ checked, onChange, label }) {
	return (
		<label className="world-hud-label flex cursor-pointer items-center justify-between gap-3">
			<span>{label}</span>
			<button type="button" role="switch" aria-checked={checked} aria-label={label} onClick={() => onChange(!checked)} className="hud-switch" />
		</label>
	);
}

/** A state chip that flips when its value changes; `frost` adds the frost pulse to the flip. */
export function HudChip({ value, children, className = '', frost = false }) {
	const [shown, setShown] = useState(value);
	const [flipping, setFlipping] = useState(false);
	if (value !== shown) {
		setShown(value);
		setFlipping(true);
	}
	return (
		<span onAnimationEnd={() => setFlipping(false)} className={`hud-chip ${flipping ? (frost ? 'hud-chip-frost' : 'hud-chip-flip') : ''} ${className}`}>
			{children}
		</span>
	);
}

function cellValue(index) {
	return index < METER_LIMIT ? index - METER_LIMIT : index - METER_LIMIT + 1;
}

function cellState(cell, value, preview) {
	if (value > 0 && cell > 0 && cell <= value) return 'positive';
	if (value < 0 && cell < 0 && cell >= value) return 'negative';
	if (preview !== null && ((preview > 0 && cell > 0 && cell <= preview) || (preview < 0 && cell < 0 && cell >= preview))) return 'preview';
	return 'empty';
}

function signed(value) {
	return value > 0 ? `+${value}` : value < 0 ? `−${Math.abs(value)}` : '0';
}

/**
 * Twenty cells centred on 0 for a value from -10 to 10. Hovering previews a
 * value; clicking a cell sets it, and the cells fill one by one toward it.
 */
export function HudMeter({ value, onChange, label }) {
	const [pending, setPending] = useState(null);
	const [lastValue, setLastValue] = useState(value);
	if (value !== lastValue) {
		setLastValue(value);
		setPending(null);
	}
	const target = Math.round(pending ?? value);
	const [shown, setShown] = useState(target);
	const [preview, setPreview] = useState(null);
	const choose = (cell) => {
		const next = cell === target ? 0 : cell;
		setPending(next);
		void Promise.resolve(onChange(next)).then((result) => {
			if (result === false) setPending(null);
		});
	};

	useEffect(() => {
		if (shown === target) return undefined;
		const timer = setTimeout(() => setShown((current) => current + Math.sign(target - current)), METER_STEP_MS);
		return () => clearTimeout(timer);
	}, [shown, target]);

	return (
		<div className="grid grid-cols-[5.5rem_1fr_2.2rem] items-center gap-3 text-xs">
			<span className="world-hud-label">{label}</span>
			<div className="hud-meter" role="slider" aria-label={label} aria-valuemin={-METER_LIMIT} aria-valuemax={METER_LIMIT} aria-valuenow={target} onMouseLeave={() => setPreview(null)}>
				{Array.from({ length: METER_CELLS }, (_, index) => {
					const cell = cellValue(index);
					return <span key={cell} data-state={cellState(cell, shown, preview)} onMouseEnter={() => setPreview(cell)} onClick={() => choose(cell)} />;
				})}
			</div>
			<span className={`text-right tabular-nums ${shown < 0 ? 'text-danger' : 'text-accent'}`}>{signed(shown)}</span>
		</div>
	);
}

/** A number that counts to each new value, flashing up in the success color and down in danger. */
export function HudCounter({ value, className = '', suffix = '' }) {
	const [shown, setShown] = useState(value);
	const [lastValue, setLastValue] = useState(value);
	const [direction, setDirection] = useState(null);
	if (value !== lastValue) {
		setLastValue(value);
		setDirection(value === null || lastValue === null ? null : value > lastValue ? 'up' : 'down');
	}
	const from = useRef(value);

	useEffect(() => {
		const origin = from.current;
		from.current = value;
		if (value === origin) return undefined;
		if (value === null || origin === null) {
			const frame = requestAnimationFrame(() => setShown(value));
			return () => cancelAnimationFrame(frame);
		}
		const start = performance.now();
		let frame = requestAnimationFrame(function step(now) {
			const progress = Math.min(1, (now - start) / COUNTER_MS);
			setShown(Math.round(origin + (value - origin) * progress));
			if (progress < 1) frame = requestAnimationFrame(step);
		});
		const timer = setTimeout(() => setDirection(null), COUNTER_MS + 200);
		return () => {
			cancelAnimationFrame(frame);
			clearTimeout(timer);
		};
	}, [value]);

	const tone = direction === 'up' ? 'text-success' : direction === 'down' ? 'text-danger' : '';
	return <span className={`tabular-nums transition-colors duration-300 ${tone} ${className}`}>{shown === null ? '∞' : shown}{suffix}</span>;
}
