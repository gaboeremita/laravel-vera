import { useContext } from 'react';
import { ThemeContext } from '../contexts/theme.js';

export function useTheme() {
	return useContext(ThemeContext);
}
