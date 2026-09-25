/**
 * Freezes the scene clock: every frame after this gets no time at all, so
 * nothing the frame loop drives moves, animates or counts down.
 */
export function freezeClock(clock) {
	clock.stop();
}

/**
 * Starts the frozen clock again from where it stopped, so the time spent
 * paused never reaches the world: no jump in movement, and no timer that
 * thinks seconds went by.
 */
export function resumeClock(clock) {
	clock.oldTime = performance.now();
	clock.running = true;
	clock.autoStart = true;
}
