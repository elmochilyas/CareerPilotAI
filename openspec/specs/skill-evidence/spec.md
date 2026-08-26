## Purpose

Enable candidates to attach evidence to skills — either by linking existing profile items (experience, project, education, certification) or by adding external URLs. Evidence is stored as JSON within the candidate_skills record and drives skill verification.

## Requirements

### Requirement: SKILL-EVIDENCE-001 — Evidence as JSON within candidate_skills

The system SHALL store evidence as a JSON array within the candidate_skills.evidence column. Each evidence entry SHALL have a UUID key, type discriminator, and type-specific payload.

#### Scenario: Evidence structure contains key, type, and payload

- **WHEN** evidence is added to a candidate skill
- **THEN** the evidence entry contains a UUID key, type, and type-specific fields (profile_item_id or url)

#### Scenario: Multiple evidence entries

- **WHEN** a candidate adds multiple evidence items to a skill
- **THEN** the evidence array contains each entry with distinct UUID keys

#### Scenario: Evidence included in skill response

- **WHEN** a candidate views a skill with evidence via GET /api/v1/candidate/skills/1
- **THEN** the response includes the evidence array with all entries

### Requirement: SKILL-EVIDENCE-002 — Add profile-item evidence

The authenticated candidate SHALL be able to link a skill to an existing profile_item (experience, project, education, or certification) owned by them.

#### Scenario: Add experience as evidence

- **WHEN** a candidate sends POST /api/v1/candidate/skills/1/evidence with type=profile_item and profile_item_id referencing an owned experience item
- **THEN** the system adds the evidence and returns 201

#### Scenario: Add project as evidence

- **WHEN** a candidate sends POST with type=profile_item and profile_item_id referencing an owned project item
- **THEN** the system adds the evidence and returns 201

#### Scenario: Add education as evidence

- **WHEN** a candidate sends POST with type=profile_item and profile_item_id referencing owned education
- **THEN** the system adds the evidence and returns 201

#### Scenario: Add certification as evidence

- **WHEN** a candidate sends POST with type=profile_item and profile_item_id referencing owned certification
- **THEN** the system adds the evidence and returns 201

#### Scenario: Cross-user profile item rejected

- **WHEN** a candidate sends POST with profile_item_id belonging to another user
- **THEN** the system returns 422 with code profile_item_evidence_not_owned

#### Scenario: Non-existent profile item rejected

- **WHEN** a candidate sends POST with profile_item_id that does not exist
- **THEN** the system returns 422 validation error

#### Scenario: Deleted profile item rejected

- **WHEN** a candidate sends POST with profile_item_id of a deleted profile item
- **THEN** the system returns 422 with code profile_item_evidence_not_owned

### Requirement: SKILL-EVIDENCE-003 — Add URL evidence

The authenticated candidate SHALL be able to add a URL as evidence. Only HTTPS URLs SHALL be accepted. Unsafe URL schemes SHALL be rejected.

#### Scenario: Add valid HTTPS URL evidence

- **WHEN** a candidate sends POST with type=url and url=https://github.com/user/project
- **THEN** the system adds the URL evidence and returns 201

#### Scenario: Reject javascript: URL

- **WHEN** a candidate sends POST with type=url and url=javascript:alert(1)
- **THEN** the system returns 422 with code skill_evidence_invalid

#### Scenario: Reject data: URL

- **WHEN** a candidate sends POST with type=url and url=data:text/html,...
- **THEN** the system returns 422 with code skill_evidence_invalid

#### Scenario: Reject file: URL

- **WHEN** a candidate sends POST with type=url and url=file:///etc/passwd
- **THEN** the system returns 422 with code skill_evidence_invalid

#### Scenario: Reject HTTP URL in production mode

- **WHEN** a candidate sends POST with type=url and url=http://example.com
- **THEN** the system returns 422 with code skill_evidence_invalid (HTTPS required)

#### Scenario: URL evidence with optional label

- **WHEN** a candidate sends POST with type=url, url=https://example.com, and label="Portfolio"
- **THEN** the label is stored with the evidence entry

### Requirement: SKILL-EVIDENCE-004 — Update evidence

The authenticated candidate SHALL be able to update evidence entries by UUID key.

#### Scenario: Update evidence label

- **WHEN** a candidate sends PATCH /api/v1/candidate/skills/1/evidence/{key} with label="Updated Label"
- **THEN** the system updates the evidence label and returns 200

#### Scenario: Update evidence URL

- **WHEN** a candidate sends PATCH with url=https://new-url.com
- **THEN** the system updates the URL and returns 200

#### Scenario: Update non-existent evidence key

- **WHEN** a candidate sends PATCH with a key that does not exist in the evidence array
- **THEN** the system returns 404 with code skill_evidence_not_found

### Requirement: SKILL-EVIDENCE-005 — Remove evidence

The authenticated candidate SHALL be able to remove evidence entries by UUID key. Removing evidence SHALL NOT automatically change the skill state.

#### Scenario: Remove evidence

- **WHEN** a candidate sends DELETE /api/v1/candidate/skills/1/evidence/{key}
- **THEN** the system removes the evidence entry and returns 204

#### Scenario: Remove non-existent evidence key

- **WHEN** a candidate sends DELETE with a key that does not exist
- **THEN** the system returns 404 with code skill_evidence_not_found

#### Scenario: Verification at risk after removing last evidence

- **WHEN** a candidate removes the only evidence from a verified skill
- **THEN** the skill response includes a verification_at_risk flag, but state remains verified

#### Scenario: Evidence removal updates parent skill timestamp

- **WHEN** evidence is removed
- **THEN** the parent candidate_skill updated_at is updated

### Requirement: SKILL-EVIDENCE-006 — Evidence ownership

All evidence operations SHALL enforce ownership through the parent candidate skill's policy. Cross-user evidence operations SHALL return 404.

#### Scenario: Cross-user evidence add returns 404

- **WHEN** a candidate sends POST to add evidence to another user's skill
- **THEN** the system returns 404

#### Scenario: Cross-user evidence removal returns 404

- **WHEN** a candidate sends DELETE to remove evidence from another user's skill
- **THEN** the system returns 404

### Requirement: SKILL-EVIDENCE-007 — Evidence and mutation idempotency

The system SHALL NOT fetch, crawl, or inspect external URLs during this capability. Evidence URLs are stored references only.

#### Scenario: URL stored without fetching

- **WHEN** a candidate adds a URL evidence entry
- **THEN** the system stores the URL but does not make any HTTP request to it

### Requirement: SKILL-EVIDENCE-008 — Safe external link rendering

The system SHALL render external URL evidence with target="_blank" and rel="noopener noreferrer".

#### Scenario: Evidence link has safe attributes

- **WHEN** the frontend renders a URL evidence link
- **THEN** it includes target="_blank" and rel="noopener noreferrer"

### Requirement: SKILL-EVIDENCE-009 — Profile item deletion impact

When a profile_item referenced as evidence is deleted, the evidence entry SHALL remain but the system SHALL mark the reference as unavailable.

#### Scenario: Evidence referencing deleted profile item

- **WHEN** a candidate views a skill with evidence referencing a deleted profile_item
- **THEN** the evidence entry is still present but shows the profile_item as unavailable

### Requirement: CLAR-EVIDENCE-001 — Clarification answers link to skill evidence
The system SHALL record the link between an accepted clarification answer and the evidence (or explicit no-evidence acknowledgement) that drove a skill state change, so the change remains traceable to the answer, finding, and analysis.

#### Scenario: Accepted answer adds evidence
- **GIVEN** an accepted clarification answer that promoted a skill to `verified` with evidence
- **WHEN** the skill evidence is later inspected
- **THEN** the evidence SHALL reference the origin clarification answer and question

#### Scenario: Accepted no-evidence acknowledgement recorded
- **GIVEN** an accepted clarification answer with `acknowledged_no_evidence`
- **WHEN** the skill state changes to `claimed`
- **THEN** the skill record SHALL expose the explicit no-evidence acknowledgement
- **AND** SHALL NOT be recorded as `verified`

#### Scenario: No evidence without acknowledgement never verifies
- **GIVEN** a clarification answer with no evidence and no explicit acknowledgement
- **WHEN** the proposal would otherwise apply
- **THEN** the system SHALL NOT set the skill to `verified`
- **AND** the skill SHALL remain `claimed`, `missing`, or `learning` per its existing state

#### Scenario: Removal of clarification-added evidence preserves provenance
- **GIVEN** a verified skill whose only evidence came from an accepted clarification
- **WHEN** the candidate removes that evidence
- **THEN** the skill SHALL expose a `verification_at_risk` flag while the state remains verified
- **AND** the removal SHALL NOT erase the origin answer reference from the historical record
