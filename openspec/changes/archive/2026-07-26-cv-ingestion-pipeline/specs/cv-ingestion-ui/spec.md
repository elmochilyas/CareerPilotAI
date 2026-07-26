## ADDED Requirements

### Requirement: Upload stage with accessible drag-and-drop
The system SHALL provide an accessible file upload interface for CV documents.

#### Scenario: File selection via button
- **GIVEN** the candidate is on the CV ingestion page
- **WHEN** they click the "Select file" button
- **THEN** the system SHALL open the native file picker filtered to PDF and DOCX
- **AND** the button SHALL have visible focus and a text label

#### Scenario: File selection via drag-and-drop
- **GIVEN** the candidate is on the CV ingestion page
- **WHEN** they drag a file over the upload zone
- **THEN** the upload zone SHALL visually indicate the drop target
- **AND** the drop zone SHALL have an accessible label

#### Scenario: File summary before upload
- **GIVEN** the candidate has selected a file
- **WHEN** the file is loaded into the browser
- **THEN** the system SHALL display: filename, file size, and file type
- **AND** show an "Upload" button

#### Scenario: Upload progress
- **GIVEN** the candidate has clicked "Upload"
- **WHEN** the file is uploading
- **THEN** the system SHALL show real upload progress via XHR progress events
- **AND** show a cancel button

#### Scenario: Upload cancellation
- **GIVEN** the candidate is uploading a file
- **WHEN** they click cancel
- **THEN** the upload SHALL be aborted
- **AND** the candidate SHALL return to the file selection state

#### Scenario: Client-side validation
- **GIVEN** the candidate selects a non-PDF/DOCX file
- **WHEN** the file is loaded into the browser
- **THEN** the system SHALL show an inline validation error
- **AND** disable the upload button

#### Scenario: Upload server error display
- **GIVEN** the server rejects the upload
- **WHEN** the error response is received
- **THEN** the system SHALL display the error message from the problem detail
- **AND** allow the candidate to retry with a different file

### Requirement: Processing stage with named stages
The system SHALL display processing progress using named stages without fake percentages.

#### Scenario: Processing progress display
- **GIVEN** a CV has been uploaded and is processing
- **WHEN** the candidate views the processing status
- **THEN** the system SHALL display the current stage name (Validating, Extracting text, Analyzing with AI)
- **AND** use TanStack Vue Query polling (every 3 seconds) to check status

#### Scenario: Processing completed
- **GIVEN** a CV has finished processing
- **WHEN** the poll returns `ready_for_review`
- **THEN** the system SHALL automatically transition to the Review stage

#### Scenario: Processing failed
- **GIVEN** a CV has failed processing
- **WHEN** the poll returns `failed`
- **THEN** the system SHALL display the failure reason
- **AND** show a "Retry" button
- **AND** show an "Upload different file" option

#### Scenario: Leave and return during processing
- **GIVEN** the candidate navigates away during processing
- **WHEN** they return to the CV page
- **THEN** the system SHALL continue polling the document status
- **AND** show the current stage

### Requirement: Review stage with comparison workspace
The system SHALL provide a structured review workspace with current-versus-extracted comparison.

#### Scenario: Desktop review layout
- **GIVEN** the candidate is on a desktop viewport (>= 1024px)
- **WHEN** they enter the review stage
- **THEN** the system SHALL display:
  - Left panel: CV source preview (from the online download or extracted text)
  - Right panel: Suggestions list
- **AND** each suggestion SHALL show current profile value and CV extracted value

#### Scenario: Mobile review layout
- **GIVEN** the candidate is on a mobile viewport (<= 767px)
- **WHEN** they enter the review stage
- **THEN** the system SHALL display a single column layout
- **AND** provide a tab or toggle switch between CV source and suggestions

#### Scenario: Suggestion comparison
- **GIVEN** a suggestion with both current and extracted values
- **WHEN** the candidate views it
- **THEN** the system SHALL display both values side by side
- **AND** highlight differences
- **AND** show any conflict indicators with both icon and text labels (not color alone)

#### Scenario: Review actions per suggestion
- **GIVEN** a suggestion is displayed
- **WHEN** the candidate interacts with it
- **THEN** they SHALL be able to: Accept, Edit and accept, Reject
- **AND** for profile items: Create new or Update existing
- **AND** each action SHALL be accessible via keyboard

#### Scenario: Edit suggestion inline
- **GIVEN** the candidate clicks "Edit"
- **WHEN** the editor opens
- **THEN** the candidate SHALL be able to modify the suggested value
- **AND** confirm or cancel the edit

#### Scenario: Review progress indicator
- **GIVEN** the candidate has reviewed 5 of 10 suggestions
- **WHEN** viewing the review stage
- **THEN** the system SHALL show "5 of 10 reviewed"
- **AND** a visual progress indicator

### Requirement: Import preview stage
The system SHALL show a final summary before the candidate confirms the import.

#### Scenario: Import preview
- **GIVEN** all suggestions have been reviewed
- **WHEN** the candidate clicks "Preview Import"
- **THEN** the system SHALL display:
  - Count of items to update (fields)
  - Count of items to create (profile items)
  - Count of items to update (existing profile items)
  - Count of skills to claim
  - Count of items skipped (rejected or keep_existing)
  - Any detected conflicts or duplicates
- **AND** a "Confirm Import" button

#### Scenario: Import with conflicts warning
- **GIVEN** the preview detects potential duplicates
- **WHEN** the candidate views the preview
- **THEN** the system SHALL display each conflict with a clear message
- **AND** allow the candidate to return to review and adjust decisions

### Requirement: Import result stage
The system SHALL display the result after import with success or recovery states.

#### Scenario: Import success
- **GIVEN** the import was applied successfully
- **WHEN** the result is shown
- **THEN** the system SHALL display a success message with import counts
- **AND** provide a link to view the profile
- **AND** provide an option to upload another CV

#### Scenario: Import failure
- **GIVEN** the import failed
- **WHEN** the result is shown
- **THEN** the system SHALL display the failure reason
- **AND** provide a "Retry Import" button
- **AND** keep the document in `ready_for_review` state

### Requirement: Accessibility and responsiveness
The system SHALL meet WCAG 2.2 AA standards throughout the CV ingestion UI.

#### Scenario: Keyboard navigation
- **GIVEN** the candidate uses keyboard only
- **WHEN** navigating the CV ingestion page
- **THEN** all interactive elements SHALL be reachable via Tab
- **AND** all actions SHALL be activatable via Enter or Space
- **AND** focus order SHALL follow a logical sequence

#### Scenario: Screen-reader announcements
- **GIVEN** a screen reader is active
- **WHEN** the upload stage changes state
- **THEN** the system SHALL use `aria-live` regions to announce: file selected, upload progress, upload complete, errors
- **AND** announce review progress
- **AND** announce import result

#### Scenario: Focus management
- **GIVEN** the candidate opens the edit dialog for a suggestion
- **WHEN** the dialog opens
- **THEN** focus SHALL move to the first input
- **AND** focus SHALL be trapped within the dialog
- **AND** when closing, focus SHALL return to the trigger element

#### Scenario: Reduced motion
- **GIVEN** the candidate has `prefers-reduced-motion: reduce`
- **WHEN** the CV ingestion page loads
- **THEN** animations and transitions SHALL be disabled

#### Scenario: Mobile touch targets
- **GIVEN** the candidate is on a mobile device
- **WHEN** interacting with buttons and controls
- **THEN** all touch targets SHALL be at least 44x44px

### Requirement: Unsaved review protection
The system SHALL warn the candidate before leaving with unsaved review decisions.

#### Scenario: Unsaved changes warning
- **GIVEN** the candidate has made review decisions but not saved them
- **WHEN** they attempt to navigate away
- **THEN** the system SHALL show a confirmation dialog: "You have unsaved review decisions. Leave anyway?"
- **AND** cancel the navigation if the candidate chooses to stay

#### Scenario: Duplicate submission prevention
- **GIVEN** the candidate has submitted review decisions
- **WHEN** they attempt to submit again
- **THEN** the button SHALL be disabled
- **AND** a loading indicator SHALL be shown
