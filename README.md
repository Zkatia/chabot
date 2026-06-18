# local_astusse

ASTUSSE Moodle plugin for Moodle 4.5.

## What the plugin does

- issues RS256 user JWTs
- exposes the JWKS at `/local/astusse/jwks.php`
- tests connectivity via `/local/astusse/test_token.php`
- provides the ASTUSSE course pages:
  - learner chat
  - document ingestion
  - trainer AI scope
  - reference trainer

## Moodle endpoints

- `GET /local/astusse/jwks.php`
- `POST /local/astusse/token.php`
- `GET /local/astusse/test_token.php`

## Admin configuration

Under `Site administration > Plugins > Local plugins > ASTUSSE`:

- `issuer`
- `audience`
- `ttl_seconds`
- `key_id`
- `gateway_base_url`
- `gateway_timeout_seconds`
- `platform_scope_allowed`
- `delegation_enabled`

## Course pages

### Learner chat

- `/local/astusse/chat.php?courseid=...`

The chat sends:

- `courseId`
- `trainerId` when the course reference trainer is valid

Otherwise, the backend falls back to the `course` scope.

### Reference trainer

- `/local/astusse/reference_trainer.php?courseid=...`

Rules:

- available to `editingteacher` and admins
- only the course `editingteacher` users are selectable
- stored locally in `local_astusse_course_ref_trainer`

### Trainer scope

- `/local/astusse/trainer_scope.php?courseid=...`

This setting is **global to the trainer**:

- `course`
- `trainer`
- `platform` when allowed by the admin policy

### Ingestion

- `/local/astusse/ingest.php?courseid=...` (source selection and submission)
- `/local/astusse/jobs.php?courseid=...` (tracking of the user's running ingestions)

Sources supported in a single submission:

- selected Moodle resources (`resource` files, HTML pages, SCORM, H5P — see the subsections below)
- up to **10 files** uploaded at once (`.pdf`, `.txt`, `.doc`, `.docx`, `.md`, `.markdown`, `.html`, `.htm`)
- maximum size per file: **50 MB** (see the "Upload limit" section below)

#### Supported SCORM authorings

Text extraction from a SCORM package follows 3 passes, in this order:

| Authoring | Detected signature | Method |
|---|---|---|
| **Articulate Rise** (all versions) | 3 patterns covered: `__resolveJsonp("…","<base64>")` in a `.js` (classic Rise) · `window.courseData = "<base64>"` in `index.html` (intermediate variant) · `deserialize("<base64>")` inline in `index.html` (recent Rise 360) | base64 decode → JSON walker over pedagogical keys (`text`, `title`, `description`, etc.) |
| **Articulate Storyline** | `window.globalProvideData('slide'\|'data', '<JSON>')` in the `.js` files of `html5/data/js/` or `story_content/` | Extraction of GPD blocks filtered by key (`slide`, `data` only — `frame`, `paths` ignored to avoid player UI labels and SVG data) → JSON walker |
| **Other SCORM** (iSpring, Adobe Captivate, classic SCORM 1.2, …) | Generic fallback when the 2 passes above find nothing | Walks every non-framework `.html`/`.htm`/`.json`/`.js`/`.xml`/`.txt` file; DOM parsing for visible text + JSON embedded in `<script>` tags |

The JSON whitelist (`local_astusse_collect_json_texts()`) recognises generic text keys, H5P pedagogical keys (`answer`, `question`, `correctAnswer`, etc.) and the Storyline-specific `altText` key (where Storyline stores on-screen visible text).

#### Supported H5P activities

Only the `mod_h5pactivity` variant is supported (not the content bank). The pipeline walks the activity's H5P content (the `content.json` JSON and associated libraries) to extract the pedagogical text using the same JSON walker as the other sources.

Each selected document results in:

- a job inserted into the `local_astusse_ingest_jobs` table (initial status `queued`)
- for uploaded files, a persistent copy in the `local_astusse/ingestqueue` filearea (itemid = jobid)
- a Moodle `adhoc_task` (`local_astusse\task\ingest_document_task`) to be dequeued by cron

When it runs, the task:

1. Generates the trainer's user JWT
2. Sends the file to the gateway: `POST {gateway_base_url}/api/rag/ingest?courseId=...&trainerId=...` (multipart `file`)
3. Updates the job in the database (`httpstatus`, `backendjobid`, `backendtraceid`, `errormessage`, `timestarted`, `timecompleted`)
4. Retries up to 5 times on transient errors (HTTP 5xx / 408 / 429 / network). Permanent errors (4xx other than 408/429) move immediately to `failed`.
5. On success, deletes the persistent copy. On failure, the copy is kept to allow a manual retry.

The `jobs.php` page exposes:

- the list of the current trainer's jobs for the originating course
- a JS polling loop (3 s) that stops as soon as no job is `queued`/`running`
- a "Retry" button on failed jobs (re-enqueues a new adhoc_task)

Retention: completed jobs (`succeeded` or `failed`) older than **30 days** are purged by the scheduled task `local_astusse\task\cleanup_old_ingest_jobs` (daily, 03:17).

#### Upload limit

The Moodle ceiling is set in [lib.php](lib.php) via `local_astusse_get_ingest_max_upload_bytes()` (50 MB). It must be ≥ the backend limit `SPRING_SERVLET_MULTIPART_MAX_FILE_SIZE` (default 50 MB) to avoid needlessly storing files that would be rejected. If the backend admin lowers the limit, the plugin receives a clean HTTP 413 from the `GlobalExceptionHandler` and the job moves immediately to `failed` (no retry).

## Backend compatibility

The backend expects:

- a `roles` claim present
- no `scope` claim on `/api/**`
- `aud = astusse_services`
- the JWKS at `/local/astusse/jwks.php`

## Installation

```bash
php admin/cli/upgrade.php --non-interactive
php local/astusse/cli/generate_keys.php
```

## License

2026 Ingenium Digital Learning — GNU GPL v3 or later.
