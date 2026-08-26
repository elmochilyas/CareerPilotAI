## Purpose

The tailoring workspace frontend: step-based flow to initiate tailoring, review AI proposals with accept/edit/revert controls, preview, save/approve, with full state coverage, responsive layout, accessibility, and design-system conformance.

## Requirements
### Requirement: Tailoring workspace page
The system SHALL provide a tailoring workspace page accessible at `/opportunities/:id/tailor` (route name `opportunities-tailor`, lazy-loaded, `requiresAuth`). The page SHALL display a step-based flow: (1) Create/initiate tailoring, (2) Review proposed changes, (3) Preview final CV, (4) Save/approve.

#### Scenario: Page loads with opportunity context
- **WHEN** the candidate navigates to `/opportunities/:id/tailor`
- **THEN** the page loads the opportunity details, match analysis summary, and existing resume versions (if any)

#### Scenario: Page shows loading state
- **WHEN** the page is loading data
- **THEN** the system displays skeleton placeholders matching the design system

#### Scenario: Page shows error state
- **WHEN** the API request fails
- **THEN** the system displays an error message with a retry button

### Requirement: Tailor CV entry point on opportunity detail
The opportunity detail page SHALL display a "Tailor CV" button when a completed match analysis exists for the opportunity. The button SHALL navigate to the tailoring workspace.

#### Scenario: Button visible when match exists
- **WHEN** the opportunity detail page loads with a completed match analysis
- **THEN** a "Tailor CV" button is visible

#### Scenario: Button hidden when no match
- **WHEN** the opportunity has no completed match analysis
- **THEN** the "Tailor CV" button is not rendered

### Requirement: Review proposed changes step
The tailoring workspace SHALL display proposed changes in a focused view. Only content that changed from the original profile (reordered, reworded, prioritized, or newly included) SHALL be shown prominently. Unchanged content SHALL be collapsed or hidden behind a disclosure. Each proposed change SHALL have Accept / Edit / Revert controls.

#### Scenario: Reworded bullet shows comparison
- **WHEN** AI proposed a wording change for a profile item bullet
- **THEN** the UI shows the original text and proposed text side-by-side with Accept/Edit/Revert buttons

#### Scenario: Reordered section shows new order
- **WHEN** the tailoring reordered experience items
- **THEN** the UI shows the new ordering with a visual indicator of the change from original order

#### Scenario: Prioritized skills show new ranking
- **WHEN** the tailoring reordered the skills section
- **THEN** the UI shows the new skill order with match importance indicators

#### Scenario: Unchanged content is collapsed
- **WHEN** a CV section has no proposed changes
- **THEN** the section is collapsed behind a "Show unchanged content" disclosure

### Requirement: Side-by-side comparison
For reworded content, the workspace SHALL provide a side-by-side or inline-diff view comparing original profile text with the tailored version. The comparison SHALL visually distinguish AI-proposed changes (e.g., highlighted text) from original trusted content.

#### Scenario: Diff highlights changes
- **WHEN** the candidate views a reworded bullet
- **THEN** added/changed words are highlighted and unchanged words are plain

### Requirement: Edit control for proposals
The candidate SHALL be able to edit the proposed text before accepting. The edit SHALL replace the AI proposal with candidate-authored text. The edited text becomes the `current_text` and the proposal status becomes `accepted` (with `edited = true`).

#### Scenario: Candidate edits proposed text
- **WHEN** the candidate clicks Edit on a proposal and modifies the text
- **THEN** the `current_text` updates to the edited version and the proposal is marked accepted

### Requirement: Preview step
The workspace SHALL show a clean, CV-focused preview of the finalized content. The preview SHALL render the complete document as it would appear in export format. Editing controls SHALL be hidden in preview mode. The candidate SHALL be able to return to the review step from preview.

#### Scenario: Preview renders complete CV
- **WHEN** the candidate advances to the preview step
- **THEN** the system renders all accepted changes in a clean CV layout

#### Scenario: Return to review from preview
- **WHEN** the candidate clicks "Back to editing" in preview
- **THEN** the workspace returns to the review step with all changes preserved

### Requirement: Save/approve step
After preview, the candidate SHALL see a "Save CV" action. Saving SHALL approve the resume (immutabilize it) and redirect to the opportunity detail page with a success confirmation. The candidate SHALL also see an option to "Save as draft" to persist progress without approving.

#### Scenario: Save approves and redirects
- **WHEN** the candidate clicks "Save CV"
- **THEN** the system approves the resume, shows a success toast, and redirects to the opportunity detail

#### Scenario: Save as draft persists progress
- **WHEN** the candidate clicks "Save as draft"
- **THEN** the system saves the current state with status `draft` and shows a confirmation

### Requirement: All state coverage
The tailoring workspace SHALL handle: loading, empty (no profile data to tailor), error-with-retry, stale (source data changed mid-edit), and success states. Each state SHALL have a clear visual treatment per the design system.

#### Scenario: Empty state when no profile data
- **WHEN** the candidate has no profile items or skills
- **THEN** the workspace shows an empty state directing the candidate to complete their profile

#### Scenario: Stale state during editing
- **WHEN** the source profile changes while the candidate is editing a draft
- **THEN** the workspace shows a staleness warning with an option to re-tailor

### Requirement: Responsive layout
The workspace SHALL be usable at ~390px (mobile), ~768px (tablet), and 1280px+ (desktop). Mobile SHALL use a stacked layout. Desktop SHALL provide the richer side-by-side comparison view.

#### Scenario: Mobile layout is usable
- **WHEN** the workspace is viewed at 390px width
- **THEN** all controls are accessible, text is readable, and the flow completes without horizontal scroll

#### Scenario: Desktop provides side-by-side
- **WHEN** the workspace is viewed at 1280px+ width
- **THEN** the review step shows side-by-side comparison for reworded content

### Requirement: Accessibility
The workspace SHALL use semantic HTML, keyboard navigation, visible focus states, labels for all controls, ARIA live regions for status announcements, and WCAG 2.2 AA contrast. The workspace SHALL respect `prefers-reduced-motion`. No `v-html` SHALL be used for user content.

#### Scenario: Keyboard navigation
- **WHEN** the candidate navigates the workspace using only the keyboard
- **THEN** all interactive elements are reachable and operable via Tab/Enter/Space

#### Scenario: Screen reader announcements
- **WHEN** a proposal is accepted or a step changes
- **THEN** an ARIA live region announces the status change

### Requirement: Design system conformance
The workspace SHALL use the CareerPilot design tokens (colors, typography, spacing), the existing UI kit components, and follow the premium design system guidelines. No generic AI dashboard aesthetic. The workspace SHALL feel calm, focused, and professional.

#### Scenario: Design tokens applied
- **WHEN** the workspace renders
- **THEN** it uses `--cp-bg`, `--cp-surface`, `--cp-ink`, `--cp-primary` and other design tokens consistently
