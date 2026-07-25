## ADDED Requirements

### Requirement: CANDIDATE-SKILLS-001 — List candidate skills

The authenticated candidate SHALL be able to list their skills with state, proficiency, evidence summary, and timestamps. Results SHALL be paginated and filterable by state.

#### Scenario: List all candidate skills

- **WHEN** an authenticated candidate sends GET /api/v1/candidate/skills
- **THEN** the system returns a paginated list of the candidate's skills with state, proficiency, evidence count, and timestamps

#### Scenario: Filter skills by state

- **WHEN** an authenticated candidate sends GET /api/v1/candidate/skills?state=claimed
- **THEN** the system returns only skills in the claimed state

#### Scenario: List with no skills

- **WHEN** a candidate with no skills sends GET /api/v1/candidate/skills
- **THEN** the system returns an empty paginated result

#### Scenario: Cross-user skill listing

- **WHEN** a candidate attempts to access another user's skill list
- **THEN** the system returns that user's own skills (ownership scoping is implicit)

### Requirement: CANDIDATE-SKILLS-002 — Create a candidate skill

The authenticated candidate SHALL be able to add a skill from the canonical catalog, or add a custom skill when the skill does not exist in the catalog. Duplicate canonical skills SHALL be prevented.

#### Scenario: Create claimed skill from catalog

- **WHEN** a candidate sends POST /api/v1/candidate/skills with a valid skill_id, state=claimed, and proficiency_level=intermediate
- **THEN** the system creates the skill, returns 201 with the skill resource

#### Scenario: Create learning skill

- **WHEN** a candidate sends POST /api/v1/candidate/skills with a valid skill_id, state=learning, and proficiency_level=beginner
- **THEN** the system creates the skill with state=learning

#### Scenario: Duplicate canonical skill rejected

- **WHEN** a candidate sends POST /api/v1/candidate/skills with a skill_id already in their inventory
- **THEN** the system returns 409 with code candidate_skill_duplicate

#### Scenario: Custom skill creation

- **WHEN** a candidate sends POST /api/v1/candidate/skills with no skill_id and a custom name
- **THEN** the system creates a custom skill with the provided name

#### Scenario: Custom skill name conflict

- **WHEN** a candidate sends POST /api/v1/candidate/skills with a custom name that matches a canonical skill
- **THEN** the system returns a validation error suggesting the canonical skill instead

#### Scenario: Invalid proficiency level rejected

- **WHEN** a candidate sends POST /api/v1/candidate/skills with proficiency_level=nonexistent
- **THEN** the system returns a 422 validation error

#### Scenario: Proficiency level is required

- **WHEN** a candidate sends POST /api/v1/candidate/skills without proficiency_level
- **THEN** the system returns a 422 validation error

#### Scenario: Cross-user create prevented by policy

- **WHEN** a candidate attempts to create a skill by manipulating the candidate_profile_id
- **THEN** the system ignores the provided candidate_profile_id and uses the authenticated user's profile

### Requirement: CANDIDATE-SKILLS-003 — Show a candidate skill

The authenticated candidate SHALL be able to view a single candidate skill with full details including evidence array.

#### Scenario: Show candidate skill

- **WHEN** a candidate sends GET /api/v1/candidate/skills/1
- **THEN** the system returns the skill with state, proficiency, years_experience, last_used_at, evidence array, and timestamps

#### Scenario: Show non-existent skill returns 404

- **WHEN** a candidate sends GET /api/v1/candidate/skills/99999
- **THEN** the system returns 404

#### Scenario: Show cross-user skill returns 404

- **WHEN** a candidate sends GET /api/v1/candidate/skills/2 where skill 2 belongs to another user
- **THEN** the system returns 404

### Requirement: CANDIDATE-SKILLS-004 — Update a candidate skill

The authenticated candidate SHALL be able to update proficiency, years_experience, last_used_at, and state. State transitions SHALL be validated against the transition matrix.

#### Scenario: Update proficiency

- **WHEN** a candidate sends PATCH /api/v1/candidate/skills/1 with proficiency_level=advanced
- **THEN** the system updates the proficiency and returns the updated skill

#### Scenario: Invalid state transition rejected

- **WHEN** a candidate sends PATCH /api/v1/candidate/skills/1 with state=verified but has no evidence
- **THEN** the system returns 422 with code skill_verification_requirements_not_met

#### Scenario: Valid state transition

- **WHEN** a candidate sends PATCH /api/v1/candidate/skills/1 with state=archived
- **THEN** the system updates the state and returns the updated skill

#### Scenario: State transition to verified with evidence

- **WHEN** a candidate has at least one evidence entry and sends PATCH with state=verified
- **THEN** the system transitions the skill to verified

#### Scenario: Concurrency conflict on update

- **WHEN** a candidate sends PATCH with a stale updated_at value
- **THEN** the system returns 409 with code candidate_skill_conflict

#### Scenario: Update years_experience

- **WHEN** a candidate sends PATCH with years_experience=3.5
- **THEN** the system updates years_experience and returns the updated skill

#### Scenario: Update last_used_at

- **WHEN** a candidate sends PATCH with last_used_at=2026-01-15
- **THEN** the system updates last_used_at and returns the updated skill

#### Scenario: Cross-user update returns 404

- **WHEN** a candidate sends PATCH to another user's candidate skill
- **THEN** the system returns 404

### Requirement: CANDIDATE-SKILLS-005 — Archive a candidate skill

The authenticated candidate SHALL be able to archive any skill, regardless of current state.

#### Scenario: Archive claimed skill

- **WHEN** a candidate sends POST /api/v1/candidate/skills/1/archive
- **THEN** the system sets state to archived and returns the updated skill

#### Scenario: Archive verified skill

- **WHEN** a candidate sends POST /api/v1/candidate/skills/1/archive where skill is verified
- **THEN** the system archives the skill (verification is preserved but skill is inactive)

#### Scenario: Archive with stale updated_at

- **WHEN** a candidate sends POST /api/v1/candidate/skills/1/archive with stale updated_at
- **THEN** the system returns 409 conflict

### Requirement: CANDIDATE-SKILLS-006 — Restore an archived skill

The authenticated candidate SHALL be able to restore an archived skill to claimed, learning, or verified (if evidence requirements are met).

#### Scenario: Restore archived skill to claimed

- **WHEN** a candidate sends POST /api/v1/candidate/skills/1/restore with state=claimed
- **THEN** the system restores the skill to claimed state

#### Scenario: Restore archived skill to verified with evidence

- **WHEN** a candidate has evidence and sends restore with state=verified
- **THEN** the system restores the skill to verified

#### Scenario: Restore archived skill to verified without evidence

- **WHEN** a candidate has no evidence and sends restore with state=verified
- **THEN** the system returns 422 with code skill_verification_requirements_not_met

### Requirement: CANDIDATE-SKILLS-007 — Delete a candidate skill

The authenticated candidate SHALL be able to delete a skill only when its state is claimed or learning. Skills in verified, rejected, or archived states MUST be archived instead of deleted.

#### Scenario: Delete claimed skill

- **WHEN** a candidate sends DELETE /api/v1/candidate/skills/1 where skill state is claimed
- **THEN** the system deletes the skill and returns 204

#### Scenario: Delete verified skill rejected

- **WHEN** a candidate sends DELETE /api/v1/candidate/skills/1 where skill state is verified
- **THEN** the system returns 422 with code candidate_skill_removal_forbidden

#### Scenario: Delete archived skill rejected

- **WHEN** a candidate sends DELETE /api/v1/candidate/skills/1 where skill state is archived
- **THEN** the system returns 422 with code candidate_skill_removal_forbidden

#### Scenario: Delete rejected skill rejected

- **WHEN** a candidate sends DELETE /api/v1/candidate/skills/1 where skill state is rejected
- **THEN** the system returns 422 with code candidate_skill_removal_forbidden

#### Scenario: Delete with stale updated_at

- **WHEN** a candidate sends DELETE with stale updated_at
- **THEN** the system returns 409 conflict

#### Scenario: Cross-user delete returns 404

- **WHEN** a candidate sends DELETE to another user's candidate skill
- **THEN** the system returns 404

### Requirement: CANDIDATE-SKILLS-008 — Skill state transition matrix enforcement

The system SHALL enforce the defined state transition matrix. All invalid transitions SHALL return 422 with code skill_state_transition_invalid.

#### Scenario: claimed -> verified allowed with evidence

- **WHEN** a candidate transitions from claimed to verified with at least one evidence item
- **THEN** the transition is allowed

#### Scenario: claimed -> verified rejected without evidence

- **WHEN** a candidate transitions from claimed to verified with no evidence
- **THEN** the transition returns 422 with verification requirements not met

#### Scenario: learning -> verified allowed with evidence

- **WHEN** a candidate transitions from learning to verified with evidence
- **THEN** the transition is allowed

#### Scenario: verified -> claimed allowed

- **WHEN** a candidate transitions from verified to claimed
- **THEN** the transition is allowed (downgrade)

#### Scenario: rejected -> claimed allowed

- **WHEN** a candidate transitions from rejected to claimed
- **THEN** the transition is allowed (skill reactivation)

### Requirement: CANDIDATE-SKILLS-009 — Ownership and authorization

All candidate skill operations SHALL enforce ownership through policy checks. Cross-user access SHALL return 404. The candidate's user ID SHALL never be accepted from the request body.

#### Scenario: Cross-user skill access returns 404

- **WHEN** candidate A sends any request targeting candidate B's skill
- **THEN** the system returns 404

#### Scenario: Overposting candidate_profile_id is ignored

- **WHEN** a candidate sends POST /api/v1/candidate/skills with candidate_profile_id set to another user
- **THEN** the system ignores the field and uses the authenticated user's profile

### Requirement: CANDIDATE-SKILLS-010 — Concurrency control

All candidate skill mutations SHALL require the last-known updated_at timestamp. Stale values SHALL return 409 conflict.

#### Scenario: Stale updated_at returns 409

- **WHEN** a candidate sends a mutation with updated_at that does not match the current database value
- **THEN** the system returns 409 with code candidate_skill_conflict
