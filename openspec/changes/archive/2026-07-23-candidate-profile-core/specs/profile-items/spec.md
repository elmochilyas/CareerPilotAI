## ADDED Requirements

### Requirement: PROF-ITEM-001: Create profile item
The system SHALL allow authenticated candidates to create typed profile items.

#### Scenario: Create education item
- **WHEN** an authenticated user sends POST to `/api/v1/profile/items` with type `education`, title, organization, start_date, end_date, and optional metadata
- **THEN** the system SHALL create the item, recalculate profile_completion, and return HTTP 201

#### Scenario: Create experience item
- **WHEN** an authenticated user sends POST to `/api/v1/profile/items` with type `experience`, title, organization, start_date, end_date, description, and optional metadata containing employment_type
- **THEN** the system SHALL create the item and return HTTP 201

#### Scenario: Create project item
- **WHEN** an authenticated user sends POST to `/api/v1/profile/items` with type `project`, title, start_date, end_date, description, and optional metadata containing project_url and repository_url
- **THEN** the system SHALL create the item and return HTTP 201

#### Scenario: Create certification item
- **WHEN** an authenticated user sends POST to `/api/v1/profile/items` with type `certification`, title, organization, and optional metadata containing issuer, credential_url, and expiry_date
- **THEN** the system SHALL create the item and return HTTP 201

#### Scenario: Invalid type rejected
- **WHEN** an authenticated user sends POST with type `invalid-type`
- **THEN** the system SHALL return HTTP 422 with a validation error

### Requirement: PROF-ITEM-002: Update profile item
The system SHALL allow authenticated candidates to update their own profile items.

#### Scenario: Update own item
- **WHEN** an authenticated user sends PATCH to `/api/v1/profile/items/{id}` with valid fields for their own item
- **THEN** the system SHALL update the item and return HTTP 200

#### Scenario: Update another user's item
- **WHEN** an authenticated user sends PATCH to `/api/v1/profile/items/{id}` for an item belonging to another user
- **THEN** the system SHALL return HTTP 404

### Requirement: PROF-ITEM-003: Delete profile item
The system SHALL allow authenticated candidates to delete their own profile items.

#### Scenario: Delete own item
- **WHEN** an authenticated user sends DELETE to `/api/v1/profile/items/{id}` for their own item
- **THEN** the system SHALL delete the item, recalculate profile_completion, and return HTTP 204

#### Scenario: Delete another user's item
- **WHEN** an authenticated user sends DELETE to `/api/v1/profile/items/{id}` for an item belonging to another user
- **THEN** the system SHALL return HTTP 404

### Requirement: PROF-ITEM-004: Date range validation
The system SHALL require end_date to be on or after start_date when both are provided.

#### Scenario: Valid date range accepted
- **WHEN** a user creates an item with start_date `2020-01-01` and end_date `2024-12-31`
- **THEN** the system SHALL accept the item

#### Scenario: Invalid date range rejected
- **WHEN** a user creates an item with start_date `2024-01-01` and end_date `2020-01-01`
- **THEN** the system SHALL return HTTP 422 with a validation error

#### Scenario: Only start date allowed
- **WHEN** a user creates an item with only start_date and no end_date
- **THEN** the system SHALL accept the item

### Requirement: PROF-ITEM-005: Reorder profile items
The system SHALL support atomic reordering of profile items within a type.

#### Scenario: Reorder items within type
- **WHEN** an authenticated user sends PATCH to `/api/v1/profile/items/reorder` with type `experience` and an ordered array of item IDs
- **THEN** the system SHALL atomically update display_order for all affected items within a transaction

#### Scenario: Partial reorder failure
- **WHEN** a user sends a reorder request that includes an item ID belonging to another user
- **THEN** the system SHALL reject the entire request without modifying any items

### Requirement: PROF-ITEM-006: Metadata structure validation
The system SHALL validate metadata JSON structure against type-specific expected schemas.

#### Scenario: Valid education metadata accepted
- **WHEN** a user creates an education item with metadata containing `degree` and `field` keys
- **THEN** the system SHALL accept the metadata

#### Scenario: Excessively large metadata rejected
- **WHEN** a user creates an item with metadata exceeding the maximum payload size
- **THEN** the system SHALL return HTTP 422 with a validation error

### Requirement: PROF-ITEM-007: Profile completion recalculation
The system SHALL recalculate profile_completion when profile items are created, updated, or deleted.

#### Scenario: Completion increases after adding education
- **WHEN** a user with no education items creates one
- **THEN** the system SHALL recalculate and increase profile_completion

#### Scenario: Completion decreases after deleting experience
- **WHEN** a user deletes their only experience item
- **THEN** the system SHALL recalculate and decrease profile_completion
