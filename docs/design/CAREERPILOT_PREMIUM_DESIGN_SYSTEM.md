# CareerPilot — Premium Product Design Direction

> **Status:** Mandatory product UI standard  
> **Audience:** Designers, frontend developers, and AI coding agents  
> **Goal:** Make CareerPilot feel like a carefully designed premium SaaS product, not a generated dashboard template.

---

## 1. Design Position

CareerPilot should feel like a serious career operating system.

The interface must communicate:

- Precision
- Trust
- Calm confidence
- Strong product thinking
- High-quality engineering
- Editorial clarity
- Professional maturity

The product should look intentionally designed by a strong product team, not assembled from common UI patterns.

### The visual direction

Use a restrained, premium, product-led style:

- Clean white surfaces
- Warm neutral page background
- Deep ink typography
- One controlled indigo accent
- Thin borders
- Minimal shadows
- Strong spacing rhythm
- High-quality typography
- Quiet interactions
- Carefully designed empty, loading, error, and success states

### The interface must not feel like

- A generic Tailwind dashboard
- A shadcn demo
- A generated admin panel
- A purple-gradient AI product
- A collection of disconnected cards
- A landing page inside the app
- A form builder
- A prototype
- A student project

---

## 2. Non-Negotiable Rules

These rules take priority over decorative ideas.

### 2.1 No generic AI aesthetic

Do not use:

- Purple or blue gradients
- Glowing borders
- Glassmorphism
- Floating blurred blobs
- Oversized sparkles
- Robot icons
- “AI-powered” labels everywhere
- Neon colors
- Random illustrations
- Excessive rounded cards
- Huge hero sections inside authenticated pages
- Empty cards used only to fill space

AI should be expressed through product behavior, not visual clichés.

### 2.2 One dominant visual language

Every feature must use the same:

- Page shell
- Typography system
- Spacing scale
- Button hierarchy
- Card logic
- Form controls
- Status system
- Stepper behavior
- Empty-state structure
- Error presentation

Do not redesign the product independently for each feature.

### 2.3 Fewer surfaces, better hierarchy

Do not place every section inside a bordered card.

Use:

- Open page layouts
- Section spacing
- Subtle dividers
- Structured rows
- Typographic hierarchy
- One primary container only where necessary

Cards are reserved for meaningful grouped objects or interactive units.

### 2.4 Design around the user task

The user should understand within three seconds:

- Where they are
- What they need to do
- What is complete
- What needs attention
- What the next action is

If this is not obvious, the screen is not finished.

---

## 3. Brand Character

CareerPilot should feel like a blend of:

- A refined productivity tool
- A serious professional platform
- A modern financial product
- An editorial career workspace

The product personality is:

- Direct, not robotic
- Helpful, not chatty
- Premium, not luxurious
- Modern, not trendy
- Intelligent, not flashy
- Human, not playful

---

## 4. Color System

Use one accent family and mostly neutral surfaces.

### 4.1 Core palette

```css
:root {
  /* App surfaces */
  --cp-bg: #f7f8fa;
  --cp-surface: #ffffff;
  --cp-surface-subtle: #fafafa;
  --cp-surface-muted: #f2f4f7;

  /* Text */
  --cp-ink: #111827;
  --cp-text: #263244;
  --cp-text-muted: #667085;
  --cp-text-faint: #98a2b3;
  --cp-text-inverse: #ffffff;

  /* Borders */
  --cp-border: #e4e7ec;
  --cp-border-strong: #d0d5dd;
  --cp-divider: #eaecf0;

  /* Primary */
  --cp-primary: #4f46e5;
  --cp-primary-hover: #4338ca;
  --cp-primary-soft: #eef2ff;
  --cp-primary-border: #c7d2fe;

  /* Semantic */
  --cp-success: #067647;
  --cp-success-soft: #ecfdf3;
  --cp-success-border: #abefc6;

  --cp-warning: #b54708;
  --cp-warning-soft: #fffaeb;
  --cp-warning-border: #fedf89;

  --cp-danger: #b42318;
  --cp-danger-soft: #fef3f2;
  --cp-danger-border: #fecdca;

  --cp-info: #175cd3;
  --cp-info-soft: #eff8ff;
  --cp-info-border: #b2ddff;
}
```

### 4.2 Usage rules

- The page background is soft neutral, not pure white.
- Main content surfaces are white.
- Indigo is used only for:
  - Primary actions
  - Active navigation
  - Selected states
  - Focus rings
  - Active steps
- Green is only for successful and completed states.
- Amber is only for warnings and unresolved issues.
- Red is only for destructive actions and failures.
- Do not add additional brand colors unless approved.
- Never use more than one accent color in one action area.
- Avoid saturated backgrounds across large areas.

---

## 5. Typography

Typography is the main source of visual quality.

### 5.1 Font

Use the project’s existing premium sans-serif if already configured.

Preferred order:

```css
font-family:
  "Geist",
  "Inter",
  ui-sans-serif,
  system-ui,
  -apple-system,
  BlinkMacSystemFont,
  "Segoe UI",
  sans-serif;
```

### 5.2 Type scale

| Style | Size | Line height | Weight | Use |
|---|---:|---:|---:|---|
| Page title | 32px | 40px | 650–700 | Main page heading |
| Section title | 22px | 30px | 650 | Main section |
| Card title | 17px | 24px | 600 | Object title |
| Body large | 16px | 26px | 400 | Intro text |
| Body | 14px | 22px | 400 | Default UI copy |
| Label | 13px | 20px | 600 | Form labels |
| Metadata | 13px | 20px | 400 | Dates, context |
| Caption | 12px | 18px | 500 | Badges, hints |

### 5.3 Typography rules

- One H1 per page.
- Avoid large bold text everywhere.
- Use weight, spacing, and alignment before adding color.
- Keep readable content under 72 characters per line.
- Use sentence case for all UI labels.
- Avoid uppercase except very small technical metadata when necessary.
- Do not use font sizes below 12px.
- Long text should use generous line height.

---

## 6. Spacing and Rhythm

Use a strict 4px spacing scale.

```text
4, 8, 12, 16, 20, 24, 32, 40, 48, 64, 80
```

### Default rhythm

- Icon to text: 8px
- Label to control: 8px
- Related controls: 12px
- Card internal spacing: 16–24px
- Section spacing: 32px
- Major page section spacing: 48px
- Page top spacing: 40–56px
- Desktop side padding: 32px
- Mobile side padding: 16px

### Important rule

Large empty spaces are not premium.

Premium layout means controlled whitespace around meaningful content, not blank screens with a small card in the corner.

---

## 7. Radius and Shadow

### Radius

```text
Small control: 8px
Input/button: 9px
Card: 12px
Panel/dialog: 14px
Chip: 9999px
```

Avoid 20–30px radii on standard application cards.

### Shadow

Default product surfaces should use borders, not shadows.

```css
--shadow-float: 0 12px 32px rgba(16, 24, 40, 0.10);
--shadow-dialog: 0 24px 48px rgba(16, 24, 40, 0.16);
```

Use shadows only for:

- Dropdowns
- Menus
- Dialogs
- Floating action surfaces

---

## 8. Application Shell

### Top navigation

The header must feel compact and stable.

- Height: 64px
- White surface
- Thin bottom border
- Brand on the left
- Main routes in the center or left
- Account controls on the right
- Active route uses a subtle filled state or underline
- No large pill around every navigation item
- No decorative shadows

### Main content

Use clear max-widths:

| Page type | Width |
|---|---:|
| Simple form | 680–720px |
| Processing | 720–780px |
| Review workflow | 920–1040px |
| List | 1120–1240px |
| Dashboard | 1240–1320px |

### Page header

Every page header should contain:

- Page title
- One concise sentence
- Optional primary action
- Optional subtle back navigation

Avoid repeating the page title inside the first card.

---

## 9. Premium Component Philosophy

A premium product does not use more components. It uses fewer, better components.

Every component must have:

- A clear purpose
- A strong visual hierarchy
- Consistent spacing
- Complete interaction states
- Responsive behavior
- Accessibility
- Error handling

Avoid generic components that render completely different content types in the same way.

Examples:

- Experience should not look like a skill
- A language should not look like a status badge
- A job responsibility should not look like a form field
- A profile comparison should not look like a dashboard card

---

## 10. Buttons

### Primary

Use only for the main action in the current area.

Examples:

- Continue
- Save and continue
- Generate preview
- Confirm opportunity
- Apply to profile

Style:

- Solid indigo
- White text
- Height 42–44px
- Medium weight
- Quiet hover
- Clear focus ring
- Stable width while loading

### Secondary

Examples:

- Previous
- Edit
- Upload another file
- Return to opportunities

Style:

- White surface
- Neutral border
- Dark text
- Subtle hover background

### Tertiary

Examples:

- Undo
- Cancel edit
- View source
- Change decision

Style:

- Text or subtle ghost action
- Never visually compete with primary action

### Destructive

Examples:

- Delete
- Cancel ingestion
- Remove confirmed data

Use red text or border by default. Use solid red only in confirmation dialogs.

### Button rules

- One primary button per action zone.
- Do not use icon-only buttons without labels or tooltips.
- Avoid tiny text buttons.
- Avoid placing four equal-weight buttons next to every item.
- Disabled buttons must remain readable and explain why when needed.
- Loading actions must prevent duplicate submission.

---

## 11. Forms

Premium forms are calm and clear.

### Inputs

- Height: 44px
- Border: neutral
- Radius: 9px
- White background
- Soft focus ring
- No heavy shadows
- No oversized labels

### Labels

- Always visible
- Medium weight
- Placed above controls
- Helper text below when needed

### Textareas

Large content areas should include:

- Clear label
- Character count
- Minimum height around 240px
- Helpful description
- Stable layout while typing

### Validation

- Preserve user input
- Show field-level feedback
- Use page-level summaries only for multiple errors
- Do not show all errors before interaction
- Do not use red outlines without explanatory text

---

## 12. Cards and Panels

### Standard object card

Use for one meaningful entity:

- Experience
- Project
- Education
- Opportunity
- Processing result

Structure:

```text
Title
Secondary context
Metadata
Main content
Actions
```

Style:

- White surface
- 1px neutral border
- 12px radius
- 20–24px padding
- No default shadow

### Section panel

Use a light open layout with:

- Section heading
- Supporting text
- Content
- Optional divider

Do not wrap the entire page in one giant bordered container unless the workflow benefits from it.

### Avoid

- Card inside card inside card
- Repeating the same large card for every small field
- Excessive borders
- Giant empty surfaces
- Different border radii across pages
- Full-width colored backgrounds without purpose

---

## 13. Lists

List pages should feel like product surfaces, not dashboards.

### Opportunity row

```text
Job title
Company · Location · Work mode

Status                     Updated time                     Action
```

Use:

- Compact spacing
- Clear title hierarchy
- Status badge
- One primary next action
- Row hover
- Thin divider or subtle card border

Avoid:

- Large promotional cards
- Too many statistics
- Decorative icons on every line
- Multiple unrelated actions

---

## 14. Processing Experience

Processing screens must feel designed, not like debug output.

### Layout

Use a centered column around 720px.

Top area:

- Page title
- One short explanation
- Optional file/source summary

Progress area:

- Clear vertical progress
- Completed, active, pending, failed states
- Quiet animation
- No fake percentage

Example:

```text
✓ Description received
✓ Validated
● Analyzing job information
○ Preparing review
```

### State behavior

- The active step is visually strongest.
- Completed steps are calm green.
- Pending steps are neutral.
- Failure highlights only the failed step.
- Long-running state includes:
  “This is taking longer than usual. You can leave this page and return later.”
- Terminal states stop polling.
- Success transitions to review.
- Failure shows a focused recovery card.

### Error card

Use:

- Small icon
- Strong title
- One sentence
- One primary action
- One secondary action
- Optional technical reference hidden or de-emphasized

Do not show internal error codes prominently.

---

## 15. Multi-Step Review

Long review pages must be split into steps.

### Desktop

Use a horizontal stepper only when labels remain readable.

Otherwise use:

- Left-side step navigation
- Main review panel
- Sticky bottom action bar

### Mobile

Use:

```text
Step 3 of 7
Experience
[progress bar]
```

Do not create horizontal overflow.

### Step rules

- Only one step visible at a time.
- Empty steps are removed.
- Previous steps remain accessible.
- Final confirmation is gated.
- Decisions persist.
- Progress reflects actual decisions, not visited pages.
- Each step uses type-specific components.

### Footer

Use a sticky action bar inside the review area:

```text
Previous        12 of 28 reviewed        Save and continue
```

Keep it compact, aligned, and non-obstructive.

---

## 16. Review Patterns

### Profile field comparison

Use a structured comparison, not two cards.

```text
Phone

Current profile
Not provided

From CV
+212 600 000 000

Use CV value   Keep current   Edit
```

### Entity review

Experience, project, and education remain complete entities.

Use one clear card per entity.

### Accepted state

After a decision is saved:

- Reduce visual weight
- Show selected status
- Keep “Change decision” available
- Avoid leaving the full action bar permanently expanded

---

## 17. Skills

Skills should be visually compact.

### Grouped display

```text
Backend
[PHP ×] [Laravel ×] [REST APIs ×] [Queues ×]

Databases
[MySQL ×] [PostgreSQL ×] [Supabase ×]
```

### Skill chip

A skill chip includes:

- Name
- Optional small state
- Remove control
- Accessible label
- Responsive wrapping

### Rules

- No full card per skill.
- No Accept/Edit/Keep/Reject button set for every skill.
- Removal is reversible.
- Existing skills are not duplicated.
- Archived and ambiguous skills are clearly labeled.
- Required and preferred job skills stay separate.
- Extracted skills never become verified automatically.

---

## 18. Responsibilities and Requirements

Responsibilities are editable list items.

Each row should support:

- Keep
- Edit
- Remove
- Restore

Use a clean list with dividers.

Do not use a large card for every sentence.

Adding a missing item belongs at section level, not inside each row.

---

## 19. Preview and Confirmation

The final preview should feel like a professional summary, not a raw data dump.

Use sections:

- Overview
- Work details
- Responsibilities
- Experience requirements
- Education requirements
- Required skills
- Preferred skills
- Languages
- Certifications
- Compensation and benefits
- Excluded items
- Warnings

Hide empty sections.

Show one clear confirmation action.

When disabled, explain the exact reason.

Do not show raw JSON.

---

## 20. Status and Feedback

### Status badges

Use compact semantic badges:

- Draft
- Processing
- Ready for review
- Confirmed
- Failed
- Cancelled
- Archived

Avoid bright saturated pills.

### Toasts

Use for:

- Saved
- Removed with Undo
- Retry started
- Non-blocking success

Do not use toasts for critical errors.

### Inline feedback

Use for:

- Validation
- Preview stale
- Unresolved conflicts
- Processing failure
- Unsaved changes

---

## 21. Empty States

Premium empty states are specific and useful.

Structure:

- Small relevant icon
- Clear title
- One sentence
- One primary action

Example:

```text
No job opportunities yet

Add a job description to create your first opportunity and review its requirements.

Add job opportunity
```

Avoid generic illustrations and “No data found.”

---

## 22. Responsive Behavior

The product must be fully usable at 360px.

### Mobile rules

- Single-column content
- 16px horizontal padding
- Full-width primary actions when appropriate
- No horizontal scrolling
- Stepper becomes compact
- Tables become stacked rows
- Sticky footer must not cover content
- Skill chips wrap naturally
- Dialogs become full-width sheets when necessary

### Desktop rules

- Use controlled max-width
- Avoid stretching forms across the screen
- Use whitespace to improve grouping
- Avoid empty right-side areas without purpose

---

## 23. Accessibility

Target WCAG 2.2 AA.

Required:

- Semantic HTML
- Logical headings
- Skip link
- Keyboard navigation
- Visible focus
- Proper labels
- `aria-current`
- `aria-live`
- Minimum 44px mobile targets
- Color contrast
- Non-color status cues
- Reduced motion
- Accessible dialogs
- Focus restoration

Never use clickable divs.

---

## 24. Motion

Motion must be subtle and functional.

Allowed:

- 150–180ms transitions
- Small opacity changes
- Small translate changes
- Spinner for active processing
- Smooth collapse/expand

Avoid:

- Bouncing
- Large scale animations
- Continuous decorative movement
- Gradient animation
- Spring-heavy interactions
- Slow page transitions

Respect `prefers-reduced-motion`.

---

## 25. Content Style

Copy should be concise and confident.

### Good

- Review extracted information
- We found 18 items for you to review.
- Your opportunity is ready.
- We could not analyze this description.
- Save and continue
- Confirm opportunity

### Avoid

- AI-powered extraction engine
- Schema validation failed
- Mutation successful
- Payload invalid
- Unexpected provider response
- Magic generated result

The product should never sound like internal system logs.

---

## 26. AI Agent Implementation Rules

Before making UI changes, the AI agent must:

1. Read this file.
2. Inspect existing tokens and reusable components.
3. Reuse the current product shell.
4. Preserve backend contracts and business logic.
5. List all UI states before implementation.
6. Use type-specific components.
7. Avoid raw JSON.
8. Add focused tests.
9. Verify mobile, tablet, and desktop behavior.
10. Run typecheck, lint, formatting, tests, and build.

The AI agent must not:

- Introduce gradients
- Add glass effects
- Add decorative AI icons
- Add fake metrics
- Add fake progress percentages
- Add more cards to solve hierarchy problems
- Use arbitrary colors
- Use arbitrary radii
- Redesign navigation during a feature task
- Add huge empty spaces
- Add technical error text to candidate screens
- Use the same generic card for every content type
- Create horizontal overflow
- Change business logic during a visual task

---

## 27. Visual Review Standard

Before considering a screen complete, compare it against this standard.

### It should feel

- Balanced
- Deliberate
- Calm
- Efficient
- High trust
- Productized
- Consistent

### It should not feel

- Generated
- Template-based
- Overdecorated
- Sparse without reason
- Dense without hierarchy
- Inconsistent
- Experimental
- Student-level

### Final question

Would this screen look credible in a product sold to companies for serious professional use?

If the answer is not clearly yes, it is not complete.

---

## 28. Definition of Done

A frontend task is complete only when:

- It follows this design direction.
- The visual hierarchy is obvious.
- The page has no unnecessary cards.
- The page has no unexplained empty space.
- Typography is consistent.
- Interaction states are complete.
- Responsive behavior is verified.
- Accessibility is verified.
- Business logic is preserved.
- Tests pass.
- Production build passes.
- The result feels like one coherent premium product.

---

## 29. Mandatory Prompt Prefix for Future UI Work

Use this at the beginning of every frontend prompt:

```text
Before changing any UI, read and follow:

docs/design/CAREERPILOT_PREMIUM_DESIGN_SYSTEM.md

This file is mandatory and is the visual, interaction, responsive, content, and accessibility source of truth.

The result must look intentionally designed by a senior product designer and frontend engineer. It must not look generated, template-based, or like a generic Tailwind dashboard.

Do not introduce gradients, glassmorphism, excessive cards, large empty spaces, decorative AI visuals, arbitrary colors, or raw technical messages.

Preserve all existing business rules and API contracts.
```
