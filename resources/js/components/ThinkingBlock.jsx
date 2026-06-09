import { useState } from "react";
import { formatMessage } from "../utils/formatMessage.jsx";

export default function ThinkingBlock({ content }) {
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