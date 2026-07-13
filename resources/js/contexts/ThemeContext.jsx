import { createContext, useContext, useState, useEffect } from 'react';

const ThemeContext = createContext();

export function ThemeProvider({ children }) {
	const [theme, setThemeState] = useState('default');
	const [availableThemes, setAvailableThemes] = useState([]);

	useEffect(() => {
		document.documentElement.setAttribute('data-theme', 'default');
	}, []);

	const setTheme = (newTheme) => {
		setThemeState(newTheme);
		document.documentElement.setAttribute('data-theme', newTheme);
	};

	return (
		<ThemeContext.Provider value={{ theme, setTheme, availableThemes, setAvailableThemes }}>
			{children}
		</ThemeContext.Provider>
	);
}

export function useTheme() {
	return useContext(ThemeContext);
}