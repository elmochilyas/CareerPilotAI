## Purpose

Provide an accessible, responsive Vue frontend for the job opportunity ingestion flow covering import form, processing status, multi-step review, preview, confirmation, and readonly confirmed details.

## Requirements

### Requirement: Import form with validation
The system SHALL provide a form to paste a job description with optional metadata.

#### Scenario: Import form display
- **GIVEN** the candidate navigates to the import page
- **WHEN** the page loads
- **THEN** the system SHALL display a textarea for the job description
- **AND** a character count showing current / maximum
- **AND** an optional source URL input
- **AND** an optional personal label input
- **AND** a submit button labelled "Start analysis"
- **AND** an explanation that the information will be reviewed before saving

#### Scenario: Client-side description validation
- **GIVEN** the candidate enters a description shorter than 50 characters
- **WHEN** they attempt to submit
- **THEN** the system SHALL show an inline validation error
- **AND** disable the submit button

#### Scenario: Client-side URL validation
- **GIVEN** the candidate enters a source URL that is not HTTP or HTTPS
- **WHEN** they attempt to submit
- **THEN** the system SHALL show an inline validation error

#### Scenario: Duplicate detection handling
- **GIVEN** the candidate submits a duplicate description
- **WHEN** the server returns 409
- **THEN** the system SHALL display the duplicate error with a link to the existing ingestion
- **AND** offer to navigate to the existing ingestion or import a different description

#### Scenario: Unexpected ingestion creation failure
- **GIVEN** ingestion creation fails unexpectedly
- **WHEN** the server returns a 500 problem response
- **THEN** the system SHALL show a concise recovery message
- **AND** SHALL NOT display SQL, exception, file, or stack-trace details

#### Scenario: Submit success
- **GIVEN** the candidate enters a valid description
- **WHEN** they submit
- **THEN** the system SHALL create the ingestion via POST
- **AND** navigate to the processing page

### Requirement: Processing status display
The system SHALL display processing progress with named stages.

#### Scenario: Processing stage display
- **GIVEN** an ingestion is being processed
- **WHEN** the candidate views the processing page
- **THEN** the system SHALL display named stages: "Description received", "Validating", "Analyzing job information", "Preparing review"
- **AND** highlight the current active stage
- **AND** poll every 3 seconds for status updates

#### Scenario: Processing completed
- **GIVEN** the ingestion reaches `review_ready`
- **WHEN** the poll returns the new status
- **THEN** the system SHALL automatically navigate to the review page

#### Scenario: Processing failed (retryable)
- **GIVEN** the ingestion reaches `failed` with a retryable code
- **WHEN** the poll returns the failure
- **THEN** the system SHALL display the failure reason
- **AND** show a "Retry" button
- **AND** show an "Import a different description" option

#### Scenario: Processing failed (permanent)
- **GIVEN** the ingestion reaches `failed` with a permanent code
- **WHEN** the poll returns the failure
- **THEN** the system SHALL display the failure reason
- **AND** show a "Cancel and start over" option
- **AND** not show a retry button

#### Scenario: Leave and return during processing
- **GIVEN** the candidate navigates away during processing
- **WHEN** they return to the processing page
- **THEN** the system SHALL continue polling the ingestion status
- **AND** show the current stage

#### Scenario: Reanalyze cancelled ingestion
- **GIVEN** the candidate views a cancelled ingestion
- **WHEN** the detail page loads
- **THEN** the system SHALL show a "Reanalyze job" action
- **WHEN** the candidate confirms reanalysis
- **THEN** the action SHALL show a pending state
- **AND** the same page SHALL resume processing status polling after success
- **AND** a safe inline error with retry action SHALL be shown if reanalysis fails

### Requirement: Dynamic multi-step review
The system SHALL display review steps dynamically, showing only non-empty steps.

#### Scenario: Dynamic step visibility
- **GIVEN** an ingestion with only overview, responsibilities, and required skills data
- **WHEN** the review page loads
- **THEN** only steps 1 (Overview), 3 (Responsibilities), and 5 (Required skills) SHALL be visible
- **AND** steps 6 (Preferred skills), 7 (Languages), 8 (Compensation) SHALL be hidden

#### Scenario: Step navigation
- **GIVEN** the candidate is on step 1
- **WHEN** they click "Next"
- **THEN** the system SHALL show step 2 (or the next non-empty step)
- **AND** the step indicator SHALL update to show the current position

#### Scenario: Previous step navigation
- **GIVEN** the candidate is on step 3
- **WHEN** they click "Previous"
- **THEN** the system SHALL show step 2 (or the previous non-empty step)
- **AND** preserve any decisions made on step 3

#### Scenario: Step indicator
- **GIVEN** the review page is displayed
- **WHEN** the candidate views the top of the page
- **THEN** the system SHALL show a step indicator with numbered circles
- **AND** completed steps SHALL be marked
- **AND** the current step SHALL be highlighted
- **AND** hidden steps SHALL not appear in the indicator

### Requirement: Responsibilities list editing
The system SHALL allow the candidate to edit responsibilities as a list.

#### Scenario: Display responsibilities
- **GIVEN** the responsibilities step is active
- **WHEN** the page loads
- **THEN** each responsibility SHALL be displayed as a numbered list item
- **AND** each item SHALL have "Keep", "Edit", and "Remove" actions

#### Scenario: Edit a responsibility
- **GIVEN** a responsibility item
- **WHEN** the candidate clicks "Edit"
- **THEN** an inline text editor SHALL replace the static text
- **AND** the candidate SHALL be able to modify the text
- **AND** confirm or cancel the edit

#### Scenario: Remove a responsibility with undo
- **GIVEN** a responsibility item
- **WHEN** the candidate clicks "Remove"
- **THEN** the item SHALL be visually dimmed with a strikethrough
- **AND** an "Undo" button SHALL appear next to the removed item

#### Scenario: Add a missing responsibility
- **GIVEN** the responsibilities step
- **WHEN** the candidate clicks "Add responsibility"
- **THEN** a new text editor SHALL appear at the end of the list
- **AND** the candidate SHALL enter text and confirm

### Requirement: Skill chip editing
The system SHALL display skills as compact chips with minimal repeated controls.

#### Scenario: Skill chips display
- **GIVEN** the required skills step is active
- **WHEN** the page loads
- **THEN** each skill SHALL be displayed as a compact chip or row
- **AND** show: skill name, resolution status icon, source evidence tooltip

#### Scenario: Keep a skill
- **GIVEN** a skill chip
- **WHEN** the candidate clicks "Keep"
- **THEN** the chip SHALL be marked as accepted
- **AND** the decision SHALL be saved in the batch

#### Scenario: Remove a skill with undo
- **GIVEN** a skill chip
- **WHEN** the candidate clicks "Remove"
- **THEN** the chip SHALL be visually dimmed
- **AND** an "Undo" button SHALL appear

#### Scenario: Resolve ambiguous skill
- **GIVEN** a skill with resolution `ambiguous`
- **WHEN** the candidate clicks "Resolve"
- **THEN** a selection list SHALL show the possible canonical matches
- **AND** the candidate SHALL select one or mark as unknown
- **AND** the resolution SHALL be saved

#### Scenario: Add a missing skill manually
- **GIVEN** the skills step
- **WHEN** the candidate clicks "Add skill"
- **THEN** an input SHALL appear for the skill name
- **AND** the candidate SHALL enter the name and confirm
- **AND** the skill SHALL be added as an `accepted` suggestion with `edited_value`

### Requirement: Final review and confirmation gating
The system SHALL show a complete preview and gate the confirm button.

#### Scenario: Final preview display
- **GIVEN** all decisions are made
- **WHEN** the candidate navigates to the final review step
- **THEN** the system SHALL display:
  - Job fields to save
  - Requirements and responsibilities
  - Required skills
  - Preferred skills
  - Languages and certifications
  - Compensation and benefits
  - Excluded (rejected) items
  - Unknown skills (unresolved labels)
  - Warnings from extraction
  - Original source reference

#### Scenario: Confirm button disabled — incomplete review
- **GIVEN** pending suggestions remain
- **WHEN** the final step is displayed
- **THEN** the Confirm button SHALL be disabled
- **AND** the system SHALL show "Complete all decisions to confirm"

#### Scenario: Confirm button disabled — unresolved skill
- **GIVEN** ambiguous skills remain unresolved
- **WHEN** the final step is displayed
- **THEN** the Confirm button SHALL be disabled
- **AND** the system SHALL show "Resolve ambiguous skills to confirm"

#### Scenario: Confirm button disabled — stale preview
- **GIVEN** decisions changed after the last preview
- **WHEN** the final step is displayed
- **THEN** the Confirm button SHALL be disabled
- **AND** the system SHALL show "Preview is outdated — regenerate to confirm"
- **AND** a "Regenerate preview" button SHALL be available

#### Scenario: Confirm button disabled — pending mutation
- **GIVEN** a decision save is in progress
- **WHEN** the final step is displayed
- **THEN** the Confirm button SHALL be disabled
- **AND** a loading indicator SHALL be shown

#### Scenario: Successful confirmation
- **GIVEN** the candidate confirms with a fresh preview
- **WHEN** the confirmation succeeds
- **THEN** the system SHALL display a success message
- **AND** show the job title, company, and requirements summary
- **AND** provide a "View opportunity" link

#### Scenario: Confirmation failure display
- **GIVEN** confirmation fails
- **WHEN** the error is returned
- **THEN** the system SHALL display the blocking reason
- **AND** provide appropriate recovery action

### Requirement: Readonly confirmed opportunity details
The system SHALL display a confirmed opportunity in readonly mode.

#### Scenario: View confirmed opportunity
- **GIVEN** a confirmed opportunity
- **WHEN** the candidate views it
- **THEN** the system SHALL display in readonly mode:
  - Overview section: title, company, department, summary, application URL
  - Work details section: location, work mode, contract type, seniority
  - Responsibilities as a numbered list
  - Experience and education requirements
  - Required skills with resolution status
  - Preferred skills with resolution status
  - Languages and certifications
  - Compensation and benefits
  - Dates
  - Source metadata

#### Scenario: No editing on confirmed opportunity
- **GIVEN** a confirmed opportunity
- **WHEN** the candidate views it
- **THEN** no edit, delete, or mutation actions SHALL be shown

### Requirement: Opportunities list
The system SHALL display a list of all candidate opportunities and ingestions.

#### Scenario: List with mixed states
- **GIVEN** an authenticated candidate with confirmed opportunities and active ingestions
- **WHEN** they view the list page
- **THEN** the system SHALL display:
  - Confirmed opportunities with title, company, work mode
  - Processing ingestions with "Analyzing..." status
  - Review-ready ingestions with "Ready for review" and a "Review now" action
  - Failed ingestions with "Analysis failed" and a retry option
  - Each item SHALL show the last update time

#### Scenario: Empty list
- **GIVEN** an authenticated candidate with no opportunities
- **WHEN** they view the list page
- **THEN** the system SHALL show an empty state
- **AND** a "Add job opportunity" button

### Requirement: Accessibility and responsiveness
The system SHALL meet WCAG 2.2 AA standards.

#### Scenario: Keyboard navigation
- **GIVEN** the candidate uses keyboard only
- **WHEN** navigating the opportunities pages
- **THEN** all interactive elements SHALL be reachable via Tab
- **AND** all actions SHALL be activatable via Enter or Space
- **AND** focus order SHALL follow a logical sequence

#### Scenario: Screen-reader announcements
- **GIVEN** a screen reader is active
- **WHEN** processing status changes
- **THEN** the system SHALL announce stage changes via `aria-live`
- **AND** announce review step transitions
- **AND** announce confirmation results

#### Scenario: Mobile layout
- **GIVEN** the candidate is on a mobile viewport
- **WHEN** viewing the review page
- **THEN** the system SHALL display a single column layout
- **AND** maintain 44px minimum touch targets
- **AND** avoid horizontal scrolling

#### Scenario: Reduced motion
- **GIVEN** the candidate has `prefers-reduced-motion: reduce`
- **WHEN** loading opportunities pages
- **THEN** animations and transitions SHALL be disabled
