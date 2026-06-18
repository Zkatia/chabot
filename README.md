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

- `/local/astusse/ingest.php?courseid=...` (sélection des sources et envoi)
- `/local/astusse/jobs.php?courseid=...` (suivi des ingestions en cours de l'utilisateur)

Sources supportées en une seule soumission :

- ressources Moodle cochées (fichiers `resource`, pages HTML, SCORM, H5P — voir sous-sections ci-dessous)
- jusqu'à **10 fichiers** téléversés simultanément (`.pdf`, `.txt`, `.doc`, `.docx`, `.md`, `.markdown`, `.html`, `.htm`)
- taille max par fichier : **50 Mo** (voir section « Limite d'upload » ci-dessous)

#### Authorings SCORM reconnus

L'extraction de texte depuis un paquet SCORM suit 3 passes, dans cet ordre :

| Authoring | Signature détectée | Méthode |
|---|---|---|
| **Articulate Rise** (toutes versions) | 3 patterns couverts : `__resolveJsonp("…","<base64>")` dans un `.js` (Rise classique) · `window.courseData = "<base64>"` dans `index.html` (variante intermédiaire) · `deserialize("<base64>")` inline dans `index.html` (Rise 360 récent) | Décodage base64 → JSON walker sur clés pédagogiques (`text`, `title`, `description`, etc.) |
| **Articulate Storyline** | `window.globalProvideData('slide'\|'data', '<JSON>')` dans les `.js` de `html5/data/js/` ou `story_content/` | Extraction des blocs GPD filtrés par clé (`slide`, `data` seulement — `frame`, `paths` ignorés pour éviter les labels UI du player et les données SVG) → JSON walker |
| **Autres SCORM** (iSpring, Adobe Captivate, SCORM 1.2 classiques…) | Fallback générique si les 2 passes ci-dessus ne trouvent rien | Parcours de tous les fichiers `.html`/`.htm`/`.json`/`.js`/`.xml`/`.txt` non-framework ; parsing DOM pour le texte visible + extraction JSON embarqué dans les `<script>` |

La whitelist JSON (`local_astusse_collect_json_texts()`) reconnaît les clés texte génériques, pédagogiques H5P (`answer`, `question`, `correctAnswer`, etc.) et la clé spécifique Storyline `altText` (où Storyline stocke le texte visible à l'écran).

#### Activités H5P reconnues

Seule la variante `mod_h5pactivity` est supportée (pas la banque de contenus). Le pipeline parcourt le contenu H5P de l'activité (JSON `content.json` et bibliothèques associées) pour en extraire le texte pédagogique via le même JSON walker que les autres sources.

Chaque document sélectionné donne lieu à :

- un job inséré dans la table `local_astusse_ingest_jobs` (statut initial `queued`)
- pour les fichiers téléversés, une copie persistante dans le filearea `local_astusse/ingestqueue` (itemid = jobid)
- une `adhoc_task` Moodle (`local_astusse\task\ingest_document_task`) qui sera dépilée par le cron

À l'exécution, la tâche :

1. Génère le JWT utilisateur du formateur
2. Envoie le fichier au gateway : `POST {gateway_base_url}/api/rag/ingest?courseId=...&trainerId=...` (multipart `file`)
3. Met à jour le job en BDD (`httpstatus`, `backendjobid`, `backendtraceid`, `errormessage`, `timestarted`, `timecompleted`)
4. Retente jusqu'à 5 fois sur erreur transitoire (HTTP 5xx / 408 / 429 / network). Les erreurs permanentes (4xx hors 408/429) basculent immédiatement en `failed`.
5. Sur succès, supprime la copie persistante. Sur échec, la copie est conservée pour permettre un retry manuel.

La page `jobs.php` expose :

- la liste des jobs du formateur courant pour le cours d'origine
- un polling JS (3 s) qui s'arrête dès que plus aucun job n'est `queued`/`running`
- un bouton « Relancer » sur les jobs en échec (re-enqueue d'une nouvelle adhoc_task)

Rétention : les jobs terminés (`succeeded` ou `failed`) plus anciens que **30 jours** sont purgés par la scheduled task `local_astusse\task\cleanup_old_ingest_jobs` (quotidienne, 3h17).

#### Limite d'upload

Le plafond Moodle est fixé dans [lib.php](lib.php) via `local_astusse_get_ingest_max_upload_bytes()` (50 Mo). Il doit être ≥ à la limite backend `SPRING_SERVLET_MULTIPART_MAX_FILE_SIZE` (défaut 50 Mo) pour éviter de stocker inutilement des fichiers qui seraient refusés. Si l'admin backend baisse la limite, le plugin reçoit un HTTP 413 propre du `GlobalExceptionHandler` et le job bascule immédiatement en `failed` (pas de retry).

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

## Licence

2026 Ingenium Digital Learning — GNU GPL v3 ou ultérieure.
