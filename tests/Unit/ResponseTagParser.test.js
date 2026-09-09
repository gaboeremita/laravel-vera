import assert from 'node:assert/strict';
import test from 'node:test';

import { extractResponseTags, parseEmotionFromResponse, parsePoseFromResponse } from '../../resources/js/utils/parsers.js';

test('extracts identified tags anywhere while preserving OOC', () => {
    const parsed = extractResponseTags("[OOC: visible]\nHello [scene: archive] [camera: 'close-up'] there.");

    assert.equal(parsed.text, '[OOC: visible]\nHello there.');
    assert.deepEqual(parsed.tags.scene, ['archive']);
    assert.deepEqual(parsed.tags.camera, ['close-up']);
});

test('parses identified emotion and intimate tags regardless of order', () => {
    const parsed = parseEmotionFromResponse('Before [intimate: true] middle [emotion: thoughtful] after.', ['thoughtful', 'seduced']);

    assert.equal(parsed.text, 'Before middle after.');
    assert.equal(parsed.emotion, 'thoughtful');
    assert.equal(parsed.intimate, true);
});

test('parses identified pose tags regardless of position', () => {
    const parsed = parsePoseFromResponse('Before [scene: archive] middle [pose: thoughtful] after.', ['thoughtful']);

    assert.equal(parsed.text, 'Before middle after.');
    assert.equal(parsed.pose, 'thoughtful');
});

test('keeps legacy bare tags working for saved messages', () => {
    assert.deepEqual(parsePoseFromResponse('Before [wave] after.', ['wave']), { pose: 'wave', text: 'Before after.' });
    assert.deepEqual(parseEmotionFromResponse('Before [happy] after.', ['happy']), { emotion: 'happy', intimate: false, text: 'Before after.' });
});
