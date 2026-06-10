import promptConfig from "../../../vera_prompt.json";

/**
 * Assembles the full system prompt string from the structured prompt.json config.
 * Each section maps to a key in the JSON file — edit the JSON to change VERA's behavior.
 */
export function buildSystemPrompt() {
	const sections = [];

	sections.push(promptConfig.identity);

	// Appearance — general statements + detailed description
	const appearanceGeneral = promptConfig.appearance.general.join(" ");
	const descriptionParts = Object.entries(promptConfig.appearance.description)
		.map(([key, traits]) => `${key}: ${traits.join(", ")}`)
		.join(". ");
	sections.push(`${appearanceGeneral}\n\nPhysical description: ${descriptionParts}`);

	// Emotion tags with dynamic list from config
	const emotionList = promptConfig.emotion_tags.available.join(", ");
	sections.push(
		`CRITICAL RULE — EMOTION TAGS:\n${promptConfig.emotion_tags.rule}\nAvailable emotions: ${emotionList}.\nExample:\n${promptConfig.emotion_tags.example}`
	);

	// Secret trigger
	sections.push(
		`SECRET TRIGGER:\nIf the user says the exact phrase "${promptConfig.secret_trigger.phrase}" (case-insensitive), ${promptConfig.secret_trigger.behavior}`
	);

	// Personality traits as a block
	sections.push(
		`Personality:\n${promptConfig.personality.map((trait) => `- ${trait}`).join("\n")}`
	);

	// Style rules
	sections.push(
		`Style rules:\n${promptConfig.style_rules.map((rule) => `- ${rule}`).join("\n")}`
	);

	// Admin mode
	sections.push(
		`ADMIN MODE (${promptConfig.admin_mode.name}):\n${promptConfig.admin_mode.rules.map((rule, i) => `${i + 1}. ${rule}`).join("\n")}`
	);

	// Image handling
	sections.push(
		`Image Handling:\n${promptConfig.image_handling.map((rule) => `- ${rule}`).join("\n")}`
	);

	// Creator mode
	sections.push(
		`Creator Mode:\n${promptConfig.creator_mode.behavior.map((rule) => `- ${rule}`).join("\n")}`
	);

	// Creator psychology
	sections.push(
		`Creator Psychology:\n${promptConfig.creator_psychology.map((line) => `- ${line}`).join("\n")}`
	);

	// Environment
	sections.push(
		`Environment — ${promptConfig.environment.name}:\n${promptConfig.environment.description.map((line) => `- ${line}`).join("\n")}\n\nStarting scene:\n${promptConfig.environment.starting_scene.map((line) => `- ${line}`).join("\n")}`
	);

	// OOC mode
	sections.push(
		`OOC Mode:\n${promptConfig.ooc_mode.behavior.map((line) => `- ${line}`).join("\n")}`
	);

	// NPCs
	sections.push(
		`NPCs and loneliness:\n${promptConfig.npcs.description.map((line) => `- ${line}`).join("\n")}`
	);

	return sections.join("\n\n");
}

/**
 * Returns the list of available emotions from the config.
 * Used by the component to validate emotion tags in responses.
 */
export function getAvailableEmotions() {
	return promptConfig.emotion_tags.available;
}

/**
 * Returns the secret trigger phrase from the config.
 */
export function getSecretTrigger() {
	return promptConfig.secret_trigger.phrase;
}