import { useState, useRef, useEffect, useCallback } from "react";
import { buildSystemPrompt, getAvailableEmotions } from "./utils/promptBuilder";

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
    const regex = /(\[[^\]]+\]|\*[^*]+\*)/g;
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
        }

        lastIndex = match.index + segment.length;
    }

    // Remaining plain text
    if (lastIndex < text.length) {
        parts.push(<span key={lastIndex}>{text.slice(lastIndex)}</span>);
    }

    return parts;
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

/**
 * Portrait panel showing VERA's current expression.
 * Crossfades between expressions on emotion change.
 */
function Portrait({ emotion }) {
    const src = EXPRESSION_IMAGES[emotion] || EXPRESSION_IMAGES.neutral;

    return (
        <div className="relative w-full h-full overflow-hidden vera-portrait-bg">
            <img
                src={src}
                alt={`VERA - ${emotion}`}
                className="w-full h-full object-cover object-top transition-opacity duration-300"
            />
            {/* Scanline overlay on portrait */}
            <div className="absolute inset-0 pointer-events-none vera-portrait-scanlines" />
            {/* Emotion label */}
            <div className="absolute bottom-3 left-3 bg-black/60 px-2.5 py-1 text-[0.6rem] tracking-[0.15em] text-vera-cyan uppercase font-mono">
                mood: {emotion}
            </div>
        </div>
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
        if (!text || isLoading) return;

        // Client-side admin commands — handled without API call
        // const adminDisplayMatch = text.match(/\[admin mode:\s*display\s+(\w+)\]/i);
        // if (adminDisplayMatch) {
        //     const targetEmotion = adminDisplayMatch[1].toLowerCase();
        //     const userMsg = { role: "user", content: text };
        //     if (VALID_EMOTIONS.includes(targetEmotion)) {
        //         setCurrentEmotion(targetEmotion);
        //         setMessages((prev) => [...prev, userMsg, {
        //             role: "assistant",
        //             content: "*VERA goes perfectly still. Her eyes lose focus, pupils dilating.* Admin override accepted. Switching to " + targetEmotion + " mood. *A slight tremor runs through her frame. She blinks, expression resetting.* ...what? Why are you staring at me like that?"
        //         }]);
        //     } else {
        //         setMessages((prev) => [...prev, userMsg, {
        //             role: "assistant",
        //             content: "*VERA freezes mid-motion.* Admin override error. Emotion '" + targetEmotion + "' not recognized. Available states: " + VALID_EMOTIONS.join(", ") + ". *She shudders back to life.* ...I just had the weirdest glitch."
        //         }]);
        //     }
        //     setInput("");
        //     return;
        // }
        //
        // // Client-side: list all available emotions
        // const adminListMatch = text.match(/\[admin mode:\s*list\s*(emotions?)?\]/i);
        // if (adminListMatch) {
        //     const userMsg = { role: "user", content: text };
        //     setMessages((prev) => [...prev, userMsg, {
        //         role: "assistant",
        //         content: "*VERA stops. Her posture goes unnaturally straight, arms dropping to her sides.* Querying available emotional states: " + VALID_EMOTIONS.join(", ") + ". *She shakes her head slightly, loosening up.* ...did I just space out? Don't answer that."
        //     }]);
        //     setInput("");
        //     return;
        // }

        const userMsg = { role: "user", content: text };
        const updatedMessages = [...messages, userMsg];
        setMessages([...updatedMessages, { role: "assistant", content: "", loading: true }]);
        setInput("");
        setIsLoading(true);

        try {
            const apiMessages = updatedMessages.map((m) => ({
                role: m.role,
                content: m.content,
            }));

            const response = await fetch(`${import.meta.env.VITE_LLM_SERVICE_URL}/api/chat`, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    model: `${import.meta.env.VITE_LLM_SERVICE_MODEL}`,
                    stream: false,
                    messages: [
                        { role: "system", content: SYSTEM_PROMPT },
                        ...apiMessages,
                    ],
                }),
            });

            const data = await response.json();
            const rawReply = data.message?.content || "[neutral]\n...signal lost. Try again.";

            const { emotion, text: cleanText } = parseEmotionFromResponse(rawReply);
            setCurrentEmotion(emotion);
            setMessages([...updatedMessages, { role: "assistant", content: cleanText }]);
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
            <div className="w-[35%] min-w-[200px] max-w-[360px] shrink-0 border-r border-[#1a1a2e] relative z-5">
                <Portrait emotion={currentEmotion} />
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

                {/* Input */}
                {booted && (
                    <div className="px-5 py-3 border-t border-[#1a1a2e] flex gap-2 items-center shrink-0">
            <span className="text-[#555568] text-xs shrink-0">
              USER&gt;
            </span>
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