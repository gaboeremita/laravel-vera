import { useState } from 'react';
import Accordion from './common/Accordion.jsx';
import ActivityTermsEditor from './ActivityTermsEditor.jsx';
import StartingInventoryEditor from './StartingInventoryEditor.jsx';

function uniqueActivities(object) {
	const seen = new Map();
	for (const spot of object.spots ?? []) {
		for (const activity of spot.activities ?? []) if (!seen.has(activity.id)) seen.set(activity.id, activity);
	}
	return [...seen.values()];
}

function ObjectRow({ worldId, region, object, inventoryConfig, onTermsChange, addToast }) {
	const [collapsed, setCollapsed] = useState(true);
	const stock = inventoryConfig.starting.objects[region.id]?.[object.id];
	const activities = uniqueActivities(object);
	const termsFor = (activityId) => (region.activityTerms ?? []).find((terms) => terms.objectId === object.id && terms.activityId === activityId) ?? null;
	const stockedCount = stock?.items?.length ?? 0;
	const termsCount = activities.filter((activity) => termsFor(activity.id)).length;

	return (
		<Accordion
			title={object.name}
			collapsed={collapsed}
			onToggle={() => setCollapsed((current) => !current)}
			badge={
				<span className="flex gap-1">
					{stockedCount > 0 && <span className="border border-line-1 px-1.5 py-0.5 text-[0.6rem] tracking-[0.1em] text-fg-3">{stockedCount} ITEM{stockedCount === 1 ? '' : 'S'}</span>}
					{termsCount > 0 && <span className="border border-accent/40 px-1.5 py-0.5 text-[0.6rem] tracking-[0.1em] text-accent">{termsCount} WITH TERMS</span>}
				</span>
			}
		>
			{object.description && <p className="text-fg-3 text-xs">{object.description}</p>}
			<div>
				<p className="text-fg-3 text-[0.65rem] tracking-[0.15em] mb-3">STOCK <span className="normal-case tracking-normal">— what it holds at the start of every session; mark items the player can take</span></p>
				<StartingInventoryEditor
					items={inventoryConfig.items}
					value={stock}
					allowUnlimited
					flag="takeable"
					flagLabel="Can be taken"
					saveLabel="SAVE STOCK"
					onSave={(draft) => inventoryConfig.saveStarting('worlds.regions.objects.starting-inventory.update', { region: region.id, object: object.id }, draft, (current, saved) => ({
						...current,
						objects: { ...current.objects, [region.id]: { ...(current.objects[region.id] ?? {}), [object.id]: saved } },
					}))}
				/>
			</div>
			<div className="border-t border-line-1 pt-4 space-y-2">
				<p className="text-fg-3 text-[0.65rem] tracking-[0.15em]">ACTIVITIES <span className="normal-case tracking-normal">— what each one requires, costs and gives</span></p>
				{activities.length === 0 ? (
					<p className="text-fg-3 text-xs">This object offers no activities.</p>
				) : activities.map((activity) => (
					<ActivityTermsEditor key={activity.id} worldId={worldId} regionId={region.id} objectId={object.id} activity={activity} terms={termsFor(activity.id)} items={inventoryConfig.items} onSaved={onTermsChange} addToast={addToast} />
				))}
			</div>
		</Accordion>
	);
}

/** Every object of the region's environment: its stock and its activities' terms. */
export default function RegionObjectsEditor({ worldId, region, inventoryConfig, onTermsChange, addToast }) {
	const [collapsed, setCollapsed] = useState(true);
	const objects = region.layout?.objects ?? [];

	return (
		<Accordion label="OBJECTS" collapsed={collapsed} onToggle={() => setCollapsed((current) => !current)} actions={<span className="text-fg-3 text-xs">{objects.length}</span>}>
			{objects.length === 0 ? (
				<p className="text-fg-3 text-xs">This region's environment has no object markers.</p>
			) : (
				<div className="space-y-2">
					{objects.map((object) => (
						<ObjectRow key={object.id} worldId={worldId} region={region} object={object} inventoryConfig={inventoryConfig} onTermsChange={onTermsChange} addToast={addToast} />
					))}
				</div>
			)}
		</Accordion>
	);
}
