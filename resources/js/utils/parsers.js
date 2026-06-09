import { getAvailableEmotions } from "./promptBuilder";

const VALID_EMOTIONS = getAvailableEmotions();

/**
 * Parses VERA's response to extract the emotion tag and clean message text.
 * Returns neutral as default if no valid tag is found.
 */
export function parseEmotionFromResponse(text) {
    const match = text.match(/^\[([a-z]+)\]/);
    if (match && VALID_EMOTIONS.includes(match[1])) {
        return {
            emotion: match[1],
            text: text.slice(match[0].length).trim(),
        };
    }
    return { emotion: "neutral", text: text.trim() };
}