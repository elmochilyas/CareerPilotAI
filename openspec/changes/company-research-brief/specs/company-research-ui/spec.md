## Purpose

Present the company research brief inside the saved-opportunity detail experience so candidates can quickly understand the company, see which claims are confirmed vs. inferred, inspect sources, and refresh research, with clear handling for every async state.

## ADDED Requirements

### Requirement: Opportunity detail integration (CRES-UI-001)
The system SHALL display Company Research as a dedicated section/card within the saved-opportunity detail page (not a separate dashboard). The section SHALL sit in the editorial layout defined by `opportunity-detail-ui` (main column or appropriate slot) without breaking the two-column desktop / single-column mobile contract and without introducing a new top-level route.

#### Scenario: Section present on detail page
- **WHEN** a candidate opens `/opportunities/:id` for a saved opportunity
- **THEN** the page renders a Company Research card/section between the overview and the match-brief block (or as a designated tab if the detail page uses tabs) and the existing two-column layout remains intact at 1280 px

#### Scenario: Mobile stacking preserved
- **WHEN** the detail page is viewed at 390 px width
- **THEN** the Company Research section stacks in single-column flow and no horizontal scrolling occurs

### Requirement: State coverage (CRES-UI-002)
The section SHALL handle five states with distinct copy and actions: `not_researched` (CTA "Research company"), `processing` (progress/status with polling and announcement), `completed` (structured brief), `failed` (useful retry message + retry action), and `limited/fallback` (explanation that research is based only on available opportunity/company information). Each state SHALL be accessible and retryable without a full-page reload.

#### Scenario: Not researched
- **WHEN** no research exists for the opportunity
- **THEN** the section shows a compact empty state with "Research company" button and a short description of what research provides

#### Scenario: Processing
- **WHEN** research was just started and `status = processing`
- **THEN** the section shows a busy indicator, "Researching company…" text, and polls `GET /api/v1/opportunities/{id}/company-research` until terminal status without blocking other page content

#### Scenario: Completed
- **WHEN** `status = completed` and a brief is present
- **THEN** the section renders all six content groups (overview, products, technology context, role context, recent information, candidate preparation) with data or `unknown` placeholders

#### Scenario: Failed with retry
- **WHEN** `status = failed`
- **THEN** the section shows a failure notice, the `failure_reason` in user-friendly wording, and a "Retry research" button that POSTs to refresh

#### Scenario: Limited fallback
- **WHEN** `status = limited` or `completed` with `fallback_reason = fetch_unavailable`
- **THEN** the section shows a callout explaining the brief is limited to opportunity/company information and did not use externally verified sources

#### Scenario: Retry after failed does not blank page
- **WHEN** retry is triggered from the failed state
- **THEN** the section transitions to `processing` while the rest of the detail page remains interactive

### Requirement: Fact vs. inference presentation (CRES-UI-003)
The UI SHALL visually distinguish facts from inferences and unknowns. Facts SHALL carry a "FACT — Official company source" (or source-appropriate) badge; inferences SHALL carry an "INFERENCE — CareerPilot interpretation based on the sources" badge. Unknowns SHALL show muted "Unknown — could not be reliably established" text. The system SHALL never render an inference with fact styling.

#### Scenario: Badges visible
- **WHEN** a brief contains one fact claim and one inference claim in the same section
- **THEN** the fact line shows the FACT badge and the inference line shows the INFERENCE badge, each with color/aria that is distinguishable without relying solely on color

#### Scenario: Unknown rendered as unknown
- **WHEN** headquarters is `unknown`
- **THEN** the section shows "Headquarters: Unknown — could not be reliably established" in muted styling

#### Scenario: Screen reader distinction
- **WHEN** a screen reader traverses the brief
- **THEN** each claim announces its `fact`/`inference` role via an accessible label or visually hidden text

### Requirement: Section content rendering (CRES-UI-004)
The section SHALL render the six brief groups in order: Overview (name, website, industry, headquarters/location, description), Products/Services, Technology/Engineering Context, Role Context, Recent Relevant Information, Candidate Preparation (facts to know + topics worth understanding). Each group SHALL be collapsible or visually separated, handle empty data gracefully (hide empty group or show "No data" placeholder), and use semantic headings (`h3` within the section) without unsanitized `v-html`.

#### Scenario: All groups present
- **WHEN** a completed brief has all groups populated
- **THEN** all six are rendered with headings and body text

#### Scenario: Empty group hidden or placeholder
- **WHEN** `recent_information` is empty
- **THEN** the group shows a muted "No recent information available" placeholder rather than an empty white box

#### Scenario: No unsanitized HTML
- **WHEN** a brief contains text that looks like `<script>` or `<a href>`
- **THEN** it renders as escaped text and no script executes

### Requirement: Sources display (CRES-UI-005)
Every important section SHALL show a compact source indicator (e.g., "Sources: Official Website · Careers Page") without cluttering every line with citation numbers. Clicking/expanding the indicator SHALL reveal source detail: source title/name, URL domain (clickable where safe and using `rel="noopener noreferrer"` + `target="_blank"`), and `retrieved_at` date. Raw URLs and raw JSON SHALL NOT be dumped as the primary presentation.

#### Scenario: Compact indicators per section
- **WHEN** a completed brief has sources for overview and products
- **THEN** each section footer shows a compact source line with domain chips

#### Scenario: Expanded source detail
- **WHEN** the candidate expands sources for a section
- **THEN** the expansion lists each source with its title (or domain fallback), clickable URL, and "Retrieved 2026-08-25" date

#### Scenario: Safe external links
- **WHEN** a source URL is clicked
- **THEN** it opens in a new tab with `rel="noopener noreferrer"` and does not navigate the SPA away

### Requirement: Staleness and refresh (CRES-UI-006)
The UI SHALL show "Last researched: …" with relative or absolute date derived from `researched_at`/`generated_at`, indicate stale status when the API returns `stale: true` (e.g., amber badge "Potentially outdated — refresh recommended"), and expose actions "Research company" (when none) and "Refresh research" (when exists). While `processing`, the refresh action SHALL be disabled and a live-region announcement SHALL convey the status. Manual refresh SHALL require a single click and show inline feedback.

#### Scenario: Age displayed
- **WHEN** research was generated 2 days ago
- **THEN** the header shows "Last researched: 2 days ago (2026-08-23)"

#### Scenario: Stale badge after threshold
- **WHEN** `researched_at` is 31 days ago and threshold is 30 days
- **THEN** the section shows a "Potentially outdated" badge next to the date and the Refresh button remains enabled

#### Scenario: Refresh disabled while processing
- **WHEN** `status = processing`
- **THEN** the Refresh button is `disabled` with an aria-busy indicator and activating it does not dispatch a second request

#### Scenario: Action announcements
- **WHEN** research moves from `processing` to `completed`
- **THEN** an `aria-live="polite"` region announces "Company research completed"

### Requirement: Loading, error, and empty handling (CRES-UI-007)
The section SHALL respect the detail page's query lifecycle: show a skeleton while the opportunity loads, an inline error with retry when the company-research fetch fails, and never blank the rest of the page when its own request fails.

#### Scenario: Skeleton while loading
- **WHEN** the opportunity detail query is pending
- **THEN** the Company Research section shows a skeleton placeholder and no partial brief

#### Scenario: Research fetch error isolated
- **WHEN** `GET /api/v1/opportunities/:id/company-research` returns 500
- **THEN** only the Company Research card shows an error alert with "Retry" that refetches just that card

#### Scenario: Keyboard operable
- **WHEN** a keyboard user tabs through the section
- **THEN** CTA buttons, refresh, and source links are all reachable and show a visible focus ring

### Requirement: No duplication of server business rules (CRES-UI-008)
The frontend SHALL NOT duplicate normalization, scoring, or fact-vs-inference logic. It SHALL render exactly what the API returns (`fact`/`inference`/`unknown` markers, `status`, `stale`, `fallback_reason`) and derive no new claims client-side.

#### Scenario: Frontend does not reclassify
- **WHEN** API returns an inference
- **THEN** the UI renders it as inference even if the text looks factual; no client-side heuristic promotes it to fact

### Requirement: Design conformance (CRES-UI-009)
The section SHALL follow `CAREERPILOT_PREMIUM_DESIGN_SYSTEM` (warm neutral page, white card, thin border, rounded corners, ink typography, indigo reserved for primary CTA/badge) and WCAG 2.2 AA (semantic headings, contrast, visible focus, aria live for async status).

#### Scenario: Visual tokens applied
- **WHEN** the section renders
- **THEN** its card uses a white surface with `border-[var(--border-default)]`, rounded `xl`, and spacing/typography from design tokens without heavy gradients or glassmorphism

### Requirement: Source safety (CRES-UI-010)
The section SHALL treat all source titles and URLs as untrusted text, escape them, validate URL shape before making them clickable, and never execute fetched HTML.

#### Scenario: Malicious title escaped
- **WHEN** a source title contains `<img onerror=alert(1)>`
- **THEN** it renders as escaped text and no image or script loads
