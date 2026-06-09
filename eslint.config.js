import js from "@eslint/js";
import reactHooks from "eslint-plugin-react-hooks";
import reactRefresh from "eslint-plugin-react-refresh";

export default [
	js.configs.recommended,
	{
		files: ["resources/js/**/*.{js,jsx}"],
		plugins: {
			"react-hooks": reactHooks,
			"react-refresh": reactRefresh,
		},
		rules: {
			...reactHooks.configs.recommended.rules,
			"react-refresh/only-export-components": "warn",
			"no-unused-vars": "warn",
		},
	},
];