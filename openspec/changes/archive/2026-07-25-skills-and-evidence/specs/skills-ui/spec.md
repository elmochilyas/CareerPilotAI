## ADDED Requirements

### Requirement: SKILLS-UI-001 — Skills section in profile page

The system SHALL render a Skills section within the existing profile page that displays the candidate's skills with state, proficiency, evidence count, and available actions.

#### Scenario: Skills section shows loading state

- **WHEN** the candidate navigates to the profile page and skills are loading
- **THEN** the skills section shows skeleton placeholders matching the skill card layout

#### Scenario: Skills section shows empty state

- **WHEN** the candidate has no skills in their inventory
- **THEN** the skills section shows an empty state with a prompt to add their first skill and a prominent "Add Skill" button

#### Scenario: Skills section shows skill cards

- **WHEN** the candidate has skills in their inventory
- **THEN** the skills section displays skill cards with canonical name, state chip, proficiency label, evidence count, and available actions

#### Scenario: Skills section shows error state

- **WHEN** the skills API request fails
- **THEN** the skills section shows an error state with a retry button

### Requirement: SKILLS-UI-002 — Skill card display

Every skill card SHALL display the canonical skill name (or custom name with a "custom" indicator), state chip with icon and label, proficiency level, evidence count, and last-updated timestamp where useful.

#### Scenario: Skill card shows all required information

- **WHEN** a candidate views a skill card
- **THEN** it displays skill name, state chip, proficiency level, evidence count, and available action buttons

#### Scenario: Claimed state differentiation

- **WHEN** a skill is in claimed state
- **THEN** the state chip uses an outline badge with "Claimed" label and info icon

#### Scenario: Verified state differentiation

- **WHEN** a skill is in verified state
- **THEN** the state chip uses a filled badge with "Verified" label and check icon

#### Scenario: Learning state differentiation

- **WHEN** a skill is in learning state
- **THEN** the state chip uses a dashed badge with "Learning" label and book icon

#### Scenario: Rejected state differentiation

- **WHEN** a skill is in rejected state
- **THEN** the state chip uses a muted badge with "Rejected" label and x icon

#### Scenario: Archived state differentiation

- **WHEN** a skill is in archived state
- **THEN** the state chip uses a muted badge with "Archived" label and archive icon

#### Scenario: Color is supplementary to text and icons

- **WHEN** state chips are rendered
- **THEN** the state is communicated through label text and icon, not color alone

### Requirement: SKILLS-UI-003 — Add skill flow

The system SHALL provide an accessible multi-step add-skill flow: search canonical catalog, select skill, choose state, choose proficiency, optionally add evidence, review and save.

#### Scenario: Search skills with debounce

- **WHEN** a candidate types in the skill search field
- **THEN** the system debounces the input for 300ms before sending the API request

#### Scenario: Select canonical skill

- **WHEN** a candidate selects a skill from the search results
- **THEN** the skill is pre-selected and the candidate proceeds to state/proficiency selection

#### Scenario: Add custom skill option

- **WHEN** the candidate's search yields no results
- **THEN** the system offers an option to add a custom skill by name

#### Scenario: Duplicate detection before submission

- **WHEN** a candidate selects a skill that already exists in their inventory
- **THEN** the system shows a duplicate warning inline before submission

#### Scenario: Stale search results cancelled

- **WHEN** a candidate types a new search before the previous request completes
- **THEN** the previous request is cancelled and ignored

### Requirement: SKILLS-UI-004 — Proficiency selection

The system SHALL provide a proficiency selector displaying the five stored proficiency levels with candidate-facing labels.

#### Scenario: Proficiency levels displayed

- **WHEN** a candidate selects proficiency
- **THEN** the system displays options: Beginner, Elementary, Intermediate, Advanced, Expert

#### Scenario: Proficiency is required

- **WHEN** a candidate attempts to save a skill without selecting proficiency
- **THEN** the system shows a validation error

### Requirement: SKILLS-UI-005 — Evidence linking UI

The system SHALL provide UI for linking profile-item evidence and URL evidence to a candidate skill.

#### Scenario: Profile-item evidence picker

- **WHEN** a candidate chooses to add profile-item evidence
- **THEN** the system shows a dialog listing their available profile items grouped by type (experience, project, education, certification) with title and organization

#### Scenario: URL evidence input

- **WHEN** a candidate chooses to add URL evidence
- **THEN** the system shows a URL input field with validation and an optional label field

#### Scenario: Inline URL validation

- **WHEN** a candidate types a URL with an unsafe scheme
- **THEN** the system shows an inline validation error before submission

#### Scenario: Remove evidence

- **WHEN** a candidate removes evidence from a skill
- **THEN** the evidence entry is removed and the skill card updates the evidence count

### Requirement: SKILLS-UI-006 — TanStack Vue Query integration

The skills section SHALL use TanStack Vue Query for all server state. Skill data SHALL NOT be duplicated in Pinia.

#### Scenario: Candidate skills fetched on mount

- **WHEN** the profile page mounts
- **THEN** the system fetches candidate skills via useQuery with key ['candidate-skills']

#### Scenario: Mutation invalidates skills query

- **WHEN** a candidate creates, updates, archives, or deletes a skill
- **THEN** the candidate-skills query is invalidated and refetched

#### Scenario: Evidence mutation invalidates single skill query

- **WHEN** a candidate adds or removes evidence
- **THEN** the single skill query ['candidate-skills', id] is invalidated

### Requirement: SKILLS-UI-007 — Conflict handling

The system SHALL handle 409 conflict responses without page reload. A dialog or inline warning SHALL offer to refresh the data.

#### Scenario: Conflict shows refresh prompt

- **WHEN** a 409 response is received
- **THEN** the system shows a conflict warning with a "Refresh" button that invalidates the query

#### Scenario: Conflict preserves other form data

- **WHEN** a 409 response is received during a mutation
- **THEN** the system does not reload the page or clear other sections

### Requirement: SKILLS-UI-008 — Keyboard accessibility

The skills section SHALL be fully keyboard accessible. The skill search combobox SHALL follow the ARIA combobox pattern.

#### Scenario: Search combobox keyboard navigation

- **WHEN** a candidate uses the skill search combobox
- **THEN** they can navigate results with arrow keys, select with Enter, and dismiss with Escape

#### Scenario: Dialog focus trapping

- **WHEN** an evidence dialog opens
- **THEN** focus is trapped within the dialog and restored to the trigger element on close

#### Scenario: Escape closes dialogs

- **WHEN** a candidate presses Escape in an evidence dialog
- **THEN** the dialog closes without saving

### Requirement: SKILLS-UI-009 — Unsaved changes protection

The system SHALL NOT prompt for unsaved changes on individual skill edits (skills save immediately). However, the evidence dialog SHALL warn before closing with unsaved changes.

#### Scenario: Evidence dialog warns on unsaved changes

- **WHEN** a candidate has unsaved evidence input and attempts to close the dialog
- **THEN** the system shows a confirmation prompt

### Requirement: SKILLS-UI-010 — Responsive design

The skills section SHALL be responsive: grid layout on desktop, single column on mobile, no horizontal scrolling at 360px width.

#### Scenario: Desktop layout

- **WHEN** the viewport is at least 1024px wide
- **THEN** skill cards display in a 2-3 column grid

#### Scenario: Mobile layout

- **WHEN** the viewport is 360px wide
- **THEN** skill cards display in a single column with full-width controls and no horizontal scrolling

#### Scenario: Mobile touch targets

- **WHEN** displayed on mobile
- **THEN** all interactive elements have minimum 44x44px touch targets

### Requirement: SKILLS-UI-011 — Accessibility and live regions

The system SHALL use aria-live regions for save confirmations, errors, and dynamic content changes.

#### Scenario: Save confirmation announced

- **WHEN** a skill is successfully saved
- **THEN** an aria-live polite region announces the success

#### Scenario: Error announced

- **WHEN** a mutation fails
- **THEN** an aria-live assertive region announces the error

### Requirement: SKILLS-UI-012 — Archive and restore actions

The system SHALL provide archive and restore actions in the skill card context menu or action buttons.

#### Scenario: Archive visible for active skills

- **WHEN** a skill is in claimed, verified, or learning state
- **THEN** an archive action is available

#### Scenario: Restore visible for archived skills

- **WHEN** a skill is in archived state
- **THEN** a restore action is available

#### Scenario: Restore offers state selection

- **WHEN** a candidate restores an archived skill
- **THEN** they can choose the target state (claimed, learning, or verified)
