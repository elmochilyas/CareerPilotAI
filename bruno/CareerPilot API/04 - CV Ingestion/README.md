# CV Ingestion — Bruno API Test Suite

This folder validates all CV Ingestion endpoints of the CareerPilot AI API.

## Local-only execution

This suite **must only run against a local development environment**.
The `{{baseUrl}}` must point to `http://careerpilot-api.test`, `http://localhost`, or `127.0.0.1`.

**Abort immediately if `{{baseUrl}}` points to staging or production.**

## Required environment

| Variable        | Example                          |
|-----------------|----------------------------------|
| `{{baseUrl}}`   | `http://careerpilot-api.test`    |
| `{{frontendUrl}}` | `http://localhost:5173`        |

Defined in `environments/local.yml`.

## Dedicated test candidate

The suite expects the test user:

- Email: `bruno.test@example.com`
- Password: `NewPassword123!`

This account must exist in the local database.

## Required queue worker

Processing tests require the queue worker running in a separate terminal:

```powershell
cd backend
php artisan queue:work --queue=cv-ingestion,default --tries=3 --timeout=600
```

## Fixture paths

Test files live in `fixtures/` relative to this folder:

| Fixture | Purpose |
|---------|---------|
| `fixtures/valid-test.pdf` | Valid PDF upload |
| `fixtures/valid-test.docx` | Valid DOCX upload |
| `fixtures/corrupt.pdf` | Binary signature mismatch |
| `fixtures/empty.pdf` | Below minimum size |
| `fixtures/unsupported.txt` | Unsupported extension |
| `fixtures/fake-docx.docx` | ZIP without word/document.xml |

## Authentication sequence

Uses **Laravel Sanctum SPA cookie authentication**.
Run CSRF Cookie and Login before any CV request:

```
01 - Authentication/02 - CSRF Cookie
01 - Authentication/03 - Login
```

## Folder execution order

| Order | Folder | Description |
|-------|--------|-------------|
| 01 | Documents | List, upload, view, download, delete CV documents |
| 02 | Upload Validation | Negative tests for all upload rules |
| 03 | Processing | Upload and poll until processing completes |
| 04 | Suggestions | List and verify suggestion structure |
| 05 | Review Decisions | Accept, edit, reject, keep, batch decisions |
| 06 | Import Preview | Generate and verify import preview |
| 07 | Apply Import | Apply reviewed suggestions to profile |
| 08 | Retry and Recovery | Retry processing, conflict states |
| 09 | Ownership and Not Found | 404 for non-existent resources |
| 10 | Concurrency | Stale updated_at handling |
| 11 | Profile Completion | Completion invariance during ingestion |
| 12 | Cleanup | Remove temporary records |

## Runtime variables

| Variable | Created By | Cleaned Up By |
|----------|-----------|--------------|
| `cvDocumentId` | 01 - Documents/02 | 01 - Documents/06 |
| `cvDocumentForProcessing` | 03 - Processing/01 | 12 - Cleanup |
| `suggestionIds` | 04 - Suggestions/01 | 12 - Cleanup |
| `temporaryProfileItemIds` | 07 - Apply Import/02 | 12 - Cleanup |
| `temporarySkillIds` | 07 - Apply Import/02 | 12 - Cleanup |

## Apply safety rule

Section 07 modifies the candidate's real profile. It:

1. Captures profile state before Apply
2. Uses a unique `Idempotency-Key` header
3. Captures all created IDs for deterministic cleanup
4. Verifies no duplicate data on repeat invocation

**Run Section 12 (Cleanup) after Section 07 to restore state.**

## Cleanup procedure

Run independently to remove leftover test data:

```powershell
bru run "04 - CV Ingestion\12 - Cleanup" -r --env local
```

Removes:
- CV documents created during the run
- Derived processing data
- Temporary profile items and candidate skills

## Cross-user test requirements

True cross-user testing requires two dedicated test accounts.
This suite does not include cross-user tests unless two accounts are pre-configured.

## Bruno CLI commands

```powershell
# Run individual sections in order (session persists per run):
bru run "04 - CV Ingestion\02 - Upload Validation" -r --env local --output results-02.json --format json

# With JUnit reporting:
bru run "04 - CV Ingestion\01 - Documents" -r --env local --reporter-junit results-01.xml

# Full suite (requires auth first):
bru run "01 - Authentication\02 - CSRF Cookie" "01 - Authentication\03 - Login" -r --env local
bru run "04 - CV Ingestion\09 - Ownership and Not Found" -r --env local
bru run "04 - CV Ingestion\02 - Upload Validation" -r --env local
bru run "04 - CV Ingestion\01 - Documents" -r --env local
bru run "04 - CV Ingestion\03 - Processing" -r --env local
bru run "04 - CV Ingestion\04 - Suggestions" -r --env local
bru run "04 - CV Ingestion\05 - Review Decisions" -r --env local
bru run "04 - CV Ingestion\06 - Import Preview" -r --env local
bru run "04 - CV Ingestion\07 - Apply Import" -r --env local
bru run "04 - CV Ingestion\08 - Retry and Recovery" -r --env local
bru run "04 - CV Ingestion\10 - Concurrency and Idempotency" -r --env local
bru run "04 - CV Ingestion\11 - Profile Completion" -r --env local
bru run "04 - CV Ingestion\12 - Cleanup" -r --env local
```
