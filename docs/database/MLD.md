# CareerPilot AI — MLD (Modèle Logique de Données)

## Objectif

Ce document est le **Modèle Logique de Données** officiel pour la version MVP de CareerPilot AI.

Il définit les tables, colonnes, types SQL, clés, contraintes et règles de suppression. Les migrations Laravel doivent implémenter ce modèle de manière incrémentale à travers les changements OpenSpec approuvés.

## Conventions globales

- Base de données : MySQL, InnoDB, `utf8mb4`.
- Clés primaires métier : `BIGINT UNSIGNED AUTO_INCREMENT`.
- Clés étrangères : `BIGINT UNSIGNED`.
- Timestamps : Laravel `created_at` et `updated_at`.
- États métier : enums PHP stockés en chaînes de caractères.
- Montants : `DECIMAL`, jamais de type flottant.
- JSON uniquement pour les structures ne nécessitant pas de filtrage SQL complexe, de permissions indépendantes, de cycle de vie propre ou de relations.

---

## `users`

| Colonne | Type | NULL | Clé / défaut |
|---|---|---|---:|
| `id` | BIGINT UNSIGNED | NO | PK |
| `full_name` | VARCHAR(150) | NO | |
| `email` | VARCHAR(255) | NO | UNIQUE |
| `password` | VARCHAR(255) | NO | |
| `email_verified_at` | TIMESTAMP | YES | |
| `role` | VARCHAR(30) | NO | |
| `account_status` | VARCHAR(30) | NO | |
| `timezone` | VARCHAR(64) | YES | |
| `remember_token` | VARCHAR(100) | YES | |
| `created_at` | TIMESTAMP | NO | |
| `updated_at` | TIMESTAMP | NO | |
| `deleted_at` | TIMESTAMP | YES | |

**Contraintes et index :**
- UNIQUE(email)

**Valeurs approuvées :**
- role : `candidate`, `admin`

---

## `candidate_profiles`

| Colonne | Type | NULL | Clé / défaut |
|---|---|---|---:|
| `id` | BIGINT UNSIGNED | NO | PK |
| `user_id` | BIGINT UNSIGNED | NO | FK, UNIQUE |
| `headline` | VARCHAR(255) | YES | |
| `professional_summary` | TEXT | YES | |
| `phone` | VARCHAR(30) | YES | |
| `city` | VARCHAR(100) | YES | |
| `country` | VARCHAR(100) | YES | |
| `linkedin_url` | VARCHAR(500) | YES | |
| `github_url` | VARCHAR(500) | YES | |
| `portfolio_url` | VARCHAR(500) | YES | |
| `availability_status` | VARCHAR(30) | YES | |
| `target_roles` | JSON | YES | |
| `preferred_locations` | JSON | YES | |
| `work_mode` | VARCHAR(30) | YES | |
| `contract_types` | JSON | YES | |
| `salary_min` | DECIMAL(12,2) | YES | |
| `salary_max` | DECIMAL(12,2) | YES | |
| `languages` | JSON | YES | |
| `profile_completion` | DECIMAL(5,2) | NO | |
| `created_at` | TIMESTAMP | NO | |
| `updated_at` | TIMESTAMP | NO | |

**Clés étrangères :**
- `user_id` → `users.id`

**Contraintes et index :**
- UNIQUE(user_id)
- CHECK(profile_completion BETWEEN 0 AND 100)
- CHECK(salary_max IS NULL OR salary_min IS NULL OR salary_max >= salary_min)

---

## `profile_items`

| Colonne | Type | NULL | Clé / défaut |
|---|---|---|---:|
| `id` | BIGINT UNSIGNED | NO | PK |
| `candidate_profile_id` | BIGINT UNSIGNED | NO | FK |
| `type` | VARCHAR(30) | NO | |
| `title` | VARCHAR(255) | NO | |
| `organization` | VARCHAR(255) | YES | |
| `location` | VARCHAR(255) | YES | |
| `start_date` | DATE | YES | |
| `end_date` | DATE | YES | |
| `description` | TEXT | YES | |
| `metadata` | JSON | YES | |
| `display_order` | SMALLINT UNSIGNED | NO | |
| `created_at` | TIMESTAMP | NO | |
| `updated_at` | TIMESTAMP | NO | |

**Clés étrangères :**
- `candidate_profile_id` → `candidate_profiles.id`

**Contraintes et index :**
- INDEX(candidate_profile_id, display_order)
- CHECK(type IN ('education', 'experience', 'project', 'certification'))
- CHECK(end_date IS NULL OR start_date IS NULL OR end_date >= start_date)

---

## `skills`

| Colonne | Type | NULL | Clé / défaut |
|---|---|---|---:|
| `id` | BIGINT UNSIGNED | NO | PK |
| `name` | VARCHAR(150) | NO | |
| `normalized_name` | VARCHAR(150) | NO | UNIQUE |
| `category` | VARCHAR(100) | YES | |
| `is_active` | BOOLEAN | NO | |
| `created_at` | TIMESTAMP | NO | |
| `updated_at` | TIMESTAMP | NO | |

**Contraintes et index :**
- UNIQUE(normalized_name)

---

## `candidate_skills`

| Colonne | Type | NULL | Clé / défaut |
|---|---|---|---:|
| `id` | BIGINT UNSIGNED | NO | PK |
| `candidate_profile_id` | BIGINT UNSIGNED | NO | FK |
| `skill_id` | BIGINT UNSIGNED | NO | FK |
| `proficiency_level` | VARCHAR(30) | YES | |
| `years_experience` | DECIMAL(4,1) | YES | |
| `last_used_at` | DATE | YES | |
| `evidence` | JSON | YES | |
| `created_at` | TIMESTAMP | NO | |
| `updated_at` | TIMESTAMP | NO | |

**Clés étrangères :**
- `candidate_profile_id` → `candidate_profiles.id`
- `skill_id` → `skills.id`

**Contraintes et index :**
- UNIQUE(candidate_profile_id, skill_id)
- CHECK(years_experience IS NULL OR years_experience >= 0)

---

## `files`

| Colonne | Type | NULL | Clé / défaut |
|---|---|---|---:|
| `id` | BIGINT UNSIGNED | NO | PK |
| `user_id` | BIGINT UNSIGNED | NO | FK |
| `original_name` | VARCHAR(255) | NO | |
| `stored_name` | VARCHAR(255) | NO | |
| `path` | VARCHAR(500) | NO | |
| `mime_type` | VARCHAR(100) | NO | |
| `size` | BIGINT UNSIGNED | NO | |
| `checksum` | CHAR(64) | YES | |
| `purpose` | VARCHAR(30) | NO | |
| `processing_status` | VARCHAR(30) | NO | |
| `extracted_data` | JSON | YES | |
| `created_at` | TIMESTAMP | NO | |
| `updated_at` | TIMESTAMP | NO | |

**Clés étrangères :**
- `user_id` → `users.id`

**Exemples de values pour `purpose` :**
`cv_import`, `resume_generated`, `application_document`, `certificate`, `evidence`, `other`

**Exemples de values pour `processing_status` :**
`pending`, `processing`, `completed`, `failed`

---

## `companies`

| Colonne | Type | NULL | Clé / défaut |
|---|---|---|---:|
| `id` | BIGINT UNSIGNED | NO | PK |
| `name` | VARCHAR(255) | NO | |
| `website` | VARCHAR(500) | YES | |
| `industry` | VARCHAR(100) | YES | |
| `location` | VARCHAR(255) | YES | |
| `size_band` | VARCHAR(50) | YES | |
| `research` | JSON | YES | |
| `researched_at` | DATETIME | YES | |
| `created_at` | TIMESTAMP | NO | |
| `updated_at` | TIMESTAMP | NO | |

---

## `opportunities`

| Colonne | Type | NULL | Clé / défaut |
|---|---|---|---:|
| `id` | BIGINT UNSIGNED | NO | PK |
| `candidate_profile_id` | BIGINT UNSIGNED | NO | FK |
| `company_id` | BIGINT UNSIGNED | YES | FK |
| `title` | VARCHAR(255) | NO | |
| `source_type` | VARCHAR(30) | NO | |
| `source_url` | VARCHAR(500) | YES | |
| `description` | LONGTEXT | YES | |
| `location` | VARCHAR(255) | YES | |
| `work_mode` | VARCHAR(30) | YES | |
| `contract_type` | VARCHAR(30) | YES | |
| `seniority_level` | VARCHAR(30) | YES | |
| `salary_min` | DECIMAL(12,2) | YES | |
| `salary_max` | DECIMAL(12,2) | YES | |
| `status` | VARCHAR(30) | NO | |
| `saved_at` | DATETIME | NO | |
| `created_at` | TIMESTAMP | NO | |
| `updated_at` | TIMESTAMP | NO | |

**Clés étrangères :**
- `candidate_profile_id` → `candidate_profiles.id`
- `company_id` → `companies.id`

**Contraintes et index :**
- INDEX(candidate_profile_id, status)
- CHECK(salary_max IS NULL OR salary_min IS NULL OR salary_max >= salary_min)

---

## `opportunity_analyses` (remplacé)

L'ancienne table fusionnée `opportunity_analyses` regroupait l'analyse d'offre, les exigences, la correspondance, les résultats et les clarifications dans des colonnes JSON. Elle est **remplacée** par le changement OpenSpec `profile-job-matching` :

- `match_analyses` — snapshots versionnés d'analyse de correspondance (cycle de vie propre : queued → processing → completed/failed).
- `match_scores` — scores déterministes par catégorie, normalisés.
- `match_findings` — résultats par exigence, normalisés.

L'analyse d'offre (`job_analyses`) et les exigences (`job_requirements`, `job_opportunity_skills`) restent des tables séparées appartenant à leurs changements respectifs. Les tables de clarification (`clarification_questions`, `clarification_answers`, `clarification_proposals`, `clarification_audit_events`) sont définies par le changement `clarification-workflow`.

## `match_analyses`

| Colonne | Type | NULL | Clé / défaut |
|---|---|---|---:|
| `id` | BIGINT UNSIGNED | NO | PK |
| `candidate_profile_id` | BIGINT UNSIGNED | NO | FK |
| `job_opportunity_id` | BIGINT UNSIGNED | NO | FK |
| `status` | VARCHAR(30) | NO | |
| `operation_key` | CHAR(64) | NO | |
| `overall_score` | SMALLINT UNSIGNED | YES | |
| `evidence_coverage_score` | SMALLINT UNSIGNED | YES | |
| `required_count` | SMALLINT UNSIGNED | YES | |
| `preferred_count` | SMALLINT UNSIGNED | YES | |
| `matched_count` | SMALLINT UNSIGNED | YES | |
| `partial_count` | SMALLINT UNSIGNED | YES | |
| `gap_count` | SMALLINT UNSIGNED | YES | |
| `unknown_count` | SMALLINT UNSIGNED | YES | |
| `profile_fingerprint` | CHAR(64) | NO | |
| `opportunity_fingerprint` | CHAR(64) | NO | |
| `profile_updated_at` | DATETIME | YES | |
| `opportunity_updated_at` | DATETIME | YES | |
| `algorithm_version` | VARCHAR(30) | NO | |
| `scoring_version` | VARCHAR(30) | NO | |
| `classifier_schema_version` | VARCHAR(30) | NO | |
| `failure_code` | VARCHAR(100) | YES | |
| `failure_reason` | TEXT | YES | |
| `request_id` | VARCHAR(64) | YES | |
| `classifier_provider` | VARCHAR(50) | YES | |
| `classifier_model` | VARCHAR(100) | YES | |
| `classifier_prompt_version` | VARCHAR(30) | YES | |
| `classifier_latency_ms` | SMALLINT UNSIGNED | YES | |
| `classifier_tokens_prompt` | INT UNSIGNED | YES | |
| `classifier_tokens_completion` | INT UNSIGNED | YES | |
| `classifier_response_id` | VARCHAR(100) | YES | |
| `classifier_status` | VARCHAR(20) | YES | |
| `queued_at` | DATETIME | YES | |
| `processing_started_at` | DATETIME | YES | |
| `completed_at` | DATETIME | YES | |
| `failed_at` | DATETIME | YES | |
| `created_at` | TIMESTAMP | NO | |
| `updated_at` | TIMESTAMP | NO | |

**Clés étrangères :**
- `candidate_profile_id` → `candidate_profiles.id` (CASCADE)
- `job_opportunity_id` → `job_opportunities.id` (CASCADE)

**Contraintes et index :**
- UNIQUE(candidate_profile_id, operation_key)
- INDEX(job_opportunity_id, status, created_at)
- CHECK(status IN ('queued', 'processing', 'completed', 'failed'))
- CHECK(overall_score IS NULL OR overall_score BETWEEN 0 AND 100)
- CHECK(evidence_coverage_score IS NULL OR evidence_coverage_score BETWEEN 0 AND 100)

**Remarque :** Un profil et une offre peuvent avoir plusieurs analyses (snapshots successifs, la plus récente étant marquée `latest` côté API). Une seule analyse active (`queued`/`processing`) est autorisée à la fois par profil et offre. `operation_key` (SHA-256 du profil, de l'offre et de la clé client) garantit l'idempotence des créations. Une analyse terminée est immuable ; le recalcul crée un nouveau snapshot.

---

## `match_scores`

| Colonne | Type | NULL | Clé / défaut |
|---|---|---|---:|
| `id` | BIGINT UNSIGNED | NO | PK |
| `match_analysis_id` | BIGINT UNSIGNED | NO | FK |
| `category` | VARCHAR(30) | NO | |
| `weight` | DECIMAL(4,3) | NO | |
| `score` | SMALLINT UNSIGNED | NO | |
| `achieved_points` | DECIMAL(8,2) | NO | |
| `total_points` | DECIMAL(8,2) | NO | |
| `has_candidate_data` | BOOLEAN | NO | |
| `created_at` | TIMESTAMP | NO | |
| `updated_at` | TIMESTAMP | NO | |

**Clés étrangères :**
- `match_analysis_id` → `match_analyses.id` (CASCADE)

**Contraintes et index :**
- UNIQUE(match_analysis_id, category)
- CHECK(category IN ('required_skills', 'preferred_skills', 'evidence', 'experience_education', 'language_soft'))
- CHECK(score BETWEEN 0 AND 100)
- CHECK(weight BETWEEN 0 AND 1)

**Remarque :** Poids approuvés : required_skills 0.500, preferred_skills 0.200, evidence 0.150, experience_education 0.100, language_soft 0.050. `has_candidate_data = false` signale une catégorie sans données candidat, exclue de la contribution au score global.

---

## `match_findings`

| Colonne | Type | NULL | Clé / défaut |
|---|---|---|---:|
| `id` | BIGINT UNSIGNED | NO | PK |
| `match_analysis_id` | BIGINT UNSIGNED | NO | FK |
| `source_type` | VARCHAR(30) | NO | |
| `source_id` | BIGINT UNSIGNED | NO | |
| `requirement_text` | VARCHAR(500) | NO | |
| `requirement_label` | VARCHAR(255) | YES | |
| `importance` | VARCHAR(20) | NO | |
| `category` | VARCHAR(30) | YES | |
| `match_state` | VARCHAR(20) | NO | |
| `factor` | DECIMAL(4,2) | NO | |
| `matched_candidate_skill_id` | BIGINT UNSIGNED | YES | FK |
| `evidence_refs` | JSON | YES | |
| `justification` | TEXT | YES | |
| `confidence` | VARCHAR(20) | YES | |
| `classifier_source` | VARCHAR(255) | YES | |
| `display_order` | SMALLINT UNSIGNED | NO | |
| `created_at` | TIMESTAMP | NO | |
| `updated_at` | TIMESTAMP | NO | |

**Clés étrangères :**
- `match_analysis_id` → `match_analyses.id` (CASCADE)
- `matched_candidate_skill_id` → `candidate_skills.id` (NULL ON DELETE)

**Contraintes et index :**
- INDEX(match_analysis_id, importance)
- INDEX(match_analysis_id, match_state)
- CHECK(source_type IN ('job_requirement', 'job_opportunity_skill'))
- CHECK(importance IN ('required', 'preferred'))
- CHECK(match_state IN ('matched', 'partial', 'gap', 'unknown'))
- CHECK(factor IN (0, 0.2, 0.5, 1))

**Remarque :** Facteurs approuvés : verified 1.00, claimed 0.50, learning 0.20, missing 0.00. L'état `unknown` est exclu des points et ne constitue pas une lacune. `evidence_refs` (JSON) référence les preuves candidat (`profile_items`, `candidate_skills`, `resumes`, `files`).

---

## `clarification_questions`

| Colonne | Type | NULL | Clé / défaut |
|---|---|---:|---:|
| `id` | BIGINT UNSIGNED | NO | PK |
| `match_analysis_id` | BIGINT UNSIGNED | NO | FK |
| `match_finding_id` | BIGINT UNSIGNED | YES | FK |
| `question_no` | SMALLINT UNSIGNED | NO | |
| `question_type` | VARCHAR(30) | NO | |
| `prompt` | VARCHAR(500) | NO | |
| `detail` | VARCHAR(1000) | YES | |
| `template_key` | VARCHAR(100) | NO | |
| `options_json` | JSON | YES | |
| `unit` | VARCHAR(30) | YES | |
| `status` | VARCHAR(20) | NO | |
| `ai_metadata` | JSON | YES | |
| `created_at` | TIMESTAMP | NO | |
| `updated_at` | TIMESTAMP | NO | |

**Clés étrangères :**
- `match_analysis_id` → `match_analyses.id` (CASCADE)
- `match_finding_id` → `match_findings.id` (CASCADE)

**Contraintes et index :**
- INDEX(match_analysis_id, status)
- INDEX(match_analysis_id, match_finding_id)
- CHECK(question_type IN ('yes_no', 'yes_no_with_details', 'text', 'select', 'number'))
- CHECK(status IN ('pending', 'answered', 'skipped', 'expired'))

**Remarque :** Une question par résultat incertain à fort impact (`partial`/`gap` avec facteur 0.00/0.50, sans réponse de confiance déjà présente), composée depuis un template déterministe versionné (`template_key`) ; au plus trois questions par passe et une seule réponse par question. `status` défaut `pending`. `ai_metadata` (JSON) enregistre la provenance du classement/reformulation de l'assistant ou la raison de repli déterministe.

---

## `clarification_answers`

| Colonne | Type | NULL | Clé / défaut |
|---|---|---:|---:|
| `id` | BIGINT UNSIGNED | NO | PK |
| `user_id` | BIGINT UNSIGNED | NO | FK |
| `question_id` | BIGINT UNSIGNED | NO | FK, UNIQUE |
| `answer_type` | VARCHAR(30) | NO | |
| `value` | TEXT | NO | |
| `acknowledged_no_evidence` | BOOLEAN | NO | |
| `status` | VARCHAR(20) | NO | |
| `proposal_id` | BIGINT UNSIGNED | YES | FK |
| `created_at` | TIMESTAMP | NO | |
| `updated_at` | TIMESTAMP | NO | |

**Clés étrangères :**
- `user_id` → `users.id` (CASCADE)
- `question_id` → `clarification_questions.id` (CASCADE)
- `proposal_id` → `clarification_proposals.id` (NULL ON DELETE)

**Contraintes et index :**
- UNIQUE(question_id)
- INDEX(user_id)
- INDEX(user_id, status)
- INDEX(proposal_id)
- CHECK(answer_type IN ('yes', 'no', 'no_with_ack', 'text', 'select_option', 'number'))
- CHECK(status IN ('pending', 'accepted', 'rejected', 'skipped', 'expired'))

**Remarque :** Une réponse est une saisie candidat avec statut explicite, jamais une donnée de confiance par défaut. `acknowledged_no_evidence` (défaut `false`) est la reconnaissance explicite d'absence de preuve : avec `yes` elle produit `claimed` (0.50), jamais `verified`. `proposal_id` relie la réponse à sa proposition (0..1) après revue.

---

## `clarification_proposals`

| Colonne | Type | NULL | Clé / défaut |
|---|---|---:|---:|
| `id` | BIGINT UNSIGNED | NO | PK |
| `answer_id` | BIGINT UNSIGNED | NO | FK, UNIQUE |
| `target_type` | VARCHAR(40) | NO | |
| `target_id` | BIGINT UNSIGNED | YES | |
| `field` | VARCHAR(60) | NO | |
| `before_value` | JSON | YES | |
| `after_value` | JSON | YES | |
| `status` | VARCHAR(20) | NO | |
| `created_at` | TIMESTAMP | NO | |
| `updated_at` | TIMESTAMP | NO | |

**Clés étrangères :**
- `answer_id` → `clarification_answers.id` (CASCADE)

**Contraintes et index :**
- UNIQUE(answer_id)
- INDEX(target_type, target_id)
- INDEX(status)
- CHECK(target_type IN ('candidate_skill', 'profile_item'))
- CHECK(status IN ('proposed', 'accepted', 'rejected', 'skipped'))

**Remarque :** Une proposition décrit une mutation concrète (entité cible, champ, avant → après) issue d'une réponse d'origine ; une proposition au plus par réponse. Une fois `accepted`, la proposition est immuable et auditable ; seule son acceptation explicite produit une mutation des données de confiance. `status` défaut `proposed`. `target_id` référence l'entité cible (polymorphe) selon `target_type`.

---

## `clarification_audit_events`

| Colonne | Type | NULL | Clé / défaut |
|---|---|---:|---:|
| `id` | BIGINT UNSIGNED | NO | PK |
| `answer_id` | BIGINT UNSIGNED | YES | FK |
| `proposal_id` | BIGINT UNSIGNED | YES | FK |
| `user_id` | BIGINT UNSIGNED | NO | FK |
| `match_analysis_id` | BIGINT UNSIGNED | YES | FK |
| `target_type` | VARCHAR(40) | YES | |
| `target_id` | BIGINT UNSIGNED | YES | |
| `field` | VARCHAR(60) | YES | |
| `before_value` | JSON | YES | |
| `after_value` | JSON | YES | |
| `metadata` | JSON | YES | |
| `created_at` | TIMESTAMP | NO | |
| `updated_at` | TIMESTAMP | NO | |

**Clés étrangères :**
- `answer_id` → `clarification_answers.id` (NULL ON DELETE)
- `proposal_id` → `clarification_proposals.id` (NULL ON DELETE)
- `user_id` → `users.id` (CASCADE)
- `match_analysis_id` → `match_analyses.id` (NULL ON DELETE)

**Contraintes et index :**
- INDEX(answer_id, proposal_id)
- INDEX(user_id, created_at)
- INDEX(match_analysis_id)
- CHECK(target_type IN ('candidate_skill', 'profile_item'))

**Remarque :** Écrit lors de l'acceptation d'une proposition (réponse d'origine, cible, avant/après, métadonnées) de manière best-effort et non bloquante ; les références sont conservées (NULL ON DELETE) pour préserver l'historique.

---

## `resumes`

| Colonne | Type | NULL | Clé / défaut |
|---|---|---|---:|
| `id` | BIGINT UNSIGNED | NO | PK |
| `candidate_profile_id` | BIGINT UNSIGNED | NO | FK |
| `opportunity_id` | BIGINT UNSIGNED | YES | FK, UNIQUE |
| `file_id` | BIGINT UNSIGNED | YES | FK |
| `title` | VARCHAR(255) | NO | |
| `template_key` | VARCHAR(100) | YES | |
| `content` | JSON | NO | |
| `status` | VARCHAR(30) | NO | |
| `generated_by` | VARCHAR(30) | NO | |
| `approved_at` | DATETIME | YES | |
| `created_at` | TIMESTAMP | NO | |
| `updated_at` | TIMESTAMP | NO | |

**Clés étrangères :**
- `candidate_profile_id` → `candidate_profiles.id`
- `opportunity_id` → `opportunities.id`
- `file_id` → `files.id`

**Contraintes et index :**
- UNIQUE(opportunity_id)
- INDEX(candidate_profile_id, status)

**Remarque :** Les versions de CV et les exports sont intégrés dans cette table via le champ JSON `content` (pour le contenu structuré) et la référence `file_id` (pour le fichier exporté).

---

## `applications`

| Colonne | Type | NULL | Clé / défaut |
|---|---|---|---:|
| `id` | BIGINT UNSIGNED | NO | PK |
| `candidate_profile_id` | BIGINT UNSIGNED | NO | FK |
| `opportunity_id` | BIGINT UNSIGNED | NO | FK |
| `resume_id` | BIGINT UNSIGNED | YES | FK |
| `current_status` | VARCHAR(30) | NO | |
| `applied_at` | DATETIME | YES | |
| `contact_name` | VARCHAR(255) | YES | |
| `contact_email` | VARCHAR(255) | YES | |
| `contact_phone` | VARCHAR(30) | YES | |
| `next_action_at` | DATETIME | YES | |
| `created_at` | TIMESTAMP | NO | |
| `updated_at` | TIMESTAMP | NO | |

**Clés étrangères :**
- `candidate_profile_id` → `candidate_profiles.id`
- `opportunity_id` → `opportunities.id`
- `resume_id` → `resumes.id`

**Contraintes et index :**
- UNIQUE(candidate_profile_id, opportunity_id)
- INDEX(candidate_profile_id, current_status)

---

## `application_activities`

| Colonne | Type | NULL | Clé / défaut |
|---|---|---|---:|
| `id` | BIGINT UNSIGNED | NO | PK |
| `application_id` | BIGINT UNSIGNED | NO | FK |
| `file_id` | BIGINT UNSIGNED | YES | FK |
| `type` | VARCHAR(30) | NO | |
| `old_status` | VARCHAR(30) | YES | |
| `new_status` | VARCHAR(30) | YES | |
| `content` | TEXT | YES | |
| `occurred_at` | DATETIME | NO | |
| `created_at` | TIMESTAMP | NO | |
| `updated_at` | TIMESTAMP | NO | |

**Clés étrangères :**
- `application_id` → `applications.id`
- `file_id` → `files.id`

**Contraintes et index :**
- INDEX(application_id, occurred_at)

**Exemples de values pour `type` :**
`status_change`, `note`, `document`, `email`, `interview`

---

## `tasks`

| Colonne | Type | NULL | Clé / défaut |
|---|---|---|---:|
| `id` | BIGINT UNSIGNED | NO | PK |
| `candidate_profile_id` | BIGINT UNSIGNED | NO | FK |
| `application_id` | BIGINT UNSIGNED | YES | FK |
| `type` | VARCHAR(30) | NO | |
| `title` | VARCHAR(255) | NO | |
| `description` | TEXT | YES | |
| `status` | VARCHAR(30) | NO | |
| `priority` | VARCHAR(20) | NO | |
| `scheduled_at` | DATETIME | YES | |
| `due_at` | DATETIME | YES | |
| `remind_at` | DATETIME | YES | |
| `reminder_sent_at` | DATETIME | YES | |
| `metadata` | JSON | YES | |
| `created_at` | TIMESTAMP | NO | |
| `updated_at` | TIMESTAMP | NO | |

**Clés étrangères :**
- `candidate_profile_id` → `candidate_profiles.id`
- `application_id` → `applications.id`

**Contraintes et index :**
- INDEX(candidate_profile_id, status)
- INDEX(application_id, status)

**Exemples de values pour `type` :**
`task`, `reminder`, `interview`, `follow_up`

**Exemples de values pour `status` :**
`pending`, `in_progress`, `completed`, `cancelled`

---

## `learning_roadmaps`

| Colonne | Type | NULL | Clé / défaut |
|---|---|---|---:|
| `id` | BIGINT UNSIGNED | NO | PK |
| `candidate_profile_id` | BIGINT UNSIGNED | NO | FK |
| `title` | VARCHAR(255) | NO | |
| `status` | VARCHAR(30) | NO | |
| `generated_at` | DATETIME | YES | |
| `created_at` | TIMESTAMP | NO | |
| `updated_at` | TIMESTAMP | NO | |

**Clés étrangères :**
- `candidate_profile_id` → `candidate_profiles.id`

---

## `roadmap_items`

| Colonne | Type | NULL | Clé / défaut |
|---|---|---|---:|
| `id` | BIGINT UNSIGNED | NO | PK |
| `learning_roadmap_id` | BIGINT UNSIGNED | NO | FK |
| `skill_id` | BIGINT UNSIGNED | YES | FK |
| `title` | VARCHAR(255) | NO | |
| `description` | TEXT | YES | |
| `priority` | VARCHAR(20) | NO | |
| `status` | VARCHAR(30) | NO | |
| `progress_percent` | DECIMAL(5,2) | NO | |
| `target_date` | DATE | YES | |
| `display_order` | SMALLINT UNSIGNED | NO | |
| `created_at` | TIMESTAMP | NO | |
| `updated_at` | TIMESTAMP | NO | |

**Clés étrangères :**
- `learning_roadmap_id` → `learning_roadmaps.id`
- `skill_id` → `skills.id`

**Contraintes et index :**
- CHECK(progress_percent BETWEEN 0 AND 100)

---

## `ai_runs`

> **⚠️ Table technique optionnelle.** Ne fait pas partie des entités métier principales du MCD. Ne sera créée que lorsque l'observabilité IA, le suivi des coûts, les tentatives ou le débogage seront implémentés.

| Colonne | Type | NULL | Clé / défaut |
|---|---|---|---:|
| `id` | BIGINT UNSIGNED | NO | PK |
| `user_id` | BIGINT UNSIGNED | NO | FK |
| `operation` | VARCHAR(100) | NO | |
| `provider` | VARCHAR(100) | NO | |
| `model` | VARCHAR(100) | NO | |
| `status` | VARCHAR(30) | NO | |
| `input_data` | JSON | YES | |
| `output_data` | JSON | YES | |
| `token_usage` | JSON | YES | |
| `error_message` | TEXT | YES | |
| `started_at` | DATETIME | NO | |
| `completed_at` | DATETIME | YES | |
| `created_at` | TIMESTAMP | NO | |
| `updated_at` | TIMESTAMP | NO | |

**Clés étrangères :**
- `user_id` → `users.id`

---

## Tables Laravel techniques

Ces tables sont des tables d'infrastructure, pas des entités métier du MCD :

- `migrations`
- `password_reset_tokens`
- `sessions`
- `jobs`
- `job_batches`
- `failed_jobs`
- `cache`
- `cache_locks`
- `personal_access_tokens` (installé par Sanctum)

Utiliser le schéma fourni par le framework pour la version Laravel installée, sauf si un changement OpenSpec approuvé en dispose autrement.

---

## Tables supprimées ou fusionnées

| Table précédente | Devenue |
|---|---|
| `candidate_preferences` | Colonnes JSON dans `candidate_profiles` |
| `candidate_languages` | Colonne JSON `languages` dans `candidate_profiles` |
| `educations` | `profile_items` avec type `education` |
| `experiences` | `profile_items` avec type `experience` |
| `projects` | `profile_items` avec type `project` |
| `certifications` | `profile_items` avec type `certification` |
| `skill_evidences` | Colonne JSON `evidence` dans `candidate_skills` |
| `cv_imports` | Fichier avec purpose `cv_import` + `extracted_data` dans `files` |
| `company_research` | Colonne JSON `research` dans `companies` |
| `job_analyses` | Table `job_analyses` (changement job-analysis) |
| `job_requirements` | Table `job_requirements` + `job_opportunity_skills` |
| `match_analyses` | Table `match_analyses` normalisée (snapshots versionnés) |
| `match_scores` | Table `match_scores` normalisée |
| `match_findings` | Table `match_findings` normalisée |
| `clarification_questions` | Table `clarification_questions` (changement clarification-workflow) |
| `clarification_answers` | Table `clarification_answers` (changement clarification-workflow) |
| `clarification_proposals` | Table `clarification_proposals` (changement clarification-workflow) |
| `clarification_audit_events` | Table `clarification_audit_events` (changement clarification-workflow) |
| `resume_versions` | Colonne JSON `content` dans `resumes` |
| `resume_exports` | Clé étrangère `file_id` dans `resumes` |
| `application_status_histories` | `application_activities` avec type `status_change` |
| `application_notes` | `application_activities` avec type `note` |
| `application_documents` | `application_activities` avec type `document` + `file_id` |
| `interviews` | `tasks` avec type `interview` + `metadata` JSON |
| `preparation_packs` | Reporté |
| `mock_interview_sessions` | Reporté |
| `mock_interview_turns` | Reporté |
| `notifications` | Reporté |
| `notification_preferences` | Reporté |

---

## Invariants transversaux

1. Un profil par utilisateur.
2. Une compétence par profil et compétence normalisée.
3. Une seule analyse de correspondance active (queued/processing) par profil et opportunité ; les snapshots terminés sont immuables.
4. Un CV ciblé par opportunité non nulle.
5. Une candidature par profil candidat et opportunité.
6. Les activités de candidature sont immuables.
7. Au plus un roadmap actif par candidat.
8. L'IA ne peut pas vérifier une compétence automatiquement.
