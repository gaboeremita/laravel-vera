import { useState, useRef, useCallback, useEffect } from 'react';

const VAD_ASSET_PATH = '/vendor/vad/';

/**
 * Fires onSpeechEnd with a WAV Blob whenever the user stops speaking.
 *
 * Reads MicVAD off window.vad (loaded via a <script> tag in welcome.blade.php)
 * rather than importing @ricky0123/vad-web directly — its onnxruntime-web
 * dependency is CJS/require-based and breaks under Vite's ESM pre-bundling.
 */
export function useVoiceMode({ onSpeechEnd } = {}) {
	const [isListening, setIsListening] = useState(false);
	const [isSpeaking, setIsSpeaking] = useState(false);
	const [error, setError] = useState(null);
	const vadRef = useRef(null);

	// MicVAD.new() only runs once per start() call and locks in whatever
	// callback it's given at that moment. Routing through a ref means later
	// renders (fresh `messages` closures, etc.) are still picked up without
	// having to tear down and recreate the VAD instance every render.
	const onSpeechEndRef = useRef(onSpeechEnd);
	useEffect(() => {
		onSpeechEndRef.current = onSpeechEnd;
	}, [onSpeechEnd]);

	const start = useCallback(async () => {
		if (vadRef.current) return;

		if (!window.vad) {
			setError('Voice detection failed to load');
			return;
		}

		try {
			const vad = await window.vad.MicVAD.new({
				baseAssetPath: VAD_ASSET_PATH,
				onnxWASMBasePath: VAD_ASSET_PATH,
				onSpeechStart: () => setIsSpeaking(true),
				onVADMisfire: () => setIsSpeaking(false),
				onSpeechEnd: (audio) => {
					setIsSpeaking(false);
					const wav = window.vad.utils.encodeWAV(audio);
					onSpeechEndRef.current?.(new Blob([wav], { type: 'audio/wav' }));
				},
			});

			vadRef.current = vad;
			vad.start();
			setIsListening(true);
			setError(null);
		} catch (err) {
			setError(err.message || 'Microphone access denied');
		}
	}, []);

	const stop = useCallback(() => {
		vadRef.current?.destroy();
		vadRef.current = null;
		setIsListening(false);
		setIsSpeaking(false);
	}, []);

	return { isListening, isSpeaking, error, start, stop };
}
