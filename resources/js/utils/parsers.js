function unquoteTagValue(value) {
    const trimmed = value.trim();
    if (trimmed.length < 2) return trimmed;

    const first = trimmed[0];
    const last = trimmed[trimmed.length - 1];
    if ((first === "'" && last === "'") || (first === '"' && last === '"')) {
        return trimmed.slice(1, -1).trim();
    }

    return trimmed;
}

function cleanTaggedText(text) {
    return text
        .replace(/[ \t]+$/gm, '')
        .replace(/^[ \t]+/gm, '')
        .replace(/[ \t]{2,}/g, ' ')
        .replace(/\s+([,.;!?])/g, '$1')
        .replace(/(?:\r?\n){3,}/g, '\n\n')
        .trim();
}

/**
 * Extracts every identified metadata tag regardless of position. OOC is a
 * conversation protocol rather than response metadata, so it remains visible.
 */
export function extractResponseTags(text) {
    const tags = {};
    const remaining = text.replace(/\[([a-z][a-z0-9 _-]*):\s*([^\]\r\n]*)\]/giu, (match, rawIdentifier, rawValue) => {
        const identifier = rawIdentifier.trim().replace(/\s+/g, ' ').toLowerCase();
        if (identifier === 'ooc') return match;

        (tags[identifier] ||= []).push(unquoteTagValue(rawValue));
        return '';
    });

    return { tags, text: cleanTaggedText(remaining) };
}

/**
 * Parses identified emotion metadata and legacy bare emotion tags from saved
 * messages. New model output uses [emotion: name].
 */
export function parseEmotionFromResponse(text, validEmotions = []) {
    const extracted = extractResponseTags(text);
    let remaining = extracted.text;
    let emotion = null;
    let intimate = (extracted.tags.intimate || []).some((value) => ['1', 'true', 'yes', 'on', 'intimate'].includes(value.toLowerCase()));

    const identifiedEmotion = extracted.tags.emotion?.[0];
    if (identifiedEmotion) {
        const canonical = validEmotions.find((candidate) => candidate.toLowerCase() === identifiedEmotion.toLowerCase());
        if (canonical !== undefined || validEmotions.length === 0) emotion = canonical ?? identifiedEmotion;
    }

    remaining = remaining.replace(/\[([^\]\r\n]+)\]/gu, (match, rawValue) => {
        const value = rawValue.trim();
        if (/^ooc(?:\s*:)?$/i.test(value)) return match;

        if (value.toLowerCase() === 'intimate') {
            intimate = true;
            return '';
        }

        const canonical = validEmotions.find((candidate) => candidate.toLowerCase() === value.toLowerCase());
        if (canonical !== undefined || (validEmotions.length === 0 && /^[a-zA-Z]+$/.test(value))) {
            emotion ??= canonical ?? value.toLowerCase();
            return '';
        }

        return match;
    });

    emotion ??= intimate ? 'seduced' : 'default';

    return { emotion, intimate, text: cleanTaggedText(remaining) };
}

/**
 * Parses identified pose metadata and legacy bare pose tags from saved
 * messages. New model output uses [pose: exact name].
 */
export function parsePoseFromResponse(text, validPoseNames = []) {
    const extracted = extractResponseTags(text);
    let remaining = extracted.text;
    let pose = null;

    const identifiedPose = extracted.tags.pose?.[0];
    if (identifiedPose) {
        pose = validPoseNames.find((candidate) => candidate.toLowerCase() === identifiedPose.toLowerCase()) ?? null;
    }

    remaining = remaining.replace(/\[([^\]\r\n]+)\]/gu, (match, rawValue) => {
        const value = rawValue.trim();
        if (/^ooc(?:\s*:)?$/i.test(value)) return match;

        const canonical = validPoseNames.find((candidate) => candidate.toLowerCase() === value.toLowerCase());
        if (canonical !== undefined) {
            pose ??= canonical;
            return '';
        }

        return match;
    });

    return { pose, text: cleanTaggedText(remaining) };
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
