## 1. Inspection and baseline

- [x] 1.1 Inspect `OpportunityDetailPage.vue`, its existing suite, the opportunities api/types, router routes (`opportunities-detail`, `opportunities-match`), the UI kit, and the design system
- [x] 1.2 Run baseline frontend checks (`npm run lint`, `npm run test:unit -- --run`) and confirm green before editing

## 2. Page redesign

- [x] 2.1 Rewrite `OpportunityDetailPage.vue` template + scoped styles: page header (back link, single H1 title, company · location · work mode subtitle, personal-label badge), two-column layout, main-column open sections, sticky quick-facts rail, skill chips
- [x] 2.2 Add the primary "View match brief" CTA as a `RouterLink` to `{ name: 'opportunities-match', params: { id } }` at the top of the rail, full-width in the stacked mobile layout
- [x] 2.3 Reuse `Skeleton`, `Badge`, `Button`, global tokens, and `formatDate`; drop the page-local date formatter
- [x] 2.4 Preserve script logic: same `useQuery` options, computed partitions, `hasWorkDetails`/`hasCompensation`/`hasDates` guards, `formatLabel`, salary formatting, invalid-id/loading/error+retry states, empty-section hiding

## 3. Tests

- [x] 3.1 Update `OpportunityDetailPage.spec.ts` for the new markup while keeping the existing mocked `useQuery` + api module and the opportunity fixture
- [x] 3.2 Add assertions: quick-facts rail renders work/compensation/dates/source; required vs preferred skills separated; "View match brief" links to `opportunities-match`; no edit/delete/mutation actions
- [x] 3.3 Run `npm run test:unit -- --run` and confirm the updated suite passes

## 4. Quality gates

- [x] 4.1 Run `npm run format` and `npm run lint`
- [x] 4.2 Run `npm run build` (vite + vue-tsc) and confirm clean

## 5. Visual-depth pass (approved follow-up)

- [x] 5.1 Add app-wide warm neutral background `body { background-color: #f8fafc; }` in `frontend/src/assets/main.css`
- [x] 5.2 Add the `primary` variant to `Badge.vue` for the personal-label badge
- [x] 5.3 Rewrite the `OpportunityDetailPage.vue` presentation: "Saved opportunity" eyebrow, clamp-sized H1, company line, bordered icon chips, single white main-panel card, section dividers, "Quick facts" eyebrow, balanced indigo accents, CTA shadow, primary-tinted rail icons, matching skeleton; script logic unchanged
- [x] 5.4 Extend `OpportunityDetailPage.spec.ts` with assertions for the header/rail eyebrows and personal-label badge
- [x] 5.5 Record the approved visual-depth pass in `design.md` and `spec.md` (OPDETAIL-006)

## 6. Quality gates

- [x] 6.1 Run `npm run format` and `npm run lint`
- [x] 6.2 Run `npm run test:unit -- --run` and confirm the updated suite passes
- [x] 6.3 Run `npm run build` (vite + vue-tsc) and confirm clean

## 7. Browser verification

- [ ] 7.1 Verify the detail page at ~390, ~768, 1280, and 1440 px with no console errors, usable focus/keyboard, and the match CTA round trip to `/opportunities/:id/match`
- [ ] 7.2 Confirm empty sections and the loading/error states render correctly

## 8. OpenSpec verification

- [ ] 8.1 Confirm all artifacts present and `/opsx:verify` resolves with no unresolved critical findings
