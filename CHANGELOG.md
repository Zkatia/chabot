# Changelog

Toutes les modifications notables de `local_astusse` sont documentées dans ce fichier.

Le format s'appuie sur [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/),
et le projet suit le [versionnage sémantique](https://semver.org/lang/fr/).

## [1.0.0] - 2026-06-16

Première version publique, stable, mise en conformité avec le Moodle plugins directory.

### Fonctionnalités
- **Émetteur JWT** : génération de jetons utilisateur RS256 et exposition du JWKS
  (`/local/astusse/jwks.php`) pour l'authentification auprès de la plateforme IA ASTUSSE.
- **Chat apprenant** : assistant IA de cours (modes explicatif et socratique), avec
  transmission du contexte cours et du formateur de référence.
- **Ingestion documentaire** : envoi asynchrone de ressources de cours et de fichiers
  vers le service RAG (tâches adhoc, suivi des jobs, relance, purge à 30 jours).
  Extraction de texte SCORM (Articulate Rise/Storyline, fallback générique) et H5P
  (`mod_h5pactivity`).
- **Formateur de référence** : configuration par cours du formateur servant de référence IA.
- **Périmètre IA formateur** : réglage global du scope (`course`, `trainer`, `platform`).
- **Révision espacée** : pop-up de quiz interleavé piloté par l'algorithme FSRS-5, avec
  contrôle apprenant (snooze, annulation, opt-out, maîtrise automatique).

### Conformité & sécurité
- Implémentation complète de la **Privacy API** (métadonnées des données stockées et
  transmises à la gateway, préférences utilisateur).
- **Bibliothèques tierces embarquées localement** (Marked, polices Geist) avec
  `thirdpartylibs.xml` ; suppression de tout appel à des ressources externes (CDN, fonts).
- **`riskbitmask`** déclaré sur les capabilities ; correction d'une fuite de visibilité
  des ressources de cours (`uservisible`).
- Durcissement CSRF de `token.php` (suppression du repli `sesskey()` côté serveur).
- Mise au standard de code Moodle (`moodle-cs` : 0 erreur / 0 warning), PHPDoc complet,
  tests PHPUnit, lint JS/CSS.

### Intégration continue
- Workflow GitHub Actions (`moodle-plugin-ci`) validé sur **PHP 8.1 / 8.2 / 8.3**
  × **PostgreSQL / MariaDB**, sous **Moodle 4.5**.

### Prérequis
- Moodle 4.5 ou supérieur (`requires` 2023100400), PHP 8.1+.
- Une plateforme IA ASTUSSE accessible (paramètre admin `gateway_base_url`).

[1.0.0]: https://moodle.org/plugins/local_astusse
