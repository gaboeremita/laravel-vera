const SIZES = { sm: 'w-8 h-8 text-xs', md: 'w-11 h-11 text-sm', lg: 'w-16 h-16 text-lg' };

/** An item's card image, or a lettered tile when it has none. */
export default function ItemThumb({ item, size = 'md', className = '' }) {
	const box = `${SIZES[size]} shrink-0 border border-line-1 overflow-hidden ${className}`;
	if (item?.cardImageUrl) {
		return <img src={item.cardImageUrl} alt={item.name} className={`${box} object-cover`} />;
	}
	return (
		<div className={`${box} flex items-center justify-center bg-gradient-to-br from-bg-1 to-bg-2 text-accent/80 tracking-[0.1em]`} aria-hidden="true">
			{(item?.name ?? '?').trim().charAt(0).toUpperCase()}
		</div>
	);
}
