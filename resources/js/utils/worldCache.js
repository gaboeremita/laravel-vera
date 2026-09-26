/**
 * Things worked out from a file that never changes once uploaded (uploads get
 * new paths), kept in the browser between visits: parsed pose clips, walkable
 * grids and floor maps. Every read and write is best effort; a private window
 * or a full disk only means the work is done again.
 */
const DB_NAME = 'vera-world-cache';
const VERSION = 1;
export const STORES = { poseClips: 'pose-clips', navigation: 'navigation', floorMaps: 'floor-maps' };

let database = null;

function openDatabase() {
	if (database) return database;
	database = new Promise((resolve) => {
		try {
			const request = globalThis.indexedDB?.open(DB_NAME, VERSION);
			if (!request) {
				resolve(null);
				return;
			}
			request.onupgradeneeded = () => {
				for (const store of Object.values(STORES)) {
					if (!request.result.objectStoreNames.contains(store)) request.result.createObjectStore(store);
				}
			};
			request.onsuccess = () => resolve(request.result);
			request.onerror = () => resolve(null);
		} catch {
			resolve(null);
		}
	});
	return database;
}

export async function readCached(store, key) {
	const db = await openDatabase();
	if (!db) return null;
	return new Promise((resolve) => {
		try {
			const request = db.transaction(store).objectStore(store).get(key);
			request.onsuccess = () => resolve(request.result ?? null);
			request.onerror = () => resolve(null);
		} catch {
			resolve(null);
		}
	});
}

export async function writeCached(store, key, value) {
	const db = await openDatabase();
	if (!db) return;
	try {
		db.transaction(store, 'readwrite').objectStore(store).put(value, key);
	} catch {
		// A full or blocked store only costs doing the work again next visit.
	}
}

function hashText(text) {
	let hash = 5381;
	for (let index = 0; index < text.length; index++) hash = ((hash * 33) ^ text.charCodeAt(index)) >>> 0;
	return hash.toString(36);
}

/** The key a world's walkable grid is kept under: its file, the grid's extent and the probing version. */
export function navigationCacheKey(fileUrl, options, version) {
	if (!fileUrl) return null;
	return `${fileUrl}|v${version}|${hashText(JSON.stringify(options))}`;
}

/** The key a world's floor maps are kept under: its file and the floors and zones that frame each map. */
export function floorMapCacheKey(fileUrl, layout, version) {
	if (!fileUrl) return null;
	const frame = { floors: layout?.floors ?? [], zones: (layout?.zones ?? []).map((zone) => [zone.id, zone.floorId, zone.outline]) };
	return `${fileUrl}|v${version}|${hashText(JSON.stringify(frame))}`;
}
