import { memo } from 'react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import ThinkingBlock from "./ThinkingBlock";

function InlineText({ text }) {
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

const markdownComponents = {
    p: ({ children }) => {
        const hasBlock = Array.isArray(children)
            ? children.some((child) => child?.type === 'pre' || child?.type === 'div')
            : children?.type === 'pre' || children?.type === 'div';

        return hasBlock
            ? <div className="mb-2 last:mb-0">{children}</div>
            : <p className="mb-2 last:mb-0">{children}</p>;
    },
    h1: ({ children }) => <h1 className="text-accent font-bold text-base tracking-[0.05em] mt-3 mb-1">{children}</h1>,
    h2: ({ children }) => <h2 className="text-accent font-bold text-sm tracking-[0.05em] mt-3 mb-1">{children}</h2>,
    h3: ({ children }) => <h3 className="text-accent-3 font-bold text-sm mt-2 mb-1">{children}</h3>,
    strong: ({ children }) => <strong className="font-bold text-fg-1">{children}</strong>,
    em: ({ children }) => <em className="italic text-fg-2">{children}</em>,
    code: ({ inline, children }) =>
        inline ? (
            <code className="bg-bg-1 border border-line-1 text-accent text-[0.8em] px-1 py-0.5 rounded font-mono">
                {children}
            </code>
        ) : (
            <pre className="bg-bg-1 border border-line-1 text-accent text-[0.8em] p-3 my-2 overflow-x-auto font-mono">
                <code>{children}</code>
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

            {isAssistant && msg.thinking && <ThinkingBlock content={msg.thinking} />}

            {msg.image && (
                <img
                    src={msg.image}
                    alt="User attachment"
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
                {msg.loading && <span className="cursor-effect text-danger">_</span>}
            </div>
        </div>
    );
}

export default memo(ChatMessage);
