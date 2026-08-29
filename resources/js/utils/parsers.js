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
        const matchedEmotion = emotionMatch[1].toLowerCase();
        if (validEmotions.length === 0 || validEmotions.some((e) => e.toLowerCase() === matchedEmotion)) {
            emotion = matchedEmotion;
        }
    }

    // Check for [intimate] tag immediately after
    const intimateMatch = remaining.match(/^\[intimate\]/i);
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
 * Parses a pose tag (e.g. "[pose: spin] ...") from the start of text,
 * distinct from the emotion tag syntax so a pose name can never be mistaken
 * for an emotion tag. Intended to run on the text already returned by
 * parseEmotionFromResponse, after any [emotion]/[intimate] tags are
 * stripped, so both tags can appear on the same message.
 */
export function parsePoseFromResponse(text, validPoseNames = []) {
    let remaining = text;
    let pose = null;

    const poseMatch = remaining.match(/^\[pose:\s*([^\]]+)\]/i);
    if (poseMatch) {
        remaining = remaining.slice(poseMatch[0].length);
        const matchedPose = poseMatch[1].trim();
        if (validPoseNames.some((p) => p.toLowerCase() === matchedPose.toLowerCase())) {
            pose = matchedPose;
        }
    }

    return {
        pose,
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
