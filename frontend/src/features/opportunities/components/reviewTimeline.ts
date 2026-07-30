export interface ReviewTimelineStep {
  key: string
  label: string
  itemCount: number
  reviewedCount: number
  unresolvedCount: number
  kind?: 'review' | 'summary'
}

export type ReviewTimelineStatus = 'not-started' | 'in-progress' | 'complete' | 'blocked' | 'ready'

export interface ReviewTimelineItem extends ReviewTimelineStep {
  index: number
  isCurrent: boolean
  status: ReviewTimelineStatus
  statusLabel: string
  progressLabel: string
  accessibleLabel: string
}

export function normalizeReviewTimelineIndex(
  steps: ReviewTimelineStep[],
  currentIndex: number,
): number {
  if (steps.length === 0) return -1
  if (!Number.isFinite(currentIndex)) return 0

  return Math.min(Math.max(Math.trunc(currentIndex), 0), steps.length - 1)
}

export function statusForReviewTimelineStep(step: ReviewTimelineStep): ReviewTimelineStatus {
  if (step.kind === 'summary') {
    return step.unresolvedCount > 0 ? 'blocked' : 'ready'
  }

  if (step.itemCount > 0 && step.reviewedCount >= step.itemCount && step.unresolvedCount === 0) {
    return 'complete'
  }

  return step.reviewedCount > 0 ? 'in-progress' : 'not-started'
}

export function progressLabelForReviewTimelineStep(
  step: ReviewTimelineStep,
  status = statusForReviewTimelineStep(step),
): string {
  if (step.kind === 'summary') {
    return status === 'ready' ? 'Ready' : `${step.unresolvedCount} decisions remaining`
  }

  if (step.itemCount === 0) return 'No items'

  if (status === 'complete') {
    return `${step.reviewedCount} reviewed`
  }

  if (status === 'in-progress') {
    return `${step.reviewedCount} of ${step.itemCount} reviewed`
  }

  return `${step.itemCount} pending`
}

function statusLabelForReviewTimelineItem(
  step: ReviewTimelineStep,
  status: ReviewTimelineStatus,
): string {
  if (status === 'complete') return 'complete'
  if (status === 'in-progress') return 'in progress'
  if (status === 'blocked') return `${step.unresolvedCount} items remaining`
  if (status === 'ready') return 'ready for final review'
  return 'not started'
}

function displayStatusLabelForReviewTimelineItem(status: ReviewTimelineStatus): string {
  if (status === 'complete') return 'Reviewed'
  if (status === 'in-progress') return 'In progress'
  if (status === 'blocked') return 'Attention'
  if (status === 'ready') return 'Ready'
  return 'Pending'
}

export function createReviewTimelineItems(
  steps: ReviewTimelineStep[],
  currentIndex: number,
): ReviewTimelineItem[] {
  const normalizedIndex = normalizeReviewTimelineIndex(steps, currentIndex)

  return steps.map((step, index) => {
    const status = statusForReviewTimelineStep(step)
    const statusLabel = displayStatusLabelForReviewTimelineItem(status)
    const progressLabel = progressLabelForReviewTimelineStep(step, status)
    const reviewedLabel =
      step.kind === 'summary'
        ? statusLabelForReviewTimelineItem(step, status)
        : `${step.reviewedCount} of ${step.itemCount} reviewed, ${statusLabelForReviewTimelineItem(step, status)}`

    return {
      ...step,
      index,
      isCurrent: index === normalizedIndex,
      status,
      statusLabel,
      progressLabel,
      accessibleLabel: `${step.label}, ${step.itemCount} ${step.label.toLowerCase()} item${step.itemCount === 1 ? '' : 's'}, ${reviewedLabel}${
        index === normalizedIndex ? ', current step' : ''
      }`,
    }
  })
}
