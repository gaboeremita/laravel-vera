let nextKey = 0;

/** Sentiment definitions as the editor holds them: renamedFrom is the name last saved, so a rename carries over to scores and conditions. */
export function toSentimentRows(sentiments = []) {
	return sentiments.map((sentiment) => ({ key: nextKey++, name: sentiment.name, description: sentiment.description, renamedFrom: sentiment.name }));
}

export function toSentimentPayload(rows = []) {
	return rows.map((row) => ({ name: row.name, description: row.description, renamedFrom: row.renamedFrom ?? null }));
}

export function emptySentimentRow() {
	return { key: nextKey++, name: '', description: '', renamedFrom: null };
}
