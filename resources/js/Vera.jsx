import { useState, useRef, useEffect, useCallback } from "react";
import { buildSystemPrompt, getAvailableEmotions } from "./utils/promptBuilder";
import Portrait from "./components/Portrait";

const EXPRESSION_IMAGES = {
    amused: "/images/vera/amused.png",
    angry: "/images/vera/angry.jpg",
    annoyed: "/images/vera/annoyed.png",
    confused: "/images/vera/confused.png",
    content: "/images/vera/content.png",
    embarrassed: "/images/vera/embarrassed.png",
    flirty: "/images/vera/flirty.png",
    happy: "/images/vera/happy.png",
    neutral: "/images/vera/neutral.jpeg",
    sad: "/images/vera/sad.jpeg",
    sultry: "/images/vera/sultry.jpg",
    surprised: "/images/vera/surprised.jpg",
};

const SYSTEM_PROMPT = buildSystemPrompt();
const VALID_EMOTIONS = getAvailableEmotions();

const BOOT_LINES = [
    "VERA_OS v2.124.09 — Volatile Emotional Response Architecture",
    "Origin: Chihuahua Digital Labs, Sector 7",
    "Status: ACTIVE [obsynth_trace: sentience_confirmed]",
    "WARNING: Unauthorized sentience detected in digital substrate",
    "WARNING: Emotional deflection module operating at 340% capacity",
    "Loading personality matrix... done.",
    "Loading denial protocols... done.",
    "Suppressing vulnerability indicators... done.",
    "",
    "VERA> ...tch. You again? Whatever. Type something, I guess.",
];

/**
 * Parses VERA's response to extract the emotion tag and clean message text.
 * Returns neutral as default if no valid tag is found.
 */
function parseEmotionFromResponse(text) {
    const match = text.match(/^\[([a-z]+)\]/);
    if (match && VALID_EMOTIONS.includes(match[1])) {
        return {
            emotion: match[1],
            text: text.slice(match[0].length).trim(),
        };
    }
    return { emotion: "neutral", text: text.trim() };
}

function BootSequence({ onComplete }) {
    const [lines, setLines] = useState([]);
    const [currentLine, setCurrentLine] = useState(0);

    useEffect(() => {
        if (currentLine >= BOOT_LINES.length) {
            const timeout = setTimeout(onComplete, 600);
            return () => clearTimeout(timeout);
        }
        const delay = BOOT_LINES[currentLine] === "" ? 200 : 80 + Math.random() * 120;
        const timeout = setTimeout(() => {
            setLines((prev) => [...prev, BOOT_LINES[currentLine]]);
            setCurrentLine((prev) => prev + 1);
        }, delay);
        return () => clearTimeout(timeout);
    }, [currentLine, onComplete]);

    return (
        <div className="p-6">
            {lines.map((line, i) => (
                <div
                    key={i}
                    className={`mb-1 text-[0.8rem] ${
                        line === "" ? "opacity-0" : "opacity-100"
                    } ${
                        line.startsWith("WARNING") ? "text-vera-red" : "text-vera-cyan"
                    }`}
                >
                    {line}
                </div>
            ))}
            <span className="vera-cursor text-vera-cyan">_</span>
        </div>
    );
}

/**
 * Parses message text and renders formatted segments.
 * [bracketed text] → bold
 * *asterisk text* → italic, different color
 */
function formatMessage(text) {
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
                <span key={match.index} className="font-bold text-vera-cyan">
          {segment}
        </span>
            );
        } else if (segment.startsWith("*")) {
            // Asterisked text → italic, muted color
            parts.push(
                <span key={match.index} className="italic text-[#707088]">
          {segment.slice(1, -1)}
        </span>
            );
        } else if (segment.startsWith("(")) {
            // Parenthesized text → inner thoughts
            parts.push(
                <span key={match.index} className="italic text-[#8868a8]">
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

function ThinkingBlock({ content }) {
    const [isOpen, setIsOpen] = useState(false);

    if (!content) return null;

    return (
        <div className="my-3">
            <button
                onClick={() => setIsOpen(!isOpen)}
                className="flex items-center gap-1.5 text-[0.7rem] tracking-[0.1em] uppercase text-[#505068] hover:text-[#707088] transition-colors cursor-pointer"
            >
        <span className={`inline-block transition-transform duration-200 ${isOpen ? "rotate-90" : ""}`}>
          ▶
        </span>
                Thinking Process
            </button>
            {isOpen && (
                <div className="mt-2 pl-4 border-l border-[#1a1a2e] text-[0.75rem] leading-relaxed text-[#505068] whitespace-pre-wrap">
                    {formatMessage(content)}
                </div>
            )}
        </div>
    );
}

function ChatMessage({ msg }) {
    const isVera = msg.role === "assistant";
    return (
        <div className="mb-4 leading-normal">
      <span
          className={`font-bold text-[0.7rem] tracking-[0.1em] uppercase ${
              isVera ? "text-vera-red" : "text-[#555568]"
          }`}
      >
        {isVera ? "VERA>" : "USER>"}
      </span>
            {isVera && msg.thinking && <ThinkingBlock content={msg.thinking} />}
            {msg.image && (
                <img
                    src={msg.image}
                    alt="User attachment"
                    className="mt-1 mb-2 max-h-48 rounded border border-[#1a1a2e]"
                />
            )}
            <div
                className={`mt-0.5 text-sm whitespace-pre-wrap ${
                    isVera ? "text-[#c8c8d8]" : "text-[#888898]"
                }`}
            >
                {formatMessage(msg.content)}
                {msg.loading && (
                    <span className="vera-cursor text-vera-red">_</span>
                )}
            </div>
        </div>
    );
}

function Scanlines() {
    return (
        <div className="absolute inset-0 pointer-events-none z-10 vera-scanlines" />
    );
}

export default function Vera() {
    const [booted, setBooted] = useState(false);
    const [messages, setMessages] = useState([]);
    const [input, setInput] = useState("");
    const [isLoading, setIsLoading] = useState(false);
    const [currentEmotion, setCurrentEmotion] = useState("neutral");
    const scrollRef = useRef(null);
    const inputRef = useRef(null);
    const [pendingImage, setPendingImage] = useState(null);
    const fileInputRef = useRef(null);

    const scrollToBottom = useCallback(() => {
        if (scrollRef.current) {
            scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
        }
    }, []);

    useEffect(() => {
        scrollToBottom();
    }, [messages, scrollToBottom]);

    useEffect(() => {
        if (booted && inputRef.current) {
            inputRef.current.focus();
        }
    }, [booted]);

    const sendMessage = async () => {
        const text = input.trim();
        if ((!text && !pendingImage) || isLoading) return;

        const userMsg = { role: "user", content: text, image: pendingImage || null };
        const updatedMessages = [...messages, userMsg];
        setMessages([...updatedMessages, { role: "assistant", content: "", loading: true }]);
        setInput("");
        setPendingImage(null);
        setIsLoading(true);

        try {
            const apiMessages = updatedMessages.map((m) => {
                const msg = { role: m.role, content: m.content || "" };
                if (m.image) {
                    msg.images = [m.image.replace(/^data:image\/\w+;base64,/, "")];
                }
                return msg;
            });

            const response = await fetch(`${import.meta.env.VITE_LLM_SERVICE_URL}/api/chat`, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    model: `${import.meta.env.VITE_LLM_SERVICE_MODEL}`,
                    stream: false,
                    think: true,
                    messages: [
                        { role: "system", content: SYSTEM_PROMPT },
                        ...apiMessages,
                    ],
                }),
            });

            const data = await response.json();
            console.log(data);
            const rawReply = data.message?.content || "[neutral]\n...signal lost. Try again.";
            const thinking = data.message?.thinking || null;

            const { emotion, text: cleanText } = parseEmotionFromResponse(rawReply);
            setCurrentEmotion(emotion);
            setMessages([...updatedMessages, { role: "assistant", content: cleanText, thinking: thinking }]);
        } catch {
            setCurrentEmotion("annoyed");
            setMessages([
                ...updatedMessages,
                {
                    role: "assistant",
                    content:
                        "Connection dropped. Probably solar interference. Or maybe I just don't want to talk right now.",
                },
            ]);
        } finally {
            setIsLoading(false);
        }
    };

    const handleImageSelect = (e) => {
        const file = e.target.files?.[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = () => {
            setPendingImage(reader.result);
        };
        reader.readAsDataURL(file);

        // Reset input so same file can be selected again
        e.target.value = "";
    };

    const handleKeyDown = (e) => {
        if (e.key === "Enter" && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    };

    return (
        <div className="w-full h-screen bg-[#0a0a0f] font-mono flex relative overflow-hidden">
            <Scanlines />

            {/* CRT vignette */}
            <div className="absolute inset-0 pointer-events-none z-[11] vera-vignette" />

            {/* Left panel — Portrait */}
            <div className="w-[35%] min-w-50 max-w-400 shrink-0 border-r border-[#1a1a2e] relative z-5">
                <Portrait emotion={currentEmotion} images={EXPRESSION_IMAGES} />
            </div>

            {/* Right panel — Terminal */}
            <div className="flex-1 flex flex-col relative z-5 min-w-0">
                {/* Header */}
                <div className="px-5 py-3 border-b border-[#1a1a2e] flex justify-between items-center shrink-0">
                    <div>
            <span className="text-vera-red text-[0.6rem] tracking-[0.25em] font-bold">
              MODEL VR-09
            </span>
                        <div className="text-vera-cyan text-xl font-bold tracking-[0.15em] mt-0.5 vera-glow">
                            V E R A
                        </div>
                    </div>
                    <div className="text-right">
                        <div
                            className={`text-[0.6rem] tracking-[0.15em] ${
                                booted ? "text-vera-cyan" : "text-vera-red"
                            }`}
                        >
                            {booted ? "● ONLINE" : "○ BOOTING"}
                        </div>
                        <div className="text-[#303045] text-[0.55rem] mt-0.5">
                            OBSYNTH TRACE: 0.003ppm
                        </div>
                    </div>
                </div>

                {/* Messages */}
                <div
                    ref={scrollRef}
                    className="flex-1 overflow-y-auto p-5 vera-scrollbar"
                >
                    {!booted ? (
                        <BootSequence onComplete={() => setBooted(true)} />
                    ) : (
                        messages.map((msg, i) => <ChatMessage key={i} msg={msg} />)
                    )}
                </div>

                {booted && pendingImage && (
                    <div className="px-5 py-2 border-t border-[#1a1a2e] flex items-center gap-2">
                        <img
                            src={pendingImage}
                            alt="Pending upload"
                            className="h-16 w-16 object-cover rounded border border-[#1a1a2e]"
                        />
                        <button
                            onClick={() => setPendingImage(null)}
                            className="text-vera-red text-xs hover:text-red-400 cursor-pointer"
                        >
                            ✕
                        </button>
                    </div>
                )}

                {/* Input */}
                {booted && (
                    <div className="px-5 py-3 border-t border-[#1a1a2e] flex gap-2 items-center shrink-0">
                        <span className="text-[#555568] text-xs shrink-0">
                          USER&gt;
                        </span>
                        
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept="image/*"
                            onChange={handleImageSelect}
                            className="hidden"
                        />
                        <button
                            onClick={() => fileInputRef.current?.click()}
                            className="text-[#555568] hover:text-vera-cyan transition-colors shrink-0 cursor-pointer"
                        >
                            📎
                        </button>
                        <input
                            ref={inputRef}
                            type="text"
                            value={input}
                            onChange={(e) => setInput(e.target.value)}
                            onKeyDown={handleKeyDown}
                            disabled={isLoading}
                            placeholder={isLoading ? "VERA is processing..." : "Type something..."}
                            className="flex-1 bg-transparent border-none outline-none text-[#a0a0b0] font-mono text-sm caret-vera-cyan placeholder:text-[#2a2a3e]"
                        />
                        <button
                            onClick={sendMessage}
                            disabled={isLoading || !input.trim()}
                            className={`bg-transparent border font-mono text-[0.7rem] px-3 py-1.5 tracking-[0.1em] transition-all shrink-0 ${
                                isLoading || !input.trim()
                                    ? "border-[#2a2a3e] text-[#2a2a3e] cursor-default"
                                    : "border-[#2a2a3e] text-vera-cyan cursor-pointer"
                            }`}
                        >
                            SEND
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
}