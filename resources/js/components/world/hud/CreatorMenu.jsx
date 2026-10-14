import { useEffect, useRef, useState } from 'react';
import { HudSwitch } from './HudControls.jsx';
import CreatorLog from './CreatorLog.jsx';
import CreatorGoToTab from './CreatorGoToTab.jsx';
import CreatorResidentsTab from './CreatorResidentsTab.jsx';
import CreatorQuestsTab from './CreatorQuestsTab.jsx';
import CreatorItemsTab from './CreatorItemsTab.jsx';
import CreatorWorldTab from './CreatorWorldTab.jsx';

const TABS = [
	{ id: 'goto', label: 'Go to', Component: CreatorGoToTab },
	{ id: 'residents', label: 'Residents', Component: CreatorResidentsTab },
	{ id: 'quests', label: 'Quests', Component: CreatorQuestsTab },
	{ id: 'items', label: 'Items', Component: CreatorItemsTab },
	{ id: 'world', label: 'World', Component: CreatorWorldTab },
];
const PASSWORD_CELLS = 8;
const LIGHT_STEP_MS = 30;
const WRONG_MS = 500;

function LockPanel({ onUnlock, onCancel }) {
	const [password, setPassword] = useState('');
	const [error, setError] = useState(null);
	const [state, setState] = useState('typing');
	const [litCount, setLitCount] = useState(0);
	const inputRef = useRef(null);

	useEffect(() => {
		inputRef.current?.focus();
	}, []);

	useEffect(() => {
		if (state !== 'lighting') return undefined;
		const total = Math.max(PASSWORD_CELLS, password.length);
		if (litCount >= total) return undefined;
		const timer = setTimeout(() => setLitCount((current) => current + 1), LIGHT_STEP_MS);
		return () => clearTimeout(timer);
	}, [state, litCount, password.length]);

	const submit = async (event) => {
		event.preventDefault();
		if (state !== 'typing' || password === '') return;
		setState('checking');
		const result = await onUnlock(password);
		if (result.ok) {
			setLitCount(0);
			setState('lighting');
			return;
		}
		setError(result.message);
		setState('wrong');
		setTimeout(() => {
			setState('typing');
			setPassword('');
			inputRef.current?.focus();
		}, WRONG_MS);
	};

	const cellCount = Math.max(PASSWORD_CELLS, password.length + 1);
	const cellState = (index) => {
		if (state === 'wrong' && index < password.length) return 'wrong';
		if (state === 'lighting' && index < litCount) return 'lit';
		return index < password.length ? 'filled' : 'empty';
	};

	return (
		<form onSubmit={submit} onClick={() => inputRef.current?.focus()} className={`world-hud-panel hud-enter-scale flex w-[min(30rem,94%)] flex-col items-center gap-4 px-8 py-10 ${state === 'wrong' ? 'hud-shake' : ''}`}>
			<span className="world-hud-label text-accent">Restricted layer</span>
			<h2 className="hud-title world-hud-glow world-pause-title hud-enter-wipe text-4xl">Creator</h2>
			<span className="hud-rule hud-rule-center hud-enter-rule w-64 origin-center" />
			<p className="text-center text-fg-2 text-sm leading-relaxed">Enter your creator password to take control of this session.</p>
			<input ref={inputRef} type="password" value={password} onChange={(event) => { setError(null); setPassword(event.target.value); }} disabled={state !== 'typing'} onKeyDown={(event) => { if (event.key === 'Escape') onCancel(); }} aria-label="Creator password" autoComplete="off" className="absolute h-px w-px opacity-0" />
			<div className="flex flex-wrap justify-center gap-1.5" aria-hidden="true">
				{Array.from({ length: cellCount }, (_, index) => (
					<span key={index} className="hud-cell" data-state={cellState(index)}>
						{index < password.length ? '•' : index === password.length && state === 'typing' ? <span className="hud-caret" /> : null}
					</span>
				))}
			</div>
			{error && <p className="hud-enter-type text-danger text-xs">{error}</p>}
			<div className="mt-1 flex gap-2">
				<button type="submit" disabled={state !== 'typing'} className={`hud-button hud-primary ${state === 'checking' ? 'hud-busy' : ''}`}>Authenticate</button>
				<button type="button" onClick={onCancel} className="hud-button">Cancel</button>
			</div>
			<span className="world-hud-label"><span className="world-hud-key">T</span> to close</span>
		</form>
	);
}

/**
 * The Creator menu over the paused world: a lock screen until the session is
 * unlocked, then the tabs of creator powers with the log of changes made.
 */
export default function CreatorMenu({ creator, sessionTitle, context, keyHandlerRef }) {
	const { open, close, unlocked, unlock, overview, log } = creator;
	const [shown, setShown] = useState(open);
	const [tabIndex, setTabIndex] = useState(0);
	const [itemsHolder, setItemsHolder] = useState(null);
	const [unlockedView, setUnlockedView] = useState(unlocked);
	if (open && !shown) setShown(true);
	if (!open && unlockedView !== unlocked) setUnlockedView(unlocked);
	const leaving = shown && !open;

	const arrowHandler = useRef(null);
	useEffect(() => {
		keyHandlerRef.current = (event) => {
			if (event.code === 'Escape') {
				close();
				return true;
			}
			const digit = /^Digit([1-5])$/.exec(event.code);
			if (digit && unlockedView) {
				setTabIndex(Number(digit[1]) - 1);
				return true;
			}
			if ((event.code === 'ArrowUp' || event.code === 'ArrowDown') && arrowHandler.current) {
				arrowHandler.current(event.code === 'ArrowUp' ? -1 : 1);
				return true;
			}
			return false;
		};
		return () => { keyHandlerRef.current = null; };
	}, [keyHandlerRef, close, unlockedView]);

	if (!shown) return null;

	const handleUnlock = async (password) => {
		const result = await unlock(password);
		if (result.ok) setTimeout(() => setUnlockedView(true), 600);
		return result;
	};
	const { Component } = TABS[tabIndex];

	return (
		<div
			className={`world-pause-backdrop absolute inset-0 z-40 flex items-center justify-center p-8 ${leaving ? 'hud-exit-fade' : 'hud-enter-fade'}`}
			onAnimationEnd={(event) => { if (leaving && event.target === event.currentTarget) setShown(false); }}
			onClick={close}
		>
			<div className="contents" onClick={(event) => event.stopPropagation()}>
				{!unlockedView ? (
					<LockPanel onUnlock={handleUnlock} onCancel={close} />
				) : (
					<div className="world-hud-panel hud-enter-scale flex max-h-[88%] w-[min(64rem,96%)] flex-col px-6 py-5">
						<div className="flex items-end justify-between gap-4">
							<div className="flex flex-col gap-1">
								<span className="world-hud-label text-accent">Creator menu · session “{sessionTitle}”</span>
								<h2 className="hud-title world-hud-glow hud-enter-wipe text-3xl">Creator</h2>
							</div>
							<div className="flex items-center gap-5">
								<span className="world-hud-label">World paused</span>
								<span className="world-hud-label flex items-center gap-1.5">
									<span className="world-hud-key">1–5</span> tabs <span className="world-hud-key">↑↓</span> select <span className="world-hud-key">T</span> close
								</span>
							</div>
						</div>
						<span className="hud-rule hud-enter-rule my-4 w-full origin-left" />

						<div className="grid min-h-0 flex-1 grid-cols-[11rem_1fr] gap-5">
							<nav className="relative flex flex-col gap-1 border-r border-line-1 pr-4">
								<span
									className="pointer-events-none absolute left-0 right-4 border border-accent/60 bg-accent/10 transition-transform duration-[250ms] ease-[cubic-bezier(0.2,0.8,0.2,1)]"
									style={{ height: '2.4rem', transform: `translateY(${tabIndex * 2.65}rem)`, borderRadius: 'calc(var(--hud-radius) / 2)' }}
								/>
								{TABS.map((tab, index) => (
									<button
										key={tab.id}
										type="button"
										onClick={() => setTabIndex(index)}
										className={`world-hud-label relative flex h-[2.4rem] items-center gap-3 border border-transparent px-3 text-left transition-colors duration-150 hover:text-fg-1 ${index === tabIndex ? 'text-fg-1' : ''}`}
										style={{ borderRadius: 'calc(var(--hud-radius) / 2)' }}
									>
										<span className={`w-3 text-[0.6rem] ${index === tabIndex ? 'text-accent' : 'text-fg-3'}`}>{index + 1}</span>
										{tab.label}
									</button>
								))}
								<div className="flex-1" />
								<div className="flex flex-col gap-2.5 border-t border-line-1 pt-3">
									<HudSwitch label="Fly" checked={context.flying} onChange={context.setFlying} />
									<HudSwitch label="Fast walk" checked={context.fastWalk} onChange={context.setFastWalk} />
									<HudSwitch label="Show zones" checked={context.showZones} onChange={context.setShowZones} />
								</div>
							</nav>

							<div className="custom-scrollbar flex min-h-0 flex-col gap-4 overflow-y-auto pr-1">
								<div key={TABS[tabIndex].id} className="hud-enter-fade min-h-0">
									{overview ? <Component overview={overview} creator={creator} context={context} arrowHandler={arrowHandler} itemsHolder={itemsHolder} openItems={(holder) => { setItemsHolder(holder); setTabIndex(3); }} /> : <p className="world-hud-label py-10 text-center text-fg-3">Loading…</p>}
								</div>
								<CreatorLog entries={log} />
							</div>
						</div>
					</div>
				)}
			</div>
		</div>
	);
}

