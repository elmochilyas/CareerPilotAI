## ADDED Requirements

### Requirement: CANDIDATE-SKILLS-011: CV-imported skills enter as claimed
Skills imported from CV ingestion SHALL always enter the candidate skill inventory in the `claimed` state. The system SHALL NOT automatically transition them to `verified`.

#### Scenario: CV import creates claimed skill
- **GIVEN** a candidate imports a CV that suggests skill "PHP"
- **WHEN** the candidate accepts the PHP skill suggestion
- **THEN** the system creates the candidate_skill with `state: 'claimed'`
- **AND** the skill SHALL appear in the skills list as `claimed`

#### Scenario: CV import skill cannot be verified automatically
- **GIVEN** a candidate accepts a "Laravel" skill from a CV import
- **WHEN** the skill is created as `claimed`
- **THEN** the state transition matrix SHALL require explicit candidate action to change to `verified`
- **AND** no automatic verification SHALL occur at import time

#### Scenario: CV import custom skill from catalog
- **GIVEN** the CV suggests "Docker" which exists in the canonical skill catalog
- **WHEN** the candidate accepts it
- **THEN** the system SHALL normalize the name to the canonical `skill_id`
- **AND** create the candidate_skill with `skill_id` pointing to the canonical skill

#### Scenario: CV import custom skill not in catalog
- **GIVEN** the CV suggests "Terraform" which does not exist in the canonical catalog
- **WHEN** the candidate accepts it
- **THEN** the system SHALL create a custom skill with `custom_skill_name: 'Terraform'`
- **AND** `skill_id` SHALL be null

### Requirement: CANDIDATE-SKILLS-012: CV import skill evidence
Skills created from CV ingestion SHALL include evidence referencing the source CV document.

#### Scenario: CV import evidence structure
- **GIVEN** a candidate accepts a skill suggestion from a CV import
- **WHEN** the skill is created
- **THEN** the `evidence` JSON array SHALL contain an entry with:
  - `type: 'cv_import'`
  - `cv_document_id: <document_id>`
  - `source_text: <text snippet from CV>`
  - `extracted_at: <timestamp>`

#### Scenario: Additional evidence is not added by import
- **GIVEN** a candidate already has a skill with existing evidence
- **WHEN** a CV import creates a duplicate skill (prevented by unique constraint)
- **THEN** the existing skill's evidence SHALL NOT be modified by the import
- **AND** if the candidate updates an existing skill via the import, only the evidence from the CV SHALL be added
