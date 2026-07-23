## Purpose

Provide an authenticated single-page profile interface with section-based editing, completion guidance, item timelines, and career preferences. The frontend consumes server-calculated completion data without duplicating scoring logic.

## Requirements

### Requirement: PROF-UI-001: Profile page routes
The system SHALL provide a `/profile` route accessible only to authenticated users.

#### Scenario: Authenticated user navigates to /profile
- **WHEN** an authenticated user navigates to `/profile`
- **THEN** the system SHALL display the profile page with the user's data

#### Scenario: Unauthenticated user redirected from /profile
- **WHEN** an unauthenticated user navigates to `/profile`
- **THEN** the system SHALL redirect to the login page

### Requirement: PROF-UI-002: Profile header display
The system SHALL display candidate name, headline, location, availability, and profile completion in the profile header.

#### Scenario: Profile header renders with all data
- **WHEN** an authenticated user views their profile with complete data
- **THEN** the header SHALL display the user's name, headline, city/country, availability status, and completion percentage

#### Scenario: Profile header renders with empty data
- **WHEN** an authenticated user views their profile with no data
- **THEN** the header SHALL show the user's name and prompt to complete the profile

### Requirement: PROF-UI-003: Section-based editing
The system SHALL support editing each profile section independently with explicit save and cancel actions.

#### Scenario: Edit headline section
- **WHEN** a user clicks edit on the headline section, changes the text, and saves
- **THEN** the system SHALL persist the change and show a success indicator

#### Scenario: Cancel edit reverts changes
- **WHEN** a user edits a section and clicks cancel
- **THEN** the system SHALL revert to the original values without persisting

### Requirement: PROF-UI-004: Profile item CRUD from UI
The system SHALL support creating, editing, deleting, and reordering profile items through accessible controls.

#### Scenario: Add education from UI
- **WHEN** a user clicks "Add Education", fills in the form, and saves
- **THEN** the new education SHALL appear in the timeline and completion SHALL update

#### Scenario: Delete item with confirmation
- **WHEN** a user clicks delete on a profile item
- **THEN** the system SHALL show a confirmation dialog before deleting

#### Scenario: Reorder items via buttons
- **WHEN** a user clicks move up or move down on a profile item
- **THEN** the item SHALL change position and the change SHALL persist

### Requirement: PROF-UI-005: Completion guidance display
The system SHALL show which mandatory profile areas are complete and which are missing using the server-provided completion details. The frontend SHALL NOT duplicate the Laravel completion algorithm. Certifications may be shown as optional profile-strength recommendations, but their absence and missing salary expectations SHALL NOT be shown as completion gaps.

#### Scenario: Incomplete profile shows guidance
- **WHEN** a user has an incomplete profile
- **THEN** the system SHALL display actionable suggestions for missing sections

#### Scenario: Complete profile hides guidance
- **WHEN** a user has all required areas populated
- **THEN** the system SHALL not show missing-section prompts

### Requirement: PROF-UI-006: Loading states
The system SHALL show loading indicators while profile data is being fetched.

#### Scenario: Profile page shows skeleton while loading
- **WHEN** the profile page is loading
- **THEN** the system SHALL display skeleton placeholders, not empty or flashing content

### Requirement: PROF-UI-007: Server validation errors in UI
The system SHALL treat Laravel server validation as authoritative and display server validation errors at the field level using the existing problem-details mapping. Client validation SHALL use native form constraints and focused, typed, reusable TypeScript utilities kept separate from presentation components. No form-validation dependency SHALL be added by this change.

#### Scenario: Field-level error shown after invalid submission
- **WHEN** a user submits invalid data
- **THEN** the system SHALL display the server validation error under the corresponding field

#### Scenario: Client validation focuses the first invalid field
- **WHEN** client-side validation finds one or more invalid fields
- **THEN** the system SHALL preserve entered values, display field-level messages, and move focus to the first invalid field

#### Scenario: Server validation overrides client assumptions
- **WHEN** Laravel rejects a submission that passed client validation
- **THEN** the system SHALL preserve entered values and display the authoritative server field errors without duplicating the rejected business rule in presentation components

### Requirement: PROF-UI-008: Unsaved changes warning
The system SHALL warn users before navigating away with unsaved changes.

#### Scenario: Navigate away with unsaved edits
- **WHEN** a user has unsaved edits in a section and attempts to leave the page
- **THEN** the system SHALL show a confirmation prompt

### Requirement: PROF-UI-009: Responsive layout
The system SHALL render the profile page without horizontal scrolling on 360px to desktop viewports.

#### Scenario: Mobile layout adapts
- **WHEN** the profile page is viewed on a 360px width device
- **THEN** all sections SHALL be readable without horizontal scrolling and form controls SHALL have adequate touch targets

### Requirement: PROF-UI-010: Accessibility
The system SHALL meet WCAG 2.2 AA for the profile page.

#### Scenario: Keyboard navigation works
- **WHEN** a user navigates the profile page using only a keyboard
- **THEN** all interactive elements SHALL be reachable and operable

#### Scenario: Screen reader announces changes
- **WHEN** a save, delete, or error action completes
- **THEN** the system SHALL announce the result to screen readers

### Requirement: PROF-UI-011: Network error handling
The system SHALL handle network failures gracefully without losing form data.

#### Scenario: Network error during save
- **WHEN** a network error occurs while saving profile changes
- **THEN** the system SHALL show an error message and keep the form data intact

#### Scenario: Retry after network error
- **WHEN** a network error occurs and the user clicks retry
- **THEN** the system SHALL re-attempt the save operation
