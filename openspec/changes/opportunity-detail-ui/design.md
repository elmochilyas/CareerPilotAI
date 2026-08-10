## Context

The saved-opportunity detail page is `frontend/src/features/opportunities/pages/OpportunityDetailPage.vue`. It fetches the opportunity with TanStack Vue Query (`opportunityKeys.detail(id)` + `fetchOpportunity`), partitions `requirements` into responsibilities, experience/education, and languages/certifications, partitions `skills` into required and preferred, and renders every section as a bordered card with a description list — a flat, single-column, text-only presentation.

Relevant existing files:
- `frontend/src/features/opportunities/pages/OpportunityDetailPage.vue` — the page to redesign
- `frontend/src/__tests__/features/opportunities/OpportunityDetailPage.spec.ts` — existing suite (mocks `@tanstack/vue-query` and the api module; fixture opportunity id 8 "Platform Engineer"/Acme)
- `frontend/src/features/opportunities/api/index.ts` — `opportunityKeys.detail`, `fetchOpportunity`
- `frontend/src/features/opportunities/types` — `JobOpportunity` shape (skills with `classification`, requirements with `category`/`content`/`source_evidence`, work/compensation/date fields, `benefits`, `additional_requirements`)
- `frontend/src/router/index.ts` — routes `opportunities` (list), `opportunities-detail` (`/opportunities/:id`), `opportunities-match` (`/opportunities/:id/match` → `MatchBriefPage`)
- `frontend/src/features/matching/pages/MatchBriefPage.vue` — links back via `{ name: 'opportunities-detail', params: { id } }`
- `docs/design/CAREERPILOT_PREMIUM_DESIGN_SYSTEM.md` — mandatory UI standard (open layout, fewer cards, one indigo accent, `--cp-*`-equivalent tokens)
- `frontend/src/assets/main.css` — global Tailwind v4 `@theme` tokens (`--color-primary-*` indigo scale, semantic success/warning/error/info, Inter)
- `frontend/src/components/ui/` — `Button.vue` (primary/secondary/outline/ghost/danger, sm/md/lg), `Badge.vue` (default/success/warning/error/info), `Skeleton.vue`
- `frontend/src/app/utils/date.ts` — `formatDate(date, locale = 'en-US')`
- Icon library: `@lucide/vue`

Constraint notes: the current page hardcodes colors (e.g. `#667085`, `#e4e7ec`, `#4338ca`) that already match the design-system palette; the redesign keeps that palette but switches to the global tokens so the page stops carrying its own bespoke scoped CSS. The `opportunities-match` route already exists, so the match CTA needs no router change.

## Goals / Non-Goals

**Goals:**
- Two-column desktop layout: editorial sections in the main column, sticky quick-facts rail on the right
- Quick-facts rail groups Work details, Compensation, Dates, and Source with the same data as today
- Primary "View match brief" CTA to the existing `opportunities-match` route
- Required vs preferred skills as compact, visually distinct chip groups
- Identical data loading, computed logic, guards, and state behavior
- Conformance to `CAREERPILOT_PREMIUM_DESIGN_SYSTEM` and WCAG 2.2 AA

**Non-Goals:**
- Any backend, API, schema, route, or business-logic change
- Changes to the match-brief page, the list page, or the app shell
- New dependencies, new design tokens, or dark mode
- Embedding the brief in the detail page (a follow-up option noted in `profile-job-matching/design.md` Open Questions)

## Decisions

### Decision 1: Two-column layout with a sticky quick-facts rail

Desktop uses a grid with a fluid main column and a fixed-width rail (`lg:grid-cols-[minmax(0,1fr)_320px]`). The rail is `lg:sticky lg:top-*` so quick facts remain visible while the candidate scans responsibilities and skills. The main column uses open sections separated by typographic hierarchy and subtle dividers — not a bordered card per section (design system §2.3, §12). The rail is a single meaningful panel (an interactive/factual unit) and is therefore an allowed card.

Rejected alternatives:
- **Keep the current flat single column** — leaves the "where/pay/when/next-action" problem unsolved.
- **Three columns** — too dense for a detail read; the design system prefers fewer surfaces.
- **Tabs (Overview/Facts/Requirements)** — hides facts behind a click; the whole point is instant answers.

### Decision 2: Page header, not a hero card

Per design system §8, the page header contains the page title and optional actions, and the title is not repeated inside the first card. The job title becomes the single H1, with company · location · work mode as subtitle, the personal label as a badge, and a subtle back-to-list link above. No hero card.

### Decision 3: Main-column section ordering and types

Main column order: Overview (department/reference/summary/application link), Responsibilities (numbered with collapsible source evidence), Experience & education, Required skills chips, Preferred skills chips, Languages & certifications, Additional requirements. Each section uses a type-appropriate component (prose summary, numbered list, chip groups, requirement rows) rather than the same generic card — per design system §9 ("Experience should not look like a skill; a job responsibility should not look like a form field").

### Decision 4: Quick-facts rail content and behavior

Rail blocks: **Work details** (location, work mode, contract, seniority, hours, travel, relocation), **Compensation** (salary + period, compensation note, benefits), **Dates** (published, deadline, expected start, duration), **Source** (saved date, original posting link). Every block renders only when it has data (the existing `hasWorkDetails`/`hasCompensation`/`hasDates` computeds are reused); an empty rail collapses entirely so a sparse opportunity still reads cleanly.

The "View match brief" CTA sits at the top of the rail (desktop) and above the rail blocks in the stacked mobile layout, full-width. It uses `RouterLink` with `{ name: 'opportunities-match', params: { id } }` and is styled as the primary action; a secondary "Back to list" tertiary link stays in the page header.

### Decision 5: Skill chips

Skills render as two groups — Required and Preferred — each a wrapping row of compact chips (name + "catalog matched"/"original label" cue via small Badge where meaningful). This matches design system §17 ("no full card per skill", "required and preferred stay separate") and reduces the current full-width-row density.

### Decision 6: State and logic preservation

The script section stays functionally identical: same `useQuery` options, same computed partitions, same guards (`opportunityId === null`, `isPending`, `isError || !opportunity`, `hasWorkDetails`, `hasCompensation`, `hasDates`), same `formatLabel`, same salary/date formatting. Only rendering changes; `formatDate` from `src/app/utils/date.ts` replaces the page-local formatter to reduce duplication. Loading uses the `Skeleton` component; error keeps the inline retry.

### Decision 7: Frontend state and accessibility handling

- Every async screen state is covered: invalid id (alert), loading (skeletons + `role="status"` + sr-only text), error (alert + retry), data (all sections with empty-section hiding).
- Semantic HTML: one H1, sectioned content with visible headings, `dl` only inside the rail, lists for responsibilities/skills/benefits.
- Keyboard: back link and CTA are real `RouterLink`s with visible focus; source links keep `rel="noopener noreferrer"`.
- `prefers-reduced-motion` is respected via the global animation guards; no new animation is introduced.
- No `v-html`; all API content renders escaped.

### Decision 8: Visual-depth pass (approved follow-up to the first redesign)

The first rewrite rendered correctly but read as flat as a Wikipedia article, so an approved visual-depth pass was applied without any data, API, route, or business-logic change. The approved direction (confirmed by the owner):

- **App-wide warm neutral background**: `body { background-color: #f8fafc; }` added to `frontend/src/assets/main.css` so the page no longer sits on white-on-white.
- **One white main-panel card**: the main column content (all sections) now lives inside a single `rounded-xl border border-slate-200 bg-white p-6 sm:p-8` panel — the one permitted container card for a reading workflow (design system §12). Rail remains its own panel.
- **Balanced indigo accent**: indigo is used only as an eyebrow ("Saved opportunity", "Quick facts"), the personal-label `Badge` (new `primary` variant in `Badge.vue`), required-skill chips, the match CTA, and tinted block icons. Saturated indigo stays limited to the CTA (design system §4.2).
- **Editorial header**: single clamp-sized H1 with tight tracking, company line, and bordered icon chips (MapPin / BriefcaseBusiness / FileCheck2) instead of a plain subtitle.
- **Section styling**: titles at `text-[1.375rem] font-semibold tracking-tight`, `border-t border-slate-100 pt-10` dividers, summary as `text-base leading-7`.
- **Skeleton pass**: the loading skeleton mirrors the new panel + rail layout.

Rejected alternatives: a full hero card, per-section cards, gradient/glassmorphism surfaces, and a second accent color.

### Decision 9: Persistent gap-review completion on the detail page

The shared `ClarificationEntryCard` keeps its existing hidden-empty behavior by default for the match brief. The opportunity detail page opts into a completed state when the session has historical questions (`progress.total > 0`) but no actionable questions. If another capped batch can be generated, the completed state acknowledges the finished round and offers an optional “Review more gaps” action. This uses the existing clarification session resource, adds no API or business rule, survives refresh, and avoids treating a never-started empty session as completed.

The same derived completion signal is passed into `MatchAtAGlance`. That card replaces its primary “Review gaps” prompt with a success status and secondary “Review gaps again” action, while continuing to list the deterministic gaps until a profile change and match recalculation alter them.

## Risks / Trade-offs

- **Density on mobile**: the rail stacks below the main content; the CTA appears once, above the rail, so it is reachable without scrolling past the entire detail. Trade-off accepted over a sticky mobile action bar (kept as a possible follow-up).
- **Sticky rail height**: on very long pages the sticky rail scrolls with the viewport; with `max-h` + overflow scroll it could exceed the viewport. Mitigation: keep the rail compact (facts are short rows) and let it be `self-start` so it stops when its column ends instead of overlapping the footer.
- **Test churn**: the redesign changes many selectors in the existing suite; the suite is updated in the same change and kept green.

## Migration Plan

1. Rewrite `OpportunityDetailPage.vue` template + styles; keep the script logic.
2. Update `OpportunityDetailPage.spec.ts` in the same commit.
3. Run frontend gates (`npm run format`, `npm run lint`, `npm run test:unit -- --run`, `npm run build`).
4. Browser verify at ~390 / ~768 / 1280 / 1440 px, console and network, keyboard/focus, and the match-brief round trip.
5. No backend or database migration; no rollback risk beyond reverting the page + test files.

## Open Questions

- Whether the mobile experience should later add a sticky bottom action bar for the match CTA — deferred; the stacked full-width CTA is sufficient for this change.
