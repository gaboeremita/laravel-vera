# Quickstart Validation Guide: VRMA Avatar Pose Animations

**Feature**: `006-vrma-avatar-poses` | **Date**: 2026-08-29

This guide covers the runnable scenarios that prove the feature works end-to-end. Complete them in order — each depends on the previous. Requires `004-vrm-3d-avatar` to already be functional (an assistant in 3D avatar mode with a `.vrm` file).

---

## Prerequisites

- App running locally (Herd)
- At least one user account and one assistant configured with portrait type "3D Avatar" and a valid `.vrm` file uploaded (per `004-vrm-3d-avatar/quickstart.md` Scenario 1)
- A valid `.vrma` animation file (any publicly available sample VRMA clip compatible with the uploaded VRM's skeleton works)
- A valid Mixamo-sourced `.fbx` animation file (downloaded directly from Mixamo's library)
- An animation file >10 MB handy (for rejection test)

---

## Scenario 1: Configure a Pose With Blendshape Weights Only

1. Open assistant settings (Edit page) for the 3D avatar assistant.
2. Scroll to the new Poses section (below the emotion editor).
3. Add a pose named "happy-hands" and set a blendshape weight (e.g., `happy` at 80%) without uploading a file.
4. Save.
5. **Expected**: The pose is listed with the blendshape configuration and no animation file.

**Verifies**: FR-001, FR-002, FR-003 (weights-only path), US1 Scenario 1

---

## Scenario 2: Configure a Pose With an Uploaded Animation Only

1. Add a pose named "spin", upload a valid `.vrma` file, and do not set any blendshape weights.
2. Save.
3. **Expected**: The pose is listed with an animation file and no blendshape configuration.
4. Repeat with a second pose named "jump", uploading a valid Mixamo-sourced `.fbx` file instead.
5. **Expected**: Same result — the pose is listed with an animation file and no blendshape configuration, regardless of which format was uploaded.

**Verifies**: FR-003 (upload-only path), FR-005, FR-006, FR-017, US1 Scenario 2

---

## Scenario 3: Configure a Pose With Both

1. Add a pose named "wave", set a blendshape weight, and upload a `.vrma` file for the same pose.
2. Save.
3. **Expected**: The pose is listed with both the blendshape configuration and the animation file.
4. Reload the edit page.
5. **Expected**: Both pieces of data are still present — configuring one did not clear the other.

**Verifies**: FR-003 (combined path), FR-004, US1 Scenarios 3–4, SC-005

---

## Scenario 4: LLM Prompt Includes Poses, Never Emotions

1. With poses configured on the assistant (from Scenarios 1–3), open the chat view and send any message.
2. Inspect the assistant's response for character/behavior consistent with awareness of "spin," "wave," and "happy-hands" it can perform — or inspect server logs / a debug endpoint if available for the constructed system prompt.
3. **Expected**: The prompt sent to the LLM includes a `pose tags` section listing all three pose names, and no `emotion tags` section at all for this assistant.

**Verifies**: FR-008, FR-009, FR-016, US3 Scenarios 1 and 3

---

## Scenario 5: Triggering a Pose in Chat

1. In the chat view for the configured assistant, ask the character to "do a spin."
2. **Expected**: Within 2 seconds, the avatar plays the "spin" (`.vrma`) animation once, then returns to its normal idle behavior (blink/head-sway resume).
3. Ask the character to "jump."
4. **Expected**: Same result for the "jump" pose (`.fbx`, Mixamo-retargeted) — plays once, then returns to idle.

**Verifies**: FR-010, FR-015, FR-017, SC-002

**Note**: Visual verification only — no automated test covers 3D rendering behavior.

---

## Scenario 6: Pose Combines Body Animation and Facial Expression

1. Ask the character to do something that would trigger the "wave" pose (configured in Scenario 3 with both a blendshape weight and an animation file).
2. **Expected**: The avatar plays the wave animation and applies the pose's own blendshape weights simultaneously — neither signal is dropped or overridden by the other.

**Verifies**: FR-012, US2 Scenario 3, SC-006

**Note**: Visual verification only.

---

## Scenario 7: Image Mode Is Unaffected; Existing avatar3d Emotions Convert to Poses

1. Confirm that an assistant with no poses configured shows no pose-related prompt guidance (repeat Scenario 4's inspection with a pose-free assistant).
2. Confirm that an assistant in image portrait mode shows no pose configuration controls in its settings, still has its emotion editor, and behaves exactly as before this feature — sending a chat message still yields emotion-tag-driven behavior, never pose tags.
3. For an assistant that had configured emotions on `portrait_type = 'avatar3d'` before this feature shipped, confirm each of those emotions now appears as an equivalent pose (same name, same blendshape weights) and the assistant's emotion editor is gone.

**Verifies**: FR-002, FR-009, FR-013, FR-020, SC-003, SC-007

---

## Scenario 8: Default Pose as Idle Baseline

1. On the 3D avatar assistant's edit page, open the always-present "default" pose section (separate from the regular pose list, cannot be renamed or deleted).
2. Set a blendshape weight (e.g., `relaxed` at 30%) and upload an idle-loop animation file. Save.
3. **Expected**: With no pose currently triggered, the avatar's face reflects the configured weight and its body continuously loops the uploaded animation.
4. Trigger a different pose (e.g., "spin" from Scenario 2).
5. **Expected**: The spin animation plays, interrupting the default loop; once it finishes, the default loop resumes smoothly (no visible snap on either transition).
6. Delete the default pose's animation file (leaving the blendshape weight in place).
7. **Expected**: The avatar's body returns to the pre-existing hardcoded idle stance, while the face still reflects the configured blendshape weight.

**Verifies**: FR-018, FR-019, US4 Scenarios 1–5, SC-008

---

## Scenario 9: File Size and Format Rejection

1. In assistant settings, attempt to upload an animation file (`.vrma` or `.fbx`) larger than 10 MB to a pose.
2. **Expected**: The upload is rejected with a clear error message.
3. Attempt to upload a file that is neither `.vrma` nor `.fbx` (e.g., a `.png`) to a pose's animation field.
4. **Expected**: The upload is rejected with a clear error message.
5. Upload a non-Mixamo `.fbx` file (different bone-naming convention) to a pose.
6. **Expected**: The upload itself succeeds (extension validation only checks format, not rig compatibility), but triggering the pose in chat does not animate the body correctly — this is a known v1 scope limit (FR-017), not a bug.

**Verifies**: FR-005, FR-014, FR-017, SC-004

---

## API-Level Tests (automated)

Run:

```bash
php artisan test --compact --filter=AssistantPose
```

Covers: pose CRUD (create, rename, delete, name-collision rejection), animation upload/delete, size/format rejection, ownership scoping, and the extended `emotions` endpoint response shape including `poses`. See `tests/Feature/Api/AssistantPoseTest.php` and `tests/Feature/Api/AssistantPoseAnimationTest.php`.
