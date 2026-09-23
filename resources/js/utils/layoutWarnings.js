export function reportLayoutWarnings(warnings, addToast) {
	if (!warnings?.length) return;
	const details = warnings.map(({ node, reason }) => `${node}: ${reason}`).join('; ');
	addToast(`Some environment markers were skipped — ${details}`, 'error');
}
