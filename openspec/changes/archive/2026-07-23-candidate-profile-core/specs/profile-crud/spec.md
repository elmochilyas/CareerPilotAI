## ADDED Requirements

### Requirement: PROF-CRUD-001: Authenticated candidate can view their profile
The system SHALL return the authenticated candidate's profile data including grouped profile items when requested.

#### Scenario: Authenticated user views their existing profile
- **WHEN** a GET request is made to `/api/v1/profile` by an authenticated user who has a complete profile
- **THEN** the system SHALL return HTTP 200 with profile data including headline, summary, contact links, career preferences, languages, and grouped profile items

#### Scenario: Authenticated user views their profile when no profile exists
- **WHEN** a GET request is made to `/api/v1/profile` by an authenticated user who has not yet created a profile
- **THEN** the system SHALL return HTTP 200 with a profile object containing default/empty values and profile_completion of 0

#### Scenario: Unauthenticated request to view profile
- **WHEN** a GET request is made to `/api/v1/profile` without authentication
- **THEN** the system SHALL return HTTP 401 with the standard problem-details error format

### Requirement: PROF-CRUD-002: Authenticated candidate can create or update their profile
The system SHALL accept partial updates to the authenticated candidate's profile using PATCH semantics.

#### Scenario: First-time profile creation
- **WHEN** an authenticated user sends a PATCH request to `/api/v1/profile` with valid profile fields for the first time
- **THEN** the system SHALL create a new candidate_profile record, set profile_completion, and return HTTP 200 with the created profile

#### Scenario: Update existing profile
- **WHEN** an authenticated user sends a PATCH request to `/api/v1/profile` with valid fields for an existing profile
- **THEN** the system SHALL update only the provided fields and return HTTP 200 with the updated profile

#### Scenario: Partial profile update
- **WHEN** an authenticated user sends a PATCH request with only a subset of profile fields
- **THEN** the system SHALL update only the provided fields without affecting unmentioned fields

#### Scenario: Invalid profile update rejected
- **WHEN** an authenticated user sends a PATCH request with invalid data such as an invalid URL or headline exceeding length limits
- **THEN** the system SHALL return HTTP 422 with problem-details validation errors

### Requirement: PROF-CRUD-003: Salary validation
The system SHALL enforce salary range validation.

#### Scenario: Valid salary range accepted
- **WHEN** a user sets salary_min to 30000 and salary_max to 60000
- **THEN** the system SHALL accept the values

#### Scenario: Invalid salary range rejected
- **WHEN** a user sets salary_min to 80000 and salary_max to 40000
- **THEN** the system SHALL reject with a validation error

#### Scenario: Single salary value allowed
- **WHEN** a user sets only salary_min or only salary_max
- **THEN** the system SHALL accept the single value

### Requirement: PROF-CRUD-004: One profile per user
The system SHALL enforce at most one candidate profile per user.

#### Scenario: Second profile creation attempt
- **WHEN** a user who already has a profile sends a PATCH request
- **THEN** the system SHALL update the existing profile rather than creating a duplicate

### Requirement: PROF-CRUD-005: URL validation
The system SHALL validate LinkedIn, GitHub, and portfolio URLs.

#### Scenario: Valid HTTPS URL accepted
- **WHEN** a user sets linkedin_url to `https://linkedin.com/in/username`
- **THEN** the system SHALL accept the URL

#### Scenario: Unsafe URL scheme rejected
- **WHEN** a user sets linkedin_url to `javascript:alert(1)`
- **THEN** the system SHALL reject with a validation error

#### Scenario: Non-HTTP URL scheme rejected
- **WHEN** a user sets portfolio_url to `file:///etc/passwd`
- **THEN** the system SHALL reject with a validation error

### Requirement: PROF-CRUD-006: Ownership protection
The system SHALL prevent cross-user profile access.

#### Scenario: User cannot view another user's profile
- **WHEN** an authenticated user attempts to access profile data that belongs to another user
- **THEN** the system SHALL return HTTP 404 with a consistent problem-details error

### Requirement: PROF-CRUD-007: Concurrent update protection
The system SHALL use updated_at optimistic concurrency to prevent silent overwrites.

#### Scenario: Stale data update rejected
- **WHEN** a user submits a PATCH with an updated_at value that does not match the current server value
- **THEN** the system SHALL return HTTP 409 with a conflict problem-details error

#### Scenario: Fresh data update accepted
- **WHEN** a user submits a PATCH with an updated_at value matching the current server value
- **THEN** the system SHALL accept and process the update

### Requirement: PROF-CRUD-008: Profile collapse with user deletion
The system SHALL cascade-delete the candidate profile when the owning user is deleted.

#### Scenario: User deletion removes profile
- **WHEN** a user account is deleted
- **THEN** the associated candidate_profile record SHALL also be deleted

### Requirement: PROF-CRUD-009: Overposting prevention
The system SHALL reject or ignore privileged fields sent in profile update requests.

#### Scenario: Privileged user_id ignored
- **WHEN** a user sends a PATCH request containing `user_id` or `candidate_profile_id` fields
- **THEN** the system SHALL silently ignore those fields or reject them with a validation error

### Requirement: PROF-CRUD-010: Availability and work modes enum validation
The system SHALL validate availability_status, work_modes, and contract_types against allowed values.

#### Scenario: Valid availability status accepted
- **WHEN** a user sets availability_status to 'immediately'
- **THEN** the system SHALL accept the value

#### Scenario: Invalid availability status rejected
- **WHEN** a user sets availability_status to 'not-real-status'
- **THEN** the system SHALL reject with a validation error

#### Scenario: Valid work modes accepted
- **WHEN** a user sets work_modes to ['remote']
- **THEN** the system SHALL accept the value
