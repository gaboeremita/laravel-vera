import { useEffect, useRef } from 'react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import { RESIDENT_BUSY, USER_BUSY, describeStep, executeAction } from '../components/world/residentActions.js';
import { finishActivity, startActivity } from '../components/world/activityLog.js';
import { fullSpotIds, stackedSpots } from '../components/world/spotOccupancy.js';
import { idleWait } from '../components/world/residentMotion.js';

const STAGGER_MS = 2000;
const RECHECK_MS = 5000;
const RATE_LIMITED_WAIT_MS = 8000;
const USER_AWAY_MS = 5 * 60 * 1000;
const USER_INPUT_EVENTS = ['keydown', 'mousedown', 'mousemove', 'wheel', 'touchstart'];

/**
 * Runs the self-chosen activities of the world's autonomous residents while
 * the user is in the world: each one decides her next step 10–30 s after the
 * previous one finishes, skipping while the user talks to her, while she is
 * in a conversation with another resident, while the world is paused or the
 * page is hidden, and after five minutes with no input from the user.
 */
export function useResidentAgency({ enabled, worldId, sessionId, residents, layout, chatResidentId, residentCommands, occupiedSpots, getPositions, getFollowTarget, getResidentPosition, getUserState, getUserBusyWith, getBusyResidents, claimTarget, releaseTarget, isBusy, isPaused, onSpeak, onThought, addToast }) {
	const chatResidentRef = useRef(chatResidentId);
	const runningRef = useRef(new Map());
	const latestRef = useRef(null);

	useEffect(() => {
		latestRef.current = { residents, layout, getPositions, getFollowTarget, getResidentPosition, getUserState, getUserBusyWith, getBusyResidents, claimTarget, releaseTarget, isBusy, isPaused, onSpeak, onThought, addToast };
	});

	useEffect(() => {
		chatResidentRef.current = chatResidentId;
		if (chatResidentId !== null && runningRef.current.has(chatResidentId)) {
			void residentCommands.current.get(chatResidentId)?.stop();
		}
	}, [chatResidentId, residentCommands]);

	const autonomousIds = (residents ?? []).filter((resident) => resident.behavior === 'autonomous').map((resident) => resident.id).join(',');

	useEffect(() => {
		if (!enabled || !sessionId || !autonomousIds) return undefined;
		let cancelled = false;
		const timers = new Set();
		const running = runningRef.current;
		const commandsByResident = residentCommands.current;
		let lastInputAt = Date.now();
		const noteInput = () => { lastInputAt = Date.now(); };
		for (const type of USER_INPUT_EVENTS) window.addEventListener(type, noteInput, { passive: true });
		const sleep = (ms) => new Promise((resolve) => {
			const timer = setTimeout(() => {
				timers.delete(timer);
				resolve();
			}, ms);
			timers.add(timer);
		});

		const reportOutcome = async (residentId, activityId, result) => {
			if (!activityId) return;
			try {
				await finishActivity({ worldId, sessionId, residentId }, activityId, result);
			} catch (error) {
				console.error('[useResidentAgency] could not report an outcome', error);
			}
		};
		const recordStep = async (resident, goal, step, index, total) => {
			try {
				return await startActivity({ worldId, sessionId, residentId: resident.id }, {
					verb: step.verb,
					target: step.verb === 'do' ? step.description : step.target,
					activity: step.activity,
					reason: `step ${index + 1} of ${total} of ${goal}`,
					source: 'idle',
					position: latest().getPositions().residents?.[resident.id] ?? null,
				});
			} catch (error) {
				console.error(`[useResidentAgency] could not record ${resident.assistant.name}'s step`, error);
				return null;
			}
		};

		const latest = () => latestRef.current;

		const decide = async (residentId) => {
			const commands = residentCommands.current.get(residentId);
			const others = fullSpotIds(occupiedSpots.current, latest().layout, residentId);
			const response = await api.post(route('worlds.sessions.residents.decisions.store', { world: worldId, session: sessionId, resident: residentId }), {
				positions: latest().getPositions(),
				residentPosture: commands.posture(),
				residentState: commands.bodyState(),
				occupiedSpots: others,
				stackedSpots: stackedSpots(occupiedSpots.current),
				userBusyWith: latest().getUserBusyWith?.() ?? null,
				busyResidents: latest().getBusyResidents?.() ?? [],
				userState: latest().getUserState?.() ?? null,
			});
			if (response.status === 429) return { retryIn: RATE_LIMITED_WAIT_MS };
			if (!response.ok) {
				const error = await response.json().catch(() => ({}));
				throw new Error(error.message || `HTTP ${response.status}`);
			}
			return { decision: await response.json() };
		};

		const live = async (resident, index) => {
			let wait = idleWait() + index * STAGGER_MS;
			while (!cancelled) {
				await sleep(wait);
				if (cancelled) return;
				const commands = residentCommands.current.get(resident.id);
				const userAway = Date.now() - lastInputAt > USER_AWAY_MS;
				if (!commands || userAway || document.visibilityState === 'hidden' || chatResidentRef.current === resident.id || latest().isBusy?.(resident.id) || latest().isPaused?.()) {
					wait = RECHECK_MS;
					continue;
				}

				let outcome;
				try {
					outcome = await decide(resident.id);
				} catch (error) {
					console.error(`[useResidentAgency] ${resident.assistant.name} could not decide what to do`, error);
					latest().addToast(`${resident.assistant.name} couldn't decide what to do: ${error.message}`, 'error');
					wait = idleWait();
					continue;
				}
				if (cancelled) return;
				if (outcome.retryIn) {
					wait = outcome.retryIn;
					continue;
				}

				const { decision } = outcome;
				running.set(resident.id, decision.activityId);
				latest().onThought(resident.id, decision.line || null);
				let result;
				if (decision.action) {
					result = await executeAction(decision.action, {
						commands,
						layout: latest().layout,
						getFollowTarget: latest().getFollowTarget,
						getResidentPosition: latest().getResidentPosition,
						onSpeak: (action) => latest().onSpeak?.(resident.id, action),
						claimTarget: (target) => latest().claimTarget?.(resident.id, target) ?? true,
						releaseTarget: (target) => latest().releaseTarget?.(resident.id, target),
						fromUser: false,
						residentId: resident.id,
						occupiedSpots: occupiedSpots.current,
						onStepStart: (step, index, total) => {
							latest().onThought(resident.id, `Step ${index + 1}/${total}: ${describeStep(step)} — ${decision.line}`);
							return recordStep(resident, decision.action.target, step, index, total);
						},
						onStepEnd: (step, index, stepResult, stepActivityId) => reportOutcome(resident.id, stepActivityId, stepResult),
					});
				} else if (decision.pose) {
					result = await commands.pose(decision.pose);
				} else {
					result = { outcome: 'completed', reason: null };
				}
				running.delete(resident.id);
				latest().onThought(resident.id, null);
				if (result.outcome === 'failed' && ![USER_BUSY, RESIDENT_BUSY].includes(result.reason)) {
					console.error(`[useResidentAgency] ${resident.assistant.name}'s step failed: ${result.reason}`);
					latest().addToast(`${resident.assistant.name} couldn't do what she chose: ${result.reason}`, 'error');
				}
				await reportOutcome(resident.id, decision.activityId, result);
				wait = decision.action?.verb === 'plan' && result.outcome === 'failed' ? RATE_LIMITED_WAIT_MS : idleWait(commands.posture());
			}
		};

		const pauseWhenHidden = () => {
			if (document.visibilityState !== 'hidden') return;
			for (const residentId of running.keys()) void residentCommands.current.get(residentId)?.stop();
		};
		document.addEventListener('visibilitychange', pauseWhenHidden);

		const autonomous = latest().residents.filter((resident) => resident.behavior === 'autonomous');
		autonomous.forEach((resident, index) => { void live(resident, index); });

		return () => {
			cancelled = true;
			document.removeEventListener('visibilitychange', pauseWhenHidden);
			for (const type of USER_INPUT_EVENTS) window.removeEventListener(type, noteInput);
			for (const timer of timers) clearTimeout(timer);
			for (const residentId of running.keys()) void commandsByResident.get(residentId)?.stop();
		};
	}, [enabled, worldId, sessionId, autonomousIds, residentCommands, occupiedSpots]);
}
