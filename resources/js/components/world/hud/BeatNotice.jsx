import { useEffect, useState } from 'react';
import { rewardSummary } from '../../../utils/questReward.js';

const HOLD_MS = 3500;
const REWARD_HOLD_MS = 8000;

const HEADLINES = {
	beatFinished: 'BEAT COMPLETE',
	questStarted: 'QUEST STARTED',
	completed: 'QUEST COMPLETE',
	failed: 'QUEST FAILED',
	abandoned: 'QUEST ABANDONED',
	rewardGiven: 'REWARD',
};

/** Announces one quest change, then dissolves. Remount it with a new key for the next notice. */
export default function BeatNotice({ notice, onDone }) {
	const [leaving, setLeaving] = useState(false);

	useEffect(() => {
		const timer = setTimeout(() => setLeaving(true), notice.type === 'rewardGiven' ? REWARD_HOLD_MS : HOLD_MS);
		return () => clearTimeout(timer);
	}, [notice.type]);

	const ended = notice.type === 'questEnded';
	const headline = HEADLINES[ended ? notice.text : notice.type] ?? '';
	const failed = ended && notice.text !== 'completed';

	return (
		<div
			className={`world-hud-panel pointer-events-none flex w-72 flex-col gap-1 px-4 py-3 hud-enter-rise ${leaving ? 'hud-exit-dissolve' : ''}`}
			onAnimationEnd={(event) => { if (leaving && event.target === event.currentTarget) onDone(); }}
		>
			<span className={`world-hud-label ${failed ? 'text-danger' : 'text-accent'}`}>{headline}</span>
			<span className="world-hud-glow truncate text-[0.8rem] tracking-[0.08em]">{notice.questTitle}</span>
			{notice.type === 'beatFinished' && <span className="text-fg-2 text-xs leading-snug">{notice.text}</span>}
			{notice.type === 'rewardGiven' && (
				<>
					{rewardSummary(notice.reward) && <span className="text-fg-1 text-xs">{rewardSummary(notice.reward)}</span>}
					{notice.text && <span className="text-fg-2 text-xs leading-snug">{notice.fromResident ? `${notice.giverName}: "${notice.text}"` : notice.text}</span>}
				</>
			)}
			<span className="hud-enter-rule mt-1 block h-px w-full origin-left bg-gradient-to-r from-accent via-accent/40 to-transparent" />
		</div>
	);
}
