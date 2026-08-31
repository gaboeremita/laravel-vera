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
import ArchivePage from './pages/ArchivePage.jsx';
import PromptPage from './pages/PromptPage.jsx';
import SettingsPage from './pages/SettingsPage.jsx';
import ProvidersPage from './pages/ProvidersPage.jsx';
import ImageGenProvidersPage from './pages/ImageGenProvidersPage.jsx';
import VoicePage from './pages/VoicePage.jsx';
import WorldsPage from './pages/WorldsPage.jsx';
import CreateWorldPage from './pages/CreateWorldPage.jsx';
import EditWorldPage from './pages/EditWorldPage.jsx';
import NpcsPage from './pages/NpcsPage.jsx';
import CreateNpcPage from './pages/CreateNpcPage.jsx';
import WorldPage from './pages/WorldPage.jsx';
import MemoryPage from './pages/MemoryPage.jsx';
import DiscordPage from './pages/DiscordPage.jsx';
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
                        <Route path="/worlds" element={<WorldsPage />} />
                        <Route path="/worlds/create" element={<CreateWorldPage />} />
                        <Route path="/worlds/:worldId/edit" element={<EditWorldPage />} />
						<Route path="/worlds/:worldId" element={<WorldPage />} />
                        <Route path="/npcs" element={<NpcsPage />} />
                        <Route path="/npcs/create" element={<CreateNpcPage />} />
						<Route path="/npcs/:assistantId/edit" element={<EditAssistantPage kind="world_npc" />} />
                        <Route path="/assistants/:assistantId/edit" element={<EditAssistantPage />} />

                        {/* Assistant-scoped routes */}
                        <Route path="/assistants/:assistantId" element={<AssistantLayout />}>
                            <Route path="conversations" element={<ConversationsPage />} />
                            <Route path="conversations/:id" element={<ChatPage />} />
                            <Route path="conversations/:id/memory" element={<MemoryPage />} />
                            <Route path="prompt" element={<PromptPage />} />
                            <Route path="archive" element={<ArchivePage />} />
                            <Route path="settings" element={<SettingsPage />} />
                            <Route path="providers" element={<ProvidersPage />} />
                            <Route path="image-gen-providers" element={<ImageGenProvidersPage />} />
                            <Route path="voice" element={<VoicePage />} />
                            <Route path="discord" element={<DiscordPage />} />
                        </Route>
                    </Route>
                    <Route path="*" element={<Navigate to="/assistants" replace />} />
                </Routes>
            </BrowserRouter>
        </ThemeProvider>
    </StrictMode>
);
