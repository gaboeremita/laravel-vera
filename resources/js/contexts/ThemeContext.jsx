import { useState, useEffect, useCallback } from 'react';
import { ThemeContext } from './theme.js';

export function ThemeProvider({ children }) {
	const [theme, setThemeState] = useState('default');
	const [availableThemes, setAvailableThemes] = useState([]);

	useEffect(() => {
		document.documentElement.setAttribute('data-theme', 'default');
	}, []);

	const setTheme = useCallback((newTheme) => {
		setThemeState(newTheme);
		document.documentElement.setAttribute('data-theme', newTheme);
	}, []);

	return (
		<ThemeContext.Provider value={{ theme, setTheme, availableThemes, setAvailableThemes }}>
			{children}
		</ThemeContext.Provider>
	);
}
