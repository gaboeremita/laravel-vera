/**
 * Parses message text and renders formatted segments.
 * [bracketed text] → bold
 * *asterisk text* → italic, different color
 */
export function formatMessage(text) {
    const parts = [];
    // Matches [bracketed] or *asterisked* segments
    const regex = /(\[[^\]]+\]|\*[^*]+\*|\([^)]+\))/g;
    let lastIndex = 0;
    let match;

    while ((match = regex.exec(text)) !== null) {
        // Plain text before the match
        if (match.index > lastIndex) {
            parts.push(
                <span key={lastIndex}>{text.slice(lastIndex, match.index)}</span>
            );
        }

        const segment = match[0];

        if (segment.startsWith("[")) {
            // Bracketed text → bold
            parts.push(
                <span key={match.index} className="font-bold text-accent">
          {segment}
        </span>
            );
        } else if (segment.startsWith("*")) {
            // Asterisked text → italic, muted color
            parts.push(
                <span key={match.index} className="italic text-fg-2">
          {segment.slice(1, -1)}
        </span>
            );
        } else if (segment.startsWith("(")) {
            // Parenthesized text → inner thoughts
            parts.push(
                <span key={match.index} className="italic text-accent-3">
                  {segment}
                </span>
            );
        }

        lastIndex = match.index + segment.length;
    }

    // Remaining plain text
    if (lastIndex < text.length) {
        parts.push(<span key={lastIndex}>{text.slice(lastIndex)}</span>);
    }

    return parts;
}