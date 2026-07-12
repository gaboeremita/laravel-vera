import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import '../css/app.css';
import { ThemeProvider } from './contexts/ThemeContext.jsx';
import AuthenticatedLayout from './layouts/AuthenticatedLayout.jsx';
import LoginPage from './pages/LoginPage.jsx';
import ConversationsPage from './pages/ConversationsPage.jsx';
import ChatPage from './pages/ChatPage.jsx';
import LorebookPage from './pages/LorebookPage.jsx';
import SettingsPage from './pages/SettingsPage.jsx';
import { Ziggy } from './ziggy.js';
import ProvidersPage from './pages/ProvidersPage.jsx';
globalThis.Ziggy = Ziggy;

createRoot(document.getElementById('root')).render(
    <StrictMode>
        <ThemeProvider>
            <BrowserRouter>
                <Routes>
                    <Route path="/login" element={<LoginPage />} />
                    <Route element={<AuthenticatedLayout />}>
                        <Route path="/conversations" element={<ConversationsPage />} />
                        <Route path="/conversations/:id" element={<ChatPage />} />
                        <Route path="/lorebook" element={<LorebookPage />} />
                        <Route path="/settings" element={<SettingsPage />} />
                        <Route path="/providers" element={<ProvidersPage />} />
                    </Route>
                    <Route path="*" element={<Navigate to="/conversations" replace />} />
                </Routes>
            </BrowserRouter>
        </ThemeProvider>
    </StrictMode>
);
