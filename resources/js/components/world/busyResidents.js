/**
 * Who each resident other than `residentId` is busy with, as a map from
 * their id to the id of the resident they talk with or walk to. Anyone busy
 * only with `residentId` is left out, since `residentId` can still reach them.
 */
export function busyOthers(busyResidents, residentId) {
	return new Map(busyResidents
		.filter(({ id, talkingWith }) => id !== residentId && talkingWith !== residentId)
		.map(({ id, talkingWith }) => [id, talkingWith]));
}
