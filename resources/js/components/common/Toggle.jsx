export default function Toggle({ checked, onChange, disabled = false }) {
	return (
		<button
			type="button"
			onClick={onChange}
			disabled={disabled}
			aria-pressed={checked}
			className="relative w-12 h-6 rounded-full cursor-pointer transition-colors duration-200 border border-line-1 disabled:opacity-50 disabled:cursor-not-allowed"
			style={{ backgroundColor: checked ? 'var(--color-accent)' : 'var(--color-bg-1)' }}
		>
			<span
				className="absolute top-1/2 -translate-y-1/2 w-4 h-4 bg-white shadow transition-all duration-200"
				style={{ left: checked ? 'calc(100% - 20px)' : '4px' }}
			/>
		</button>
	);
}
