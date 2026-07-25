# Skills and Evidence — Bruno API Test Suite

This folder validates all Skills and Evidence endpoints of the CareerPilot AI API.

## Local-only execution

This suite **must only run against a local development environment**.
The `{{baseUrl}}` must point to `http://careerpilot-api.test`, `http://localhost`, or `127.0.0.1`.

**Abort immediately if `{{baseUrl}}` points to staging or production.**

## Required environment variables

| Variable        | Example                          |
|-----------------|----------------------------------|
| `{{baseUrl}}`   | `http://careerpilot-api.test`    |
| `{{frontendUrl}}` | `http://localhost:5173`        |

These are defined in `environments/local.yml`.

## Dedicated test candidate

The suite expects the test user:

- Email: `bruno.test@example.com`
- Password: `NewPassword123!`

This account must exist in the local database. It is used by the existing Authentication and Candidate Profile suites.

## Authentication sequence

The API uses **Laravel Sanctum SPA cookie authentication**.

Every mutation request (POST, PATCH, DELETE, POST archive/restore) uses a before-request
script that reads the `XSRF-TOKEN` cookie set by the CSRF endpoint.

The authentication flow:

1. `GET {{baseUrl}}/sanctum/csrf-cookie` — sets the XSRF-TOKEN cookie
2. `POST {{baseUrl}}/api/v1/auth/login` — authenticates with email + password
3. Subsequent requests use the established session cookie

Read-only GET requests need only `Accept: application/json`, `Origin`, and `Referer` headers.

## Folder execution order

| Order | Folder | Description |
|-------|--------|-------------|
| 01 | Authentication | CSRF Cookie, Login, Current User |
| 02 | Skill Catalog | List, search, view canonical skills |
| 03 | Candidate Skill Lifecycle | CRUD lifecycle for candidate skills |
| 04 | State Transitions | Allowed and forbidden state transitions |
| 05 | Evidence | URL evidence, unsafe URL rejection, profile-item evidence |
| 06 | Archive and Restore | Archive, restore, duplicate prevention |
| 07 | Validation | Invalid requests and error handling |
| 08 | Ownership and Not Found | 404 behavior for non-existent resources |
| 09 | Concurrency | Optimistic concurrency (stale updated_at) |
| 10 | Completion Invariance | Profile completion invariance across skill operations |
| 11 | Cleanup | Safe cleanup of any remaining test data |

## Runtime variables

The suite sets these Bruno runtime variables that cascade across requests:

- `{{testRunId}}` — unique identifier for the current run
- `{{catalogSkillId}}` — ID of a canonical skill from the catalog
- `{{claimedCandidateSkillId}}` — ID of a created candidate skill
- `{{currentCandidateSkillUpdatedAt}}` — concurrency value
- `{{staleCandidateSkillUpdatedAt}}` — intentionally stale concurrency value
- `{{urlEvidenceKey}}` — UUID key for URL evidence entry
- `{{profileItemEvidenceKey}}` — UUID key for profile-item evidence entry
- `{{temporaryProfileItemId}}` — ID of a temporary profile item
- `{{profileCompletionBefore}}` — profile completion before operations
- Various transition, archive, concurrency, and completion IDs

Each subfolder sets its own variables to avoid cross-folder contamination.

## Which tests modify persistent data

The following test folders create temporary data that is cleaned up within the same run:

- **03 - Candidate Skill Lifecycle**: creates a skill, deletes it
- **04 - State Transitions**: creates skills, transitions them, deletes them
- **05 - Evidence**: creates a skill, profile item, evidence; deletes all
- **06 - Archive and Restore**: creates a skill, archives/restores, deletes
- **07 - Validation**: creates a custom skill for duplicate test, deletes it
- **09 - Concurrency**: creates a skill, tests stale update, deletes
- **10 - Completion Invariance**: creates a skill with evidence, verifies, deletes

No pre-existing data is ever modified or deleted.

## Cleanup procedure

The `11 - Cleanup` folder can be run independently to remove any leftover test data.

It lists all candidate skills and profile items, but since Bruno does not support
conditional iteration, manual cleanup may be needed if a previous run failed.

To re-run cleanup:
```powershell
bru run "03 - Skills and Evidence\11 - Cleanup" -r --env local
```

You can also delete skills manually via the API if needed.

## Cross-user test requirements

True cross-user testing requires two dedicated test accounts configured in the Bruno
environment. This suite does not include cross-user tests unless two accounts are
pre-configured. Cross-user access is expected to return ownership-hidden 404 responses.

## Concurrency scenario

The concurrency test (folder 09):
1. Creates a skill and captures its `updated_at`
2. Performs a valid update with the current `updated_at` → 200
3. Performs a second update with the stale (original) `updated_at` → 409
4. Verifies the successful update was not overwritten
5. Cleans up

## Profile-completion invariance scenario

The completion invariance test (folder 10):
1. Captures initial profile completion
2. Creates a claimed skill — verifies completion unchanged
3. Adds evidence — verifies completion unchanged
4. Verifies skill — verifies completion unchanged
5. Archives skill — verifies completion unchanged
6. Restores skill — verifies completion unchanged
7. Removes evidence — verifies completion unchanged
8. Deletes skill — verifies completion unchanged

## Bruno CLI commands

```powershell
# Run the full Skills and Evidence suite (requires auth)
# First run the Authentication folder:
bru run "03 - Skills and Evidence\01 - Authentication" -r --env local

# Then run individual subfolders in order:
bru run "03 - Skills and Evidence\02 - Skill Catalog" -r --env local
bru run "03 - Skills and Evidence\03 - Candidate Skill Lifecycle" -r --env local
# ... etc

# Run with JSON output for reporting:
bru run "03 - Skills and Evidence\02 - Skill Catalog" -r --env local --output results.json

# Run entire collection from root (includes all tests):
bru run . -r --env local
```

Run individual folders sequentially — session cookies persist within a single `bru run`
invocation but NOT between separate invocations.
