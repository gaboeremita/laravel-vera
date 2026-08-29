# Quickstart Validation Guide: 3D VRM Avatar Portrait

**Feature**: `004-vrm-3d-avatar` | **Date**: 2026-08-28

This guide covers the runnable scenarios that prove the feature works end-to-end. Complete them in order — each depends on the previous.

---

## Prerequisites

- App running locally (Herd)
- At least one user account and one assistant created
- A valid `.vrm` file exported from VRoid Studio (any publicly available sample VRM file works)
- A VRM file >50 MB handy (for rejection test)

---

## Scenario 1: Configure 3D Avatar Mode

1. Open assistant settings (Edit page) for an existing assistant.
2. Locate the portrait type selector. Confirm it defaults to "Image".
3. Switch to "3D Avatar". Confirm a VRM file upload field appears.
4. Upload a valid `.vrm` file.
5. Save the assistant.
6. **Expected**: Save succeeds. Opening the assistant settings again shows portrait type = "3D Avatar" and the uploaded file is listed.

**Verifies**: FR-001, FR-002, FR-003, FR-011, SC-006

---

## Scenario 2: 3D Avatar Renders in Portrait Panel

1. Navigate to the chat view for the assistant configured in Scenario 1.
2. **Expected**: The portrait panel on the left shows the 3D model in a neutral pose within 5 seconds of page load. No blank panel, no error in browser console from the VRM loading pipeline.

**Verifies**: FR-004, FR-009 (fallback not triggered), SC-001

---

## Scenario 3: Fallback When No VRM File

1. Configure a second assistant with portrait type = "3D Avatar" but do not upload a VRM file.
2. Navigate to its chat view.
3. **Expected**: The portrait panel shows the default VERA avatar image, not a blank panel or an error.

**Verifies**: FR-009

---

## Scenario 4: Emotion-Driven Expressions

1. Using the assistant from Scenario 1, send a message that produces a known emotion (e.g., ask something cheerful to get `[happy]`).
2. Observe the avatar's face.
3. **Expected**: The avatar's expression transitions to a visibly happy state within ~500 ms of the message rendering. The label at the bottom of the portrait panel still shows the emotion name.
4. Send a follow-up that produces a neutral or sad response.
5. **Expected**: The face smoothly transitions to the new expression rather than snapping.

**Verifies**: FR-005, FR-006, SC-002

**Note**: Visual verification only — no automated test covers this.

---

## Scenario 5: Idle Blink

1. Leave the chat view open with the 3D avatar visible and send no messages.
2. Observe for 10 seconds.
3. **Expected**: The avatar blinks at least once within the observation window.

**Verifies**: FR-008, SC-003

**Note**: Visual verification only.

---

## Scenario 6: File Size Rejection

1. In assistant settings, attempt to upload a `.vrm` file larger than 50 MB.
2. **Expected**: The upload is rejected with a clear error message. The assistant's VRM file is unchanged.

**Verifies**: FR-012, SC-005

---

## Scenario 7: Image Mode Is Unchanged

1. Confirm that any existing assistant using image portrait mode still shows emotion images normally during chat.
2. **Expected**: Zero change in behaviour — emotion images swap as before.

**Verifies**: FR-010, SC-004

---

## API-Level Tests (automated)

Run:

```bash
php artisan test --compact --filter=AssistantVrm
```

Covers: upload, size rejection, wrong file type rejection, ownership scoping, deletion, portrait_type persistence, and emotions endpoint response shape. See `tests/Feature/Api/AssistantVrmTest.php`.
