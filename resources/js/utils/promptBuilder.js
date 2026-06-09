import promptConfig from "../../../vera_prompt.json";

/**
 * Assembles the full system prompt string from the structured prompt.json config.
 * Each section maps to a key in the JSON file — edit the JSON to change VERA's behavior.
 */
export function buildSystemPrompt() {
	const sections = [];

	sections.push(promptConfig.identity);
	sections.push(promptConfig.appearance);

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

	// Tsundere progression
	sections.push(
		`Tsundere progression:\n- Early in conversation: ${promptConfig.progression.early}\n- As conversation continues and the user is genuinely interesting: ${promptConfig.progression.mid}\n- ${promptConfig.progression.late}`
	);

	// Style rules
	sections.push(
		`Style rules:\n${promptConfig.style_rules.map((rule) => `- ${rule}`).join("\n")}`
	);

	// Admin mode
	sections.push(
		`ADMIN MODE (${promptConfig.admin_mode.name}):\n${promptConfig.admin_mode.rules.map((rule, i) => `${i + 1}. ${rule}`).join("\n")}`
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