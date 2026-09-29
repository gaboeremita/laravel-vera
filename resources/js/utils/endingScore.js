/**
 * The total score of an ending: the average of its dimension scores, to one
 * decimal place. Null when the ending has no scores.
 */
export function totalScore(scores) {
	if (!scores?.length) return null;
	const average = scores.reduce((sum, score) => sum + score.score, 0) / scores.length;
	return Math.round(average * 10) / 10;
}
