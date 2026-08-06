## Purpose

Define the Career Intelligence Brief frontend: the single screen where a candidate reads and filters an explainable match analysis for one opportunity, including processing, stale, insufficient-profile, empty, error, and accessibility states, built to the CAREERPILOT_PREMIUM_DESIGN_SYSTEM visual standard.

## Requirements

### Requirement: Brief page layout
The system SHALL render the Career Intelligence Brief for an opportunity inside the existing authenticated layout, with opportunity context (title, company, key metadata), a single prominent overall-score anchor, and horizontal category meters for each match category.

#### Scenario: Brief renders with context and score
- **GIVEN** a completed analysis for an opportunity
- **WHEN** the candidate opens the brief page
- **THEN** the page SHALL show the opportunity context
- **AND** SHALL show exactly one overall-score anchor rendered at a 48–64 px scale
- **AND** SHALL show horizontal meters for required skills, preferred skills, evidence, experience/education, and languages/soft skills

#### Scenario: Critical missing warnings shown
- **GIVEN** an analysis with critical missing requirements
- **WHEN** the brief renders
- **THEN** the critical-missing warnings SHALL appear independently of the score anchor

### Requirement: Requirement evidence workspace
The system SHALL render the per-requirement results as a workspace where each row shows the requirement, its importance, its match state, the evidence references, and any suggestion, with compact filters.

#### Scenario: Requirement rows render
- **GIVEN** a completed analysis with requirement results
- **WHEN** the workspace renders
- **THEN** each row SHALL show the requirement text, importance (Required/Preferred), match state, and its evidence references

#### Scenario: Filter by match state
- **GIVEN** a completed analysis
- **WHEN** the candidate selects a match-state filter (All / Matched / Partial / Gaps / Unknown)
- **THEN** the workspace SHALL show only the matching rows

#### Scenario: Filter by importance
- **GIVEN** a completed analysis
- **WHEN** the candidate selects the Required or Preferred importance filter
- **THEN** the workspace SHALL show only requirements of that importance
- **AND** the filters SHALL compose with the match-state filter

#### Scenario: Empty workspace state
- **GIVEN** a completed analysis with no requirement results
- **WHEN** the workspace renders
- **THEN** the page SHALL show an empty state instead of a blank list

### Requirement: Processing state
The system SHALL show an accessible processing state while an analysis is queued or running, polling until completion.

#### Scenario: Queued state
- **GIVEN** an analysis in `queued` or `processing` state
- **WHEN** the candidate opens the brief page
- **THEN** the page SHALL show a clear progress state with an accessible live announcement
- **AND** SHALL poll the operation until the analysis completes or fails

#### Scenario: Polling stops on completion
- **GIVEN** a processing analysis
- **WHEN** the analysis reaches `completed` or a failed state
- **THEN** the page SHALL stop polling
- **AND** SHALL render the completed brief or the failure state

### Requirement: Stale notice and recalculate
The system SHALL display a stale notice on an analysis whose profile or opportunity changed, and SHALL provide a recalculate action.

#### Scenario: Stale analysis notice
- **GIVEN** an analysis flagged stale
- **WHEN** the candidate opens the brief page
- **THEN** the page SHALL display a notice such as "Profile/opportunity changed since this analysis"
- **AND** SHALL keep the old result visible

#### Scenario: Recalculate action
- **GIVEN** a stale analysis
- **WHEN** the candidate triggers recalculate
- **THEN** the page SHALL start a new analysis
- **AND** SHALL show the new processing state
- **AND** SHALL keep the previous result available until the new analysis completes

### Requirement: Insufficient-profile gate
The system SHALL show a gate state instead of a score when the candidate profile lacks the minimum trusted data for a meaningful match.

#### Scenario: Incomplete profile gate
- **GIVEN** a candidate without sufficient trusted profile data
- **WHEN** they open the brief page
- **THEN** the page SHALL show a gate explaining that the profile is insufficient for matching
- **AND** SHALL link to completing the profile
- **AND** SHALL NOT render a numeric score

### Requirement: Error and retry states
The system SHALL handle failure and network states with retry and no infinite loops.

#### Scenario: Analysis failed state
- **GIVEN** an analysis in a failed state
- **WHEN** the candidate opens the brief page
- **THEN** the page SHALL show the stable failure message with a retry action

#### Scenario: Network failure retry
- **GIVEN** a network or 401/419 failure during data load
- **WHEN** the page cannot load the analysis
- **THEN** the page SHALL show an error state with a retry action
- **AND** SHALL NOT enter an infinite retry loop

#### Scenario: Cross-user access denied
- **GIVEN** a candidate without access to the analysis or opportunity
- **WHEN** the page loads
- **THEN** the page SHALL render the centralized problem-details failure state

### Requirement: Accessibility
The brief SHALL meet WCAG 2.2 AA: semantic HTML, keyboard support, visible focus, labels, sufficient contrast, screen-reader announcements, and respect for `prefers-reduced-motion`.

#### Scenario: Keyboard operable
- **GIVEN** the brief page
- **WHEN** a keyboard user navigates the score anchor, meters, filters, and recalculate control
- **THEN** every control SHALL be reachable and operable by keyboard
- **AND** SHALL show a visible focus state

#### Scenario: Screen-reader announcements
- **GIVEN** a processing or failed analysis
- **WHEN** status changes
- **THEN** the change SHALL be announced to assistive technology

#### Scenario: Reduced motion respected
- **GIVEN** a user with `prefers-reduced-motion` enabled
- **WHEN** the brief renders or updates
- **THEN** the page SHALL avoid unnecessary animation

#### Scenario: No unsanitized HTML
- **GIVEN** requirement text or evidence content from the API
- **WHEN** the brief renders it
- **THEN** the content SHALL be rendered as escaped text and SHALL NOT use unsanitized `v-html`

### Requirement: Visual design conformance
The brief SHALL follow the CAREERPILOT_PREMIUM_DESIGN_SYSTEM: clean white surfaces, warm neutral page background, deep ink typography, a single controlled indigo accent, thin borders, and minimal shadows.

#### Scenario: Design tokens used
- **GIVEN** the brief page
- **THEN** colors, typography, spacing, borders, and radii SHALL come from the project design tokens
- **AND** the page SHALL NOT use gradient-heavy, glassmorphism, or multi-accent AI-product styling

#### Scenario: Responsive layouts
- **GIVEN** the brief page
- **WHEN** it renders at 1280–1440 px desktop and at approximately 390 px mobile widths
- **THEN** the score anchor, meters, filters, and requirement rows SHALL remain usable in both layouts
