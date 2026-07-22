<?php

use App\Directors\PromptDirector;

function voicePrompt(): array
{
	return [
		'personality' => ['You are helpful.'],
		'opening_message' => 'Hey there.',
		'style rules' => ['Everything between asterisks are actions.'],
		'OOC mode' => ['description' => ['OOC means out of character.']],
		'image handling' => ['You must analyze images.'],
		'voice mode' => ['Speak in plain sentences only.'],
	];
}

test('voice mode excludes style rules, OOC mode, and image handling but keeps voice mode', function () {
	$built = (new PromptDirector(voicePrompt()))
		->except(['opening_message', 'style rules', 'OOC mode', 'image handling'])
		->build();

	expect($built)
		->toContain('Speak in plain sentences only.')
		->toContain('You are helpful.')
		->not->toContain('Everything between asterisks are actions.')
		->not->toContain('OOC means out of character.')
		->not->toContain('You must analyze images.')
		->not->toContain('Hey there.');
});

test('text mode excludes voice mode but keeps style rules, OOC mode, and image handling', function () {
	$built = (new PromptDirector(voicePrompt()))
		->except(['opening_message', 'voice mode'])
		->build();

	expect($built)
		->toContain('Everything between asterisks are actions.')
		->toContain('OOC means out of character.')
		->toContain('You must analyze images.')
		->not->toContain('Speak in plain sentences only.')
		->not->toContain('Hey there.');
});

test('excluding voice mode when the assistant has not authored one does not error', function () {
	$prompt = voicePrompt();
	unset($prompt['voice mode']);

	$built = (new PromptDirector($prompt))
		->except(['opening_message', 'style rules', 'OOC mode', 'image handling'])
		->build();

	expect($built)->toContain('You are helpful.');
});
