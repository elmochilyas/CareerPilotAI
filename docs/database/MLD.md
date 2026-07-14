# CareerPilot AI — MLD

## Purpose

This is the authoritative **Modèle Logique de Données** for the CareerPilot AI MVP.

Laravel migrations must implement this model incrementally through approved OpenSpec changes.

## Global conventions

- Database: MySQL, InnoDB, `utf8mb4`.
- Business PKs: `BIGINT UNSIGNED AUTO_INCREMENT`.
- FKs: `BIGINT UNSIGNED`.
- Timestamps: Laravel `created_at` and `updated_at`.
- Business states: PHP backed enums stored as strings.
- Money: `DECIMAL`, never floating point.
- JSON only where explicitly approved.
- Shared catalogues use `ON DELETE RESTRICT`.
- True child records may use `ON DELETE CASCADE`.
- Historical or externally referenced records use `RESTRICT` or `SET NULL`.
- All user-owned queries require authorization.


# Identity and profile

## `users`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `full_name` | VARCHAR(150) | NO |  |
| `email` | VARCHAR(255) | NO | UNIQUE |
| `password` | VARCHAR(255) | NO | hashed |
| `email_verified_at` | TIMESTAMP | YES |  |
| `role` | VARCHAR(30) | NO | DEFAULT candidate |
| `account_status` | VARCHAR(30) | NO | DEFAULT active |
| `timezone` | VARCHAR(64) | NO | DEFAULT UTC |
| `remember_token` | VARCHAR(100) | YES |  |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |
| `deleted_at` | TIMESTAMP | YES | soft delete |

**Constraints and indexes**
- UNIQUE(email)
- INDEX(role)
- INDEX(account_status)

**Approved values**
- role: candidate, admin
- account_status: active, suspended, disabled

## `candidate_profiles`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `user_id` | BIGINT UNSIGNED | NO | FK, UNIQUE |
| `headline` | VARCHAR(180) | YES |  |
| `professional_summary` | TEXT | YES |  |
| `phone` | VARCHAR(30) | YES |  |
| `city` | VARCHAR(120) | YES |  |
| `country` | VARCHAR(120) | YES |  |
| `linkedin_url` | VARCHAR(500) | YES |  |
| `github_url` | VARCHAR(500) | YES |  |
| `portfolio_url` | VARCHAR(500) | YES |  |
| `availability_status` | VARCHAR(30) | YES |  |
| `profile_completion` | TINYINT UNSIGNED | NO | DEFAULT 0 |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |
| `deleted_at` | TIMESTAMP | YES | soft delete |

**Foreign keys**
- user_id → users.id ON DELETE CASCADE

**Constraints and indexes**
- UNIQUE(user_id)
- CHECK(profile_completion BETWEEN 0 AND 100)

## `candidate_preferences`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `candidate_profile_id` | BIGINT UNSIGNED | NO | FK, UNIQUE |
| `target_roles` | JSON | YES |  |
| `desired_locations` | JSON | YES |  |
| `work_mode` | VARCHAR(30) | YES |  |
| `contract_types` | JSON | YES |  |
| `salary_min` | DECIMAL(12,2) | YES |  |
| `salary_max` | DECIMAL(12,2) | YES |  |
| `salary_currency` | CHAR(3) | YES | ISO 4217 |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |

**Foreign keys**
- candidate_profile_id → candidate_profiles.id ON DELETE CASCADE

**Constraints and indexes**
- UNIQUE(candidate_profile_id)
- CHECK(salary_min IS NULL OR salary_max IS NULL OR salary_max >= salary_min)

## `educations`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `candidate_profile_id` | BIGINT UNSIGNED | NO | FK |
| `institution` | VARCHAR(180) | NO |  |
| `degree` | VARCHAR(180) | NO |  |
| `field_of_study` | VARCHAR(180) | YES |  |
| `start_date` | DATE | YES |  |
| `end_date` | DATE | YES |  |
| `description` | TEXT | YES |  |
| `display_order` | SMALLINT UNSIGNED | NO | DEFAULT 0 |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |

**Foreign keys**
- candidate_profile_id → candidate_profiles.id ON DELETE CASCADE

**Constraints and indexes**
- INDEX(candidate_profile_id, display_order)
- CHECK(start_date IS NULL OR end_date IS NULL OR end_date >= start_date)

## `experiences`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `candidate_profile_id` | BIGINT UNSIGNED | NO | FK |
| `company_name` | VARCHAR(180) | NO |  |
| `job_title` | VARCHAR(180) | NO |  |
| `employment_type` | VARCHAR(40) | YES |  |
| `location` | VARCHAR(180) | YES |  |
| `start_date` | DATE | NO |  |
| `end_date` | DATE | YES |  |
| `is_current` | BOOLEAN | NO | DEFAULT FALSE |
| `description` | TEXT | YES |  |
| `display_order` | SMALLINT UNSIGNED | NO | DEFAULT 0 |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |

**Foreign keys**
- candidate_profile_id → candidate_profiles.id ON DELETE CASCADE

**Constraints and indexes**
- INDEX(candidate_profile_id, start_date)
- CHECK(end_date IS NULL OR end_date >= start_date)
- Application validation: is_current=true requires end_date=NULL

## `projects`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `candidate_profile_id` | BIGINT UNSIGNED | NO | FK |
| `title` | VARCHAR(180) | NO |  |
| `context` | VARCHAR(100) | YES |  |
| `description` | TEXT | YES |  |
| `repository_url` | VARCHAR(500) | YES |  |
| `demo_url` | VARCHAR(500) | YES |  |
| `start_date` | DATE | YES |  |
| `end_date` | DATE | YES |  |
| `display_order` | SMALLINT UNSIGNED | NO | DEFAULT 0 |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |

**Foreign keys**
- candidate_profile_id → candidate_profiles.id ON DELETE CASCADE

**Constraints and indexes**
- INDEX(candidate_profile_id, display_order)
- CHECK(start_date IS NULL OR end_date IS NULL OR end_date >= start_date)

## `certifications`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `candidate_profile_id` | BIGINT UNSIGNED | NO | FK |
| `title` | VARCHAR(180) | NO |  |
| `issuer` | VARCHAR(180) | NO |  |
| `issue_date` | DATE | YES |  |
| `expiry_date` | DATE | YES |  |
| `credential_id` | VARCHAR(180) | YES |  |
| `credential_url` | VARCHAR(500) | YES |  |
| `display_order` | SMALLINT UNSIGNED | NO | DEFAULT 0 |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |

**Foreign keys**
- candidate_profile_id → candidate_profiles.id ON DELETE CASCADE

**Constraints and indexes**
- INDEX(candidate_profile_id, display_order)
- CHECK(issue_date IS NULL OR expiry_date IS NULL OR expiry_date >= issue_date)

## `candidate_languages`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `candidate_profile_id` | BIGINT UNSIGNED | NO | FK |
| `language` | VARCHAR(100) | NO |  |
| `proficiency_level` | VARCHAR(30) | NO |  |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |

**Foreign keys**
- candidate_profile_id → candidate_profiles.id ON DELETE CASCADE

**Constraints and indexes**
- UNIQUE(candidate_profile_id, language)

**Approved values**
- proficiency_level: beginner, intermediate, advanced, fluent, native


# Skills, files, and CV import

## `skills`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `name` | VARCHAR(150) | NO |  |
| `normalized_name` | VARCHAR(150) | NO | UNIQUE |
| `category` | VARCHAR(100) | YES |  |
| `aliases` | JSON | YES |  |
| `is_active` | BOOLEAN | NO | DEFAULT TRUE |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |

**Constraints and indexes**
- UNIQUE(normalized_name)
- INDEX(category)
- INDEX(is_active)

## `candidate_skills`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `candidate_profile_id` | BIGINT UNSIGNED | NO | FK |
| `skill_id` | BIGINT UNSIGNED | NO | FK |
| `state` | VARCHAR(30) | NO |  |
| `proficiency_level` | VARCHAR(30) | YES |  |
| `years_experience` | DECIMAL(4,1) | YES |  |
| `last_used_at` | DATE | YES |  |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |

**Foreign keys**
- candidate_profile_id → candidate_profiles.id ON DELETE CASCADE
- skill_id → skills.id ON DELETE RESTRICT

**Constraints and indexes**
- UNIQUE(candidate_profile_id, skill_id)
- INDEX(state)
- CHECK(years_experience IS NULL OR years_experience >= 0)

**Approved values**
- state: claimed, verified, learning, rejected, archived

## `stored_files`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `user_id` | BIGINT UNSIGNED | NO | FK |
| `original_name` | VARCHAR(255) | NO |  |
| `stored_name` | VARCHAR(255) | NO |  |
| `disk` | VARCHAR(50) | NO | DEFAULT local |
| `storage_path` | VARCHAR(1000) | NO |  |
| `mime_type` | VARCHAR(150) | NO |  |
| `extension` | VARCHAR(20) | YES |  |
| `size_bytes` | BIGINT UNSIGNED | NO |  |
| `checksum_sha256` | CHAR(64) | NO |  |
| `visibility` | VARCHAR(20) | NO | DEFAULT private |
| `scan_status` | VARCHAR(30) | NO | DEFAULT pending |
| `scanned_at` | DATETIME | YES |  |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |
| `deleted_at` | TIMESTAMP | YES | soft delete |

**Foreign keys**
- user_id → users.id ON DELETE CASCADE

**Constraints and indexes**
- UNIQUE(disk, storage_path)
- INDEX(user_id, created_at)
- INDEX(checksum_sha256)
- INDEX(scan_status)

## `skill_evidences`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `candidate_skill_id` | BIGINT UNSIGNED | NO | FK |
| `evidence_type` | VARCHAR(30) | NO |  |
| `title` | VARCHAR(180) | NO |  |
| `description` | TEXT | YES |  |
| `url` | VARCHAR(500) | YES |  |
| `stored_file_id` | BIGINT UNSIGNED | YES | FK |
| `project_id` | BIGINT UNSIGNED | YES | FK |
| `experience_id` | BIGINT UNSIGNED | YES | FK |
| `certification_id` | BIGINT UNSIGNED | YES | FK |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |

**Foreign keys**
- candidate_skill_id → candidate_skills.id ON DELETE CASCADE
- stored_file_id → stored_files.id ON DELETE SET NULL
- project_id → projects.id ON DELETE SET NULL
- experience_id → experiences.id ON DELETE SET NULL
- certification_id → certifications.id ON DELETE SET NULL

**Constraints and indexes**
- INDEX(candidate_skill_id)
- INDEX(evidence_type)
- Application invariant: at least one meaningful evidence source or description

**Approved values**
- evidence_type: manual, url, file, project, experience, certification

## `cv_imports`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `candidate_profile_id` | BIGINT UNSIGNED | NO | FK |
| `stored_file_id` | BIGINT UNSIGNED | NO | FK, UNIQUE |
| `status` | VARCHAR(30) | NO |  |
| `extracted_data` | JSON | YES |  |
| `reviewed_data` | JSON | YES |  |
| `error_message` | TEXT | YES | sanitized |
| `started_at` | DATETIME | YES |  |
| `completed_at` | DATETIME | YES |  |
| `reviewed_at` | DATETIME | YES |  |
| `confirmed_at` | DATETIME | YES |  |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |

**Foreign keys**
- candidate_profile_id → candidate_profiles.id ON DELETE CASCADE
- stored_file_id → stored_files.id ON DELETE RESTRICT

**Constraints and indexes**
- UNIQUE(stored_file_id)
- INDEX(candidate_profile_id, status)

**Approved values**
- status: pending, processing, review_required, confirmed, failed


# Companies, opportunities, and analysis

## `companies`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `candidate_profile_id` | BIGINT UNSIGNED | NO | FK |
| `name` | VARCHAR(180) | NO |  |
| `normalized_name` | VARCHAR(180) | NO |  |
| `website` | VARCHAR(500) | YES |  |
| `industry` | VARCHAR(150) | YES |  |
| `location` | VARCHAR(180) | YES |  |
| `size_band` | VARCHAR(50) | YES |  |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |
| `deleted_at` | TIMESTAMP | YES | soft delete |

**Foreign keys**
- candidate_profile_id → candidate_profiles.id ON DELETE CASCADE

**Constraints and indexes**
- UNIQUE(candidate_profile_id, normalized_name)

## `company_research`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `company_id` | BIGINT UNSIGNED | NO | FK, UNIQUE |
| `status` | VARCHAR(30) | NO |  |
| `summary` | TEXT | YES |  |
| `culture` | TEXT | YES |  |
| `activity` | TEXT | YES |  |
| `interview_tips` | TEXT | YES |  |
| `sources` | JSON | YES |  |
| `error_message` | TEXT | YES | sanitized |
| `researched_at` | DATETIME | YES |  |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |

**Foreign keys**
- company_id → companies.id ON DELETE CASCADE

**Constraints and indexes**
- UNIQUE(company_id)
- INDEX(status)

## `opportunities`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `candidate_profile_id` | BIGINT UNSIGNED | NO | FK |
| `company_id` | BIGINT UNSIGNED | YES | FK |
| `title` | VARCHAR(220) | NO |  |
| `source_type` | VARCHAR(30) | NO |  |
| `source_url` | VARCHAR(1000) | YES |  |
| `description` | LONGTEXT | NO |  |
| `location` | VARCHAR(180) | YES |  |
| `work_mode` | VARCHAR(30) | YES |  |
| `contract_type` | VARCHAR(40) | YES |  |
| `seniority_level` | VARCHAR(50) | YES |  |
| `salary_info` | JSON | YES |  |
| `status` | VARCHAR(30) | NO | DEFAULT saved |
| `saved_at` | DATETIME | NO |  |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |
| `deleted_at` | TIMESTAMP | YES | soft delete |

**Foreign keys**
- candidate_profile_id → candidate_profiles.id ON DELETE CASCADE
- company_id → companies.id ON DELETE SET NULL

**Constraints and indexes**
- INDEX(candidate_profile_id, status)
- INDEX(company_id)
- INDEX(title)

**Approved values**
- source_type: text, url, file
- status: saved, analyzing, analyzed, archived

## `job_analyses`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `opportunity_id` | BIGINT UNSIGNED | NO | FK, UNIQUE |
| `status` | VARCHAR(30) | NO |  |
| `summary` | TEXT | YES |  |
| `confidence` | DECIMAL(4,3) | YES |  |
| `notes` | TEXT | YES |  |
| `error_message` | TEXT | YES | sanitized |
| `analyzed_at` | DATETIME | YES |  |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |

**Foreign keys**
- opportunity_id → opportunities.id ON DELETE CASCADE

**Constraints and indexes**
- UNIQUE(opportunity_id)
- INDEX(status)
- CHECK(confidence IS NULL OR confidence BETWEEN 0 AND 1)

## `job_requirements`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `job_analysis_id` | BIGINT UNSIGNED | NO | FK |
| `skill_id` | BIGINT UNSIGNED | YES | FK |
| `requirement_type` | VARCHAR(40) | NO |  |
| `label` | VARCHAR(255) | NO |  |
| `importance` | VARCHAR(30) | NO |  |
| `confidence` | DECIMAL(4,3) | YES |  |
| `is_required` | BOOLEAN | NO | DEFAULT FALSE |
| `details` | JSON | YES |  |
| `display_order` | SMALLINT UNSIGNED | NO | DEFAULT 0 |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |

**Foreign keys**
- job_analysis_id → job_analyses.id ON DELETE CASCADE
- skill_id → skills.id ON DELETE SET NULL

**Constraints and indexes**
- INDEX(job_analysis_id, requirement_type)
- INDEX(skill_id)
- INDEX(is_required)
- CHECK(confidence IS NULL OR confidence BETWEEN 0 AND 1)

**Approved values**
- requirement_type: skill, language, experience, education, location, availability, contract, other
- importance: critical, high, medium, low


# Matching and clarification

## `match_analyses`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `candidate_profile_id` | BIGINT UNSIGNED | NO | FK |
| `opportunity_id` | BIGINT UNSIGNED | NO | FK |
| `job_analysis_id` | BIGINT UNSIGNED | NO | FK |
| `status` | VARCHAR(30) | NO | DEFAULT completed |
| `overall_score` | DECIMAL(5,2) | NO |  |
| `scoring_version` | VARCHAR(30) | NO |  |
| `weights` | JSON | NO |  |
| `profile_snapshot` | JSON | NO |  |
| `job_snapshot` | JSON | NO |  |
| `calculated_at` | DATETIME | NO |  |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |

**Foreign keys**
- candidate_profile_id → candidate_profiles.id ON DELETE CASCADE
- opportunity_id → opportunities.id ON DELETE CASCADE
- job_analysis_id → job_analyses.id ON DELETE RESTRICT

**Constraints and indexes**
- INDEX(candidate_profile_id, calculated_at)
- INDEX(opportunity_id, calculated_at)
- CHECK(overall_score BETWEEN 0 AND 100)

## `match_findings`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `match_analysis_id` | BIGINT UNSIGNED | NO | FK |
| `job_requirement_id` | BIGINT UNSIGNED | YES | FK |
| `candidate_skill_id` | BIGINT UNSIGNED | YES | FK |
| `finding_type` | VARCHAR(40) | NO |  |
| `status` | VARCHAR(30) | NO |  |
| `explanation` | TEXT | NO |  |
| `score_contribution` | DECIMAL(6,2) | NO | DEFAULT 0 |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |

**Foreign keys**
- match_analysis_id → match_analyses.id ON DELETE CASCADE
- job_requirement_id → job_requirements.id ON DELETE SET NULL
- candidate_skill_id → candidate_skills.id ON DELETE SET NULL

**Constraints and indexes**
- INDEX(match_analysis_id, status)
- INDEX(job_requirement_id)

**Approved values**
- status: matched, partially_matched, missing, uncertain

## `clarifications`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `match_analysis_id` | BIGINT UNSIGNED | NO | FK |
| `job_requirement_id` | BIGINT UNSIGNED | YES | FK |
| `question` | TEXT | NO |  |
| `answer` | TEXT | YES |  |
| `status` | VARCHAR(30) | NO | DEFAULT pending |
| `candidate_decision` | VARCHAR(30) | YES |  |
| `answered_at` | DATETIME | YES |  |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |

**Foreign keys**
- match_analysis_id → match_analyses.id ON DELETE CASCADE
- job_requirement_id → job_requirements.id ON DELETE SET NULL

**Constraints and indexes**
- INDEX(match_analysis_id, status)

**Approved values**
- status: pending, answered, dismissed
- candidate_decision: accepted, rejected, learning, evidence_added


# Resumes and exports

## `resumes`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `candidate_profile_id` | BIGINT UNSIGNED | NO | FK |
| `opportunity_id` | BIGINT UNSIGNED | YES | FK, UNIQUE |
| `title` | VARCHAR(220) | NO |  |
| `target_role` | VARCHAR(180) | YES |  |
| `template_key` | VARCHAR(50) | NO | DEFAULT default |
| `status` | VARCHAR(30) | NO | DEFAULT active |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |
| `deleted_at` | TIMESTAMP | YES | soft delete |

**Foreign keys**
- candidate_profile_id → candidate_profiles.id ON DELETE CASCADE
- opportunity_id → opportunities.id ON DELETE SET NULL

**Constraints and indexes**
- UNIQUE(opportunity_id)
- INDEX(candidate_profile_id, status)

## `resume_versions`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `resume_id` | BIGINT UNSIGNED | NO | FK |
| `match_analysis_id` | BIGINT UNSIGNED | YES | FK |
| `version_number` | SMALLINT UNSIGNED | NO |  |
| `content` | JSON | NO |  |
| `status` | VARCHAR(30) | NO | DEFAULT draft |
| `generated_by` | VARCHAR(30) | NO |  |
| `approved_at` | DATETIME | YES |  |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |

**Foreign keys**
- resume_id → resumes.id ON DELETE CASCADE
- match_analysis_id → match_analyses.id ON DELETE SET NULL

**Constraints and indexes**
- UNIQUE(resume_id, version_number)
- INDEX(resume_id, status)

**Approved values**
- status: draft, approved, archived
- generated_by: ai, candidate, system

## `resume_exports`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `resume_version_id` | BIGINT UNSIGNED | NO | FK |
| `stored_file_id` | BIGINT UNSIGNED | NO | FK, UNIQUE |
| `format` | VARCHAR(10) | NO |  |
| `status` | VARCHAR(30) | NO |  |
| `error_message` | TEXT | YES | sanitized |
| `generated_at` | DATETIME | YES |  |
| `expires_at` | DATETIME | YES |  |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |

**Foreign keys**
- resume_version_id → resume_versions.id ON DELETE CASCADE
- stored_file_id → stored_files.id ON DELETE RESTRICT

**Constraints and indexes**
- UNIQUE(stored_file_id)
- INDEX(resume_version_id, format)
- INDEX(status, expires_at)

**Approved values**
- format: pdf, docx
- status: pending, processing, completed, failed, expired


# Applications

## `applications`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `candidate_profile_id` | BIGINT UNSIGNED | NO | FK |
| `opportunity_id` | BIGINT UNSIGNED | NO | FK |
| `current_status` | VARCHAR(30) | NO |  |
| `applied_at` | DATETIME | YES |  |
| `contact_name` | VARCHAR(180) | YES |  |
| `contact_email` | VARCHAR(255) | YES |  |
| `contact_phone` | VARCHAR(30) | YES |  |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |
| `deleted_at` | TIMESTAMP | YES | soft delete |

**Foreign keys**
- candidate_profile_id → candidate_profiles.id ON DELETE CASCADE
- opportunity_id → opportunities.id ON DELETE RESTRICT

**Constraints and indexes**
- UNIQUE(candidate_profile_id, opportunity_id)
- INDEX(candidate_profile_id, current_status)
- INDEX(applied_at)

**Approved values**
- current_status: draft, applied, screening, interview, offer, accepted, rejected, withdrawn

## `application_status_histories`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `application_id` | BIGINT UNSIGNED | NO | FK |
| `changed_by_user_id` | BIGINT UNSIGNED | YES | FK |
| `previous_status` | VARCHAR(30) | YES |  |
| `new_status` | VARCHAR(30) | NO |  |
| `reason` | VARCHAR(255) | YES |  |
| `note` | TEXT | YES |  |
| `changed_at` | DATETIME | NO |  |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |

**Foreign keys**
- application_id → applications.id ON DELETE CASCADE
- changed_by_user_id → users.id ON DELETE SET NULL

**Constraints and indexes**
- INDEX(application_id, changed_at)
- Rows are immutable

## `application_notes`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `application_id` | BIGINT UNSIGNED | NO | FK |
| `note` | TEXT | NO |  |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |

**Foreign keys**
- application_id → applications.id ON DELETE CASCADE

**Constraints and indexes**
- INDEX(application_id, created_at)

## `application_documents`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `application_id` | BIGINT UNSIGNED | NO | FK |
| `resume_export_id` | BIGINT UNSIGNED | YES | FK |
| `stored_file_id` | BIGINT UNSIGNED | YES | FK |
| `document_type` | VARCHAR(40) | NO |  |
| `label` | VARCHAR(180) | NO |  |
| `sent_at` | DATETIME | YES |  |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |

**Foreign keys**
- application_id → applications.id ON DELETE CASCADE
- resume_export_id → resume_exports.id ON DELETE RESTRICT
- stored_file_id → stored_files.id ON DELETE RESTRICT

**Constraints and indexes**
- INDEX(application_id)
- INDEX(resume_export_id)
- INDEX(stored_file_id)
- CHECK(exactly one of resume_export_id or stored_file_id is non-null)


# Learning, tasks, and interviews

## `learning_roadmaps`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `candidate_profile_id` | BIGINT UNSIGNED | NO | FK, UNIQUE |
| `status` | VARCHAR(30) | NO | DEFAULT active |
| `last_calculated_at` | DATETIME | YES |  |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |

**Foreign keys**
- candidate_profile_id → candidate_profiles.id ON DELETE CASCADE

**Constraints and indexes**
- UNIQUE(candidate_profile_id)

## `roadmap_items`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `learning_roadmap_id` | BIGINT UNSIGNED | NO | FK |
| `skill_id` | BIGINT UNSIGNED | NO | FK |
| `priority_score` | DECIMAL(6,2) | NO |  |
| `demand_count` | INT UNSIGNED | NO | DEFAULT 0 |
| `reason` | TEXT | NO |  |
| `status` | VARCHAR(30) | NO | DEFAULT recommended |
| `progress_percent` | TINYINT UNSIGNED | NO | DEFAULT 0 |
| `target_date` | DATE | YES |  |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |

**Foreign keys**
- learning_roadmap_id → learning_roadmaps.id ON DELETE CASCADE
- skill_id → skills.id ON DELETE RESTRICT

**Constraints and indexes**
- UNIQUE(learning_roadmap_id, skill_id)
- INDEX(learning_roadmap_id, priority_score)
- INDEX(status)
- CHECK(progress_percent BETWEEN 0 AND 100)

## `tasks`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `candidate_profile_id` | BIGINT UNSIGNED | NO | FK |
| `application_id` | BIGINT UNSIGNED | YES | FK |
| `roadmap_item_id` | BIGINT UNSIGNED | YES | FK |
| `title` | VARCHAR(180) | NO |  |
| `description` | TEXT | YES |  |
| `status` | VARCHAR(30) | NO | DEFAULT pending |
| `priority` | VARCHAR(20) | NO | DEFAULT medium |
| `due_at` | DATETIME | YES |  |
| `remind_at` | DATETIME | YES |  |
| `reminder_sent_at` | DATETIME | YES |  |
| `completed_at` | DATETIME | YES |  |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |

**Foreign keys**
- candidate_profile_id → candidate_profiles.id ON DELETE CASCADE
- application_id → applications.id ON DELETE SET NULL
- roadmap_item_id → roadmap_items.id ON DELETE SET NULL

**Constraints and indexes**
- INDEX(candidate_profile_id, status)
- INDEX(application_id, status)
- INDEX(status, due_at)
- INDEX(remind_at)
- CHECK(application_id IS NULL OR roadmap_item_id IS NULL)

**Approved values**
- status: pending, in_progress, completed, cancelled
- priority: low, medium, high

## `interviews`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `application_id` | BIGINT UNSIGNED | NO | FK |
| `stage` | VARCHAR(50) | NO |  |
| `scheduled_at` | DATETIME | NO |  |
| `timezone` | VARCHAR(64) | NO |  |
| `interview_mode` | VARCHAR(30) | NO |  |
| `location` | VARCHAR(255) | YES |  |
| `meeting_url` | VARCHAR(1000) | YES |  |
| `status` | VARCHAR(30) | NO |  |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |

**Foreign keys**
- application_id → applications.id ON DELETE CASCADE

**Constraints and indexes**
- INDEX(application_id, scheduled_at)
- INDEX(status, scheduled_at)

## `preparation_packs`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `interview_id` | BIGINT UNSIGNED | NO | FK |
| `status` | VARCHAR(30) | NO |  |
| `summary` | TEXT | YES |  |
| `likely_questions` | JSON | YES |  |
| `checklist` | JSON | YES |  |
| `context_snapshot` | JSON | YES |  |
| `error_message` | TEXT | YES | sanitized |
| `generated_at` | DATETIME | YES |  |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |

**Foreign keys**
- interview_id → interviews.id ON DELETE CASCADE

**Constraints and indexes**
- INDEX(interview_id, status)

## `mock_interview_sessions`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `preparation_pack_id` | BIGINT UNSIGNED | NO | FK |
| `mode` | VARCHAR(30) | NO | DEFAULT text |
| `status` | VARCHAR(30) | NO |  |
| `started_at` | DATETIME | YES |  |
| `completed_at` | DATETIME | YES |  |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |

**Foreign keys**
- preparation_pack_id → preparation_packs.id ON DELETE CASCADE

**Constraints and indexes**
- INDEX(preparation_pack_id, created_at)

## `mock_interview_turns`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `mock_interview_session_id` | BIGINT UNSIGNED | NO | FK |
| `sequence_number` | SMALLINT UNSIGNED | NO |  |
| `question` | TEXT | NO |  |
| `answer` | TEXT | YES |  |
| `feedback` | JSON | YES |  |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |

**Foreign keys**
- mock_interview_session_id → mock_interview_sessions.id ON DELETE CASCADE

**Constraints and indexes**
- UNIQUE(mock_interview_session_id, sequence_number)


# AI audit and notifications

## `ai_runs`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `user_id` | BIGINT UNSIGNED | NO | FK |
| `operation_type` | VARCHAR(50) | NO |  |
| `subject_type` | VARCHAR(255) | YES | polymorphic |
| `subject_id` | BIGINT UNSIGNED | YES | polymorphic |
| `provider` | VARCHAR(80) | NO |  |
| `model` | VARCHAR(120) | NO |  |
| `prompt_version` | VARCHAR(50) | NO |  |
| `status` | VARCHAR(30) | NO |  |
| `input_tokens` | INT UNSIGNED | NO | DEFAULT 0 |
| `output_tokens` | INT UNSIGNED | NO | DEFAULT 0 |
| `estimated_cost` | DECIMAL(12,6) | NO | DEFAULT 0 |
| `request_id` | VARCHAR(100) | YES |  |
| `error_message` | TEXT | YES | sanitized |
| `started_at` | DATETIME | YES |  |
| `completed_at` | DATETIME | YES |  |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |

**Foreign keys**
- user_id → users.id ON DELETE CASCADE

**Constraints and indexes**
- INDEX(user_id, operation_type)
- INDEX(subject_type, subject_id)
- INDEX(status, created_at)
- INDEX(request_id)

## `notification_preferences`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | NO | PK |
| `user_id` | BIGINT UNSIGNED | NO | FK, UNIQUE |
| `email_enabled` | BOOLEAN | NO | DEFAULT TRUE |
| `app_enabled` | BOOLEAN | NO | DEFAULT TRUE |
| `reminders_enabled` | BOOLEAN | NO | DEFAULT TRUE |
| `interviews_enabled` | BOOLEAN | NO | DEFAULT TRUE |
| `application_updates_enabled` | BOOLEAN | NO | DEFAULT TRUE |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |

**Foreign keys**
- user_id → users.id ON DELETE CASCADE

**Constraints and indexes**
- UNIQUE(user_id)

## `notifications`

| Column | Type | Null | Key / default |
|---|---|---:|---|
| `id` | CHAR(36) | NO | PK |
| `type` | VARCHAR(255) | NO |  |
| `notifiable_type` | VARCHAR(255) | NO |  |
| `notifiable_id` | BIGINT UNSIGNED | NO |  |
| `data` | JSON | NO |  |
| `channel` | VARCHAR(30) | NO | DEFAULT database |
| `status` | VARCHAR(30) | NO | DEFAULT pending |
| `sent_at` | DATETIME | YES |  |
| `read_at` | TIMESTAMP | YES |  |
| `created_at` | TIMESTAMP | YES |  |
| `updated_at` | TIMESTAMP | YES |  |

**Constraints and indexes**
- INDEX(notifiable_type, notifiable_id)
- INDEX(status, created_at)
- INDEX(read_at)


# Laravel technical tables

These are implementation tables, not MCD business entities:

- `migrations`
- `password_reset_tokens`
- `sessions`
- `jobs`
- `job_batches`
- `failed_jobs`
- `cache`
- `cache_locks`
- `personal_access_tokens` when installed by Sanctum

Use the framework-provided schema for the installed Laravel version unless an approved OpenSpec change says otherwise.

# Cross-table invariants

1. One profile per user.
2. One candidate skill per profile and normalized skill.
3. One CV import per stored source file.
4. One current job analysis per opportunity.
5. Replacing a job analysis and its requirements is transactional.
6. Match analyses are append-only results.
7. One tailored logical resume per non-null opportunity.
8. Resume version number is unique inside a resume.
9. Approved resume versions are immutable.
10. One application per candidate profile and opportunity.
11. Application current-status update and history insertion are one transaction.
12. Application status history is immutable.
13. An application document has exactly one source.
14. One current learning roadmap per profile.
15. One roadmap item per roadmap and skill.
16. A task cannot belong to both an application and a roadmap item.
17. Mock-interview sequence number is unique inside a session.
18. AI cannot automatically verify a skill or persist unconfirmed CV data.
