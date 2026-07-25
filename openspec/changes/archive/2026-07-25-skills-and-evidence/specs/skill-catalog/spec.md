## ADDED Requirements

### Requirement: SKILL-CATALOG-001 — Canonical skill catalog

The system SHALL maintain a canonical skill catalog with globally unique normalized skill names, optional categories, and an active/inactive status.

#### Scenario: Catalog returns paginated skills

- **WHEN** an authenticated candidate sends GET /api/v1/skills
- **THEN** the system returns a paginated list of active canonical skills with their aliases

#### Scenario: Catalog search by name

- **WHEN** an authenticated candidate sends GET /api/v1/skills?q=laravel
- **THEN** the system returns skills whose name or normalized_name matches the search query, ordered by relevance

#### Scenario: Catalog search minimum length

- **WHEN** an authenticated candidate sends GET /api/v1/skills?q=a
- **THEN** the system returns a validation error because the search query is shorter than 2 characters

#### Scenario: Catalog search no results

- **WHEN** an authenticated candidate sends GET /api/v1/skills?q=zzzzunknown
- **THEN** the system returns an empty paginated result

#### Scenario: Show single skill

- **WHEN** an authenticated candidate sends GET /api/v1/skills/1
- **THEN** the system returns the skill with its id, name, normalized_name, category, is_active, aliases, and timestamps

#### Scenario: Show non-existent skill

- **WHEN** an authenticated candidate sends GET /api/v1/skills/99999
- **THEN** the system returns a 404 problem-detail response

#### Scenario: Unauthenticated catalog access

- **WHEN** an unauthenticated client sends GET /api/v1/skills
- **THEN** the system returns a 401 unauthenticated response

### Requirement: SKILL-CATALOG-002 — Normalized unique names

The system SHALL enforce that every canonical skill has a globally unique normalized_name. Normalization SHALL trim whitespace, convert to lowercase, and replace multiple internal spaces with a single space.

#### Scenario: Duplicate normalized name rejected

- **WHEN** a seeder attempts to create a skill with normalized_name "javascript" that already exists
- **THEN** the database UNIQUE constraint rejects the duplicate

#### Scenario: Normalized name trimming

- **WHEN** a skill is created with text "  JavaScript  "
- **THEN** the stored normalized_name is "javascript"

### Requirement: SKILL-CATALOG-003 — Skill aliases for deterministic normalization

The system SHALL maintain a skill_aliases table with UNIQUE(alias) constraint and foreign key to skills. Each alias SHALL resolve to exactly one canonical skill.

#### Scenario: Alias resolves to canonical skill

- **WHEN** a candidate searches or the system normalizes input "JS"
- **THEN** the alias "JS" resolves to the canonical skill "JavaScript"

#### Scenario: Duplicate alias prevented

- **WHEN** a seeder attempts to create alias "JS" for two different skills
- **THEN** the database UNIQUE constraint rejects the duplicate alias

#### Scenario: Ambiguous alias returns multiple candidates

- **WHEN** a candidate enters an ambiguous alias that matches multiple canonical skills
- **THEN** the system returns all matching skill candidates for the candidate to select from

#### Scenario: No match for input

- **WHEN** input text does not match any alias or normalized_name
- **THEN** the system indicates no match and offers custom skill creation

### Requirement: SKILL-CATALOG-004 — Active skill filtering

The system SHALL only return active skills (is_active = true) in search results by default. Inactive skills SHALL be excluded from candidate-facing results.

#### Scenario: Inactive skill excluded from search

- **WHEN** a candidate searches for a skill that has is_active = false
- **THEN** the skill is not returned in search results

### Requirement: SKILL-CATALOG-005 — Seeded catalog

The system SHALL ship with a seeded set of canonical skills covering common junior technology roles, including programming languages, frameworks, databases, tools, cloud platforms, and relevant soft skills.

#### Scenario: Seeded catalog available after migration

- **WHEN** migrations and seeders have run
- **THEN** the skills table contains approximately 150-200 seeded entries

### Requirement: SKILL-CATALOG-006 — Unauthenticated access denied

All skill catalog endpoints SHALL require authentication. Unauthenticated requests SHALL receive a 401 response.

#### Scenario: Unauthenticated catalog access returns 401

- **WHEN** an unauthenticated request is made to any skill endpoint
- **THEN** the system returns a 401 problem-detail response
