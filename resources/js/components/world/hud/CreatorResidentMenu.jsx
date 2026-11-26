import { useEffect, useRef, useState } from 'react';
import { ChevronRight } from 'lucide-react';
import { Avatar } from './CreatorResidentsTab.jsx';
import { timeAgo } from '../../../utils/timeAgo.js';

const SCREEN_MARGIN = 8;

/** Moves a submenu up, and over to the left of its row, as far as it needs to stay on screen, again whenever its size changes. */
function keepSubmenuOnScreen(submenu) {
	const place = () => {
		submenu.style.top = '';
		submenu.style.left = '';
		submenu.style.right = '';
		const rect = submenu.getBoundingClientRect();
		const overflowBottom = rect.bottom - (window.innerHeight - SCREEN_MARGIN);
		if (overflowBottom > 0) submenu.style.top = `${-Math.min(overflowBottom, rect.top - SCREEN_MARGIN)}px`;
		if (rect.right > window.innerWidth - SCREEN_MARGIN) {
			submenu.style.left = 'auto';
			submenu.style.right = '100%';
		}
	};
	const observer = new ResizeObserver(place);
	observer.observe(submenu);
	return () => observer.disconnect();
}

/**
 * The creator's commands over the resident they clicked in the world, opened
 * at the click. "Talk to…" and "Go to…" are off while the resident is frozen. "Talk to…" lists everyone else in the region on hover;
 * "Switch conversation…" lists the player's conversations with the resident;
 * "Pose…" lists every pose they have and plays the clicked one; "Go to…"
 * lists the region's zones in nesting order and walks them to the clicked one; "Move to"
 * hands them to the cursor to be placed; "Open in Creator menu" opens the
 * Creator menu's Residents tab on them.
 */
export default function CreatorResidentMenu({ resident, others, poses, zones, point, frozen = false, goingToTalkTo = null, onTalkTo, loadConversations, onSwitchConversation, onPose, onGoTo, onMoveTo, onSendHome, onFreeze, onOpenInCreatorMenu, onClose }) {
	const [openSubmenu, setOpenSubmenu] = useState(null);
	const [conversations, setConversations] = useState(null);
	const [conversationsError, setConversationsError] = useState(null);
	const loadingConversations = useRef(false);
	const menuRef = useRef(null);

	useEffect(() => {
		const keyDown = (event) => {
			if (event.code !== 'Escape') return;
			event.stopImmediatePropagation();
			onClose();
		};
		const pointerDown = (event) => {
			if (!menuRef.current?.contains(event.target)) onClose();
		};
		window.addEventListener('keydown', keyDown, true);
		window.addEventListener('pointerdown', pointerDown, true);
		return () => {
			window.removeEventListener('keydown', keyDown, true);
			window.removeEventListener('pointerdown', pointerDown, true);
		};
	}, [onClose]);

	const openConversations = () => {
		setOpenSubmenu('conversations');
		if (loadingConversations.current) return;
		loadingConversations.current = true;
		loadConversations()
			.then(setConversations)
			.catch((error) => setConversationsError(error.message || 'Unable to load conversations'));
	};

	const itemClass = 'flex w-full items-center gap-2.5 px-3 py-1.5 text-left text-sm transition-colors';
	const submenuClass = 'world-hud-panel hud-enter-fade absolute left-full top-0 flex max-h-[60vh] min-w-[14rem] flex-col overflow-y-auto py-1.5';
	const rowClass = `${itemClass} text-fg-2 hover:bg-accent/10 hover:text-fg-1 disabled:cursor-default disabled:opacity-40 disabled:hover:bg-transparent`;

	return (
		<div ref={menuRef} className="world-hud-panel hud-enter-scale fixed z-40 flex min-w-[12rem] flex-col py-1.5" style={{ left: point.x, top: point.y }}>
			<span className="world-hud-label truncate px-3 pb-1.5 text-accent">{resident.name}</span>
			<div className="relative" onMouseEnter={() => setOpenSubmenu(frozen ? null : 'talk')} onMouseLeave={() => setOpenSubmenu(null)}>
				<button type="button" disabled={frozen} className={`${itemClass} ${openSubmenu === 'talk' ? 'bg-accent/10 text-fg-1' : 'text-fg-2'} disabled:cursor-default disabled:opacity-40`} onClick={() => setOpenSubmenu('talk')}>
					<span className="flex-1">Talk to…</span>
					{frozen ? <span className="world-hud-label text-fg-3">frozen</span> : <ChevronRight size={14} className="text-fg-3" />}
				</button>
				{openSubmenu === 'talk' && (
					<div ref={keepSubmenuOnScreen} className={submenuClass}>
						{goingToTalkTo && <span className="px-3 py-1.5 text-fg-3 text-xs">Going to talk to {goingToTalkTo}…</span>}
						{others.length === 0 && <span className="px-3 py-1.5 text-fg-3 text-xs">Nobody else is in this region.</span>}
						{others.map(({ resident: other, busy, busyWith }) => (
							<button
								key={other.id}
								type="button"
								disabled={busy || goingToTalkTo !== null}
								onClick={() => { onTalkTo(other.id); onClose(); }}
								className={rowClass}
							>
								<Avatar resident={other} />
								<span className="flex-1 truncate">{other.name}</span>
								{busy && <span className="world-hud-label text-fg-3">{busyWith ? `busy with ${busyWith}` : 'busy'}</span>}
							</button>
						))}
					</div>
				)}
			</div>
			<div className="relative" onMouseEnter={openConversations} onMouseLeave={() => setOpenSubmenu(null)}>
				<button type="button" className={`${itemClass} ${openSubmenu === 'conversations' ? 'bg-accent/10 text-fg-1' : 'text-fg-2'}`} onClick={openConversations}>
					<span className="flex-1">Switch conversation…</span>
					<ChevronRight size={14} className="text-fg-3" />
				</button>
				{openSubmenu === 'conversations' && (
					<div ref={keepSubmenuOnScreen} className={submenuClass}>
						{conversationsError && <span className="px-3 py-1.5 text-danger text-xs">{conversationsError}</span>}
						{!conversationsError && conversations === null && <span className="px-3 py-1.5 text-fg-3 text-xs">Loading…</span>}
						{conversations?.length === 0 && <span className="px-3 py-1.5 text-fg-3 text-xs">No conversations yet.</span>}
						{conversations?.map((conversation) => (
							<button
								key={conversation.id}
								type="button"
								disabled={conversation.current}
								onClick={() => { onSwitchConversation(conversation.id); onClose(); }}
								className={rowClass}
							>
								<span className="flex-1 truncate">{conversation.title}</span>
								<span className="world-hud-label text-fg-3">{conversation.current ? 'current' : timeAgo(conversation.updatedAt)}</span>
							</button>
						))}
					</div>
				)}
			</div>
			<div className="relative" onMouseEnter={() => setOpenSubmenu('poses')} onMouseLeave={() => setOpenSubmenu(null)}>
				<button type="button" className={`${itemClass} ${openSubmenu === 'poses' ? 'bg-accent/10 text-fg-1' : 'text-fg-2'}`} onClick={() => setOpenSubmenu('poses')}>
					<span className="flex-1">Pose…</span>
					<ChevronRight size={14} className="text-fg-3" />
				</button>
				{openSubmenu === 'poses' && (
					<div ref={keepSubmenuOnScreen} className={submenuClass}>
						{poses.length === 0 && <span className="px-3 py-1.5 text-fg-3 text-xs">No poses yet.</span>}
						{poses.map((pose) => (
							<button key={`${pose.posture}-${pose.name}`} type="button" onClick={() => { onPose(pose); onClose(); }} className={rowClass}>
								<span className="flex-1 truncate">{pose.name}</span>
								<span className="world-hud-label text-fg-3">{pose.posture}</span>
							</button>
						))}
					</div>
				)}
			</div>
			<div className="relative" onMouseEnter={() => setOpenSubmenu(frozen ? null : 'zones')} onMouseLeave={() => setOpenSubmenu(null)}>
				<button type="button" disabled={frozen} className={`${itemClass} ${openSubmenu === 'zones' ? 'bg-accent/10 text-fg-1' : 'text-fg-2'} disabled:cursor-default disabled:opacity-40`} onClick={() => setOpenSubmenu('zones')}>
					<span className="flex-1">Go to…</span>
					{frozen ? <span className="world-hud-label text-fg-3">frozen</span> : <ChevronRight size={14} className="text-fg-3" />}
				</button>
				{openSubmenu === 'zones' && (
					<div ref={keepSubmenuOnScreen} className={submenuClass}>
						{zones.length === 0 && <span className="px-3 py-1.5 text-fg-3 text-xs">This region has no zones.</span>}
						{zones.map(({ zone, depth }) => (
							<button key={zone.id} type="button" onClick={() => { onGoTo(zone); onClose(); }} className={rowClass}>
								<span className="flex-1 truncate" style={{ paddingLeft: `${depth * 0.75}rem` }}>{zone.name}</span>
							</button>
						))}
					</div>
				)}
			</div>
			<button type="button" onMouseEnter={() => setOpenSubmenu(null)} onClick={() => { onMoveTo(); onClose(); }} className={rowClass}>
				<span className="flex-1">Move to</span>
			</button>
			<button type="button" onMouseEnter={() => setOpenSubmenu(null)} onClick={() => { onSendHome(); onClose(); }} className={rowClass}>
				<span className="flex-1">Send home</span>
			</button>
			<button type="button" onMouseEnter={() => setOpenSubmenu(null)} onClick={() => { onFreeze(!frozen); onClose(); }} className={rowClass}>
				<span className="flex-1">{frozen ? 'Unfreeze' : 'Freeze'}</span>
			</button>
			<button type="button" onMouseEnter={() => setOpenSubmenu(null)} onClick={() => { onOpenInCreatorMenu(); onClose(); }} className={rowClass}>
				<span className="flex-1">Open in Creator menu</span>
			</button>
		</div>
	);
}
