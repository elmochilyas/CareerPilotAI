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
| `proficiency_level` | VARCHAR(30) | NO | |
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

## `opportunity_analyses`

| Colonne | Type | NULL | Clé / défaut |
|---|---|---|---:|
| `id` | BIGINT UNSIGNED | NO | PK |
| `opportunity_id` | BIGINT UNSIGNED | NO | FK, UNIQUE |
| `status` | VARCHAR(30) | NO | |
| `summary` | TEXT | YES | |
| `match_score` | DECIMAL(5,2) | YES | |
| `confidence` | DECIMAL(5,2) | YES | |
| `requirements` | JSON | YES | |
| `findings` | JSON | YES | |
| `clarification` | JSON | YES | |
| `profile_snapshot` | JSON | YES | |
| `job_snapshot` | JSON | YES | |
| `scoring_version` | VARCHAR(30) | YES | |
| `analyzed_at` | DATETIME | YES | |
| `created_at` | TIMESTAMP | NO | |
| `updated_at` | TIMESTAMP | NO | |

**Clés étrangères :**
- `opportunity_id` → `opportunities.id`

**Contraintes et index :**
- UNIQUE(opportunity_id)
- CHECK(match_score IS NULL OR match_score BETWEEN 0 AND 100)
- CHECK(confidence IS NULL OR confidence BETWEEN 0 AND 100)

**Remarque :** Cette table regroupe les concepts d'analyse d'offre, d'exigences, de correspondance, de résultats et de clarifications. Les champs JSON permettent de stocker des structures complexes lues et écrites comme une seule unité. La normalisation en tables séparées interviendra uniquement si un filtrage SQL complexe ou un cycle de vie indépendant devient nécessaire.

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
| `job_analyses` | Colonnes JSON dans `opportunity_analyses` |
| `job_requirements` | Colonne JSON `requirements` dans `opportunity_analyses` |
| `match_analyses` | Colonnes dans `opportunity_analyses` |
| `match_findings` | Colonne JSON `findings` dans `opportunity_analyses` |
| `clarifications` | Colonne JSON `clarification` dans `opportunity_analyses` |
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
3. Une analyse courante par opportunité.
4. Un CV ciblé par opportunité non nulle.
5. Une candidature par profil candidat et opportunité.
6. Les activités de candidature sont immuables.
7. Au plus un roadmap actif par candidat.
8. L'IA ne peut pas vérifier une compétence automatiquement.
