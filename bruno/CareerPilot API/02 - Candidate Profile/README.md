# Candidate Profile - Bruno API Collection

## Before You Begin

- **Run against the local environment only.** Do not run against production.
- **Use a dedicated local test candidate.** Profile PATCH requests modify real
  profile fields on the authenticated user.
- **Prerequisites:** Run `01 - Authentication/02 - CSRF Cookie` then
  `01 - Authentication/03 - Login` before any profile request.

## Folder Guide

| Folder | Type | Notes |
|--------|------|-------|
| `01 - Profile` | Standalone | Each PATCH request updates one area. These modify real profile fields. |
| `02 - Experience` | Ordered | Creates temporary items, run from top to bottom, must reach cleanup. |
| `03 - Education` | Ordered | Creates temporary items, run from top to bottom, must reach cleanup. |
| `04 - Projects` | Ordered | Creates temporary items, run from top to bottom, must reach cleanup. |
| `05 - Certifications` | Ordered | Creates temporary items, run from top to bottom, must reach cleanup. |
| `06 - Validation` | Standalone | Each request verifies a single validation rule. No data created. |
| `07 - Ownership and Not Found` | Standalone | Tests 404 for non-existent items. No data created. |
| `08 - Item Ordering` | Ordered | Creates temp items, reorders, restores original order, cleans up. |
| `09 - End-to-End Scenarios` | Ordered | Multi-step completion and lifecycle verification. |

## Variables Generated Automatically

| Variable | Created By | Cleaned Up By |
|----------|-----------|--------------|
| `currentExperienceItemId` | 02 - Experience/01 | 02 - Experience/07 |
| `completedExperienceItemId` | 02 - Experience/02 | 02 - Experience/08 |
| `ongoingEducationItemId` | 03 - Education/01 | 03 - Education/05 |
| `completedEducationItemId` | 03 - Education/02 | 03 - Education/06 |
| `ongoingProjectItemId` | 04 - Projects/01 | 04 - Projects/07 |
| `completedProjectItemId` | 04 - Projects/02 | 04 - Projects/08 |
| `technologyProjectItemId` | 04 - Projects/03 | 04 - Projects/09 |
| `nonExpiringCertificationItemId` | 05 - Certifications/01 | 05 - Certifications/06 |
| `expiringCertificationItemId` | 05 - Certifications/02 | 05 - Certifications/07 |
| `tempProjectAId`, `tempProjectBId` | 08 - Item Ordering/01-02 | 08 - Item Ordering/08-09 |
| `e2eCertItemId` | 09 - End-to-End/02 | 09 - End-to-End/04 |
| `e2eExperienceItemId` | 09 - End-to-End/05 | 09 - End-to-End/07 |
| `projectIdsBefore` | 08 - Item Ordering/03 | Restored in 08 - Item Ordering/07 |
| `projectIdsAfter` | 08 - Item Ordering/04 | Used for verification only |
| `beforeCompletion` | 09 - End-to-End/01 | Used for delta verification |

## Destructive Operations

- **Every temporary item has a dedicated cleanup request.**
- Run all cleanup requests to restore the original state.
- Profile PATCH requests are not reverted. Use a dedicated test candidate.

## Notes

- **Completed experience without end date:** The backend does not enforce
  `end_date` when `is_current` is false. This validation was not added.
- **Cross-user authorization:** Not automated. Requires two authenticated
  sessions which this single-cookie collection cannot provide.
- **409 Conflict (`profile_conflict`):** Not automated. Requires precise
  `updated_at` timing. The backend returns 409 when the provided `updated_at`
  does not match the current record.
- **Non-HTTPS URLs:** The backend rejects non-HTTPS URLs via both
  `url:http,https` and `starts_with:https://` rules.
- **Maximum array sizes:** target_roles (10), preferred_locations (10),
  work_modes (5), contract_types (5), languages (10).