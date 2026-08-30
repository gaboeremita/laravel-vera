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
 * Parses a pose tag (e.g. "[spin] ...") from the start of text — same bare
 * bracket format as an emotion tag. There's no separate "pose:" syntax:
 * poses are the only expression/action signal a 3D avatar assistant emits
 * (it has no emotion tags to disambiguate against), so a plain [name] is
 * unambiguous. Unlike parseEmotionFromResponse, an unmatched/unrecognized
 * tag leaves `pose` as null rather than falling back to a default name —
 * a pose is a one-off trigger, not an ongoing state to default into.
 *
 * Pose names aren't restricted to a single letters-only word the way
 * emotion names are (e.g. "deer_dance", "happy hands") — the bracket
 * content is matched as anything up to the closing `]`, not [a-zA-Z]+.
 */
export function parsePoseFromResponse(text, validPoseNames = []) {
    let remaining = text;
    let pose = null;

    const poseMatch = remaining.match(/^\[([^\]]+)\]/);
    if (poseMatch) {
        const matchedText = poseMatch[1].trim().toLowerCase();
        const canonical = validPoseNames.find((p) => p.toLowerCase() === matchedText);

        // Only strip the tag (and resolve `pose`) when it actually matches a
        // configured pose — otherwise a reply that happens to start with an
        // unrelated bracketed aside (e.g. "[Note] ...") would have that
        // content silently eaten. Resolved to the pose's actual stored name
        // (not the LLM's typed casing), since callers look it up with an
        // exact match against the stored pose list.
        if (canonical !== undefined) {
            pose = canonical;
            remaining = remaining.slice(poseMatch[0].length);
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
