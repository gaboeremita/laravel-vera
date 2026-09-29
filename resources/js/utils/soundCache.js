let audioContext = null;
const buffers = new Map();

/**
 * Decoded sounds by content hash: a sound shared by several items, or used
 * again later, is fetched and decoded once.
 */
function bufferFor({ url, hash }) {
	if (!buffers.has(hash)) {
		audioContext ??= new AudioContext();
		const loading = fetch(url)
			.then((response) => {
				if (!response.ok) throw new Error(`Sound ${hash} failed to load`);
				return response.arrayBuffer();
			})
			.then((data) => audioContext.decodeAudioData(data));
		loading.catch(() => buffers.delete(hash));
		buffers.set(hash, loading);
	}
	return buffers.get(hash);
}

export async function playSound(sound, volume = 0.7) {
	if (!sound?.url || !sound?.hash) return;
	const buffer = await bufferFor(sound);
	if (audioContext.state === 'suspended') await audioContext.resume();
	const source = audioContext.createBufferSource();
	source.buffer = buffer;
	const gain = audioContext.createGain();
	gain.gain.value = volume;
	source.connect(gain).connect(audioContext.destination);
	source.start();
}
