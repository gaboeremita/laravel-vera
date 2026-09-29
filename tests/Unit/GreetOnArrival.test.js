import assert from 'node:assert/strict';
import { test } from 'node:test';
import { greetingResident } from '../../resources/js/components/world/greetOnArrival.js';
import { parseBehaviorSettings, withGreetOnArrival } from '../../resources/js/components/world/behaviorSettings.js';

const greeter = (id) => ({ id, behaviorSettings: { greetOnArrival: true } });
const bystander = { id: 9, behaviorSettings: null };

test('the first greeting resident opens the conversation of a session without conversations', () => {
	assert.equal(greetingResident([bystander, greeter(1), greeter(2)], { hasConversations: false }).id, 1);
});

test('no one greets once the session has a conversation, or when no resident greets', () => {
	assert.equal(greetingResident([greeter(1)], { hasConversations: true }), null);
	assert.equal(greetingResident([bystander], { hasConversations: false }), null);
	assert.equal(greetingResident([greeter(1)], null), null);
});

test('greetOnArrival must be a boolean', () => {
	assert.equal(parseBehaviorSettings('{"greetOnArrival":"yes"}').error, '"greetOnArrival" must be true or false');
	assert.deepEqual(parseBehaviorSettings('{"greetOnArrival":true}').behaviorSettings, { greetOnArrival: true });
});

test('the toggle keeps the other settings and leaves invalid text alone', () => {
	assert.equal(withGreetOnArrival('', true), '{"greetOnArrival":true}');
	assert.equal(withGreetOnArrival('{"radius":1}', true), '{"radius":1,"greetOnArrival":true}');
	assert.equal(withGreetOnArrival('{"radius":1,"greetOnArrival":true}', false), '{"radius":1}');
	assert.equal(withGreetOnArrival('{"greetOnArrival":true}', false), '');
	assert.equal(withGreetOnArrival('{oops', true), '{oops');
});
