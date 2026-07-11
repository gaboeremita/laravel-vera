import { useState } from 'react';
import { useTheme } from '../contexts/ThemeContext';
import Header from '../components/Header';
import { useNavigate } from 'react-router-dom';
import { route } from 'ziggy-js';
import { api } from '../utils/api';

export default function SettingsPage() {
	const { theme, setTheme, availableThemes } = useTheme();
	const [selectedTheme, setSelectedTheme] = useState(theme);
	const [isSaving, setIsSaving] = useState(false);
	const navigate = useNavigate();

	const hasChanges = selectedTheme !== theme;

	const handleSave = async () => {
		setIsSaving(true);
		await api.put(route('settings.update'), { theme: selectedTheme });
		setTheme(selectedTheme);
		setIsSaving(false);
	};

	return (
		<>
			<Header
				actions={
					<button
						onClick={() => navigate(-1)}
						className="bg-accent-3/15 border border-accent-3 text-accent-3 hover:bg-accent-3/25 text-[0.75rem] tracking-[0.1em]  cursor-pointer transition-colors px-4 py-1.5"
					>
						← BACK
					</button>
				}
			>
                <span className="text-fg-2 text-lg tracking-[0.05em]">
                    Settings
                </span>
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
							className="bg-bg-1 border border-line-1 text-fg-1  text-[0.75rem] tracking-[0.1em] px-4 py-1.5 cursor-pointer outline-none focus:border-accent transition-colors"
						>
							{availableThemes.map((t) => (
								<option key={t} value={t}>
									{t.toUpperCase()}
								</option>
							))}
						</select>
					</div>
				</div>
			</div>

			{/* Save button */}
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