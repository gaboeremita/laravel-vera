let audioContext = null;

function noiseBurst({ seconds, filterType, frequency, q = 0.8, peak, attack = 0.02 }) {
	audioContext ??= new AudioContext();
	const buffer = audioContext.createBuffer(1, Math.floor(audioContext.sampleRate * seconds), audioContext.sampleRate);
	const samples = buffer.getChannelData(0);
	for (let index = 0; index < samples.length; index++) samples[index] = Math.random() * 2 - 1;
	const source = audioContext.createBufferSource();
	source.buffer = buffer;
	const filter = audioContext.createBiquadFilter();
	filter.type = filterType;
	filter.frequency.value = frequency;
	filter.Q.value = q;
	const gain = audioContext.createGain();
	const now = audioContext.currentTime;
	gain.gain.setValueAtTime(0.0001, now);
	gain.gain.exponentialRampToValueAtTime(peak, now + attack);
	gain.gain.exponentialRampToValueAtTime(0.0001, now + seconds);
	source.connect(filter).connect(gain).connect(audioContext.destination);
	source.start(now);
}

export function playSplash(entering) {
	noiseBurst({ seconds: entering ? 0.7 : 0.45, filterType: 'bandpass', frequency: entering ? 900 : 520, peak: entering ? 0.35 : 0.2, attack: 0.03 });
}

export function playJump() {
	noiseBurst({ seconds: 0.25, filterType: 'highpass', frequency: 1800, q: 0.5, peak: 0.06, attack: 0.05 });
}

/** A thump that grows with the speed of the fall. */
export function playLanding(fallSpeed) {
	noiseBurst({ seconds: 0.18, filterType: 'lowpass', frequency: 220, q: 1.2, peak: Math.min(0.5, 0.08 + Math.abs(fallSpeed) * 0.04), attack: 0.005 });
}
