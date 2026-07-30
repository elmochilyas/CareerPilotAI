<script setup lang="ts">
import { shallowRef } from 'vue'
import DesignLabResponsibilityRow from './DesignLabResponsibilityRow.vue'

const steps = [
  { number: '01', label: 'Overview', count: 2 },
  { number: '02', label: 'Work details', count: 2 },
  { number: '03', label: 'Responsibilities', count: 5, current: true },
  { number: '04', label: 'Experience & education', count: 3 },
  { number: '05', label: 'Required skills', count: 2 },
  { number: '06', label: 'Languages & certifications', count: 2 },
  { number: '07', label: 'Compensation & dates', count: 6 },
  { number: '08', label: 'Final review', count: 22 },
]

const responsibilities = [
  'Konzeption, Entwicklung und kontinuierliche Optimierung skalierbarer Backend-Funktionen auf Basis moderner Technologien wie Node.js und TypeScript mit klarem Fokus auf Wartbarkeit, Performance und nachhaltige Architekturentscheidungen.',
  'Aufbau und Weiterentwicklung einer robusten, serverlosen Architektur innerhalb der AWS Cloud durch den Einsatz automatisierter Tests, strukturierter Code Reviews und effizienter CI/CD-Pipelines.',
  'Automatisierung und Weiterentwicklung komplexer Compliance-Workflows unter Einbindung von REST- und GraphQL-Schnittstellen sowie Cloud-Services.',
  'Enge Zusammenarbeit mit Produktmanagement, UX und Engineering in agilen, interdisziplinären Teams.',
  'Aktive Mitgestaltung der technischen Roadmap, Identifikation von Innovationspotenzialen in der Cloud-Architektur und kontinuierliche Optimierung von Prozessen.',
]

const announcement = shallowRef('')

function prototypeAction(action: string): void {
  announcement.value = `${action} is shown for design review only. No data was saved.`
}
</script>

<template>
  <main class="design-lab-page">
    <a class="skip-link" href="#design-lab-review-content">Skip to review content</a>

    <div id="design-lab-review-content" class="page-shell" tabindex="-1">
      <header class="page-header">
        <p class="lab-label">CareerPilot design lab</p>
        <h1>Opportunity review</h1>
        <p>Review extracted information before saving this opportunity.</p>
      </header>

      <section class="opportunity-summary" aria-labelledby="prototype-opportunity-title">
        <div class="company-stamp" aria-hidden="true">
          <span>RG</span>
          <small>DE</small>
        </div>

        <div class="opportunity-copy">
          <div class="opportunity-heading">
            <div>
              <h2 id="prototype-opportunity-title">
                Backend Entwickler node.js (m/w/d) – Serverless Backend Entwickler
              </h2>
              <p>Ratbacher GmbH</p>
            </div>
            <span class="review-status">
              <span aria-hidden="true" />
              Ready for review
            </span>
          </div>

          <div class="opportunity-details">
            <span>Remote</span>
            <span aria-hidden="true">·</span>
            <span>Full-time</span>
          </div>

          <div class="summary-progress">
            <div>
              <span>Review progress</span>
              <strong>0 of 22 reviewed</strong>
            </div>
            <div
              class="progress-track"
              role="progressbar"
              aria-label="0 of 22 review items complete"
              aria-valuenow="0"
              aria-valuemin="0"
              aria-valuemax="22"
            >
              <span />
            </div>
          </div>
        </div>
      </section>

      <div class="mobile-step" aria-label="Current review step">
        <span>Step 3 of 8</span>
        <strong>Responsibilities</strong>
        <div class="mobile-progress" aria-hidden="true"><span /></div>
      </div>

      <div class="review-layout">
        <nav class="editorial-index" aria-label="Opportunity review sections">
          <ol>
            <li v-for="step in steps" :key="step.number" :class="{ 'index-current': step.current }">
              <span class="index-number">{{ step.number }}</span>
              <span class="index-label" :aria-current="step.current ? 'step' : undefined">
                {{ step.label }}
              </span>
              <span class="index-count">{{ step.count }}</span>
            </li>
          </ol>
        </nav>

        <section class="workspace" aria-labelledby="responsibilities-heading">
          <header class="workspace-header">
            <div>
              <p class="section-kicker">Extracted evidence</p>
              <h2 id="responsibilities-heading">Responsibilities</h2>
              <p>Keep only responsibilities clearly supported by the job description.</p>
            </div>
            <p class="section-progress">
              5 responsibilities <span aria-hidden="true">·</span> 0 reviewed
            </p>
          </header>

          <div class="responsibility-list">
            <DesignLabResponsibilityRow
              v-for="(responsibility, index) in responsibilities"
              :key="index"
              :index="index"
              :text="responsibility"
            />
          </div>

          <footer class="workspace-footer">
            <button type="button" class="previous-action" @click="prototypeAction('Previous step')">
              Previous
            </button>
            <p>
              <strong>Step 3 of 8</strong>
              <span aria-hidden="true">·</span>
              0 of 22 reviewed
            </p>
            <button
              type="button"
              class="primary-action"
              @click="prototypeAction('Save and continue')"
            >
              Save and continue
            </button>
          </footer>
        </section>
      </div>

      <p class="prototype-note" aria-live="polite">{{ announcement }}</p>
    </div>
  </main>
</template>

<style scoped>
.design-lab-page {
  --lab-canvas: #f4f4f1;
  --lab-paper: #ffffff;
  --lab-ink: #17191f;
  --lab-text: #323741;
  --lab-muted: #66707a;
  --lab-faint: #9299a4;
  --lab-rule: #dee1e6;
  --lab-rule-strong: #c8cdd5;
  --lab-hover: #f7f8fa;
  --lab-cobalt: #315ee7;
  --lab-cobalt-dark: #2449bc;
  --lab-cobalt-soft: #eef2ff;
  --lab-success: #18705a;
  --lab-success-soft: #edf8f4;
  --lab-success-rule: #acd6c8;
  --lab-danger: #ae3b35;
  --lab-danger-soft: #fff2f0;
  --lab-danger-rule: #efc7c3;
  --lab-radius-control: 0.5rem;
  --lab-radius-surface: 0.75rem;
  --lab-workspace-shadow: 0 1.25rem 3.5rem rgb(23 25 31 / 0.075);
  --lab-menu-shadow: 0 0.75rem 1.75rem rgb(23 25 31 / 0.12);

  min-height: 100vh;
  background: var(--lab-canvas);
  color: var(--lab-text);
  font-family: 'Geist', 'Inter', ui-sans-serif, system-ui, sans-serif;
}

.skip-link {
  position: fixed;
  z-index: 100;
  top: 0.75rem;
  left: 0.75rem;
  transform: translateY(-200%);
  border-radius: var(--lab-radius-control);
  padding: 0.625rem 0.875rem;
  background: var(--lab-ink);
  color: var(--lab-paper);
  font-size: 0.875rem;
  font-weight: 630;
  transition: transform 160ms ease;
}

.skip-link:focus-visible {
  transform: translateY(0);
  outline: 2px solid var(--lab-cobalt);
  outline-offset: 2px;
}

.page-shell {
  width: min(100% - 2rem, 74rem);
  margin: 0 auto;
  padding: 2.5rem 0 5rem;
}

.page-header {
  max-width: 46rem;
}

.lab-label,
.section-kicker {
  color: var(--lab-cobalt);
  font-size: 0.75rem;
  font-weight: 680;
  letter-spacing: 0.06em;
  line-height: 1.125rem;
  text-transform: uppercase;
}

.page-header h1 {
  margin-top: 0.375rem;
  color: var(--lab-ink);
  font-size: 2rem;
  font-weight: 690;
  letter-spacing: -0.035em;
  line-height: 2.5rem;
}

.page-header > p:last-child {
  margin-top: 0.25rem;
  color: var(--lab-muted);
  font-size: 0.9375rem;
  line-height: 1.5rem;
}

.opportunity-summary {
  display: grid;
  grid-template-columns: 4.25rem minmax(0, 1fr);
  gap: 1.5rem;
  margin-top: 2rem;
  border: 1px solid var(--lab-rule);
  border-radius: var(--lab-radius-surface);
  padding: 1.5rem;
  background: var(--lab-paper);
}

.company-stamp {
  display: grid;
  position: relative;
  width: 4.25rem;
  height: 4.25rem;
  grid-template-rows: 1fr auto;
  border-radius: 0.625rem;
  padding: 0.75rem;
  background: var(--lab-cobalt);
  color: var(--lab-paper);
}

.company-stamp::after {
  position: absolute;
  right: -0.25rem;
  bottom: 0.5rem;
  width: 0.25rem;
  height: 2rem;
  background: var(--lab-cobalt-dark);
  content: '';
}

.company-stamp span {
  align-self: center;
  font-size: 1.125rem;
  font-weight: 720;
  letter-spacing: -0.02em;
}

.company-stamp small {
  justify-self: end;
  font-size: 0.625rem;
  font-weight: 650;
  letter-spacing: 0.08em;
  opacity: 0.72;
}

.opportunity-copy {
  min-width: 0;
}

.opportunity-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1.5rem;
}

.opportunity-heading h2 {
  max-width: 48rem;
  overflow-wrap: anywhere;
  color: var(--lab-ink);
  font-size: 1.3125rem;
  font-weight: 650;
  letter-spacing: -0.022em;
  line-height: 1.75rem;
  text-wrap: pretty;
}

.opportunity-heading p {
  margin-top: 0.25rem;
  color: var(--lab-muted);
  font-size: 0.875rem;
  line-height: 1.375rem;
}

.review-status {
  display: inline-flex;
  flex: 0 0 auto;
  align-items: center;
  gap: 0.5rem;
  color: var(--lab-success);
  font-size: 0.75rem;
  font-weight: 630;
}

.review-status > span {
  width: 0.4375rem;
  height: 0.4375rem;
  border-radius: 999px;
  background: var(--lab-success);
  box-shadow: 0 0 0 0.1875rem var(--lab-success-soft);
}

.opportunity-details {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-top: 0.875rem;
  color: var(--lab-muted);
  font-size: 0.8125rem;
  font-weight: 540;
}

.summary-progress {
  margin-top: 1.25rem;
  border-top: 1px solid var(--lab-rule);
  padding-top: 1rem;
}

.summary-progress > div:first-child {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 1rem;
  color: var(--lab-muted);
  font-size: 0.75rem;
}

.summary-progress strong {
  color: var(--lab-text);
  font-variant-numeric: tabular-nums;
  font-weight: 640;
}

.progress-track {
  height: 0.1875rem;
  margin-top: 0.625rem;
  overflow: hidden;
  background: var(--lab-rule);
}

.progress-track span {
  display: block;
  width: 0;
  height: 100%;
  background: var(--lab-cobalt);
}

.review-layout {
  display: grid;
  grid-template-columns: 14rem minmax(0, 1fr);
  gap: 2rem;
  margin-top: 2rem;
  align-items: start;
}

.editorial-index {
  position: sticky;
  top: 1.5rem;
  padding-top: 0.5rem;
}

.editorial-index ol {
  display: grid;
}

.editorial-index li {
  display: grid;
  position: relative;
  grid-template-columns: 2rem minmax(0, 1fr) auto;
  align-items: baseline;
  gap: 0.5rem;
  min-height: 3.25rem;
  padding: 0.875rem 0.25rem 0.875rem 0.75rem;
  color: var(--lab-muted);
}

.editorial-index li::before {
  position: absolute;
  top: 0.75rem;
  bottom: 0.75rem;
  left: 0;
  width: 2px;
  background: transparent;
  content: '';
}

.editorial-index .index-current {
  color: var(--lab-ink);
}

.editorial-index .index-current::before {
  background: var(--lab-cobalt);
}

.index-number {
  color: var(--lab-faint);
  font-size: 0.6875rem;
  font-variant-numeric: tabular-nums;
  font-weight: 680;
  letter-spacing: 0.06em;
}

.index-label {
  overflow: hidden;
  font-size: 0.875rem;
  font-weight: 540;
  line-height: 1.25rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.index-current .index-label {
  font-weight: 680;
}

.index-count {
  color: var(--lab-faint);
  font-size: 0.75rem;
  font-variant-numeric: tabular-nums;
  font-weight: 620;
}

.workspace {
  min-width: 0;
  border: 1px solid var(--lab-rule);
  border-radius: var(--lab-radius-surface);
  background: var(--lab-paper);
  box-shadow: var(--lab-workspace-shadow);
}

.workspace-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 2rem;
  border-bottom: 1px solid var(--lab-rule);
  padding: 2rem 2.25rem 1.5rem;
}

.workspace-header h2 {
  margin-top: 0.25rem;
  color: var(--lab-ink);
  font-size: 1.5rem;
  font-weight: 670;
  letter-spacing: -0.03em;
  line-height: 1.875rem;
}

.workspace-header > div > p:last-child {
  max-width: 38rem;
  margin-top: 0.375rem;
  color: var(--lab-muted);
  font-size: 0.875rem;
  line-height: 1.375rem;
}

.section-progress {
  flex: 0 0 auto;
  color: var(--lab-muted);
  font-size: 0.75rem;
  font-variant-numeric: tabular-nums;
  line-height: 1.125rem;
}

.section-progress span {
  margin: 0 0.25rem;
}

.responsibility-list {
  padding: 0 2.25rem;
}

.workspace-footer {
  display: grid;
  position: sticky;
  z-index: 5;
  bottom: 0;
  grid-template-columns: minmax(8rem, auto) minmax(0, 1fr) minmax(10rem, auto);
  align-items: center;
  gap: 1rem;
  border-top: 1px solid var(--lab-rule);
  border-radius: 0 0 var(--lab-radius-surface) var(--lab-radius-surface);
  padding: 0.875rem 1.25rem;
  background: var(--lab-paper);
}

.workspace-footer button {
  min-height: 2.75rem;
  border-radius: var(--lab-radius-control);
  padding: 0.625rem 1rem;
  font-size: 0.875rem;
  font-weight: 630;
}

.previous-action {
  justify-self: start;
  border: 0;
  background: transparent;
  color: var(--lab-muted);
}

.previous-action:hover {
  background: var(--lab-hover);
  color: var(--lab-ink);
}

.workspace-footer p {
  color: var(--lab-muted);
  font-size: 0.75rem;
  font-variant-numeric: tabular-nums;
  text-align: center;
}

.workspace-footer p span {
  margin: 0 0.25rem;
}

.workspace-footer strong {
  color: var(--lab-text);
  font-weight: 640;
}

.primary-action {
  justify-self: end;
  border: 1px solid var(--lab-cobalt);
  background: var(--lab-cobalt);
  color: var(--lab-paper);
}

.primary-action:hover {
  border-color: var(--lab-cobalt-dark);
  background: var(--lab-cobalt-dark);
}

.workspace-footer button:focus-visible {
  outline: 2px solid var(--lab-cobalt);
  outline-offset: 2px;
}

.design-lab-page button,
.design-lab-page summary {
  touch-action: manipulation;
  -webkit-tap-highlight-color: transparent;
}

.mobile-step {
  display: none;
}

.prototype-note {
  min-height: 1.25rem;
  margin-top: 1rem;
  color: var(--lab-muted);
  font-size: 0.8125rem;
  text-align: right;
}

@media (max-width: 47.999rem) {
  .page-shell {
    width: min(100% - 2rem, 74rem);
    padding-top: 1.5rem;
  }

  .lab-label {
    display: none;
  }

  .page-header h1 {
    margin-top: 0;
    font-size: 1.75rem;
    line-height: 2.25rem;
  }

  .opportunity-summary {
    grid-template-columns: 3.25rem minmax(0, 1fr);
    gap: 1rem;
    padding: 1.25rem;
  }

  .company-stamp {
    width: 3.25rem;
    height: 3.25rem;
    padding: 0.625rem;
  }

  .company-stamp span {
    font-size: 0.9375rem;
  }

  .opportunity-heading {
    display: block;
  }

  .opportunity-heading h2 {
    font-size: 1.125rem;
    line-height: 1.5rem;
  }

  .review-status {
    margin-top: 0.75rem;
  }

  .summary-progress {
    grid-column: 1 / -1;
  }

  .mobile-step {
    display: grid;
    gap: 0.25rem;
    margin-top: 1.5rem;
    border-bottom: 1px solid var(--lab-rule);
    padding-bottom: 0.875rem;
    color: var(--lab-muted);
    font-size: 0.75rem;
  }

  .mobile-step strong {
    color: var(--lab-ink);
    font-size: 0.9375rem;
    font-weight: 650;
  }

  .mobile-progress {
    height: 0.125rem;
    margin-top: 0.375rem;
    background: var(--lab-rule);
  }

  .mobile-progress span {
    display: block;
    width: 37.5%;
    height: 100%;
    background: var(--lab-cobalt);
  }

  .review-layout {
    display: block;
    margin-top: 1rem;
  }

  .editorial-index {
    display: none;
  }

  .workspace-header {
    display: block;
    padding: 1.5rem 1.25rem 1rem;
  }

  .section-progress {
    margin-top: 0.75rem;
  }

  .responsibility-list {
    padding: 0 1.25rem;
  }

  .workspace-footer {
    grid-template-columns: 1fr;
    gap: 0.5rem;
    padding: 0.75rem;
    padding-bottom: max(0.75rem, env(safe-area-inset-bottom));
  }

  .workspace-footer p {
    grid-row: 1;
  }

  .workspace-footer .primary-action {
    width: 100%;
    grid-row: 2;
  }

  .previous-action {
    width: 100%;
    grid-row: 3;
  }

  .prototype-note {
    text-align: left;
  }
}

@media (prefers-reduced-motion: reduce) {
  .design-lab-page *,
  .design-lab-page *::before,
  .design-lab-page *::after {
    scroll-behavior: auto !important;
    transition-duration: 0.01ms !important;
  }
}

:global(body:has(.design-lab-page) #vue-inspector-container),
:global(body:has(.design-lab-page) #__vue-devtools-container__),
:global(body:has(.design-lab-page) #open-side-panel) {
  display: none !important;
}
</style>
