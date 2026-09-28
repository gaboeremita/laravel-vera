import { useEffect, useRef, useState } from 'react';
import { route } from 'ziggy-js';
import { api } from '../../../utils/api.js';
import ItemThumb from '../../ItemThumb.jsx';
import { formatAmount } from '../inventoryChanges.js';
import { isTypingTarget } from '../keyboardFocus.js';

const COLUMNS = 4;
const TABS = [{ id: 'items', label: 'ITEMS' }, { id: 'history', label: 'CREDIT HISTORY' }];

function timeAgo(isoDate) {
	const seconds = Math.max(0, Math.round((Date.now() - new Date(isoDate).getTime()) / 1000));
	if (seconds < 60) return 'JUST NOW';
	if (seconds < 3600) return `${Math.floor(seconds / 60)} MIN AGO`;
	if (seconds < 86400) return `${Math.floor(seconds / 3600)} H AGO`;
	return `${Math.floor(seconds / 86400)} D AGO`;
}

function CreditHistory({ worldId, sessionId }) {
	const [entries, setEntries] = useState(null);

	useEffect(() => {
		let active = true;
		const load = async () => {
			try {
				const response = await api.get(route('worlds.sessions.credit-history.index', { world: worldId, session: sessionId }));
				if (!response.ok) throw new Error();
				const loaded = await response.json();
				if (active) setEntries(loaded);
			} catch {
				if (active) setEntries([]);
			}
		};
		void load();
		return () => { active = false; };
	}, [worldId, sessionId]);

	if (entries === null) return <p className="world-hud-label py-10 text-center text-fg-3">LOADING...</p>;
	if (entries.length === 0) return <p className="world-hud-label py-10 text-center italic text-fg-3">NO CREDITS HAVE CHANGED HANDS YET</p>;

	return (
		<div className="divide-y divide-accent/10">
			{entries.map((entry, index) => (
				<div key={`${entry.createdAt}-${index}`} className="hud-enter-rise flex items-center gap-4 py-2.5" style={{ animationDelay: `${Math.min(index, 10) * 30}ms` }}>
					<span className={`w-20 shrink-0 text-right font-display text-lg tabular-nums ${entry.direction === 'in' ? 'world-hud-glow' : 'text-fg-2'}`}>
						{entry.direction === 'in' ? '+' : '−'}{entry.amount.toLocaleString()}
					</span>
					<div className="min-w-0 flex-1">
						<p className="truncate text-sm text-fg-1">{entry.direction === 'in' ? 'From' : 'To'} {entry.counterpart}</p>
						<p className="truncate text-xs text-fg-3">{entry.reason}</p>
					</div>
					<span className="world-hud-label shrink-0 text-fg-3">{timeAgo(entry.createdAt)}</span>
				</div>
			))}
		</div>
	);
}

/** The player's inventory: items with examine and use, and the credit history. */
export default function InventoryPanel({ worldId, sessionId, inventory, busyItemId, onExamine, onUse, onClose }) {
	const [tab, setTab] = useState('items');
	const [highlighted, setHighlighted] = useState(0);
	const [attempt, setAttempt] = useState('');
	const [attempting, setAttempting] = useState(false);
	const attemptRef = useRef(null);
	const items = inventory?.items ?? [];
	const index = Math.min(highlighted, Math.max(0, items.length - 1));
	const selected = items[index] ?? null;

	const use = () => {
		if (!selected?.canUse) return;
		onUse(selected, attempt.trim() || null);
		setAttempt('');
		setAttempting(false);
	};

	useEffect(() => {
		const keyDown = (event) => {
			if (event.code === 'KeyP') return;
			if (isTypingTarget(event.target)) {
				if (event.code === 'Escape') { event.preventDefault(); event.target.blur(); setAttempting(false); }
				return;
			}
			event.stopImmediatePropagation();
			if (event.code === 'Tab' || event.code === 'Escape') { event.preventDefault(); if (!event.repeat) onClose(); return; }
			if (tab !== 'items' || items.length === 0) return;
			const moves = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -COLUMNS, ArrowDown: COLUMNS };
			if (moves[event.code] !== undefined) {
				event.preventDefault();
				setHighlighted(Math.min(items.length - 1, Math.max(0, index + moves[event.code])));
			} else if (event.code === 'KeyX' && selected?.canExamine) {
				onExamine(selected);
			} else if ((event.code === 'Enter' || event.code === 'NumpadEnter') && selected?.canUse) {
				event.preventDefault();
				setAttempting(true);
				setTimeout(() => attemptRef.current?.focus(), 0);
			}
		};
		window.addEventListener('keydown', keyDown, true);
		return () => window.removeEventListener('keydown', keyDown, true);
	}, [tab, items.length, index, selected, onClose, onExamine]);

	return (
		<div className="world-pause-backdrop hud-enter-fade absolute inset-0 z-30 flex items-center justify-center p-8" onClick={onClose}>
			<div className="world-hud-panel hud-enter-scale flex max-h-[84%] w-[min(54rem,94%)] flex-col p-6" onClick={(event) => event.stopPropagation()}>
				<div className="flex items-end justify-between gap-6">
					<div>
						<span className="world-hud-label">INVENTORY</span>
						<div className="mt-3 flex gap-1">
							{TABS.map((item) => (
								<button
									key={item.id}
									type="button"
									onClick={() => setTab(item.id)}
									className={`border-b-2 px-3 py-1 text-[0.65rem] tracking-[0.16em] transition-colors cursor-pointer ${tab === item.id ? 'border-accent text-accent' : 'border-transparent text-fg-3 hover:text-fg-1'}`}
									style={{ borderRadius: 0 }}
								>
									{item.label}
								</button>
							))}
						</div>
					</div>
					<div className="text-right">
						<span className="world-hud-label">CREDITS</span>
						<p className="world-hud-glow font-display text-3xl font-semibold tabular-nums tracking-[0.06em]">{formatAmount(inventory?.credits)}</p>
					</div>
				</div>
				<span className="hud-enter-rule my-4 block h-px w-full origin-left bg-gradient-to-r from-accent via-accent/40 to-transparent" />

				<div className="min-h-0 flex-1 overflow-y-auto custom-scrollbar pr-1">
					{tab === 'history' ? (
						<CreditHistory worldId={worldId} sessionId={sessionId} />
					) : items.length === 0 ? (
						<div className="flex flex-col items-center gap-2 py-14">
							<span className="world-hud-label italic text-fg-3">YOUR POCKETS ARE EMPTY</span>
							<span className="text-fg-3 text-xs">Things you find, buy or are given show up here.</span>
						</div>
					) : (
						<div className="grid grid-cols-1 gap-6 md:grid-cols-[1fr_16rem]">
							<div role="listbox" className="grid grid-cols-4 content-start gap-2">
								{items.map((item, itemIndex) => {
									const active = itemIndex === index;
									return (
										<button
											key={item.itemId}
											type="button"
											role="option"
											aria-selected={active}
											onMouseEnter={() => setHighlighted(itemIndex)}
											onClick={() => setHighlighted(itemIndex)}
											className={`hud-enter-rise relative flex flex-col items-center gap-2 border px-2 pb-2 pt-3 transition-colors cursor-pointer ${active ? 'world-hud-shimmer border-accent/60 bg-accent/10 shadow-[0_0_16px_color-mix(in_oklab,var(--accent)_25%,transparent)]' : 'border-line-1 hover:border-accent/40'}`}
											style={{ animationDelay: `${Math.min(itemIndex, 12) * 25}ms`, borderRadius: 0 }}
										>
											<ItemThumb item={item} size="lg" />
											<span className={`w-full truncate text-center text-xs ${active ? 'text-fg-1' : 'text-fg-2'}`}>{item.name}</span>
											<span className="absolute right-1.5 top-1 text-[0.65rem] tabular-nums text-accent">×{formatAmount(item.quantity)}</span>
										</button>
									);
								})}
							</div>
							{selected && (
								<div key={selected.itemId} className="hud-enter-fade flex flex-col border-l border-accent/20 pl-5">
									<ItemThumb item={selected} size="lg" className="!h-24 !w-24" />
									<h3 className="world-hud-glow mt-3 font-display text-xl font-semibold uppercase tracking-[0.08em]">{selected.name}</h3>
									<span className="world-hud-label mt-1 text-fg-3">×{formatAmount(selected.quantity)}{selected.basePrice !== null ? ` · WORTH ABOUT ${selected.basePrice} CR` : ''}</span>
									<p className="mt-3 text-sm leading-relaxed text-fg-1/85">{selected.description}</p>
									<div className="mt-auto space-y-2 pt-5">
										{selected.canExamine && (
											<button type="button" disabled={busyItemId === selected.itemId} onClick={() => onExamine(selected)} className="world-hud-panel flex w-full items-center gap-3 px-4 py-2 text-left text-[0.7rem] tracking-[0.16em] text-fg-1 hover:text-accent cursor-pointer disabled:opacity-40">
												<span className="world-hud-key">X</span><span>EXAMINE</span>
											</button>
										)}
										{selected.canUse && (attempting ? (
											<form onSubmit={(event) => { event.preventDefault(); use(); }} className="space-y-2">
												<input
													ref={attemptRef}
													value={attempt}
													onChange={(event) => setAttempt(event.target.value)}
													placeholder="What do you do? (optional)"
													className="w-full border border-accent/40 bg-bg-0/70 px-3 py-2 text-sm text-fg-1 outline-none focus:border-accent"
												/>
												<button type="submit" disabled={busyItemId === selected.itemId} className="world-hud-panel flex w-full items-center gap-3 px-4 py-2 text-left text-[0.7rem] tracking-[0.16em] text-fg-1 hover:text-accent cursor-pointer disabled:opacity-40">
													<span className="world-hud-key">ENTER</span><span>{busyItemId === selected.itemId ? 'TRYING...' : 'TRY IT'}</span>
												</button>
											</form>
										) : (
											<button type="button" disabled={busyItemId === selected.itemId} onClick={() => { setAttempting(true); setTimeout(() => attemptRef.current?.focus(), 0); }} className="world-hud-panel flex w-full items-center gap-3 px-4 py-2 text-left text-[0.7rem] tracking-[0.16em] text-fg-1 hover:text-accent cursor-pointer disabled:opacity-40">
												<span className="world-hud-key">ENTER</span><span>USE</span>
											</button>
										))}
									</div>
								</div>
							)}
						</div>
					)}
				</div>

				<div className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-accent/20 pt-3">
					{tab === 'items' && items.length > 0 && <span className="flex items-center gap-1"><span className="world-hud-key">← ↑ ↓ →</span><span className="world-hud-label">CHOOSE</span></span>}
					<span className="flex items-center gap-1"><span className="world-hud-key">TAB</span><span className="world-hud-label">CLOSE</span></span>
				</div>
			</div>
		</div>
	);
}
