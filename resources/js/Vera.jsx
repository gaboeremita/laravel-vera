import { useState, useRef, useEffect, useCallback } from "react";
import { buildSystemPrompt } from "./utils/promptBuilder";
import { parseEmotionFromResponse } from "./utils/parsers";
import Portrait from "./components/Portrait";
import ChatMessage from "./components/ChatMessage";
import BootSequence from "./components/BootSequence";
import Scanlines from "./components/Scanlines";
import {api} from "./utils/api.js";
import { useConversations } from "./hooks/useConversations";
import ConversationList from "./components/ConversationList.jsx";

const SYSTEM_PROMPT = buildSystemPrompt();

export default function Vera() {
    const [booted, setBooted] = useState(false);
    const [input, setInput] = useState("");
    const [isLoading, setIsLoading] = useState(false);
    const [currentEmotion, setCurrentEmotion] = useState("neutral");
    const scrollRef = useRef(null);
    const inputRef = useRef(null);
    const [pendingImage, setPendingImage] = useState(null);
    const fileInputRef = useRef(null);
    const [isAuthenticated, setIsAuthenticated] = useState(false);
    const [loginStep, setLoginStep] = useState('email');
    const [loginEmail, setLoginEmail] = useState('');
    const {
        conversations,
        conversationId,
        messages,
        setMessages,
        setConversationId,
        fetchConversations,
        selectConversation,
        createNewConversation,
    } = useConversations();

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

    useEffect(() => {
        api.get('/api/user')
            .then((res) => {
                if (res.ok) setIsAuthenticated(true);
            })
            .catch(() => {});
    }, []);

    const sendMessage = async () => {
        const text = input.trim();
        if ((!text && !pendingImage) || isLoading) return;

        const userMsg = {
            role: "user",
            content: text,
            image: pendingImage || null,
        };
        const updatedMessages = [...messages, userMsg];
        setMessages([
            ...updatedMessages,
            { role: "assistant", content: "", loading: true },
        ]);
        setInput("");
        setPendingImage(null);
        setIsLoading(true);

        try {
            const apiMessages = updatedMessages.map((m) => {
                const msg = { role: m.role, content: m.content || "" };
                if (m.image) {
                    msg.images = [
                        m.image.replace(/^data:image\/\w+;base64,/, ""),
                    ];
                }
                return msg;
            });

            const response = await api.post(`/api/conversations/${conversationId}/messages`, {
                messages: [
                    { role: "system", content: SYSTEM_PROMPT },
                    ...apiMessages,
                ],
            });

            const data = await response.json();
            setConversationId(data.conversation_id);
            const rawReply = data.content || "[neutral]\n...signal lost. Try again.";
            const thinking = data.thinking || null;

            const { emotion, text: cleanText } =
                parseEmotionFromResponse(rawReply);
            setCurrentEmotion(emotion);
            setMessages([
                ...updatedMessages,
                { role: "assistant", content: cleanText, thinking: thinking },
            ]);
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

    const handleLogin = async () => {
        const text = input.trim();
        if (!text) return;

        if (loginStep === 'email') {
            setLoginEmail(text);
            setMessages((prev) => [...prev,
                { role: "system", content: `> ${text}` },
            ]);
            setLoginStep('password');
            setInput('');
            return;
        }

        if (loginStep === 'password') {
            setMessages((prev) => [...prev,
                { role: "system", content: "> ********" },
                { role: "system", content: "> Authenticating..." },
            ]);
            setInput('');
            setLoginStep('authenticating');

            try {
                await api.getCsrfCookie();
                const res = await api.login(loginEmail, text);

                if (res.ok) {
                    setMessages((prev) => [...prev,
                        { role: "system", content: "> Access granted." },
                    ]);
                    setIsAuthenticated(true);
                    setLoginStep(null);
                    setTimeout(() => setMessages([]), 1500);
                } else {
                    setMessages((prev) => [...prev,
                        { role: "system", content: "> Authentication failed. Try again." },
                    ]);
                    setLoginStep('email');
                }
            } catch {
                setMessages((prev) => [...prev,
                    { role: "system", content: "> Connection error. Try again." },
                ]);
                setLoginStep('email');
            }
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

    const bootComplete = useCallback(() => {
        setBooted(true);
        fetchConversations();
    }, []);

    const handleKeyDown = (e) => {
        if (e.key === "Enter" && !e.shiftKey) {
            e.preventDefault();
            if (!isAuthenticated) {
                handleLogin();
            } else {
                sendMessage();
            }
        }
    };

    return (
        <div className="w-full h-screen bg-[#0a0a0f] font-mono flex relative overflow-hidden">
            <Scanlines />

            {/* CRT vignette */}
            <div className="absolute inset-0 pointer-events-none z-[11] vera-vignette" />

            {/* Left panel — Portrait */}
            <div className="w-[35%] min-w-50 max-w-400 shrink-0 border-r border-[#1a1a2e] relative z-5">
                <Portrait emotion={currentEmotion} authenticated={isAuthenticated} />
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
                <div ref={scrollRef} className="flex-1 overflow-y-auto p-5 vera-scrollbar">
                    {!isAuthenticated ? (
                        <>
                            <div className="text-vera-cyan text-[0.8rem] whitespace-pre mb-6">
                                {`
 ██╗   ██╗███████╗██████╗  █████╗ 
 ██║   ██║██╔════╝██╔══██╗██╔══██╗
 ██║   ██║█████╗  ██████╔╝███████║
 ╚██╗ ██╔╝██╔══╝  ██╔══██╗██╔══██║
  ╚████╔╝ ███████╗██║  ██║██║  ██║
   ╚═══╝  ╚══════╝╚═╝  ╚═╝╚═╝  ╚═╝
                                `}
                            </div>
                            <div className="text-[#555568] text-[0.8rem] mb-2">Volatile Emotional Response Architecture v2.124.09</div>
                            <div className="text-[#555568] text-[0.8rem] mb-6">Secure connection required.</div>
                            {messages.length === 0 && (
                                <div className="text-vera-cyan text-[0.8rem]">{"> Enter your credentials in the text input at the bottom of the screen:"}</div>
                            )}
                            {messages.map((msg, i) => (
                                <div key={i} className="text-[0.8rem] text-vera-cyan">{msg.content}</div>
                            ))}
                        </>
                    ) : !booted ? (
                        <BootSequence onComplete={bootComplete} />
                    ) : !conversationId ? (
                        <ConversationList
                            conversations={conversations}
                            onSelect={selectConversation}
                            onNew={createNewConversation}
                        />
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
                {(booted || !isAuthenticated) && (
                    <div className="px-5 py-3 border-t border-[#1a1a2e] flex gap-2 items-center shrink-0">
                        {isAuthenticated && (
                            <>
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
                            </>
                        )}
                        {!isAuthenticated && (
                            <span className="text-vera-cyan text-xs shrink-0 whitespace-nowrap">
                                {loginStep === 'password' ? '> Enter password:' : '> Enter email:'}
                            </span>
                        )}
                        <input
                            ref={inputRef}
                            type={loginStep === 'password' ? 'password' : 'text'}
                            value={input}
                            onChange={(e) => setInput(e.target.value)}
                            onKeyDown={handleKeyDown}
                            disabled={isLoading}
                            placeholder={
                                !isAuthenticated
                                    ? (loginStep === 'password' ? 'Enter password...' : 'Enter email...')
                                    : (isLoading ? "VERA is processing..." : "Type something...")
                            }
                            className="flex-1 bg-transparent border-none outline-none text-[#a0a0b0] font-mono text-sm caret-vera-cyan placeholder:text-[#2a2a3e]"
                        />
                        {isAuthenticated && (
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
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}
