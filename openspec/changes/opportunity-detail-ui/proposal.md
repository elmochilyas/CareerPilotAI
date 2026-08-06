## Why

The saved-opportunity detail page (`/opportunities/:id`) is a flat, single-column, text-only dump of sections and description lists. It gives the candidate no quick answer to the first three questions a saved job triggers — "where is it, what does it pay, when is it" — and no obvious next action. Meanwhile the matching feature already exists behind `/opportunities/:id/match` (the Career Intelligence Brief), but the detail page gives no entry point to it, so candidates who saved an opportunity have no discoverable way to start a match analysis from the opportunity record.

This change redesigns the opportunity detail page as a fresh two-column layout with a sticky quick-facts rail (location, contract, compensation, dates, source) and a primary "View match brief" call to action linking to the existing `opportunities-match` route. It is a frontend-only UI change: no API, schema, business-logic, or data-model change. It is built to the mandatory `docs/design/CAREERPILOT_PREMIUM_DESIGN_SYSTEM.md` standard and follows the OpenSpec delta conventions.

## What Changes

- **Fresh two-column detail layout** replacing the flat text-only page: a main column with the editorial sections (overview, responsibilities, experience/education, required vs preferred skills, languages/certifications, additional requirements) and a right quick-facts rail.
- **Sticky quick-facts rail** grouping Work details, Compensation, Dates, and Source with icon-led metadata rows, hidden when empty, stacking below the main content on mobile.
- **Primary "View match brief" CTA** linking to the existing named route `opportunities-match` (`/opportunities/:id/match`) for the same opportunity id, shown at the top of the rail on desktop and as a full-width action in the stacked rail on mobile.
- **Skill chips**: required and preferred skills render as compact chips in two separated groups instead of full-width list rows (per design-system §17).
- **Preserved behavior**: identical data fetching (`opportunityKeys.detail` + `fetchOpportunity`), identical computed partitions and guards, all current states (invalid id, loading skeleton, error with retry), empty sections hidden.
- **Design-language cleanup**: the page drops its bespoke scoped CSS in favor of the global design tokens and the reusable UI kit (`Button`, `Badge`, `Skeleton`), giving the page the same visual language as the rest of the product without cloning the review/design-lab pages.

### Contradictions resolved

None. The mandatory design system (`docs/design/CAREERPILOT_PREMIUM_DESIGN_SYSTEM.md`) explicitly forbids the generic-card-per-section layout the current page uses ("Do not place every section inside a bordered card") and mandates fewer, better surfaces. This change brings the page in line with that standard. The match-brief CTA targets the already-approved `opportunities-match` route from the `profile-job-matching` change; no route or backend change is needed.

## Capabilities

### New Capabilities

- `opportunity-detail-ui`: Frontend redesign of the saved-opportunity detail page — two-column layout, sticky quick-facts rail, primary match-brief CTA, skill chips, full state coverage, responsive and accessible per the design system.

### Modified Capabilities

- `match-brief-ui` (canonical, read-only reference): this change only adds an entry-point link to the existing brief route; it does not change brief behavior.

## Impact

### Backend

- None. No routes, controllers, actions, policies, migrations, config, or dependencies change.

### Frontend

- **Modified page**: `frontend/src/features/opportunities/pages/OpportunityDetailPage.vue` — script logic preserved; template and scoped styles rewritten to the new layout using global tokens and the UI kit.
- **Modified tests**: `frontend/src/__tests__/features/opportunities/OpportunityDetailPage.spec.ts` — assertions adapted to the new markup plus new coverage for the rail and the match-brief CTA.
- **Reused components**: `components/ui/Button.vue`, `components/ui/Badge.vue`, `components/ui/Skeleton.vue`, icon set `@lucide/vue`, `src/app/utils/date.ts` (`formatDate`). No new dependencies.

### Database

- None.

### Security and privacy

- No new data exposure. All values rendered come from the existing candidate-owned opportunity resource already fetched by the page. Links use `target="_blank"` with `rel="noopener noreferrer"` as today. No `v-html`; content stays escaped.

### Risks and assumptions

- **Visual direction**: "fresh new design" means a new layout/composition built on the mandatory design system's tokens and rules (one indigo accent, neutral surfaces, thin borders), not a new color or gradient direction. Deviating from the design system would require a separate approved change.
- **Route existence**: `opportunities-match` (`/opportunities/:id/match`) already exists and is lazy-loaded inside `DefaultLayout`; the CTA only navigates to it.
- **Rail data availability**: rail blocks render only when the corresponding data exists (same guards as today), so partial opportunities still render cleanly.

### Out of scope

- Backend or API changes of any kind
- Match-brief page changes (the CTA navigates to the existing brief)
- Other feature pages (list, import, processing, review)
- Navigation or app-shell changes
- New dependencies or design-token changes
- Dark mode

### Acceptance criteria

- AC-ODETAIL-01: The detail page renders in a two-column layout at desktop (main content left, quick-facts rail right, sticky on scroll) and a single column at mobile, with all existing sections and their data still visible.
- AC-ODETAIL-02: The quick-facts rail shows Work details, Compensation, Dates, and Source grouped with the same data today's page shows, and hides groups that have no data.
- AC-ODETAIL-03: The page shows a primary "View match brief" action linking to the `opportunities-match` route for the same opportunity id; on mobile it remains reachable.
- AC-ODETAIL-04: Required and preferred skills render as separate compact chip groups.
- AC-ODETAIL-05: Invalid-id, loading, error-with-retry states behave exactly as before; no section renders empty when it has no data.
- AC-ODETAIL-06: The page passes lint, format, vue-tsc, Vitest (including the updated detail-page suite), and the production build; browser verification at ~390, ~768, 1280, and 1440 px shows no console errors and keyboard-focusable, contrast-compliant controls.

### Requirement IDs

- OPDETAIL-001: Two-column detail layout with sticky quick-facts rail.
- OPDETAIL-002: Quick-facts rail groups Work details, Compensation, Dates, and Source.
- OPDETAIL-003: Primary "View match brief" CTA linking to the `opportunities-match` route for the same opportunity.
- OPDETAIL-004: Required and preferred skills render as separate compact chip groups.
- OPDETAIL-005: All existing states and data sections are preserved with identical behavior.
- OPDETAIL-006: Responsive, accessible, and conformant to the CAREERPILOT_PREMIUM_DESIGN_SYSTEM.
