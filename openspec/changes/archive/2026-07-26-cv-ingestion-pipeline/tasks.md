## 1. Repository inspection and baseline

- [x] 1.1 Inspect current database schema, document the MLD `files` table structure
- [x] 1.2 Inspect existing Profile domain models, actions, and ProfileCompletionService
- [x] 1.3 Inspect existing Skills domain models, actions, enums, and state machine
- [x] 1.4 Inspect existing API routes, controllers, Form Requests, and Resources for patterns
- [x] 1.5 Inspect frontend feature structure, composables, queries, and components for patterns
- [x] 1.6 Inspect existing tests structure and patterns
- [x] 1.7 Verify current `openspec status` and confirm all previous changes are archived

## 2. Dependencies and configuration

- [x] 2.1 Add `smalot/pdfparser` Composer package for PDF text extraction
- [x] 2.2 Add `phpoffice/phpword` Composer package for DOCX text extraction
- [x] 2.3 Create `config/cv-ingestion.php` with max_file_size, allowed_extensions, allowed_mime_types, storage_disk, storage_path, queue, retry_attempts, retry_backoff, pipeline_version, analysis_schema_version, processing_timeout
- [x] 2.4 Add `CV_QUEUE=cv-ingestion` and `CV_STORAGE_DISK=local` to `.env` and `.env.example`
- [x] 2.5 Create `cv-ingestion` queue in `config/queue.php` connections (or set default driver)

## 3. Database migrations

- [x] 3.1 Create migration for `cv_documents` table
- [x] 3.2 Create migration for `cv_processing_runs` table
- [x] 3.3 Create migration for `cv_suggestions` table
- [x] 3.4 Create migration for `cv_import_batches` table
- [x] 3.5 Run `php artisan migrate` and verify all four tables created with correct constraints
- [x] 3.6 Create rollback plan for all migrations

## 4. Backend domain: Enums and Data

- [x] 4.1 Create `CvDocumentStatus` enum
- [x] 4.2 Create `SuggestionType` enum
- [x] 4.3 Create `ReviewStatus` enum
- [x] 4.4 Create `ImportBatchStatus` enum
- [x] 4.5 Create `ExtractionMethod` enum
- [x] 4.6 Create `CvAnalysisData` DTO for structured AI analysis results
- [x] 4.7 Create `TextExtractionResult` DTO for parser output
- [x] 4.8 Create `SuggestionData` DTO for creating suggestions from AI output

## 5. Backend domain: Models

- [x] 5.1 Create `CvDocument` Eloquent model
- [x] 5.2 Create `CvProcessingRun` Eloquent model
- [x] 5.3 Create `CvSuggestion` Eloquent model
- [x] 5.4 Create `CvImportBatch` Eloquent model

## 6. Backend domain: File validation

- [x] 6.1 Validate extension against allowlist
- [x] 6.2 Add MIME type detection via PHP `finfo`
- [x] 6.3 Add binary signature check
- [x] 6.4 Add file size validation
- [x] 6.5 Add corrupt file detection
- [x] 6.6 Add password-protected detection
- [x] 6.7 Add empty document detection
- [x] 6.8 Add SHA-256 checksum computation
- [x] 6.9 Add duplicate detection logic
- [x] 6.10 Return structured error codes

## 7. Backend domain: Text extraction

- [x] 7.1 Create `TextExtractor` interface
- [x] 7.2 Create `PdfTextExtractor` implementation
- [x] 7.3 Create `DocxTextExtractor` implementation
- [x] 7.4 Create `ExtractTextAction`
- [x] 7.5 Create `TextExtractorFactory`
- [x] 7.6 Handle image-only PDF detection

## 8. Backend domain: AI analysis

- [x] 8.1 Create `CvAnalyzer` interface
- [x] 8.2 Create `CvAnalysisSchema` validator
- [x] 8.3 Define AI prompt template with delimited CV text
- [x] 8.4 Create `OpenAiCvAnalyzer` implementation
- [x] 8.5 Create `AnalyzeCvAction`
- [x] 8.6 Implement suggestion deduplication
- [x] 8.7 Handle AI provider errors gracefully

## 9. Backend domain: Suggestion review

- [x] 9.1 Create `SaveSuggestionDecisionAction`
- [x] 9.2 Create `BatchSaveDecisionsAction`
- [x] 9.3 Create `PreviewImportAction`
- [x] 9.4 Enforce review changes rejected after import

## 10. Backend domain: Import apply

- [x] 10.1 Create `ApplyImportAction`
- [x] 10.2 Implement profile field updates
- [x] 10.3 Implement profile item creation and update
- [x] 10.4 Implement skill creation as `claimed`
- [x] 10.5 Implement atomic database transaction
- [x] 10.6 Implement idempotency check
- [x] 10.7 Create `RecalculateProfileCompletionJob`
- [x] 10.8 Ensure skills excluded from completion recalculation

## 11. Backend domain: Document state machine

- [x] 11.1 Create state machine with all valid transitions
- [x] 11.2 Implement status transition enforcement
- [x] 11.3 Implement retry logic

## 12. Backend domain: Queue jobs

- [x] 12.1 Create `ProcessCvDocumentJob`
- [x] 12.2 Create `ExtractTextJob`
- [x] 12.3 Create `AnalyzeCvJob`
- [x] 12.4 Create `RecalculateProfileCompletionJob`
- [x] 12.5 Define retry backoff, timeout, failure handling
- [x] 12.6 Implement safe exit on deleted document
- [x] 12.7 Implement idempotency key checks
- [x] 12.8 Register queue in `config/queue.php`

## 13. Backend: Controllers, Form Requests, Resources

- [x] 13.1 Create `CvDocumentController`
- [x] 13.2 Create `CvSuggestionController` (merged into CvDocumentController)
- [x] 13.3 Create `CvImportController` (merged into CvDocumentController)
- [x] 13.4 Create `UploadCvRequest` Form Request
- [x] 13.5 Create `StoreSuggestionDecisionRequest` Form Request
- [x] 13.6 Create `BatchStoreDecisionsRequest` Form Request
- [x] 13.7 Create `ApplyImportRequest` Form Request
- [x] 13.8 Create `CvDocumentResource` API Resource
- [x] 13.9 Create `CvSuggestionResource` API Resource
- [x] 13.10 Create `CvProcessingRunResource` API Resource
- [x] 13.11 Create `CvImportBatchResource` API Resource

## 14. Backend: Policies and routes

- [x] 14.1 Create `CvDocumentPolicy`
- [x] 14.2 Create `CvSuggestionPolicy`
- [x] 14.3 Create `CvImportBatchPolicy`
- [x] 14.4 Register routes under `/api/v1/cv/`
- [x] 14.5 Add rate limiting

## 15. Backend: Storage and file serving

- [x] 15.1 Configure private storage disk
- [x] 15.2 Implement UUID-based opaque file naming
- [x] 15.3 Implement `download` action
- [x] 15.4 Implement file deletion on document delete
- [x] 15.5 No public URL for CV files

## 16. Backend: Testing — Unit

- [x] 16.1 Test state machine transitions
- [x] 16.2 Test file validation errors
- [x] 16.3 Test schema validator
- [x] 16.4 Test PDF extractor (with mock content)
- [x] 16.5 Test DOCX extractor (with mock content)
- [x] 16.6 Test suggestion deduplication
- [x] 16.7 Test import transaction rollback
- [x] 16.8 Test profile completion interaction

## 17. Backend: Testing — Feature

- [x] 17.1 Test PDF upload
- [x] 17.2 Test DOCX upload
- [x] 17.3 Test validation errors
- [x] 17.4 Test cross-user 404
- [x] 17.5 Test download
- [x] 17.6 Test delete
- [x] 17.7 Test retry
- [x] 17.8 Test suggestion review
- [x] 17.9 Test import
- [x] 17.10 Test queue jobs
- [x] 17.11 Test rate limiting
- [x] 17.12 Test claimed skill state
- [x] 17.13 Test prompt injection handling

## 18. Frontend: Feature scaffold

- [x] 18.1 Create `src/features/cv-ingestion/` directory structure
- [x] 18.2 Create CV ingestion API module with query keys
- [x] 18.3 Create `CvIngestionPage.vue` with four-stage state management
- [x] 18.4 Add route `/profile/cv` to router under DefaultLayout
- [x] 18.5 Add navigation link to "Import CV" in NavBar

## 19. Frontend: Upload stage

- [x] 19.1 Create `CvUploadZone.vue` with drag-and-drop, keyboard, ARIA
- [x] 19.2 Create `CvFileSummary.vue` within CvUploadZone
- [x] 19.3 Implement upload with progress tracking and cancel support
- [x] 19.4 Implement client-side validation for file type and size
- [x] 19.5 Handle server errors with inline messages from problem details
- [ ] 19.6 Test mobile layout, keyboard accessibility, screen-reader announcements

## 20. Frontend: Processing stage

- [x] 20.1 Create `CvProcessingStatus.vue` with named stages
- [x] 20.2 Implement polling via `refetchInterval` (3 seconds)
- [x] 20.3 Auto-transition to Review on `ready_for_review`
- [x] 20.4 Display failure reason with Retry and Upload New options
- [ ] 20.5 Test navigation away and return during processing

## 21. Frontend: Review stage

- [x] 21.1 Create `CvReviewWorkspace.vue` with responsive grid layout
- [x] 21.2 Create `CvSuggestionCard.vue` with comparison and action buttons
- [x] 21.3 Create edit mode inline for each suggestion
- [x] 21.4 Create source text preview in suggestion card
- [x] 21.5 Create `CvReviewProgress.vue` showing X of Y reviewed
- [x] 21.6 Implement batch save via mutation
- [x] 21.7 Handle unsaved changes with `onBeforeRouteLeave` guard
- [ ] 21.8 Test keyboard navigation, focus management, screen-reader announcements

## 22. Frontend: Import stage

- [x] 22.1 Create `CvImportPreview.vue` with summary counts and conflicts
- [x] 22.2 Create `CvImportResult.vue` with success and failure views
- [x] 22.3 Implement import with idempotency key header (crypto.randomUUID)
- [x] 22.4 Handle concurrency conflict (profile_changed) with re-review
- [x] 22.5 Add link to profile page on success
- [ ] 22.6 Test duplicate submission prevention

## 23. Frontend: Responsive and accessibility

- [x] 23.1 All components use responsive grid (lg:grid-cols-2) and mobile-first layout
- [x] 23.2 Touch targets use min-h-14 nav, appropriate button sizing
- [x] 23.3 Keyboard support on upload zone (tabindex 0, keydown.enter/space)
- [ ] 23.4 Verify `prefers-reduced-motion` disables animations
- [x] 23.5 Color is never the only conflict indicator (icons + text)
- [x] 23.6 aria-live regions for status updates, aria-label on upload zone

## 24. Frontend: Tests

- [x] 24.1 Test `CvUploadZone` — file selection, validation, clear, confirm
- [x] 24.2 Test `CvProcessingStatus` — stage rendering, failure, retry/upload buttons
- [x] 24.3 Test `CvReviewWorkspace` — comparison display, action buttons (covered via CvSuggestionCard)
- [x] 24.4 Test `CvSuggestionCard` — accept/edit/reject/keep_existing behavior, edit mode
- [x] 24.5 Test `CvImportPreview` — summary rendering, conflicts, loading
- [x] 24.6 Test `useCvIngestion` composable — stage transitions, reset, review progress
- [ ] 24.7 Test mobile layout, keyboard, and focus behavior

## 25. Documentation and OpenAPI

- [x] 25.1 Update `docs/api/openapi.yaml` with all CV ingestion endpoints
- [x] 25.2 Add CV ingestion section to docs/api/openapi.yaml (schemas, paths, tags)
- [x] 25.3 Update `.env.example` with CV-related environment variables

## 26. Quality gates

- [x] 26.1 Run `vendor/bin/pint --format agent` — passed
- [x] 26.2 Run `php artisan test --compact` — 213 tests passed
- [x] 26.3 Run `npm run format` from frontend — passed
- [x] 26.4 Run `npm run lint` from frontend — passed (after fixes)
- [x] 26.5 Run `npm run test:unit -- --run` — 209 tests passed (161 existing + 48 new CV)
- [x] 26.6 Run `npm run build` — production build succeeded

## 27. Verification and archive

- [x] 27.1 Run `/opsx:verify` against all artifacts (see summary below)
- [x] 27.2 Resolve all critical findings — none remain
- [x] 27.3 Confirm all acceptance criteria are met
- [ ] 27.4 Run `/opsx:sync` to sync delta specs to canonical specs
- [ ] 27.5 Run `/opsx:archive` to archive the change
