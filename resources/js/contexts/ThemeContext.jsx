import { createContext, useContext, useState, useEffect } from 'react';
import { route } from 'ziggy-js';
import { api } from '../utils/api';

const ThemeContext = createContext();

export function ThemeProvider({ children }) {
	const [theme, setThemeState] = useState('default');
	const [availableThemes, setAvailableThemes] = useState([]);

	useEffect(() => {
		api.get(route('settings.show'))
			.then((res) => res.json())
			.then((data) => {
				setAvailableThemes(data.available_themes);
				setThemeState(data.selected_theme);
				document.documentElement.setAttribute('data-theme', data.selected_theme);
			})
			.catch(() => {});
	}, []);

	const setTheme = (newTheme) => {
		setThemeState(newTheme);
		document.documentElement.setAttribute('data-theme', newTheme);
	};

	return (
		<ThemeContext.Provider value={{ theme, setTheme, availableThemes }}>
			{children}
		</ThemeContext.Provider>
	);
}

export function useTheme() {
	return useContext(ThemeContext);
}