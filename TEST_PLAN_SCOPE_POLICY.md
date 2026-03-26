# Plan de test - Politique de scope RAG

Ce document couvre uniquement la logique de perimetre RAG:

- policy admin (`platformScopeAllowed`, `delegationEnabled`)
- scope formateur stocke (`rag_scope_config.scope`)
- scope resolu applique a la recherche (`resolvedScope`)

## 1. Objectif

Verifier que la hierarchie de permissions est respectee:

1. Admin fixe le plafond.
2. Formateur choisit dans ce plafond (si delegation activee).
3. Le backend resolve le scope autorise a chaque requete.

Important:

- les options non autorisees sont masquees dans l UI formateur
- la table `rag_scope_config` peut contenir une valeur devenue non autorisee
- la resolution runtime doit quand meme appliquer le fallback correct

## 2. Perimetre teste

- UI admin Moodle (`local_astusse` settings)
- UI formateur Moodle (`/local/astusse/trainer_scope.php?courseid=...`)
- API gateway:
  - `POST /api/admin/scope/policy`
  - `POST /api/trainer/scope`
  - `GET /api/trainer/scope?trainerId=...`
  - `GET /api/rag/search?...` (champ `resolvedScope`)
- BDD Postgres:
  - `platform_scope_policy`
  - `rag_scope_config`

## 3. Preconditions

- Plugin `local_astusse` installe et a jour
- JWT et gateway operationnels (`/api/ping` = 200)
- Orchestration-service connecte a Postgres (`RAG_VECTOR_STORE_TYPE=postgres`)
- Un compte admin
- Un compte formateur (role enseignant/editingteacher)
- Un `trainerId` connu pour le formateur cible
- Si le test est rejoue via le chat Moodle, un formateur de reference doit etre configure sur le cours

## 4. Regles de reference (attendu backend)

- Si `delegationEnabled = false`:
  - scope resolu = `course` pour tout le monde
- Si `delegationEnabled = true`:
  - scope formateur `course` -> resolu `course`
  - scope formateur `trainer` -> resolu `trainer`
  - scope formateur `platform`:
    - si `platformScopeAllowed = true` -> resolu `platform`
    - si `platformScopeAllowed = false` -> fallback `trainer` (ou `course` si trainerId absent)

## 5. Cas de test (fonctionnels)

### TC-01 - Valeurs par defaut admin

Etapes:

1. Ouvrir settings `local_astusse`.
2. Verifier:
   - `platform_scope_allowed = Non`
   - `delegation_enabled = Oui`

Attendu:

- Valeurs par defaut conformes.

---

### TC-02 - Formateur voit uniquement options autorisees

Etapes:

1. Admin: `platform_scope_allowed = Non`, `delegation_enabled = Oui`, enregistrer.
2. Formateur: ouvrir `trainer_scope.php` depuis un cours.

Attendu:

- Options visibles:
  - `Ce cours uniquement`
  - `Tous mes cours`
- Option `Toute la plateforme` non visible (masquee, pas desactivee).

---

### TC-03 - Formateur peut choisir platform si admin autorise

Etapes:

1. Admin: `platform_scope_allowed = Oui`, `delegation_enabled = Oui`, enregistrer.
2. Formateur: ouvrir `trainer_scope.php`.
3. Choisir `Toute la plateforme`, enregistrer.
4. Lire `GET /api/trainer/scope?trainerId=<id_formateur>`.

Attendu:

- Option `Toute la plateforme` visible.
- Sauvegarde OK.
- API retourne `scope = platform`.
- En BDD `rag_scope_config.scope = platform` pour ce formateur.

---

### TC-04 - Cas critique demande: admin coupe platform apres choix formateur

Contexte:

- Formateur a deja `scope = platform` en base.

Etapes:

1. Admin met `platform_scope_allowed = Non`, `delegation_enabled = Oui`, enregistrer.
2. Verifier BDD:
   - `rag_scope_config.scope` peut rester `platform`.
3. Lancer une recherche:
   - `GET /api/rag/search?...` avec `courseId` + `trainerId`.
4. Verifier la reponse JSON.

Attendu:

- La ligne BDD peut rester `platform` (pas de reecriture automatique requise).
- `resolvedScope` retourne `trainer` (fallback runtime).
- Filtrage effectif cote recherche: `trainer_id = ?`.

---

### TC-05 - Delegation desactivee force course

Etapes:

1. Admin met `delegation_enabled = Non` (valeur de `platform_scope_allowed` indifferente), enregistrer.
2. Formateur ouvre `trainer_scope.php`.
3. Lancer `GET /api/rag/search?...`.

Attendu:

- UI formateur: pas de selection editable (information delegation desactivee).
- `resolvedScope` retourne `course`.
- Filtrage effectif: `course_id = ?`.

---

### TC-06 - Controle de coherence UI vs backend

Etapes:

1. Dans settings admin, observer bloc "etat de synchronisation et etat backend".
2. Provoquer un ecart (ex: suppression manuelle de ligne DB policy).
3. Recharger la page.

Attendu:

- Le bloc signale un ecart local/backend.
- Apres nouvel enregistrement admin, le bloc revient "aligne" si sync OK.

## 6. Requetes SQL utiles

```sql
SELECT * FROM platform_scope_policy ORDER BY updated_at DESC;
SELECT * FROM rag_scope_config WHERE trainer_id = '7';
```

## 7. Verifications API utiles

```bash
# Scope formateur
GET /api/trainer/scope?trainerId=7

# Recherche RAG (verifier resolvedScope)
GET /api/rag/search?query=test&courseId=course-1&trainerId=7&topK=5&minScore=0.4
```

Verifier dans la reponse:

- `resolvedScope` (`course` | `trainer` | `platform`)

## 8. Criteres d acceptation

- Les options non autorisees sont masquees en UI formateur.
- La policy admin prevaut toujours sur la valeur formateur stockee.
- Le fallback runtime est correct pour les cas limites (notamment `platform` revoque).
- `resolvedScope` reflete le scope effectivement applique.
- Aucun 500 inattendu pendant les transitions admin/formateur.
