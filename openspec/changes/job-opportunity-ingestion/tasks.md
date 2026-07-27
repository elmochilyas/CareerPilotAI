## 1. Repository inspection and baseline

- [x] 1.1 Inspect current database schema for existing `opportunities`, `companies` tables
- [x] 1.2 Inspect existing Skills domain normalization and alias resolution
- [x] 1.3 Inspect existing CvIngestion domain for patterns (enums, state machine, AI analysis, suggestion review, queue jobs)
- [x] 1.4 Inspect existing API routes, controllers, Form Requests, and Resources for patterns
- [x] 1.5 Inspect frontend feature structure, composables, queries, and components for patterns
- [x] 1.6 Inspect existing tests structure and patterns
- [x] 1.7 Verify current `openspec status` and confirm all previous changes are archived

## 2. Configuration

- [x] 2.1 Create `config/job-ingestion.php` with max_description_length, min_description_length, max_source_url_length, max_label_length, queue, retry_attempts, retry_backoff, pipeline_version, analysis_schema_version, processing_timeout
- [x] 2.2 Add `JOB_INGESTION_QUEUE=job-ingestion` to `.env` and `.env.example`

## 3. Database migrations

- [x] 3.1 Create migration for `job_opportunity_ingestions` table
- [x] 3.2 Create migration for `job_opportunity_suggestions` table
- [x] 3.3 Create migration for `job_opportunities` table
- [x] 3.4 Create migration for `job_requirements` table
- [x] 3.5 Create migration for `job_opportunity_skills` table
- [x] 3.6 Run `php artisan migrate` and verify all tables created with correct constraints
- [x] 3.7 Create rollback plan for all migrations

## 4. Backend domain: Enums and Data

- [x] 4.1 Create `JobIngestionStatus` enum with state machine methods (canTransitionTo, allowedTransitions, isRetryable, isTerminal, isProcessing)
- [x] 4.2 Create `SeniorityLevel` enum (junior, mid, senior, lead, manager, director, executive, intern, graduate)
- [x] 4.3 Create `SuggestionType` enum for job ingestion
- [x] 4.4 Create `ReviewDecision` enum (pending, accepted, edited, rejected, keep_blank, resolved)
- [x] 4.5 Create `SkillResolutionState` enum (exact, alias, ambiguous, unknown, candidate_resolved)
- [x] 4.6 Create `JobAnalysisResult` DTO for structured AI output
- [x] 4.7 Create `SuggestionData` DTO for creating suggestions from AI output

## 5. Backend domain: Models

- [x] 5.1 Create `JobOpportunityIngestion` Eloquent model
- [x] 5.2 Create `JobOpportunitySuggestion` Eloquent model
- [x] 5.3 Create `JobOpportunity` Eloquent model
- [x] 5.4 Create `JobRequirement` Eloquent model
- [x] 5.5 Create `JobOpportunitySkill` Eloquent model

## 6. Backend domain: Ingestion creation and validation

- [x] 6.1 Create `CreateIngestionAction` with description, optional URL, optional label
- [x] 6.2 Implement description normalization (whitespace trim, collapse)
- [x] 6.3 Implement content hash computation (SHA-256)
- [x] 6.4 Implement duplicate detection (content hash, external ref, source URL)
- [x] 6.5 Implement source URL validation (HTTP or HTTPS only)
- [x] 6.6 Return structured error codes for validation failures

## 7. Backend domain: AI analysis

- [x] 7.1 Create `JobAnalyzer` interface (following CvAnalyzer pattern)
- [x] 7.2 Create `JobAnalysisSchema` validator for structured AI output
- [x] 7.3 Define AI prompt template with delimited job description
- [x] 7.4 Create `OpenAiJobAnalyzer` implementation using Laravel AI SDK
- [x] 7.5 Create `AnalyzeJobAction`
- [x] 7.6 Implement suggestion deduplication
- [x] 7.7 Handle AI provider errors gracefully (timeout, rate limit, invalid response)

## 8. Backend domain: Skill resolution

- [x] 8.1 Create `ResolveJobSkillsAction`
- [x] 8.2 Implement exact match resolution against `skills.normalized_name`
- [x] 8.3 Implement alias resolution through `skill_aliases`
- [x] 8.4 Implement ambiguous match detection (multiple candidate skills)
- [x] 8.5 Implement unknown skill handling (keep original label)
- [x] 8.6 Implement duplicate mention merging within same classification
- [x] 8.7 Ensure required and preferred skills remain strictly separate

## 9. Backend domain: Suggestion review

- [x] 9.1 Create `SaveSuggestionDecisionAction` (single)
- [x] 9.2 Create `BatchSaveDecisionsAction` (grouped)
- [x] 9.3 Implement optimistic concurrency via version field
- [x] 9.4 Prevent decisions after confirmation or cancellation

## 10. Backend domain: Preview

- [x] 10.1 Create `GeneratePreviewAction`
- [x] 10.2 Build structured preview from persisted suggestions and decisions
- [x] 10.3 Generate preview version token (hash of data + decisions + schema)
- [x] 10.4 Reject incomplete reviews (pending mandatory decisions)
- [x] 10.5 Reject unresolved ambiguous skills
- [x] 10.6 Reject unresolved conflicts
- [x] 10.7 Return preview without side effects

## 11. Backend domain: Confirmation

- [x] 11.1 Create `ConfirmOpportunityAction`
- [x] 11.2 Verify ownership, review_ready state, complete decisions
- [x] 11.3 Verify preview freshness (version token)
- [x] 11.4 Implement atomic database transaction
- [x] 11.5 Create job_opportunities record with confirmed fields
- [x] 11.6 Create job_requirements for responsibilities, experience, education, languages, certifications
- [x] 11.7 Create job_opportunity_skills for required and preferred skills
- [x] 11.8 Update ingestion to confirmed with confirmed_at
- [x] 11.9 Implement idempotent confirmation (return existing on repeat)
- [x] 11.10 Full rollback on any failure

## 12. Backend domain: Ingestion state machine

- [x] 12.1 Create state transition service
- [x] 12.2 Implement all valid transitions
- [x] 12.3 Implement retryable vs permanent failure logic
- [x] 12.4 Implement cancellation behavior
- [x] 12.5 Implement duplicate processing protection

## 13. Backend domain: Queue jobs

- [x] 13.1 Create `ProcessJobIngestionJob`
- [x] 13.2 Create `ExtractJobInformationJob`
- [x] 13.3 Create `ResolveJobSkillsJob`
- [x] 13.4 Define retry backoff, timeout, failure handling
- [x] 13.5 Implement safe exit on cancelled or deleted ingestion
- [x] 13.6 Implement idempotency key checks (or equivalent)
- [x] 13.7 Register queue name

## 14. Backend: Controllers, Form Requests, Resources

- [x] 14.1 Create `JobOpportunityIngestionController`
- [x] 14.2 Create `JobOpportunitySuggestionController`
- [x] 14.3 Create `JobOpportunityConfirmedController`
- [x] 14.4 Create `StoreIngestionRequest` Form Request
- [x] 14.5 Create `SaveSuggestionDecisionRequest` Form Request
- [x] 14.6 Create `BatchSaveDecisionsRequest` Form Request
- [x] 14.7 Create `PreviewRequest` Form Request
- [x] 14.8 Create `ConfirmRequest` Form Request
- [x] 14.9 Create `IngestionResource` API Resource
- [x] 14.10 Create `SuggestionResource` API Resource
- [x] 14.11 Create `OpportunityResource` API Resource
- [x] 14.12 Create `PreviewResource` API Resource

## 15. Backend: Policies and routes

- [x] 15.1 Create `JobOpportunityIngestionPolicy`
- [x] 15.2 Create `JobOpportunitySuggestionPolicy`
- [x] 15.3 Create `JobOpportunityPolicy`
- [x] 15.4 Register routes under `/api/v1/opportunities/`
- [x] 15.5 Add rate limiting to all endpoints

## 16. Backend: Testing — Unit

- [x] 16.1 Test state machine transitions (`JobIngestionStateMachineTest`)
- [x] 16.2 Test schema validator (`JobAnalysisSchemaValidatorTest`)
- [x] 16.3 Test skill resolution (exact, alias, ambiguous, unknown, duplicate, cross-classification — `SkillResolutionTest`)
- [x] 16.4 Test duplicate detection (`DuplicateDetectionTest`)
- [x] 16.5 Test confirmation transaction rollback (covered in `PreviewConfirmTest`)
- [x] 16.6 Test suggestion deduplication (covered in `PreviewConfirmTest`)
- [x] 16.7 Test preview rejection conditions (covered in `PreviewConfirmTest`)

## 17. Backend: Testing — Feature

- [x] 17.1 Test create ingestion (`CreateIngestionTest`)
- [x] 17.2 Test list ingestions (`IngestionLifecycleTest`)
- [x] 17.3 Test view ingestion (`AuthorizationTest`)
- [x] 17.4 Test retry (`IngestionLifecycleTest`)
- [x] 17.5 Test cancel/delete (`IngestionLifecycleTest`)
- [x] 17.6 Test suggestion review (`SuggestionReviewTest`)
- [x] 17.7 Test skill resolution (`SkillResolutionTest`)
- [x] 17.8 Test preview (`PreviewConfirmTest`)
- [x] 17.9 Test confirm (`PreviewConfirmTest`)
- [x] 17.10 Test view confirmed opportunity (`AuthorizationTest`)
- [x] 17.11 Test rate limiting
- [x] 17.12 Test prompt injection handling
- [x] 17.13 Test malformed AI output
- [x] 17.14 Test optimistic concurrency on retry and decision mutations (covered in `SuggestionReviewTest`)

## 18. Frontend: Feature scaffold

- [x] 18.1 Create `src/features/opportunities/` directory structure
- [x] 18.2 Create opportunities API module with query keys
- [x] 18.3 Create `useJobIngestion` composable with queries and mutations
- [x] 18.4 Add routes to router under DefaultLayout
- [x] 18.5 Add navigation link to "Opportunities" in NavBar

## 19. Frontend: Opportunities list page

- [x] 19.1 Create `OpportunitiesListPage.vue` with confirmed opportunities and ingestions
- [x] 19.2 Create `OpportunityCard.vue` with status, company, title, work mode, last update, next action
- [x] 19.3 Show processing, review-ready, failed, and confirmed items
- [x] 19.4 Show empty state when no opportunities exist
- [x] 19.5 Handle loading and error states

## 20. Frontend: Import form page

- [x] 20.1 Create `ImportJobPage.vue` with description textarea
- [x] 20.2 Implement character count display
- [x] 20.3 Add optional source URL input
- [x] 20.4 Add optional personal label input
- [x] 20.5 Implement client-side validation (description required, length limits, URL format)
- [x] 20.6 Show explanation that information will be reviewed before saving
- [x] 20.7 Handle submission and redirect to processing page
- [x] 20.8 Handle server errors with inline messages
- [x] 20.9 Handle duplicate detection (409 with link to existing)

## 21. Frontend: Processing page

- [x] 21.1 Create `ProcessingPage.vue`
- [x] 21.2 Show named pipeline stages (Received, Validated, Analyzing, Preparing review)
- [x] 21.3 Implement polling via `refetchInterval` (3 seconds)
- [x] 21.4 Auto-redirect to review page on `review_ready`
- [x] 21.5 Display retryable failure with Retry and Cancel options
- [x] 21.6 Display permanent failure with Cancel and Import New options
- [x] 21.7 Handle navigation away and return during processing (resume polling)

## 22. Frontend: Multi-step review page

- [x] 22.1 Create `ReviewPage.vue` with nine-step ReviewWizard (inline)
- [x] 22.2 Create step indicator showing active/completed/hidden steps
- [x] 22.3 Step 1: overview (title, company, department, reference, summary, application URL)
- [x] 22.4 Step 2: work details (location, work mode, contract type, seniority, hours, travel, relocation)
- [x] 22.5 Step 3: responsibilities (inline editing with keep/remove)
- [x] 22.6 Step 4: experience & education
- [x] 22.7 Step 5: required skills (compact chips with keep/remove/resolve)
- [x] 22.8 Step 6: preferred skills (same interaction, visually separate)
- [x] 22.9 Step 7: languages & certifications
- [x] 22.10 Step 8: compensation & dates
- [x] 22.11 Step 9: final review with preview and confirm gating
- [x] 22.12 Dynamic step hiding (empty groups not shown)
- [x] 22.13 Decision persistence (individual save, batch bulk)
- [x] 22.14 Extract `SkillChipEditor.vue` component (inline in page)

## 23. Frontend: Confirmation and detail pages

- [x] 23.1 Confirmation result shown on review page after confirm
- [x] 23.2 Show job title, company, requirements summary
- [x] 23.3 "View opportunity" action
- [x] 23.4 Create `OpportunityDetailPage.vue` with readonly structured view
- [x] 23.5 Show overview, work details, responsibilities, experience, education, skills, languages, certifications, compensation, benefits, dates, source metadata

## 24. Frontend: Tests

- [x] 24.1 Test `IngestionForm` — validation, submit, character count, duplicate error
- [x] 24.2 Test `ProcessingStatus` — stage rendering, polling, failure, retry
- [x] 24.3 Test `ReviewWizard` — step navigation, dynamic step visibility
- [x] 24.4 Test `SkillChipEditor` — keep, remove, undo, ambiguous resolution
- [x] 24.5 Test `ResponsibilityEditor` — keep, edit, remove (8 tests)
- [x] 24.6 Test `PreviewSummary` — all sections, empty state, warnings (16 tests)
- [x] 24.7 Test `OpportunityCard` — title, company, date, work mode, confirmed badge (7 tests)
- [x] 24.8 Test `useJobIngestion` composable — stage transitions, decision tracking, preview, confirm

## 25. Documentation and OpenAPI

- [x] 25.1 Update `docs/api/openapi.yaml` with all job opportunity ingestion endpoints
- [x] 25.2 Add schemas for Ingestion, Suggestion, Preview, Confirmed Opportunity
- [x] 25.3 Update `.env.example` with job-ingestion-related environment variables

## 26. Quality gates

- [x] 26.1 Run `vendor/bin/pint --format agent` from backend
- [x] 26.2 Run `php artisan test --compact` from backend (302 tests, 743 assertions)
- [x] 26.3 Run `npm run format` from frontend
- [x] 26.4 Run `npm run lint` from frontend
- [x] 26.5 Run `npm run test:unit -- --run` from frontend (274 tests)
- [x] 26.6 Run `npm run build` from frontend

## 27. Corrective hardening: ingestion navigation

- [x] 27.1 Remove self-referential Vue Query polling options from the processing page and shared ingestion composable
- [x] 27.2 Validate ingestion route parameters and prevent navigation to an undefined opportunity
- [x] 27.3 Expose `confirmed_opportunity_id` through the existing one-to-one ingestion relationship
- [x] 27.4 Add frontend regression coverage using a real Vue Query client
- [x] 27.5 Add API regression coverage for confirmed linkage, nullable companies, and ownership
- [x] 27.6 Run corrective backend, frontend, and browser verification

## 28. Corrective hardening: cancellation behavior

- [x] 28.1 Replace history-dependent import cancellation with deterministic opportunities navigation
- [x] 28.2 Make the shared confirmation dialog support action-specific labels and busy states
- [x] 28.3 Implement confirmation-backed ingestion cancellation with pending, error, cache invalidation, and deterministic navigation states
- [x] 28.4 Add frontend regression coverage for dismissal, success, pending, failure, and navigation
- [x] 28.5 Add backend coverage for cancellation from every non-terminal state and rejection from terminal states
- [x] 28.6 Run corrective backend, frontend, and browser verification

## 29. Corrective hardening: duplicate ingestion safety and UX

- [x] 29.1 Align duplicate detection with the database invariant for all ingestion states
- [x] 29.2 Make ingestion creation atomic under concurrent duplicate submissions
- [x] 29.3 Prevent API problem responses from exposing internal exception details
- [x] 29.4 Add status-aware duplicate recovery actions and safe unexpected-error messaging
- [x] 29.5 Add backend and frontend regression coverage
- [x] 29.6 Run corrective backend, frontend, and browser verification

## 30. Corrective hardening: cancelled ingestion reanalysis

- [x] 30.1 Add an authorized, rate-limited reanalysis endpoint for cancelled ingestions
- [x] 30.2 Reset stale suggestions and failure state transactionally on the same ingestion record
- [x] 30.3 Version extraction jobs so stale pre-cancellation work cannot overwrite reanalysis
- [x] 30.4 Correct failed-ingestion retry dispatch to resume extraction
- [x] 30.5 Add confirmation-backed reanalysis UX with pending, success, and safe error states
- [x] 30.6 Add backend and frontend regression coverage
- [x] 30.7 Update OpenAPI and run corrective quality/browser verification

## 31. Verification and archive

- [x] 31.1 Run `/opsx:verify` against all artifacts
- [ ] 31.2 Resolve all critical findings
- [ ] 31.3 Confirm all acceptance criteria are met
- [ ] 31.4 Run `/opsx:sync` to sync delta specs to canonical specs
- [ ] 31.5 Run `/opsx:archive` to archive the change

## 32. Corrective hardening: AI analysis runtime and failure UX

- [x] 32.1 Audit the configured provider, installed Laravel AI SDK API, queue worker, database state, and exact failure stage without logging source content
- [x] 32.2 Correct anonymous structured-agent invocation and response mapping for Laravel AI SDK 0.10.1
- [x] 32.3 Classify provider configuration, authentication, rate limit, timeout, availability, malformed output, persistence, and unexpected processing failures distinctly
- [x] 32.4 Add safe structured diagnostics with ingestion, provider, model, schema, stage, exception class, validation paths, and attempt metadata
- [x] 32.5 Present candidate-friendly failure guidance with technical codes available only as expandable support details
- [x] 32.6 Add focused backend and frontend regression coverage
- [x] 32.7 Clear cached runtime state, restart one fresh queue worker, and complete a controlled end-to-end ingestion verification
