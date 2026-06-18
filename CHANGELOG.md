# Changelog

All notable changes to `local_astusse` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-06-16

First public, stable release, brought into compliance with the Moodle plugins directory.

### Features
- **JWT issuer**: generates RS256 user tokens and exposes the JWKS
  (`/local/astusse/jwks.php`) for authentication against the ASTUSSE AI platform.
- **Learner chat**: course AI assistant (explanatory and Socratic modes), passing the
  course context and the reference trainer.
- **Document ingestion**: asynchronous submission of course resources and files to the
  RAG service (adhoc tasks, job tracking, retry, 30-day purge). Text extraction from
  SCORM (Articulate Rise/Storyline, generic fallback) and H5P (`mod_h5pactivity`).
- **Reference trainer**: per-course configuration of the trainer used as the AI reference.
- **Trainer AI scope**: global setting for the scope (`course`, `trainer`, `platform`).
- **Spaced repetition**: interleaved quiz pop-up driven by the FSRS-5 algorithm, with
  learner control (snooze, cancellation, opt-out, automatic mastery).

### Compliance & security
- Full implementation of the **Privacy API** (metadata for stored data and data sent to
  the gateway, plus user preferences).
- **Third-party libraries bundled locally** (Marked, Geist fonts) with
  `thirdpartylibs.xml`; removal of all calls to external resources (CDNs, fonts).
- **`riskbitmask`** declared on capabilities; fixed a course resource visibility leak
  (`uservisible`).
- CSRF hardening of `token.php` (removed the server-side `sesskey()` fallback).
- Brought up to the Moodle coding standard (`moodle-cs`: 0 errors / 0 warnings), complete
  PHPDoc, PHPUnit tests, JS/CSS linting.

### Continuous integration
- GitHub Actions workflow (`moodle-plugin-ci`) validated on **PHP 8.1 / 8.2 / 8.3**
  × **PostgreSQL / MariaDB**, under **Moodle 4.5**.

### Requirements
- Moodle 4.5 or later (`requires` 2023100400), PHP 8.1+.
- A reachable ASTUSSE AI platform (admin setting `gateway_base_url`).

[1.0.0]: https://moodle.org/plugins/local_astusse
