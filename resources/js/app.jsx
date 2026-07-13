import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import '../css/app.css';
import { ThemeProvider } from './contexts/ThemeContext.jsx';
import { Ziggy } from './ziggy.js';
import AuthenticatedLayout from './layouts/AuthenticatedLayout.jsx';
import AssistantLayout from './layouts/AssistantLayout.jsx';
import LoginPage from './pages/LoginPage.jsx';
import AssistantsPage from './pages/AssistantsPage.jsx';
import CreateAssistantPage from './pages/CreateAssistantPage.jsx';
import EditAssistantPage from './pages/EditAssistantPage.jsx';
import ConversationsPage from './pages/ConversationsPage.jsx';
import ChatPage from './pages/ChatPage.jsx';
import LorebookPage from './pages/LorebookPage.jsx';
import PromptPage from './pages/PromptPage.jsx';
import SettingsPage from './pages/SettingsPage.jsx';
import ProvidersPage from './pages/ProvidersPage.jsx';
globalThis.Ziggy = Ziggy;

createRoot(document.getElementById('root')).render(
    <StrictMode>
        <ThemeProvider>
            <BrowserRouter>
                <Routes>
                    <Route path="/login" element={<LoginPage />} />
                    <Route element={<AuthenticatedLayout />}>
                        {/* Assistant management */}
                        <Route path="/assistants" element={<AssistantsPage />} />
                        <Route path="/assistants/create" element={<CreateAssistantPage />} />
                        <Route path="/assistants/:assistantId/edit" element={<EditAssistantPage />} />

                        {/* Assistant-scoped routes */}
                        <Route path="/assistants/:assistantId" element={<AssistantLayout />}>
                            <Route path="conversations" element={<ConversationsPage />} />
                            <Route path="conversations/:id" element={<ChatPage />} />
                            <Route path="prompt" element={<PromptPage />} />
                            <Route path="lorebook" element={<LorebookPage />} />
                            <Route path="settings" element={<SettingsPage />} />
                            <Route path="providers" element={<ProvidersPage />} />
                        </Route>
                    </Route>
                    <Route path="*" element={<Navigate to="/assistants" replace />} />
                </Routes>
            </BrowserRouter>
        </ThemeProvider>
    </StrictMode>
);