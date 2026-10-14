import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { route } from 'ziggy-js';
import { api } from '../../../utils/api.js';
import ItemThumb from '../../ItemThumb.jsx';
import { HudButton, HudCounter } from './HudControls.jsx';

const PLAYER = { type: 'player' };
const FLY_MS = 300;
const CREDIT_STEP = 100;

function holderKey(holder) {
	return holder.type === 'player' ? 'player' : holder.type === 'resident' ? `resident:${holder.residentId}` : `object:${holder.regionId}:${holder.objectId}`;
}

function holderFromKey(key) {
	const [type, first, second] = key.split(':');
	if (type === 'resident') return { type, residentId: Number(first) };
	if (type === 'object') return { type, regionId: Number(first), objectId: second };
	return PLAYER;
}

function ItemGrid({ title, items, gridKey, onPick, emptyText }) {
	return (
		<div className="flex min-w-0 flex-col gap-2 border border-line-1 p-3" style={{ borderRadius: 'calc(var(--hud-radius) / 2)' }}>
			<span className="world-hud-label">{title}</span>
			{items === null && <span className="text-fg-3 text-xs">Loading…</span>}
			{items?.length === 0 && <span className="text-fg-3 text-xs">{emptyText}</span>}
			<div className="grid grid-cols-3 gap-1.5">
				{(items ?? []).map((item) => (
					<button
						key={item.itemId}
						type="button"
						data-fly={`${gridKey}:${item.itemId}`}
						onClick={(event) => onPick(item, event.currentTarget)}
						title={`Move one ${item.name}`}
						className="flex flex-col items-start gap-1 border border-line-1 p-1.5 text-left transition-[border-color,transform] duration-150 hover:-translate-y-0.5 hover:border-accent/60 active:scale-95"
						style={{ borderRadius: 'calc(var(--hud-radius) / 3)' }}
					>
						<ItemThumb item={item} size="sm" />
						<span className="w-full truncate text-fg-1 text-xs">{item.name}</span>
						<span className="world-hud-label text-fg-3">×{item.quantity ?? '∞'}</span>
					</button>
				))}
			</div>
		</div>
	);
}

/** Credits, any item of the world, and every holder's inventory, moved by the creator. */
export default function CreatorItemsTab({ overview, creator, context, itemsHolder }) {
	const [fromKey, setFromKey] = useState(() => holderKey(itemsHolder ?? (overview.residents[0] ? { type: 'resident', residentId: overview.residents[0].id } : PLAYER)));
	const [inventories, setInventories] = useState({});
	const [reloadKey, setReloadKey] = useState(0);
	const [itemId, setItemId] = useState(overview.items[0]?.id ?? '');
	const [quantity, setQuantity] = useState(1);
	const [customCredits, setCustomCredits] = useState('');
	const flight = useRef(null);
	const from = holderFromKey(fromKey);

	useEffect(() => {
		let cancelled = false;
		const load = async () => {
			try {
				const entries = await Promise.all([fromKey, 'player'].map(async (key) => {
					const response = await api.get(route('worlds.sessions.creator.inventories.show', { world: context.worldId, session: context.sessionId, holder: holderFromKey(key) }));
					const body = await response.json();
					if (!response.ok) throw new Error(body.message || 'Unable to open that inventory');
					return [key, body.items];
				}));
				if (!cancelled) setInventories(Object.fromEntries(entries));
			} catch (error) {
				console.error('[CreatorItemsTab] could not load inventories', error);
				if (!cancelled) setInventories({ [fromKey]: [], player: [] });
			}
		};
		void load();
		return () => { cancelled = true; };
	}, [fromKey, reloadKey, context.worldId, context.sessionId]);

	useLayoutEffect(() => {
		const pending = flight.current;
		if (!pending) return;
		const target = document.querySelector(`[data-fly="${pending.to}:${pending.itemId}"]`);
		flight.current = null;
		if (!target) return;
		const end = target.getBoundingClientRect();
		target.animate([
			{ transform: `translate(${pending.rect.left - end.left}px, ${pending.rect.top - end.top}px)`, opacity: 0.6 },
			{ transform: 'none', opacity: 1 },
		], { duration: FLY_MS, easing: 'cubic-bezier(0.2, 0.8, 0.2, 1)' });
	}, [inventories]);

	const afterChange = (body) => {
		if (!body) return false;
		context.applyInventory(body.inventory);
		setReloadKey((current) => current + 1);
		return body;
	};
	const move = async (item, element, source, target, toKey) => {
		const rect = element.getBoundingClientRect();
		const body = await creator.run(`moved one ${item.name}`, () => api.post(context.route('inventories.move'), { from: source, to: target, itemId: item.itemId, quantity: 1 }));
		if (body) flight.current = { to: toKey, itemId: item.itemId, rect };
		return afterChange(body);
	};
	const changeCredits = async (amount) => afterChange(await creator.run(`${amount > 0 ? 'added' : 'took'} ${Math.abs(amount)} credits`, () => api.post(context.route('credits.store'), { amount })));
	const grab = async (holder, label) => {
		const item = overview.items.find((candidate) => candidate.id === Number(itemId));
		if (!item) return false;
		return afterChange(await creator.run(`gave ${label} ${quantity} ${item.name}`, () => api.post(context.route('inventories.grant'), { holder, itemId: item.id, quantity: Number(quantity) })));
	};
	const holderOptions = [
		...overview.residents.map((resident) => ({ key: holderKey({ type: 'resident', residentId: resident.id }), label: resident.name })),
		...overview.regions.flatMap((region) => region.objects.map((object) => ({ key: holderKey({ type: 'object', regionId: region.id, objectId: object.id }), label: `${object.name} — ${region.name}` }))),
	];
	const fromLabel = holderOptions.find((option) => option.key === fromKey)?.label ?? '';
	const selectClass = 'border border-line-2 bg-bg-1 px-2 py-1.5 text-fg-1 text-sm';

	return (
		<div className="flex flex-col gap-5">
			<div className="flex flex-wrap items-end gap-3">
				<div className="flex flex-col">
					<span className="world-hud-label">Your credits</span>
					<HudCounter value={overview.credits} className="hud-title world-hud-glow text-3xl" suffix=" ¢" />
				</div>
				<HudButton onClick={() => changeCredits(-CREDIT_STEP)}>− {CREDIT_STEP}</HudButton>
				<HudButton variant="primary" onClick={() => changeCredits(CREDIT_STEP)}>+ {CREDIT_STEP}</HudButton>
				<input type="number" value={customCredits} onChange={(event) => setCustomCredits(event.target.value)} placeholder="250" aria-label="Credits to add or take" className="w-24 border border-line-2 bg-bg-1 px-2 py-1.5 text-fg-1 text-sm" />
				<HudButton disabled={!Number(customCredits)} onClick={async () => { const done = await changeCredits(Math.trunc(Number(customCredits))); if (done) setCustomCredits(''); return done; }}>Apply</HudButton>
			</div>

			<div className="flex flex-wrap items-center gap-2">
				<span className="world-hud-label">From</span>
				<select value={fromKey} onChange={(event) => setFromKey(event.target.value)} className={selectClass} aria-label="Holder">
					{holderOptions.map((option) => <option key={option.key} value={option.key}>{option.label}</option>)}
				</select>
			</div>

			<div className="grid grid-cols-[1fr_auto_1fr] items-start gap-3">
				<ItemGrid title={`From · ${fromLabel}`} items={inventories[fromKey] ?? null} gridKey={fromKey} emptyText="Nothing here." onPick={(item, element) => move(item, element, from, PLAYER, 'player')} />
				<span className="self-center text-2xl text-accent" aria-hidden="true">⇄</span>
				<ItemGrid title="To · you" items={inventories.player ?? null} gridKey="player" emptyText="You hold nothing." onPick={(item, element) => move(item, element, PLAYER, from, fromKey)} />
			</div>

			<div className="flex flex-wrap items-center gap-2 border-t border-line-1 pt-4">
				<span className="world-hud-label">Any item</span>
				<select value={itemId} onChange={(event) => setItemId(event.target.value)} className={selectClass} aria-label="Item">
					{overview.items.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
				</select>
				<input type="number" min={1} value={quantity} onChange={(event) => setQuantity(Math.max(1, Number(event.target.value) || 1))} aria-label="Quantity" className="w-16 border border-line-2 bg-bg-1 px-2 py-1.5 text-fg-1 text-sm" />
				<HudButton variant="primary" disabled={!itemId} onClick={() => grab(PLAYER, 'you')}>Grab</HudButton>
				<HudButton disabled={!itemId} onClick={() => grab(from, fromLabel)}>Give to {fromLabel}</HudButton>
			</div>
		</div>
	);
}
