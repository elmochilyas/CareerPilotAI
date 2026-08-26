# CareerPilot AI — Plan d'implémentation de la base de données

## Principes directeurs

- Partir des flux métier, pas des exigences hypothétiques futures.
- Implémenter uniquement les tables nécessaires au changement OpenSpec actif.
- Ne pas créer toutes les migrations en un seul changement.
- Éviter le versionnage tant que les exigences produit ne demandent pas explicitement des versions historiques immuables.
- Utiliser JSON pour les structures générées par l'IA qui sont lues et écrites comme une seule unité.
- Normaliser le JSON uniquement lorsqu'un filtrage SQL complexe ou un cycle de vie indépendant devient nécessaire.
- Utiliser des entités de stockage dédiées par usage (Option B) au lieu d'une table `files` générique — `cv_documents` pour les imports CV, métadonnées d'artefact sur l'agrégat propriétaire pour les exports CV/candidature (voir `docs/database/README.md` D10). La table `files` générique est dépréciée.
- Utiliser une analyse de correspondance par profil et opportunité, avec snapshots versionnés immuables et une seule analyse active à la fois.
- Utiliser un CV ciblé courant par opportunité.
- Utiliser `application_activities` comme fil d'Ariane de la candidature.
- Utiliser `tasks` pour les tâches, rappels, entretiens et suivis.
- Traiter `ai_runs` comme infrastructure technique optionnelle.
- Ne pas implémenter les sous-systèmes de notifications ni de simulations d'entretien dans le MVP initial.

## Phases d'implémentation

### Phase 0 — Authentification existante
- `users`
- Authentification Sanctum/Session existante
- Pas de refonte de l'authentification

**Changement OpenSpec associé :** `candidate-authentication`

---

### Phase 1 — Profil candidat principal
- `candidate_profiles`
- `profile_items`
- `skills`
- `candidate_skills`
- `cv_documents` pour les importations CV (remplace `files` générique — voir D10)
- Extraction CV dans `extracted_data`
- Révision par le candidat avant mise à jour du profil

**Changements OpenSpec associés :** `candidate-profile-core`, `skills-and-evidence`, `cv-ingestion-pipeline`

---

### Phase 2 — Offres et matching
- `companies`
- `opportunities`
- `match_analyses`
- `match_scores`
- `match_findings`
- `clarification_questions`
- `clarification_answers`
- `clarification_proposals`
- `clarification_audit_events`
- Empreintes du profil et de l'offre (snapshots)
- Une seule analyse active (queued/processing) par profil et opportunité
- Une question par résultat incertain à fort impact, une seule réponse par question, acceptation explicite des propositions

**Changements OpenSpec associés :** `job-opportunity-ingestion`, `profile-job-matching`, `clarification-workflow` (l'analyse d'offre `job_requirements`/`job_opportunity_skills` appartient à un changement ultérieur)

---

### Phase 3 — CV personnalisés et candidatures
- `resumes` (version_no + fingerprints, unique composite par (profil, opportunité, version) depuis `2026_08_26_...`)
- Fichier CV généré via artefacts dédiés sur l'agrégat propriétaire (pas `files` générique — `resumes.file_id` sera supprimé en phase Documents)
- `applications`
- `application_activities`
- Une candidature par candidat et opportunité

**Changements OpenSpec associés :** `resume-tailoring-versioning`, `resume-pdf-docx-export`, `application-tracking`

---

### Phase 4 — Actions et apprentissage
- `tasks`
- `learning_roadmaps`
- `roadmap_items`
- Rappels via `remind_at` et `reminder_sent_at`
- Entretiens: Option C recommandée (tâche/rappel + table `interviews` dédiée — voir `docs/database/README.md` D11), pas `task.type` générique seul

**Changements OpenSpec associés :** `learning-roadmap`, `tasks-reminders-interviews`

---

### Phase 5 — Infrastructure optionnelle
- `ai_runs` — uniquement lorsque l'observabilité IA, le suivi des coûts, les tentatives ou le débogage sont implémentés

**Changement OpenSpec associé :** `notifications-quotas-ai-audit`

---

## Ordre des migrations (dépendances)

1. `users`
2. `candidate_profiles`
3. `cv_documents` (remplace `files` — `files` dépréciée, voir D10)
4. `profile_items`
5. `skills`
6. `candidate_skills`
7. `companies`
8. `opportunities`
9. `match_analyses`
10. `match_scores`
11. `match_findings`
12. `clarification_questions`
13. `clarification_answers`
14. `clarification_proposals`
15. `clarification_audit_events`
16. `resumes`
17. `applications`
18. `application_activities`
19. `tasks`
20. `learning_roadmaps`
21. `roadmap_items`
22. `ai_runs` (optionnel, phase 5)

Cet ordre reflète les dépendances de clés étrangères. Chaque table ne doit être créée que dans le changement OpenSpec qui la possède.

---

## Exclusions explicites pour le MVP initial

- Pas d'historique des versions d'offre
- Pas de table `resume_versions`
- Pas de table `resume_exports`
- Pas de table `company_research` séparée
- Pas de table `opportunity_analyses` fusionnée (remplacée par `match_analyses`, `match_scores`, `match_findings`)
- Clarifications : tables `clarification_questions`, `clarification_answers`, `clarification_proposals`, `clarification_audit_events` (changement clarification-workflow)
- Pas de sous-système d'entretien séparé
- Pas de simulations d'entretien (mock interviews)
- Pas de kits de préparation (preparation packs)
- Pas de centre de notifications
- Pas de préférences de notifications
- Pas de microservices
- Pas d'event sourcing
- Pas de schéma EAV générique
- Pas de tables d'audit prématurées pour chaque entité

---

## Politique de migration

- Chaque modification de base de données doit appartenir à un changement OpenSpec.
- Les migrations doivent être réversibles (methode `down` implémentée).
- Le comportement de suppression des clés étrangères doit être explicite.
- Utiliser CASCADE uniquement pour les données qui n'ont pas de sens en dehors de leur parent (ex. `profile_items` sans `candidate_profiles`).
- Utiliser RESTRICT lorsque la suppression d'un parent pourrait détruire un historique important (ex. `applications` sans `opportunities`).
- Utiliser SET NULL pour les références optionnelles telles que `company_id`, `resume_id`, `file_id`, `application_id` et `skill_id` lorsque approprié.
- N'ajouter que les index justifiés par des schémas de requête réels.
- Ne pas créer d'index spéculatifs.

---

## Liste de validation

- [ ] Chaque entité du MCD correspond à exactement une table principale du MLD.
- [ ] Chaque association many-to-many du MCD correspond à une table relationnelle.
- [ ] Chaque FK référence une table existante avec un type compatible.
- [ ] Le MCD ne contient pas de types SQL ni d'attributs FK.
- [ ] Le MLD ne contient pas de cardinalités Merise ni de verbes d'association.
- [ ] Aucune table supprimée ne subsiste dans les phases d'implémentation.
- [ ] Aucune responsabilité n'est dupliquée entre les tables.
- [ ] Les champs JSON sont documentés.
- [ ] Les contraintes métier uniques sont documentées.
- [ ] Les fonctionnalités reportées sont clairement marquées comme telles.
- [ ] La documentation est cohérente avec les changements OpenSpec.
