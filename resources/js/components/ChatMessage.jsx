import { formatMessage } from "../utils/formatMessage.jsx";
import ThinkingBlock from "./ThinkingBlock";

export default function ChatMessage({ msg }) {
    const isVera = msg.role === "assistant";
    return (
        <div className="mb-4 leading-normal">
      <span
          className={`font-bold text-[0.7rem] tracking-[0.1em] uppercase ${
              isVera ? "text-danger" : "text-fg-3"
          }`}
      >
        {isVera ? "VERA>" : "USER>"}
      </span>
            {isVera && msg.thinking && <ThinkingBlock content={msg.thinking} />}
            {msg.image && (
                <img
                    src={msg.image}
                    alt="User attachment"
                    className="mt-1 mb-2 max-h-48 rounded border border-line-1"
                />
            )}
            <div
                className={`mt-0.5 text-sm whitespace-pre-wrap ${
                    isVera ? "text-fg-1" : "text-fg-2"
                }`}
            >
                {formatMessage(msg.content)}
                {msg.loading && (
                    <span className="cursor-effect text-danger">_</span>
                )}
            </div>
        </div>
    );
}