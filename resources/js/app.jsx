import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import './app.css';

createRoot(document.getElementById('root')).render(
    <StrictMode>
        <div className="flex items-center justify-center min-h-screen bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC]">
            <h1 className="text-2xl font-medium">Hello from React</h1>
        </div>
    </StrictMode>
);
