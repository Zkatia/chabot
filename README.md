# local_astusse

Plugin Moodle ASTUSSE pour Moodle 4.5.

## Ce que fait le plugin

- émet des JWT utilisateur RS256
- expose le JWKS sur `/local/astusse/jwks.php`
- teste la connectivité via `/local/astusse/test_token.php`
- propose les pages ASTUSSE de cours :
  - chat apprenant
  - ingestion documentaire
  - périmètre IA formateur
  - formateur de référence

## Endpoints Moodle

- `GET /local/astusse/jwks.php`
- `POST /local/astusse/token.php`
- `GET /local/astusse/test_token.php`

## Configuration admin

Dans `Administration du site > Plugins > Plugins locaux > ASTUSSE` :

- `issuer`
- `audience`
- `ttl_seconds`
- `key_id`
- `gateway_base_url`
- `gateway_timeout_seconds`
- `platform_scope_allowed`
- `delegation_enabled`

## Pages de cours

### Chat apprenant

- `/local/astusse/chat.php?courseid=...`

Le chat transmet :

- `courseId`
- `trainerId` si le formateur de référence du cours est valide

Sinon, le backend retombe sur le scope `course`.

### Formateur de référence

- `/local/astusse/reference_trainer.php?courseid=...`

Règles :

- accessible à `editingteacher` et admin
- seuls les `editingteacher` du cours sont sélectionnables
- stockage local dans `local_astusse_course_ref_trainer`

### Scope formateur

- `/local/astusse/trainer_scope.php?courseid=...`

Ce réglage reste **global au formateur** :

- `course`
- `trainer`
- `platform` si autorisé par la policy admin

### Ingestion

- `/local/astusse/ingest.php?courseid=...`

L'ingestion envoie :

- `file`
- `courseId` un ou plusieurs cours
- `trainerId = utilisateur formateur courant`

## Compatibilité backend

Le backend attend :

- claim `roles` présent
- claim `scope` absent sur `/api/**`
- `aud = astusse_services`
- JWKS sur `/local/astusse/jwks.php`

## Installation

```bash
php admin/cli/upgrade.php --non-interactive
php local/astusse/cli/generate_keys.php
```

## Recette fonctionnelle

La recette de référence est :

- [TEST_A_Z_INGESTION_SCOPE_CHAT.md](C:/workspace/Astusse/TEST_A_Z_INGESTION_SCOPE_CHAT.md)
