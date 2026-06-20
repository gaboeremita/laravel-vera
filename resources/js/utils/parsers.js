/**
 * Parses VERA's response to extract the emotion tag, intimate state, and clean text.
 * Falls back to [seduced] in intimate state, [neutral] otherwise.
 */
export function parseEmotionFromResponse(text, validEmotions = []) {
    let remaining = text;
    let emotion = null;

    // Grab the emotion tag first
    const emotionMatch = remaining.match(/^\[([a-z]+)\]/);
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
        emotion = intimate ? "seduced" : "neutral";
    }

    return {
        emotion,
        intimate,
        text: remaining.trim(),
    };
}