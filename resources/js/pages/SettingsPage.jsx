import { useEffect, useState } from 'react';
import { useNavigate, useOutletContext } from 'react-router-dom';
import { route } from 'ziggy-js';
import { useTheme } from '../contexts/ThemeContext.jsx';
import { api } from '../utils/api.js';
import Header from '../components/Header.jsx';
import { getAssistantMenuItems } from '../utils/assistantMenu.jsx';

function CreatorPasswordSection() {
	const [isSet, setIsSet] = useState(null);
	const [password, setPassword] = useState('');
	const [isSaving, setIsSaving] = useState(false);
	const [status, setStatus] = useState(null);

	useEffect(() => {
		const load = async () => {
			const response = await api.get(route('creator-password.show'));
			if (response.ok) setIsSet((await response.json()).isSet);
		};
		void load();
	}, []);

	const save = async (value) => {
		setIsSaving(true);
		const response = await api.put(route('creator-password.update'), { password: value });
		if (response.ok) {
			setIsSet(value !== null);
			setPassword('');
			setStatus({ tone: 'text-success', text: value === null ? 'Creator password cleared.' : 'Creator password saved.' });
		} else {
			const body = await response.json().catch(() => ({}));
			setStatus({ tone: 'text-danger', text: body.errors?.password?.[0] ?? 'Unable to save the creator password.' });
		}
		setIsSaving(false);
	};

	const tooShort = password.length < 8;

	return (
		<div className="border border-line-1 p-4 space-y-3 max-w-xl">
			<div className="flex items-center justify-between gap-4">
				<p className="text-fg-3 text-[0.7rem] tracking-[0.15em] uppercase">Creator mode</p>
				{isSet !== null && (
					<span className={`text-[0.65rem] tracking-[0.1em] ${isSet ? 'text-success' : 'text-fg-3'}`}>{isSet ? 'PASSWORD SET' : 'NO PASSWORD'}</span>
				)}
			</div>
			<p className="text-fg-3 text-xs">
				Type <span className="text-fg-2 font-mono">[creator mode: "your password"]</span> in any conversation in the app to turn it on there. It applies to every assistant and NPC, and the password is never shown or sent to a character.
			</p>
			<div className="flex items-center gap-3">
				<input
					type="password"
					value={password}
					onChange={(event) => { setPassword(event.target.value); setStatus(null); }}
					placeholder={isSet ? 'New creator password' : 'Creator password'}
					autoComplete="new-password"
					aria-label="Creator password"
					className="flex-1 bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
				/>
				<button
					type="button"
					onClick={() => save(password)}
					disabled={tooShort || isSaving}
					className={`text-[0.7rem] tracking-[0.1em] px-4 py-2 transition-colors ${tooShort || isSaving ? 'bg-bg-3 text-fg-3 cursor-default' : 'button-success cursor-pointer'}`}
				>
					{isSaving ? 'SAVING...' : 'SAVE'}
				</button>
				{isSet && (
					<button type="button" onClick={() => save(null)} disabled={isSaving} className="text-danger text-[0.7rem] tracking-[0.1em] cursor-pointer hover:text-fg-1 transition-colors">
						CLEAR
					</button>
				)}
			</div>
			{password.length > 0 && tooShort && <p className="text-fg-3 text-xs">At least 8 characters.</p>}
			{status && <p className={`${status.tone} text-xs`}>{status.text}</p>}
		</div>
	);
}

export default function SettingsPage() {
	const { theme, setTheme, availableThemes } = useTheme();
	const { assistantId } = useOutletContext();
	const [selectedTheme, setSelectedTheme] = useState(theme);
	const [isSaving, setIsSaving] = useState(false);
	const navigate = useNavigate();

	const hasChanges = selectedTheme !== theme;

	const handleSave = async () => {
		setIsSaving(true);
		await api.put(route('settings.update', { assistant: assistantId }), {
			theme: selectedTheme,
		});
		setTheme(selectedTheme);
		setIsSaving(false);
	};

	return (
		<>
			<Header
				menuItems={getAssistantMenuItems(assistantId)}
				onBack={() => navigate(-1)}
			>
				<span className="text-fg-2 text-sm tracking-[0.05em]">Settings</span>
			</Header>

			<div className="flex-1 overflow-y-auto p-5">
				<div className="space-y-6">
					<div className="flex items-center gap-4">
						<label className="text-fg-3 text-[0.7rem] tracking-[0.15em] uppercase">
							Theme:
						</label>
						<select
							value={selectedTheme}
							onChange={(e) => setSelectedTheme(e.target.value)}
							className="bg-bg-1 border border-line-1 text-fg-1 text-[0.75rem] tracking-[0.1em] px-4 py-1.5 cursor-pointer outline-none focus:border-accent transition-colors"
						>
							{availableThemes.map((t) => (
								<option key={t} value={t}>
									{t.toUpperCase()}
								</option>
							))}
						</select>
					</div>
					<CreatorPasswordSection />
				</div>
			</div>

			<div className="px-5 py-3 border-t border-line-1 shrink-0">
				<button
					onClick={handleSave}
					disabled={!hasChanges || isSaving}
					className={`w-full text-[0.75rem] tracking-[0.1em] py-2 transition-colors ${
						hasChanges && !isSaving
							? 'bg-accent/10 border border-accent text-accent hover:bg-accent/20 cursor-pointer'
							: 'bg-line-1 text-fg-3 cursor-default'
					}`}
				>
					{isSaving ? 'SAVING...' : 'SAVE SETTINGS'}
				</button>
			</div>
		</>
	);
}
