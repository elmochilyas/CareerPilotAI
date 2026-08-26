## ADDED Requirements

### Requirement: CLARUI-001 — Clarification entry point in the brief
The system SHALL surface a clarification entry point in the Career Intelligence Brief when the analysis has open clarification questions, and SHALL hide it when there are none.

#### Scenario: Entry point shown with open questions
- **GIVEN** a completed analysis with open clarification questions
- **WHEN** the candidate opens the brief page
- **THEN** the page SHALL show a clarification entry card near the uncertain findings
- **AND** SHALL indicate the number of questions to answer

#### Scenario: Entry point hidden when empty
- **GIVEN** a completed analysis with no open clarification questions
- **WHEN** the candidate opens the brief page
- **THEN** the page SHALL NOT show a clarification entry

### Requirement: CLARUI-002 — One question at a time with progress
The system SHALL present clarification questions one at a time with a progress indicator and the ability to skip and to return to previous questions.

#### Scenario: First question rendered
- **GIVEN** a session with open questions
- **WHEN** the candidate opens the flow
- **THEN** the page SHALL render exactly one question
- **AND** SHALL show progress such as "Question 1 of 3"

#### Scenario: Next question after answer
- **GIVEN** an answered question
- **WHEN** the candidate proceeds
- **THEN** the page SHALL render the next unanswered question
- **AND** SHALL NOT re-render answered questions

#### Scenario: Question skipped
- **GIVEN** an open question
- **WHEN** the candidate chooses to skip
- **THEN** the question SHALL be recorded as skipped
- **AND** the flow SHALL advance to the next question

#### Scenario: Evidence basis disclosed
- **GIVEN** a question with a stored evidence basis
- **WHEN** the candidate opens the question details
- **THEN** the page SHALL explain why the question is asked and what evidence exists

### Requirement: CLARUI-003 — Question-type-aware inputs
The system SHALL render the correct input for the question type: yes/no, yes/no with details, text, select, and number.

#### Scenario: Yes/no with evidence input
- **GIVEN** a yes_no_with_details question
- **WHEN** the candidate answers yes
- **THEN** the page SHALL prompt for evidence (existing profile item or URL)
- **AND** SHALL require an explicit no-evidence acknowledgement before submitting without evidence

#### Scenario: Select question renders options
- **GIVEN** a select question with stored options
- **WHEN** the candidate answers
- **THEN** the page SHALL render the options as a selectable list
- **AND** SHALL NOT allow free text outside the options

#### Scenario: Number question validates input
- **GIVEN** a number question with a unit
- **WHEN** the candidate submits a value
- **THEN** the page SHALL validate it as a number within the allowed range
- **AND** SHALL show a validation error for invalid input

#### Scenario: Validation errors accessible
- **GIVEN** invalid or missing required input
- **WHEN** the candidate submits
- **THEN** the page SHALL show the validation errors
- **AND** SHALL announce the error to assistive technology and move focus to the first invalid control

### Requirement: CLARUI-004 — Proposal review before acceptance
The system SHALL show a review step that summarizes the proposed profile change with before/after values and the evidence or no-evidence acknowledgement, and SHALL require an explicit decision (accept, edit, or skip) before anything is applied.

#### Scenario: Review shows before and after
- **GIVEN** an answered question
- **WHEN** the review step renders
- **THEN** the page SHALL show the target entity, the field, the current value, and the proposed value
- **AND** SHALL show the evidence or the no-evidence acknowledgement state

#### Scenario: Accept applies the change
- **GIVEN** the review step
- **WHEN** the candidate explicitly accepts
- **THEN** the page SHALL submit the review
- **AND** SHALL show a success confirmation
- **AND** SHALL invalidate the match and skills queries so the brief and stale indicator refresh

#### Scenario: Edit before accept
- **GIVEN** the review step for a text or number value
- **WHEN** the candidate edits the proposed value
- **THEN** the edited value SHALL be submitted for review
- **AND** the skill-verified state SHALL NOT be editable

#### Scenario: Skip leaves data unchanged
- **GIVEN** the review step
- **WHEN** the candidate skips
- **THEN** the question SHALL be recorded as skipped
- **AND** no profile change SHALL be applied

### Requirement: CLARUI-005 — Loading, empty, error, expired, and success states
The system SHALL handle loading, empty, network failure, expired-session, and success states without infinite loops.

#### Scenario: Loading state
- **GIVEN** the flow is loading questions
- **WHEN** the candidate opens the entry
- **THEN** the page SHALL show a loading state

#### Scenario: Network failure retry
- **GIVEN** a network or 401/419 failure while loading or submitting
- **WHEN** the flow cannot complete the action
- **THEN** the page SHALL show an error state with a retry action
- **AND** SHALL NOT enter an infinite retry loop

#### Scenario: Expired session state
- **GIVEN** a question in expired state during the flow
- **WHEN** the candidate attempts to continue
- **THEN** the page SHALL show an expired message
- **AND** SHALL offer to start a fresh clarification session

#### Scenario: Success after last question
- **GIVEN** the candidate accepted the last question's proposal
- **WHEN** the flow completes
- **THEN** the page SHALL show a completion state
- **AND** SHALL indicate the analysis is stale and can be recalculated

### Requirement: CLARUI-006 — Accessibility
The clarification flow SHALL meet WCAG 2.2 AA: semantic HTML, keyboard support, visible focus, labels, sufficient contrast, screen-reader announcements, and respect for `prefers-reduced-motion`.

#### Scenario: Keyboard operable
- **GIVEN** the clarification flow
- **WHEN** a keyboard user navigates the question inputs, skip, back, and accept controls
- **THEN** every control SHALL be reachable and operable by keyboard
- **AND** SHALL show a visible focus state

#### Scenario: Screen-reader announcements
- **GIVEN** a state change (question shown, answer saved, proposal accepted, error)
- **WHEN** the flow updates
- **THEN** the change SHALL be announced to assistive technology

#### Scenario: No unsanitized HTML
- **GIVEN** question prompts, evidence text, or requirement content from the API
- **WHEN** the flow renders it
- **THEN** the content SHALL be rendered as escaped text and SHALL NOT use unsanitized `v-html`

#### Scenario: Reduced motion respected
- **GIVEN** a user with `prefers-reduced-motion` enabled
- **WHEN** the flow renders or updates
- **THEN** the flow SHALL avoid unnecessary animation

### Requirement: CLARUI-007 — Visual design conformance
The clarification flow SHALL follow the CAREERPILOT_PREMIUM_DESIGN_SYSTEM: clean white surfaces, warm neutral page background, deep ink typography, a single controlled indigo accent, thin borders, and minimal shadows.

#### Scenario: Design tokens used
- **GIVEN** the clarification flow
- **THEN** colors, typography, spacing, borders, and radii SHALL come from the project design tokens
- **AND** SHALL NOT use gradient-heavy, glassmorphism, or multi-accent styling

#### Scenario: Responsive layouts
- **GIVEN** the clarification flow
- **WHEN** it renders at 1280–1440 px desktop and at approximately 390 px mobile widths
- **THEN** the question card, inputs, and review step SHALL remain usable in both layouts
