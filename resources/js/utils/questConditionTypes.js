/**
 * Every kind of condition a quest can use, with its label and description in
 * the condition builder. offerOnly ones read the moment the player talks with
 * the giver, so they only work in Offer when; activityOnly ones read the
 * object being used, so they only work in an activity's responses.
 */
export const CONDITION_TYPES = [
	{ type: 'enterRegion', label: 'Enters region' },
	{ type: 'enterZone', label: 'Enters zone' },
	{ type: 'talkTo', label: 'Talks to' },
	{ type: 'use', label: 'Uses' },
	{ type: 'residentDid', label: 'Resident uses' },
	{ type: 'has', label: 'Holds item' },
	{ type: 'credits', label: 'Holds credits' },
	{ type: 'knows', label: 'Knows fact' },
	{ type: 'acknowledged', label: 'Resident learned fact' },
	{ type: 'flag', label: 'Flag' },
	{ type: 'question', label: 'Question met' },
	{ type: 'beat', label: 'Beat finished' },
	{ type: 'feeling', label: 'Feeling', description: 'How the resident feels about the player right now.' },
	{ type: 'questState', label: 'Quest state', description: 'Where another quest stands.' },
	{ type: 'declinedTimes', label: 'Times declined', description: 'How many times the player turned down a quest\'s offer, walking away included.' },
	{ type: 'gaveTo', label: 'Gave to', description: 'How many of an item the player has handed the resident this session.' },
	{ type: 'spentWith', label: 'Spent with', description: 'How many credits the player has paid the resident this session.' },
	{ type: 'messagesWith', label: 'Messages sent', description: 'How many messages the player has sent the resident this session.', offerOnly: true },
	{ type: 'giverIn', label: 'Giver in zone', description: 'Where the giver is standing when the player talks to them.', offerOnly: true },
	{ type: 'othersInTheZone', label: 'Others in the zone', description: 'Who else is in the giver\'s zone when the player talks to them.', offerOnly: true },
	{ type: 'objectState', label: 'Object state', description: 'What an earlier response changed about this object in the session.', activityOnly: true },
	{ type: 'narrator', label: 'Narrator', description: 'The narrator judges the requirement from what the player carries and says; the outcome guides the narration when it is met.', activityOnly: true },
];

export const ALL_CONDITION_TYPES = CONDITION_TYPES.filter((candidate) => !candidate.activityOnly).map((candidate) => candidate.type);

/** The kinds of condition every place but Offer when accepts. */
export const EVERYWHERE_CONDITION_TYPES = CONDITION_TYPES.filter((candidate) => !candidate.offerOnly && !candidate.activityOnly).map((candidate) => candidate.type);

/** The kinds of condition Offer when accepts: all but the quest's own beats and questions. */
export const OFFER_WHEN_CONDITION_TYPES = ALL_CONDITION_TYPES.filter((type) => !['beat', 'question'].includes(type));

/** The kinds of condition an activity's responses accept: what the session holds, and the object's own state. */
export const ACTIVITY_CONDITION_TYPES = ['has', 'credits', 'knows', 'acknowledged', 'flag', 'feeling', 'questState', 'declinedTimes', 'gaveTo', 'spentWith', 'objectState', 'narrator'];
