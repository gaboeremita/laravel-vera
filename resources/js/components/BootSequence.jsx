import { useState, useEffect } from "react";

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

export default function BootSequence({ onComplete }) {
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
                        line.startsWith("WARNING") ? "text-danger" : "text-accent"
                    }`}
                >
                    {line}
                </div>
            ))}
            <span className="cursor-effect text-accent">_</span>
        </div>
    );
}