# Checklist executable - Scope policy RAG

Ce document est une checklist de recette executable (format QA) pour valider la logique:

- policy admin
- choix formateur
- scope resolu runtime
- coherence UI/API/BDD

---

## 1) Meta execution

- Date:
- Environnement: (ex: PREPROD)
- Testeur:
- Version plugin `local_astusse`:
- Version backend (gateway/orchestration):

---

## 2) Preconditions (a cocher)

- [ ] Plugin `local_astusse` installe et upgrade effectue.
- [ ] JWT OK (`/api/ping` renvoie 200 avec token Moodle).
- [ ] Orchestration en mode Postgres (`RAG_VECTOR_STORE_TYPE=postgres`).
- [ ] Compte admin disponible.
- [ ] Compte formateur disponible.
- [ ] `trainerId` connu pour le formateur cible.
- [ ] Si le test porte sur le chat Moodle, un formateur de reference valide est defini sur le cours.

---

## 3) Tableau de recette

Statut recommande:

- `TODO` (pas execute)
- `OK` (valide)
- `KO` (echec)
- `BLOQUE` (impossible a tester)

| ID | Scenario | Etapes (resume) | Resultat attendu | Statut | Preuve / Commentaire |
|---|---|---|---|---|---|
| SC-01 | Valeurs par defaut admin | Ouvrir settings `local_astusse` | `platform_scope_allowed=Non`, `delegation_enabled=Oui` | TODO | |
| SC-02 | Visibilite menu formateur | Ouvrir un cours avec role formateur puis etudiant | Lien `Perimetre IA ASTUSSE` visible formateur, absent etudiant | TODO | |
| SC-03 | Delegation desactivee | Admin met `delegation_enabled=Non`; formateur ouvre page scope | Formulaire non editable, info delegation desactivee | TODO | |
| SC-04 | Delegation active + plateforme NON autorisee | Admin: delegation=Oui, platform=Non; formateur ouvre page | Options visibles: `course`, `trainer`; `platform` masquee | TODO | |
| SC-05 | Delegation active + plateforme autorisee | Admin: delegation=Oui, platform=Oui; formateur ouvre page | Options visibles: `course`, `trainer`, `platform` | TODO | |
| SC-06 | Sauvegarde scope formateur | Formateur selectionne chaque option autorisee et enregistre | Message succes + `GET /api/trainer/scope` coherent | TODO | |
| SC-07 | Cas critique: revoke platform | Formateur scope=platform puis admin repasse platform=Non | `rag_scope_config` peut rester `platform`, mais runtime fallback actif | TODO | |
| SC-08 | Verification runtime fallback | Appeler `GET /api/rag/search?...trainerId=<id>` apres SC-07 | `resolvedScope=trainer` (ou `course` si trainerId absent) | TODO | |
| SC-09 | Delegation OFF force course | Admin met delegation=Non puis test `GET /api/rag/search` | `resolvedScope=course` pour tout le monde | TODO | |
| SC-10 | Coherence settings local vs backend | Provoquer ecart puis re-enregistrer settings admin | Bloc etat signale ecart puis revient aligne apres sync | TODO | |

---

## 4) Requetes de verification (copier/coller)

### SQL

```sql
SELECT * FROM platform_scope_policy ORDER BY updated_at DESC;
SELECT * FROM rag_scope_config WHERE trainer_id = '7';
```

### API

```bash
# Scope formateur
GET /api/trainer/scope?trainerId=7

# Recherche RAG (verifier resolvedScope)
GET /api/rag/search?query=test&courseId=course-1&trainerId=7&topK=5&minScore=0.4
```

---

## 5) Decision de sortie

Release scope policy OK si:

- [ ] Tous les tests SC-01 a SC-10 sont `OK`
- [ ] Aucun `KO` ouvert
- [ ] Aucun `BLOQUE` sans plan de levee

Sinon: corriger puis rejouer uniquement les cas impactes + un smoke complet SC-04/05/07/08/09.
