# ADR-001 — Clarification workflow: trust boundary, deterministic-template-first generation, and additive API

- **Date :** 2026-08-07
- **Statut :** Accepted
- **Changement OpenSpec associé :** `clarification-workflow`
- **Décideurs :** ILYAS EL MOCH (owner), équipe CareerPilot AI

## Contexte

Le scoring (MATCH-001) est déterministe et la correspondance doit rester explicable : les résultats incertains (`partial`/`gap` avec facteur 0.00/0.50) ne doivent ni être inventés, ni modifiés silencieusement (MATCH-004), ni acceptés comme vérité par défaut (MATCH-005). Le candidat doit pouvoir lever ces ambiguïtés, mais chaque affirmation non prouvée est un risque d'hallucination dans le profil et le CV.

Les contraintes produit pertinentes :

- MATCH-004 — les incertitudes à fort impact créent des questions de clarification ciblées, jamais des changements silencieux.
- MATCH-005 — une réponse qui modifie le profil exige une preuve ou une reconnaissance explicite d'absence de preuve.
- MATCH-006 — chaque recalcul de score référence des versions de source exactes.
- Truthfulness (AGENTS.md §6) — l'IA ne peut jamais inventer de compétences, projets, expériences ou résultats ; les compétences en apprentissage ne sont jamais présentées comme une expérience professionnelle.
- Explainability (AGENTS.md §6) — les scores de correspondance sont des calculs Laravel déterministes ; la sortie du LLM ne détermine jamais le score final.

## Décision

### 1. Les questions sont générées en mode déterministe d'abord (template-first)

La génération des questions est pilotée par des templates versionnés et déterministes (`template_key`) : un filtre d'éligibilité (résultat `partial`/`gap`, facteur 0.00/0.50, exigence requise/préférée, pas de question déjà répondue pour le même résultat, pas de réponse de confiance déjà présente), une résolution de template par `(type de résultat, importance)`, un ordre par impact et un plafond de trois questions par passe. L'assistant IA, lorsqu'il est activé, ne fait que **classer et reformuler** les questions déjà éligibles sous validation stricte de schéma ; il n'ajoute, ne retire ni ne modifie l'éligibilité, n'écrit jamais de question en base, et ne voit jamais le score final. En cas d'échec du fournisseur ou de sortie invalide, l'ensemble déterministe est utilisé tel quel et la raison de repli est enregistrée dans `ai_metadata.fallback_reason`.

### 2. Frontière de confiance : mutation uniquement après acceptation explicite

Une réponse candidat est une **saisie avec statut explicite** (`pending`), jamais une donnée de confiance par défaut. La mutation des données de confiance du profil n'arrive qu'après l'**acceptation explicite d'une proposition** concrète (entité cible, champ, avant → après, provenance). La réponse et la proposition sont séparées en deux tables distinctes. Une réponse `yes` sans preuve est refusée à moins que le candidat ne reconnaisse explicitement l'absence de preuve (`acknowledged_no_evidence`), et dans ce cas elle produit `claimed` (0.50, affiché séparément), jamais `verified`. Les propositions acceptées sont immuables et auditées.

### 3. Obsolescence par empreinte, jamais mutation directe du score

L'application d'une proposition acceptée mute uniquement les données de confiance (compétence/profil) et les statuts réponse/proposition dans une transaction ; le marquage `stale` de l'analyse est effectué **après commit** par un job en file qui réutilise le service d'empreintes/staleness du domaine Matching. Le score n'est jamais recalculé dans ce chemin et aucune logique de scoring n'est embarquée dans le flux de clarification.

### 4. API additive et scopée par propriétaire

La surface API est additive (route réservée honorée + `review` et `skip`) :

```text
GET  /api/v1/matches/{id}/clarifications
POST /api/v1/clarifications/{id}/answer
POST /api/v1/clarifications/{id}/review
POST /api/v1/clarifications/{id}/skip
```

Toutes les routes sont derrière `auth:sanctum` + CSRF, scopées par `auth()->user()` avec politiques et 404 cross-user (BOLA). Erreurs au format RFC 9457 (codes `clarification_question_not_found`, `clarification_session_expired`, `answer_already_exists`, `proposal_not_reviewable`, `invalid_evidence_url`, `analysis_not_found`) et limite de débit sur les écritures. L'URL de preuve est stockée comme provenance mais jamais récupérée dans ce changement.

## Alternatives considérées

- **Génération de questions par LLM libre** — rejetée : viole l'explicabilité et risque d'exigences inventées.
- **Aucune IA** — acceptable comme repli et défaut MVP ; la colonne `ai_metadata` est réservée pour activer l'assistant derrière la porte d'évaluation (taux de clarification utile >= 80 %).
- **Propositions en JSON sur la réponse** — rejetée : une table séparée est nécessaire pour l'immutabilité et l'audit.
- **Étendre le domaine `Matching` ou `Skills`** — rejeté : un domaine `Clarification` dédié garde la séparation « sortie du matching » / « orchestration de clarification ».
- **Page dédiée `/clarifications/:id`** — rejetée pour le MVP : le point d'entrée est le brief de correspondance ; extraction possible plus tard sans changement d'API.

## Conséquences

### Positives

- La correspondance reste déterministe et explicable ; la sortie LLM ne détermine jamais le score final.
- Aucune donnée de confiance n'est modifiée sans acceptation explicite ; les preuves et reconnaissances d'absence de preuve sont traçables.
- La table de propositions séparée permet l'immutabilité et l'audit des mutations acceptées.
- La staleness par empreinte réutilise la source unique de vérité « quand une correspondance est-elle obsolète ».
- L'API additive est sans rupture et honore la forme de route réservée.

### Négatives

- Coût d'un domaine et de quatre tables supplémentaires pour le MVP.
- Une réponse `yes` sans preuve ni reconnaissance est refusée, ce qui peut exiger une étape d'explication pour le candidat.
- L'expiration de session en cours de flux exige un état `expired` et une relance explicite.

### Risques et atténuations

- **Violation de la frontière de confiance** → seule l'acceptation d'une proposition est un chemin d'écriture ; politiques et tests d'architecture assercent qu'aucun autre code ne promeut des compétences.
- **Dérive ou hallucination du LLM** → templates déterministes comme source ; l'assistant classe/reformule sous schéma strict ; repli déterministe avec `fallback_reason`.
- **Obsolescence non marquée après mutation** → marquage après commit via job ; le brief distingue déjà stale vs à jour.
- **Trop de questions / doublons** → unique `(question_id)` + dédoublonnage par résultat + plafond de 3 ; mesure au niveau template (porte < 5 %).
- **URL de preuve injoignable** → jamais récupérée dans ce changement ; l'acceptation n'est pas bloquée par la joignabilité.

## Références

- `openspec/changes/clarification-workflow/design.md` (décisions D1–D9)
- `openspec/changes/clarification-workflow/specs/clarification-workflow/spec.md` (CLAR-001…CLAR-005)
- `openspec/changes/clarification-workflow/specs/match-engine/spec.md` (candidats et staleness)
- `openspec/changes/clarification-workflow/specs/match-api/spec.md` (routes)
- `openspec/changes/clarification-workflow/specs/skill-evidence/spec.md` (CLAR-EVIDENCE-001)
- `docs/database/MLD.md`, `docs/database/MCD.md` (schéma des tables de clarification)
