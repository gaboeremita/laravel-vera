import { memo } from 'react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import ThinkingBlock from "./ThinkingBlock";
import VoiceInstructionsBlock from "./VoiceInstructionsBlock";
import AgentToolCallsTrace from "./AgentToolCallsTrace";
import veraAvatar from '../../images/vera-avatar.png';

function InlineText({ text }) {
    if (!text) return null;

    // Split on VERA-specific patterns: *actions* and (thoughts)
    const parts = [];
    const regex = /(\*[^*]+\*|\([^)]+\))/g;
    let lastIndex = 0;
    let match;

    while ((match = regex.exec(text)) !== null) {
        if (match.index > lastIndex) {
            parts.push(<span key={lastIndex}>{text.slice(lastIndex, match.index)}</span>);
        }

        const segment = match[0];

        if (segment.startsWith('*')) {
            parts.push(
                <span key={match.index} className="italic text-fg-2">
                    {segment.slice(1, -1)}
                </span>
            );
        } else {
            parts.push(
                <span key={match.index} className="italic text-accent-3">
                    {segment}
                </span>
            );
        }

        lastIndex = match.index + segment.length;
    }

    if (lastIndex < text.length) {
        parts.push(<span key={lastIndex}>{text.slice(lastIndex)}</span>);
    }

    return <>{parts}</>;
}

function containsBlockElement(node) {
    if (node == null || typeof node !== 'object') {
        return false;
    }

    if (Array.isArray(node)) {
        return node.some(containsBlockElement);
    }

    if (node.type === 'pre' || node.type === 'div') {
        return true;
    }

    return containsBlockElement(node.props?.children);
}

const markdownComponents = {
    p: ({ children }) => {
        return containsBlockElement(children)
            ? <div className="mb-2 last:mb-0">{children}</div>
            : <p className="mb-2 last:mb-0">{children}</p>;
    },
    h1: ({ children }) => <h1 className="text-accent font-bold text-base tracking-[0.05em] mt-3 mb-1">{children}</h1>,
    h2: ({ children }) => <h2 className="text-accent font-bold text-sm tracking-[0.05em] mt-3 mb-1">{children}</h2>,
    h3: ({ children }) => <h3 className="text-accent-3 font-bold text-sm mt-2 mb-1">{children}</h3>,
    strong: ({ children }) => <strong className="font-bold text-fg-1">{children}</strong>,
    em: ({ children }) => <em className="italic text-fg-2">{children}</em>,
    // react-markdown v10 no longer passes an `inline` prop to `code` — it never
    // did in this version, so `inline ? ... : <pre>` always took the block
    // branch. Instead, `code` and `pre` are separate components: a fenced block
    // is `pre > code` in the parsed tree; inline code is a bare `code` with no
    // `pre` ancestor at all, so there's nothing to distinguish inside `code` itself.
    code: ({ children }) => (
        <code className="bg-bg-1 border border-line-1 text-accent text-[0.8em] px-1 py-0.5 rounded font-mono">
            {children}
        </code>
    ),
    pre: ({ children }) => (
        <pre className="bg-bg-1 border border-line-1 text-accent text-[0.8em] p-3 my-2 overflow-x-auto font-mono [&>code]:border-0 [&>code]:bg-transparent [&>code]:p-0">
            {children}
        </pre>
    ),
    ul: ({ children }) => <ul className="list-disc list-inside space-y-0.5 my-1 text-fg-1">{children}</ul>,
    ol: ({ children }) => <ol className="list-decimal list-inside space-y-0.5 my-1 text-fg-1">{children}</ol>,
    li: ({ children }) => <li className="text-fg-1">{children}</li>,
    blockquote: ({ children }) => (
        <blockquote className="border-l-2 border-accent/50 pl-3 my-2 text-fg-2 italic">{children}</blockquote>
    ),
    table: ({ children }) => (
        <div className="overflow-x-auto my-2">
            <table className="w-full text-sm border-collapse">{children}</table>
        </div>
    ),
    thead: ({ children }) => <thead className="text-accent border-b border-line-2">{children}</thead>,
    th: ({ children }) => <th className="text-left px-3 py-1 text-[0.7rem] tracking-[0.1em] uppercase">{children}</th>,
    td: ({ children }) => <td className="px-3 py-1 border-b border-line-1 text-fg-1">{children}</td>,
    hr: () => <hr className="border-t border-line-1 my-3" />,
    a: ({ href, children }) => (
        <a href={href} className="text-accent underline hover:text-accent/70 transition-colors" target="_blank" rel="noreferrer">
            {children}
        </a>
    ),
};

function ChatMessage({ msg, assistantName = 'ASSISTANT' }) {
    const isAssistant = msg.role === 'assistant';

    return (
        <div className="mb-4 leading-normal">
            <span className={`font-bold text-[0.7rem] tracking-[0.1em] uppercase ${isAssistant ? 'text-danger' : 'text-fg-3'}`}>
                {isAssistant ? `${assistantName.toUpperCase()}>` : 'USER>'}
            </span>

            {isAssistant && msg.thinking && (
                <ThinkingBlock content={msg.thinking} label={msg.image ? 'Image Prompt' : 'Thinking Process'} />
            )}
            {isAssistant && msg.ttsInstructions && <VoiceInstructionsBlock content={msg.ttsInstructions} />}
            {isAssistant && msg.toolCalls && <AgentToolCallsTrace toolCalls={msg.toolCalls} />}

            {msg.image && (
                <img
                    src={msg.image}
                    alt={isAssistant ? 'Generated image' : 'User attachment'}
                    className="mt-1 mb-2 max-h-48 rounded border border-line-1"
                />
            )}

            <div className={`mt-0.5 text-sm ${isAssistant ? 'text-fg-1' : 'text-fg-2'}`}>
                {isAssistant ? (
                    <ReactMarkdown remarkPlugins={[remarkGfm]} components={markdownComponents}>
                        {msg.content}
                    </ReactMarkdown>
                ) : (
                    <p className="whitespace-pre-wrap">
                        <InlineText text={msg.content} />
                    </p>
                )}
                {msg.loading && (
                    <span className="inline-flex items-center gap-2 align-middle">
                        <span className="thinking w-4 h-4 inline-block">
                            <img src={veraAvatar} alt="" aria-hidden="true" className="w-full h-full object-contain" />
                            <img src={veraAvatar} alt="" aria-hidden="true" className="depth absolute inset-0 w-full h-full object-contain" />
                        </span>
                        <span className="thinking-label">
                            {msg.generatingImage ? 'Generating image…' : `${assistantName} is thinking…`}
                        </span>
                    </span>
                )}
            </div>
        </div>
    );
}

export default memo(ChatMessage);
