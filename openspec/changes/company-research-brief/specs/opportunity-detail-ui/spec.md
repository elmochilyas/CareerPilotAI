## MODIFIED Requirements

### Requirement: Two-column detail layout with sticky quick-facts rail (OPDETAIL-001)
The system SHALL render the saved-opportunity detail page as a two-column layout at desktop widths: a main column containing the editorial sections and a right-side quick-facts rail that SHALL be sticky while scrolling. At mobile widths the page SHALL collapse to a single column with the quick-facts rail stacked after the main content.

#### Scenario: Desktop two-column layout
- **GIVEN** a saved opportunity with data
- **WHEN** the candidate opens the detail page at a desktop width (1280–1440 px)
- **THEN** the main editorial sections render in the left column
- **AND** the quick-facts rail renders in the right column and stays visible while the candidate scrolls the main column

#### Scenario: Mobile single-column layout
- **GIVEN** a saved opportunity with data
- **WHEN** the candidate opens the detail page at a mobile width (~390 px)
- **THEN** the page renders a single column with the quick-facts rail stacked after the main content
- **AND** no horizontal scrolling occurs

### Requirement: Quick-facts rail content (OPDETAIL-002)
The quick-facts rail SHALL group the opportunity's quick facts into Work details, Compensation, Dates, and Source blocks, using the same fields the page showed before this change. Each block SHALL render only when it has data, and the rail SHALL collapse fully when no block has data.

#### Scenario: Rail shows work details, compensation, dates, and source
- **GIVEN** an opportunity with location, work mode, contract, salary, benefits, dates, and source data
- **WHEN** the detail page renders
- **THEN** the rail SHALL show Work details (location, work mode, contract, seniority, hours, travel, relocation)
- **AND** SHALL show Compensation (salary and period, compensation note, benefits)
- **AND** SHALL show Dates (published, application deadline, expected start, employment duration)
- **AND** SHALL show Source (saved date, original posting link)

#### Scenario: Empty blocks are hidden
- **GIVEN** an opportunity missing compensation or date data
- **WHEN** the detail page renders
- **THEN** the Compensation or Dates block SHALL NOT render
- **AND** the remaining rail blocks SHALL still render

### Requirement: Match brief call to action (OPDETAIL-003)
The page SHALL provide a primary "View match brief" action that navigates to the existing `opportunities-match` route (`/opportunities/:id/match`) for the same opportunity identifier. The action SHALL remain reachable at mobile widths.

#### Scenario: CTA navigates to the match brief
- **GIVEN** the detail page for an opportunity
- **WHEN** the candidate activates the "View match brief" action
- **THEN** the app SHALL navigate to the `opportunities-match` route for the same opportunity id
- **AND** the match-brief page SHALL render

#### Scenario: CTA reachable on mobile
- **GIVEN** the detail page at a mobile width
- **WHEN** the candidate scrolls through the page
- **THEN** the "View match brief" action SHALL be reachable without visiting every section

### Requirement: Required and preferred skill chips (OPDETAIL-004)
The page SHALL render required and preferred skills as two separate, compact chip groups rather than full-width rows.

#### Scenario: Separate chip groups
- **GIVEN** an opportunity with required and preferred skills
- **WHEN** the detail page renders
- **THEN** the required skills render as one compact chip group
- **AND** the preferred skills render as a visually distinct, separate compact chip group
- **AND** catalog-matched and original-label cues remain available per skill

### Requirement: Preserved states and data sections (OPDETAIL-005)
The redesign SHALL preserve the existing behavior: the same data source, the same loading, invalid-id, error-with-retry, and empty states, and the same set of data sections (overview, responsibilities, experience and education, skills, languages and certifications, additional requirements, compensation, dates, source). Sections with no data SHALL remain hidden.

#### Scenario: Loading state
- **GIVEN** the detail page while the opportunity is loading
- **WHEN** the page renders
- **THEN** the page SHALL show an accessible loading state and no partial data

#### Scenario: Invalid identifier
- **GIVEN** a detail URL with an invalid opportunity identifier
- **WHEN** the page renders
- **THEN** the page SHALL show an invalid-identifier alert

#### Scenario: Load failure with retry
- **GIVEN** a load failure for the opportunity
- **WHEN** the page renders
- **THEN** the page SHALL show an error alert with a retry action that re-fetches the opportunity

#### Scenario: Empty sections hidden
- **GIVEN** an opportunity with no responsibilities, no languages, or no additional requirements
- **WHEN** the detail page renders
- **THEN** the corresponding sections SHALL NOT render

#### Scenario: No mutation actions
- **GIVEN** the detail page
- **WHEN** the page renders
- **THEN** the page SHALL expose no edit, delete, or mutation actions

### Requirement: Responsive, accessible, and design-conformant (OPDETAIL-006)
The page SHALL follow the CAREERPILOT_PREMIUM_DESIGN_SYSTEM: a warm neutral page background, clean white surfaces, deep ink typography, a single controlled indigo accent, thin borders, and minimal shadows, and SHALL meet WCAG 2.2 AA.

#### Scenario: Visual-depth presentation
- **GIVEN** the detail page
- **THEN** the page SHALL render on a warm neutral background (#f8fafc)
- **AND** the main-column sections SHALL sit inside a single white container panel with a thin border and rounded corners
- **AND** the page header SHALL show a "Saved opportunity" eyebrow above the title
- **AND** the quick-facts rail SHALL show a "Quick facts" eyebrow above the CTA and blocks
- **AND** the personal label SHALL render as a primary-tinted badge
- **AND** indigo accents SHALL be limited to the eyebrow, badge, required-skill chips, the match CTA, and tinted block icons, with saturated indigo reserved for the CTA

#### Scenario: Design tokens used
- **GIVEN** the detail page
- **THEN** colors, typography, spacing, borders, and radii SHALL come from the project design tokens
- **AND** the page SHALL NOT use gradient-heavy, glassmorphism, or multi-accent styling

#### Scenario: Keyboard operable
- **GIVEN** the detail page
- **WHEN** a keyboard user navigates the back link, the match-brief CTA, and the source links
- **THEN** every control SHALL be reachable and operable by keyboard
- **AND** SHALL show a visible focus state

#### Scenario: Semantic structure and no unsanitized HTML
- **GIVEN** the detail page
- **THEN** the page SHALL use a single H1 and semantic sectioning
- **AND** all API content SHALL render as escaped text without unsanitized `v-html`

### Requirement: Persistent gap-review completion (OPDETAIL-007)
The opportunity detail page SHALL reflect completed clarification work from the existing session resource without requiring the candidate to reopen the match brief.

#### Scenario: Completed gap review remains visible
- **GIVEN** the latest completed match analysis has prior clarification questions
- **AND** its session has no actionable questions
- **WHEN** the candidate opens or refreshes the opportunity detail page
- **THEN** the Match Brief section SHALL show a visible “Gap review complete” status
- **AND** the “Your match at a glance” card SHALL replace its initial “Review gaps” prompt with the completed status and a “Review gaps again” action
- **AND** SHALL explain that reviewed gaps remain listed until profile evidence changes and the match is recalculated
- **AND** when another question batch is available, the status SHALL offer an optional “Review more gaps” action without hiding the completed-round confirmation

#### Scenario: Never-started review is not marked complete
- **GIVEN** the clarification session has no historical questions
- **WHEN** the opportunity detail page renders
- **THEN** the page SHALL NOT label the gap review complete

### Requirement: Company Research section on saved-opportunity detail (OPDETAIL-008)
The page SHALL include a Company Research section within the main column that is powered by the Company Research capability, reusing its states and presentation rules without duplicating them. The section SHALL be absent only when the page is in its page-level loading or invalid-id state; otherwise its own state (not-researched, processing, completed, failed, limited) SHALL render inline and SHALL NOT alter the two-column layout contract, quick-facts rail, or match-brief behavior defined above.

#### Scenario: Company Research renders alongside match brief
- **GIVEN** the detail page for a saved opportunity with no prior research
- **WHEN** the page finishes loading the opportunity
- **THEN** the main column shows the Company Research section with a "Research company" CTA while the Match Brief section and quick-facts rail remain unchanged

#### Scenario: Company Research does not break sticky rail
- **GIVEN** a desktop width with Company Research in the main column
- **WHEN** the candidate scrolls the main column
- **THEN** the quick-facts rail remains sticky and the Company Research card scrolls with the main content

#### Scenario: Page-level states take precedence
- **GIVEN** the detail page is in page-level loading or invalid-id state
- **WHEN** the page renders
- **THEN** the Company Research section SHALL NOT render and only the page-level state is shown

#### Scenario: Company Research isolated error
- **WHEN** fetching the Company Research resource fails while the opportunity loaded successfully
- **THEN** only the Company Research section shows an inline error with retry, while the rest of the detail page stays visible
