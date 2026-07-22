/**
 * Parses VERA's response to extract the emotion tag, intimate state, and clean text.
 * Falls back to [seduced] in intimate state, [default] otherwise.
 */
export function parseEmotionFromResponse(text, validEmotions = []) {
    let remaining = text;
    let emotion = null;

    // Grab the emotion tag first
    const emotionMatch = remaining.match(/^\[([a-zA-Z]+)\]/);
    if (emotionMatch) {
        remaining = remaining.slice(emotionMatch[0].length);
        if (validEmotions.length === 0 || validEmotions.includes(emotionMatch[1])) {
            emotion = emotionMatch[1];
        }
    }

    // Check for [intimate] tag immediately after
    const intimateMatch = remaining.match(/^\[intimate\]/);
    const intimate = !!intimateMatch;
    if (intimateMatch) {
        remaining = remaining.slice(intimateMatch[0].length);
    }

    // Default based on state
    if (!emotion) {
        emotion = intimate ? "seduced" : "default";
    }

    return {
        emotion,
        intimate,
        text: remaining.trim(),
    };
}

/**
 * Strips asterisk-wrapped stage directions / action narration from text
 * before it's sent to TTS. Defense-in-depth alongside the voice-mode prompt
 * instructions — models don't always follow formatting instructions.
 */
export function stripForSpeech(text) {
    return text
        .replace(/\*[^*]+\*/g, ' ')
        .replace(/[ \t]+/g, ' ')
        .replace(/\n{2,}/g, '\n')
        .trim();
}
