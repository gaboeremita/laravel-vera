/** Every kind of condition a quest can use, with its label in the condition builder. */
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
];

export const ALL_CONDITION_TYPES = CONDITION_TYPES.map((candidate) => candidate.type);
