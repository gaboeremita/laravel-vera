import { CONDITION_TYPES } from '../utils/questConditionTypes.js';
import { FieldErrors } from './RubricEditor.jsx';

const SELECT = 'bg-bg-1 border border-line-1 text-accent text-sm px-2 py-1.5 outline-none focus:border-accent/50 transition-colors min-w-0';
const SMALL_BUTTON = 'text-[0.65rem] tracking-[0.1em] px-2 py-1 border border-dashed border-line-1 text-fg-3 hover:text-accent hover:border-accent/50 transition-colors cursor-pointer';

const GROUP_LABELS = { all: 'All of', any: 'Any of', none: 'None of' };
const FEELINGS = [['romance', 'Romance'], ['trust', 'Trust'], ['liking', 'Liking']];
const FEELING_BOUNDS = [['atLeast', 'at least'], ['atMost', 'at most'], ['between', 'between']];
const QUEST_STATES = [['offered', 'Offered'], ['active', 'Active'], ['declined', 'Declined'], ['abandoned', 'Abandoned']];
const NO_ERRORS = () => [];

function isGroup(node) {
	return node !== null && typeof node === 'object' && ('all' in node || 'any' in node || 'not' in node);
}

function groupMode(node) {
	if ('all' in node) return 'all';
	if ('any' in node) return 'any';
	return 'none';
}

function groupChildren(node) {
	if ('all' in node) return node.all;
	if ('any' in node) return node.any;
	return node.not && 'any' in node.not ? node.not.any : [node.not];
}

function makeGroup(mode, children) {
	if (mode === 'all') return { all: children };
	if (mode === 'any') return { any: children };
	return { not: { any: children } };
}

/** Where each child of a group sits in the definition, as the server's errors name it. */
function childPath(node, path, index, wrapped) {
	if (wrapped) return path;
	if ('all' in node) return `${path}.all.${index}`;
	if ('any' in node) return `${path}.any.${index}`;
	return node.not && 'any' in node.not ? `${path}.not.any.${index}` : `${path}.not`;
}

function wholeNumber(value, minimum) {
	return Math.max(minimum, Math.floor(Number(value) || 0));
}

function feelingNumber(value) {
	return Math.min(10, Math.max(-10, Math.round((Number(value) || 0) * 10) / 10));
}

function firstActivity(region) {
	const object = region?.objects.find((candidate) => candidate.activities.length > 0);
	return { object: object?.id ?? '', activity: object?.activities[0]?.id ?? '' };
}

/** A new condition of the type, filled with the first choice the world offers. */
function defaultCondition(type, options, context) {
	const region = options.regions[0];
	const residentId = options.residents[0]?.id ?? null;
	const factId = options.facts[0]?.id ?? null;
	switch (type) {
		case 'enterRegion': return { enterRegion: region?.id ?? null };
		case 'enterZone': return { enterZone: { region: region?.id ?? null, zone: region?.zones[0]?.id ?? '' } };
		case 'talkTo': return { talkTo: residentId };
		case 'use': return { use: { region: region?.id ?? null, ...firstActivity(region) } };
		case 'residentDid': return { residentDid: { resident: residentId, region: region?.id ?? null, ...firstActivity(region) } };
		case 'has': return { has: { item: options.items[0]?.id ?? null, atLeast: 1 } };
		case 'credits': return { credits: { atLeast: 1 } };
		case 'knows': return { knows: factId };
		case 'acknowledged': return { acknowledged: { fact: factId, resident: residentId } };
		case 'flag': return { flag: context.flags[0] ?? '' };
		case 'question': return { question: context.questions[0]?.id ?? '' };
		case 'feeling': return { feeling: { resident: residentId, kind: 'trust', atLeast: 1 } };
		case 'questState': return { questState: { quest: context.questKey || options.quests[0]?.key || '', state: 'declined' } };
		case 'declinedTimes': return { declinedTimes: { quest: context.questKey || options.quests[0]?.key || '', atLeast: 1 } };
		case 'gaveTo': return { gaveTo: { resident: residentId, item: options.items[0]?.id ?? null, atLeast: 1 } };
		case 'spentWith': return { spentWith: { resident: residentId, atLeast: 1 } };
		case 'messagesWith': return { messagesWith: { resident: residentId, atLeast: 1 } };
		case 'giverIn': return { giverIn: { region: region?.id ?? null, zone: region?.zones[0]?.id ?? '' } };
		case 'othersInTheZone': return { othersInTheZone: { nobody: true } };
		default: return { beat: context.beats[0]?.id ?? '' };
	}
}

function toNumber(value) {
	return value === '' ? null : Number(value);
}

function RegionSelect({ options, value, onChange }) {
	return (
		<select value={value ?? ''} onChange={(event) => onChange(toNumber(event.target.value))} className={SELECT} aria-label="Region">
			{options.regions.map((region) => <option key={region.id} value={region.id}>{region.name}</option>)}
		</select>
	);
}

function ResidentSelect({ options, value, onChange }) {
	return (
		<select value={value ?? ''} onChange={(event) => onChange(toNumber(event.target.value))} className={SELECT} aria-label="Resident">
			{options.residents.map((resident) => <option key={resident.id} value={resident.id}>{resident.name}</option>)}
		</select>
	);
}

function FactPicker({ options, value, onChange }) {
	return (
		<select value={value ?? ''} onChange={(event) => onChange(toNumber(event.target.value))} className={SELECT} aria-label="Fact">
			{options.facts.map((fact) => <option key={fact.id} value={fact.id}>{fact.holderName}: {fact.topic}</option>)}
		</select>
	);
}

function ItemSelect({ options, value, onChange }) {
	return (
		<select value={value ?? ''} onChange={(event) => onChange(toNumber(event.target.value))} className={SELECT} aria-label="Item">
			{options.items.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
		</select>
	);
}

/** Any quest of the world, the one being written first. */
function QuestSelect({ options, context, value, onChange }) {
	const others = options.quests.filter((quest) => quest.key !== context.questKey);
	return (
		<select value={value} onChange={(event) => onChange(event.target.value)} className={`${SELECT} max-w-xs`} aria-label="Quest">
			{context.questKey && <option value={context.questKey}>This quest</option>}
			{others.map((quest) => <option key={quest.key} value={quest.key}>{quest.title}</option>)}
		</select>
	);
}

function AtLeast({ value, onChange, minimum = 1 }) {
	return (
		<>
			<span className="text-fg-3 text-xs">at least</span>
			<input type="number" min={minimum} step={1} value={value.atLeast} onWheel={(event) => event.target.blur()} onChange={(event) => onChange({ ...value, atLeast: wholeNumber(event.target.value, minimum) })} className={`${SELECT} w-20`} aria-label="At least" />
		</>
	);
}

function ZoneFields({ options, value, onChange }) {
	const region = options.regions.find((candidate) => candidate.id === value.region);
	return (
		<>
			<RegionSelect options={options} value={value.region} onChange={(regionId) => onChange({ region: regionId, zone: options.regions.find((candidate) => candidate.id === regionId)?.zones[0]?.id ?? '' })} />
			<select value={value.zone} onChange={(event) => onChange({ ...value, zone: event.target.value })} className={SELECT} aria-label="Zone">
				{region?.zones.map((zone) => <option key={zone.id} value={zone.id}>{zone.name}</option>)}
			</select>
		</>
	);
}

function FeelingFields({ options, value, onChange }) {
	const bound = 'atLeast' in value && 'atMost' in value ? 'between' : 'atMost' in value ? 'atMost' : 'atLeast';
	const setBound = (next) => {
		const low = value.atLeast ?? value.atMost ?? 0;
		const high = value.atMost ?? value.atLeast ?? 0;
		const base = { resident: value.resident, kind: value.kind };
		if (next === 'between') return onChange({ ...base, atLeast: Math.min(low, high), atMost: Math.max(low, high) });
		return onChange({ ...base, [next]: next === 'atLeast' ? low : high });
	};
	const number = (key, label) => (
		<input type="number" min={-10} max={10} step={0.5} value={value[key]} onWheel={(event) => event.target.blur()} onChange={(event) => onChange({ ...value, [key]: feelingNumber(event.target.value) })} className={`${SELECT} w-20`} aria-label={label} />
	);

	return (
		<>
			<ResidentSelect options={options} value={value.resident} onChange={(resident) => onChange({ ...value, resident })} />
			<select value={value.kind} onChange={(event) => onChange({ ...value, kind: event.target.value })} className={SELECT} aria-label="Feeling">
				{FEELINGS.map(([key, label]) => <option key={key} value={key}>{label}</option>)}
			</select>
			<select value={bound} onChange={(event) => setBound(event.target.value)} className={SELECT} aria-label="Bound">
				{FEELING_BOUNDS.map(([key, label]) => <option key={key} value={key}>{label}</option>)}
			</select>
			{bound !== 'atMost' && number('atLeast', 'At least')}
			{bound === 'between' && <span className="text-fg-3 text-xs">and</span>}
			{bound !== 'atLeast' && number('atMost', 'At most')}
		</>
	);
}

function OthersInTheZoneFields({ options, value, onChange }) {
	const nobody = value.nobody === true;
	return (
		<>
			<select value={nobody ? 'nobody' : 'resident'} onChange={(event) => onChange(event.target.value === 'nobody' ? { nobody: true } : { resident: options.residents[0]?.id ?? null })} className={SELECT} aria-label="Who">
				<option value="resident">a named resident</option>
				<option value="nobody">no one besides the giver</option>
			</select>
			{!nobody && <ResidentSelect options={options} value={value.resident} onChange={(resident) => onChange({ resident })} />}
		</>
	);
}

function ActivityFields({ options, value, onChange }) {
	const region = options.regions.find((candidate) => candidate.id === value.region);
	const objects = region?.objects.filter((object) => object.activities.length > 0) ?? [];
	const object = objects.find((candidate) => candidate.id === value.object);

	return (
		<>
			<RegionSelect options={options} value={value.region} onChange={(regionId) => onChange({ ...value, region: regionId, ...firstActivity(options.regions.find((candidate) => candidate.id === regionId)) })} />
			<select value={value.object} onChange={(event) => onChange({ ...value, object: event.target.value, activity: objects.find((candidate) => candidate.id === event.target.value)?.activities[0]?.id ?? '' })} className={SELECT} aria-label="Object">
				{objects.map((candidate) => <option key={candidate.id} value={candidate.id}>{candidate.name}</option>)}
			</select>
			<select value={value.activity} onChange={(event) => onChange({ ...value, activity: event.target.value })} className={SELECT} aria-label="Activity">
				{object?.activities.map((activity) => <option key={activity.id} value={activity.id}>{activity.name}</option>)}
			</select>
		</>
	);
}

function FlagFields({ value, onChange, options, context }) {
	const isOwn = typeof value === 'string';
	const source = isOwn ? '' : value.quest;
	const suggestions = isOwn ? context.flags : options.quests.find((quest) => quest.key === source)?.flags ?? [];
	const name = isOwn ? value : value.name;
	const listId = `flags-${source || 'own'}`;

	return (
		<>
			<select value={source} onChange={(event) => onChange(event.target.value === '' ? name : { quest: event.target.value, name })} className={SELECT} aria-label="Flag of">
				<option value="">This quest</option>
				{options.quests.filter((quest) => quest.key !== context.questKey).map((quest) => <option key={quest.key} value={quest.key}>{quest.title}</option>)}
			</select>
			<input list={listId} value={name} onChange={(event) => onChange(isOwn ? event.target.value : { quest: source, name: event.target.value })} placeholder="flagName" className={`${SELECT} w-36 placeholder:text-fg-3/60`} aria-label="Flag name" />
			<datalist id={listId}>{suggestions.map((flag) => <option key={flag} value={flag} />)}</datalist>
		</>
	);
}

function ConditionFields({ type, value, onChange, options, context }) {
	switch (type) {
		case 'enterRegion':
			return <RegionSelect options={options} value={value} onChange={onChange} />;
		case 'talkTo':
			return <ResidentSelect options={options} value={value} onChange={onChange} />;
		case 'knows':
			return <FactPicker options={options} value={value} onChange={onChange} />;
		case 'enterZone':
		case 'giverIn':
			return <ZoneFields options={options} value={value} onChange={onChange} />;
		case 'use':
			return <ActivityFields options={options} value={value} onChange={onChange} />;
		case 'residentDid':
			return (
				<>
					<ResidentSelect options={options} value={value.resident} onChange={(resident) => onChange({ ...value, resident })} />
					<ActivityFields options={options} value={value} onChange={onChange} />
				</>
			);
		case 'has':
			return (
				<>
					<ItemSelect options={options} value={value.item} onChange={(item) => onChange({ ...value, item })} />
					<AtLeast value={value} onChange={onChange} />
				</>
			);
		case 'feeling':
			return <FeelingFields options={options} value={value} onChange={onChange} />;
		case 'questState':
			return (
				<>
					<QuestSelect options={options} context={context} value={value.quest} onChange={(quest) => onChange({ ...value, quest })} />
					<span className="text-fg-3 text-xs">is</span>
					<select value={value.state} onChange={(event) => onChange({ ...value, state: event.target.value })} className={SELECT} aria-label="State">
						{QUEST_STATES.map(([key, label]) => <option key={key} value={key}>{label}</option>)}
					</select>
				</>
			);
		case 'declinedTimes':
			return (
				<>
					<QuestSelect options={options} context={context} value={value.quest} onChange={(quest) => onChange({ ...value, quest })} />
					<AtLeast value={value} onChange={onChange} />
				</>
			);
		case 'gaveTo':
			return (
				<>
					<ResidentSelect options={options} value={value.resident} onChange={(resident) => onChange({ ...value, resident })} />
					<ItemSelect options={options} value={value.item} onChange={(item) => onChange({ ...value, item })} />
					<AtLeast value={value} onChange={onChange} />
				</>
			);
		case 'spentWith':
		case 'messagesWith':
			return (
				<>
					<ResidentSelect options={options} value={value.resident} onChange={(resident) => onChange({ ...value, resident })} />
					<AtLeast value={value} onChange={onChange} />
				</>
			);
		case 'othersInTheZone':
			return <OthersInTheZoneFields options={options} value={value} onChange={onChange} />;
		case 'credits':
			return (
				<>
					<span className="text-fg-3 text-xs">at least</span>
					<input type="number" min={0} value={value.atLeast} onWheel={(event) => event.target.blur()} onChange={(event) => onChange({ atLeast: Math.max(0, Math.floor(Number(event.target.value))) })} className={`${SELECT} w-24`} aria-label="At least" />
				</>
			);
		case 'acknowledged':
			return (
				<>
					<ResidentSelect options={options} value={value.resident} onChange={(resident) => onChange({ ...value, resident })} />
					<span className="text-fg-3 text-xs">learned</span>
					<FactPicker options={options} value={value.fact} onChange={(fact) => onChange({ ...value, fact })} />
				</>
			);
		case 'flag':
			return <FlagFields value={value} onChange={onChange} options={options} context={context} />;
		case 'question':
			return (
				<select value={value} onChange={(event) => onChange(event.target.value)} className={`${SELECT} max-w-md`} aria-label="Question">
					{context.questions.map((question) => <option key={question.id} value={question.id}>{question.text || question.id}</option>)}
				</select>
			);
		default:
			return (
				<select value={value} onChange={(event) => onChange(event.target.value)} className={SELECT} aria-label="Beat">
					{context.beats.map((beat) => <option key={beat.id} value={beat.id}>{beat.id}</option>)}
				</select>
			);
	}
}

function ConditionRow({ node, onChange, onRemove, options, context, path, errorsAt }) {
	const type = Object.keys(node)[0];
	const described = CONDITION_TYPES.find((candidate) => candidate.type === type);

	return (
		<div className="hud-enter-fade">
			<div className="flex flex-wrap items-center gap-2">
				<select value={type} onChange={(event) => onChange(defaultCondition(event.target.value, options, context))} className={`${SELECT} text-fg-1`} aria-label="Condition type">
					{CONDITION_TYPES.filter((candidate) => context.types.includes(candidate.type) || candidate.type === type).map((candidate) => <option key={candidate.type} value={candidate.type}>{candidate.label}</option>)}
				</select>
				<ConditionFields type={type} value={node[type]} onChange={(value) => onChange({ [type]: value })} options={options} context={context} />
				{described?.offerOnly && <span className="text-[0.6rem] tracking-[0.1em] uppercase px-1.5 py-0.5 border border-accent/40 text-accent">Offer when only</span>}
				<button type="button" onClick={onRemove} className="text-fg-3 hover:text-danger text-xs px-1 cursor-pointer transition-colors" aria-label="Remove condition">✕</button>
			</div>
			{described?.description && <p className="text-fg-3 text-[0.65rem] mt-1">{described.description}</p>}
			<FieldErrors messages={errorsAt(path, true)} />
		</div>
	);
}

function ConditionGroup({ node, onChange, onRemove, options, context, depth, path, wrapped, errorsAt }) {
	const mode = groupMode(node);
	const children = groupChildren(node);
	const setChildren = (next) => onChange(makeGroup(mode, next));
	const updateChild = (index, child) => setChildren(children.map((current, position) => (position === index ? child : current)));
	const firstType = context.types[0];

	return (
		<div className={`border-l-2 pl-3 py-1 space-y-2 ${mode === 'none' ? 'border-danger/50' : 'border-accent/40'}`}>
			<div className="flex items-center gap-2">
				<select value={mode} onChange={(event) => onChange(makeGroup(event.target.value, children))} className={`${SELECT} text-[0.7rem] tracking-[0.1em] uppercase`} aria-label="Group">
					{Object.entries(GROUP_LABELS).map(([key, label]) => <option key={key} value={key}>{label}</option>)}
				</select>
				{onRemove && <button type="button" onClick={onRemove} className="text-fg-3 hover:text-danger text-xs px-1 cursor-pointer transition-colors" aria-label="Remove group">✕</button>}
			</div>
			{children.length === 0 && <p className="text-fg-3 text-xs italic">No conditions yet.</p>}
			{(!wrapped || children.length === 0) && <FieldErrors messages={errorsAt(path)} />}
			{children.map((child, index) => (isGroup(child) ? (
				<ConditionGroup key={index} node={child} depth={depth + 1} onChange={(next) => updateChild(index, next)} onRemove={() => setChildren(children.filter((_, position) => position !== index))} options={options} context={context} path={childPath(node, path, index, wrapped)} wrapped={false} errorsAt={errorsAt} />
			) : (
				<ConditionRow key={index} node={child} onChange={(next) => updateChild(index, next)} onRemove={() => setChildren(children.filter((_, position) => position !== index))} options={options} context={context} path={childPath(node, path, index, wrapped)} errorsAt={errorsAt} />
			)))}
			<div className="flex gap-2">
				<button type="button" onClick={() => setChildren([...children, defaultCondition(firstType, options, context)])} className={SMALL_BUTTON}>+ CONDITION</button>
				{depth < 3 && <button type="button" onClick={() => setChildren([...children, { all: [] }])} className={SMALL_BUTTON}>+ GROUP</button>}
			</div>
		</div>
	);
}

/**
 * Edits one condition tree. The top level always shows as a group; a group of
 * one condition is saved as that condition, and an empty one as null.
 * path is where the tree sits in the definition, so each row shows the
 * server's errors for it.
 *
 * context: { questKey, flags: string[], questions: [{ id, text }], beats: [{ id }], types: string[] }
 */
export default function ConditionBuilder({ value, onChange, options, context, path = '', errorsAt = NO_ERRORS }) {
	const wrapped = value === null || !isGroup(value);
	const root = value === null ? { all: [] } : isGroup(value) ? value : { all: [value] };
	const emit = (next) => {
		const children = groupChildren(next);
		if (groupMode(next) === 'all' && children.length === 0) return onChange(null);
		if (groupMode(next) === 'all' && children.length === 1) return onChange(children[0]);
		return onChange(next);
	};

	return <ConditionGroup node={root} onChange={emit} onRemove={null} options={options} context={context} depth={0} path={path} wrapped={wrapped} errorsAt={errorsAt} />;
}

