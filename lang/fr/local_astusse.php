<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Language strings for local_astusse (French).
 *
 * @package     local_astusse
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'ASTUSSE';
$string['privacy:metadata'] = 'Le plugin ASTUSSE ne stocke aucune donnée personnelle.';
$string['settings'] = 'Paramètres ASTUSSE';

$string['issuer'] = 'Émetteur (iss)';
$string['issuer_desc'] = 'Valeur du claim JWT iss. En général, l’URL de votre Moodle.';
$string['audience'] = 'Audience (aud)';
$string['audience_desc'] = 'Valeur du claim JWT aud attendue par les services ASTUSSE.';
$string['ttl_seconds'] = 'Durée de vie du token (secondes)';
$string['ttl_seconds_desc'] = 'Durée de validité du JWT en secondes. Défaut : 900 (15 minutes).';
$string['key_id'] = 'Identifiant de clé (kid)';
$string['key_id_desc'] = 'Identifiant optionnel utilisé dans l’en-tête JWT et le JWKS.';
$string['gateway_base_url'] = 'URL de base de la gateway';
$string['gateway_base_url_desc'] = 'URL de base de la gateway ASTUSSE utilisée par le client API (exemple : http://localhost:8888).';
$string['gateway_timeout_seconds'] = 'Timeout gateway (secondes)';
$string['gateway_timeout_seconds_desc'] = 'Timeout HTTP en secondes pour les appels API vers la gateway.';
$string['chat_history_ttl'] = 'Retention de l\'historique chat ASTUSSE';
$string['chat_history_ttl_desc'] = 'Durée de conservation demandée au backend pour les nouvelles conversations chat. Le mode illimité évite l\'expiration Redis, mais augmente le volume stocké dans le temps.';
$string['chat_history_ttl_24h'] = '24 heures';
$string['chat_history_ttl_48h'] = '48 heures';
$string['chat_history_ttl_72h'] = '72 heures';
$string['chat_history_ttl_unlimited'] = 'Illimité';
$string['key_directory'] = 'Répertoire des clés';
$string['key_directory_desc'] = 'Répertoire dans lequel les clés RSA sont stockées.';
$string['rag_scope_heading'] = 'Politique de périmètre RAG';
$string['rag_scope_heading_desc'] = 'Politique globale synchronisée vers l’API ASTUSSE. L’admin fixe le plafond, les formateurs choisissent dans ce plafond.';
$string['platform_scope_allowed'] = 'Activer le scope plateforme entière';
$string['platform_scope_allowed_desc'] = 'Si désactivé, les formateurs ne peuvent pas sélectionner le scope plateforme.';
$string['delegation_enabled'] = 'Déléguer le choix du scope aux formateurs';
$string['delegation_enabled_desc'] = 'Si désactivé, le backend force le scope cours pour tout le monde.';
$string['rag_scope_sync_state_title'] = 'État de synchronisation et état backend';
$string['rag_scope_sync_status_pending'] = 'Synchronisation API : en attente';
$string['rag_scope_sync_status_skipped'] = 'Synchronisation API : non exécutée (session admin requise)';
$string['rag_scope_sync_status_ok'] = 'Synchronisation API : OK';
$string['rag_scope_sync_status_ko'] = 'Synchronisation API : en échec';
$string['rag_scope_sync_never'] = 'jamais';
$string['rag_scope_sync_line'] = '{$a->status} — dernière tentative : {$a->time}.';
$string['rag_scope_backend_aligned'] = 'Backend aligné avec les valeurs enregistrées.';
$string['rag_scope_backend_mismatch'] = 'Écart détecté : local (plateforme={$a->localplatform}, délégation={$a->localdelegation}) ; backend (plateforme={$a->backendplatform}, délégation={$a->backenddelegation}). Enregistrez à nouveau pour resynchroniser.';
$string['rag_scope_backend_unavailable'] = 'État backend indisponible : {$a}.';
$string['astusse:requesttoken'] = 'Demander un token JWT ASTUSSE';
$string['astusse:managetrainerscope'] = 'Configurer le scope ASTUSSE formateur depuis un cours';
$string['astusse:managereferencetrainer'] = 'Configurer le formateur de référence ASTUSSE depuis un cours';
$string['astusse:ingestdocument'] = 'Indexer un document ASTUSSE depuis un cours';
$string['error:keysmissing'] = 'Les clés JWT sont absentes. Lancez le script CLI de génération des clés.';
$string['error:tokenfailed'] = 'La génération du token a échoué.';
$string['testpage:title'] = 'Test JWT et API ASTUSSE';
$string['testpage:heading'] = 'Validation JWT et gateway ASTUSSE';
$string['testpage:generate'] = 'Générer un JWT utilisateur';
$string['testpage:ping'] = 'Appeler /api/ping sur la gateway';
$string['testpage:intro'] = 'Cette page valide votre configuration JWT et la connectivité avec la gateway.';
$string['testpage:jwks_label'] = 'Endpoint JWKS';
$string['testpage:token_label'] = 'Endpoint token';
$string['testpage:gateway_label'] = 'Gateway configurée';
$string['testpage:error_label'] = 'Erreur';
$string['testpage:result_label'] = 'Résultat';
$string['testpage:invalid_sesskey'] = 'Sesskey invalide.';
$string['trainerscope:menu'] = 'Périmètre IA ASTUSSE';
$string['trainerscope:title'] = 'Périmètre formateur ASTUSSE';
$string['trainerscope:heading'] = 'Périmètre de l’IA pour vos apprenants';
$string['trainerscope:intro'] = 'Choisissez le périmètre documentaire qu’ASTUSSE peut utiliser pour répondre à vos apprenants dans l’ensemble de vos cours.';
$string['trainerscope:global_notice'] = 'Ce réglage est global au formateur : toute modification s’applique à tous vos cours.';
$string['trainerscope:trainer_id'] = 'Identifiant formateur : {$a}';
$string['trainerscope:policy_title'] = 'Politique active';
$string['trainerscope:active_scope_line'] = 'Périmètre actif pour ce formateur : {$a}';
$string['trainerscope:delegation_state'] = 'Délégation activée : {$a}';
$string['trainerscope:platform_state'] = 'Scope plateforme autorisé : {$a}';
$string['trainerscope:delegation_disabled'] = 'La délégation admin est désactivée. Le scope ne peut pas être modifié au niveau formateur.';
$string['trainerscope:label'] = 'Périmètre de l’IA pour les apprenants';
$string['trainerscope:label_help'] = 'Sélectionnez le niveau d’ouverture souhaité. Le backend applique ensuite la politique admin et le formateur de référence du cours.';
$string['trainerscope:save_ok'] = 'Le scope a été mis à jour.';
$string['trainerscope:save_button'] = 'Enregistrer';
$string['trainerscope:error_fetch'] = 'Impossible de charger le scope formateur depuis l’API.';
$string['trainerscope:error_save'] = 'Impossible de sauvegarder le scope formateur.';
$string['trainerscope:error_invalid_scope'] = 'Le scope sélectionné n’est pas autorisé par la politique admin actuelle.';
$string['trainerscope:available_options'] = 'Options disponibles : {$a}';
$string['trainerscope:options_masked_note'] = 'Les options non autorisées sont masquées (jamais affichées en désactivé).';
$string['trainerscope:platform_hidden_note'] = 'Option "Toute la plateforme" masquée par la politique admin.';
$string['trainerscope:scope_adjusted'] = 'Le scope actif a été réajusté à "{$a}" car l’ancienne valeur n’est plus autorisée.';
$string['trainerscope:scope_course_desc'] = 'Limite les réponses aux documents et ressources du cours courant.';
$string['trainerscope:scope_trainer_desc'] = 'Étend les réponses à tous les documents que vous avez indexés pour vos cours.';
$string['trainerscope:scope_platform_desc'] = 'Autorise aussi l’usage des ressources documentaires disponibles à l’échelle de la plateforme.';
$string['scope:course'] = 'Ce cours uniquement';
$string['scope:trainer'] = 'Tous mes cours';
$string['scope:platform'] = 'Toute la plateforme';
$string['referencetrainer:menu'] = 'Formateur de référence ASTUSSE';
$string['referencetrainer:title'] = 'Formateur de référence ASTUSSE';
$string['referencetrainer:heading'] = 'Formateur de référence du cours';
$string['referencetrainer:intro'] = 'Choisissez le formateur de référence utilisé par ASTUSSE pour transmettre le contexte trainerId du cours.';
$string['referencetrainer:label'] = 'Formateur de référence';
$string['referencetrainer:label_help'] = 'Sélectionnez le formateur principal du cours. ASTUSSE utilisera cet identifiant pour transmettre le contexte trainerId au backend.';
$string['referencetrainer:none'] = 'Aucun formateur de référence';
$string['referencetrainer:state_valid'] = 'Formateur défini';
$string['referencetrainer:state_invalid'] = 'Référence invalide';
$string['referencetrainer:state_missing'] = 'Aucune référence';
$string['referencetrainer:summary_course'] = 'Cours';
$string['referencetrainer:summary_course_text'] = 'Le formateur choisi sera utilisé uniquement pour ce cours.';
$string['referencetrainer:summary_candidates'] = 'Formateurs éligibles';
$string['referencetrainer:summary_candidates_text'] = 'Seuls les utilisateurs avec le rôle editingteacher sont proposés.';
$string['referencetrainer:summary_current'] = 'Formateur actuel';
$string['referencetrainer:summary_current_text'] = 'Ce formateur sert de référence ASTUSSE pour le chat apprenant.';
$string['referencetrainer:save_button'] = 'Enregistrer';
$string['referencetrainer:save_ok'] = 'Le formateur de référence a été enregistré.';
$string['referencetrainer:clear_ok'] = 'Le formateur de référence a été retiré.';
$string['referencetrainer:error_invalid_selection'] = 'Le formateur sélectionné n\'est pas un editingteacher valide de ce cours.';
$string['referencetrainer:no_candidates'] = 'Aucun editingteacher n\'est actuellement disponible dans ce cours.';
$string['referencetrainer:status_missing'] = 'Aucun formateur de référence n\'est défini. Le chat utilisera le fallback course.';
$string['referencetrainer:status_invalid'] = 'Le formateur de référence enregistré n\'est plus valide pour ce cours. Le chat utilisera le fallback course tant que ce réglage ne sera pas corrigé.';
$string['referencetrainer:status_valid'] = 'Formateur de référence actuel : {$a}.';

$string['ingest:menu'] = 'Indexer un document IA ASTUSSE';
$string['ingest:title'] = 'Ingestion documentaire ASTUSSE';
$string['ingest:heading'] = 'Indexer un document pour la RAG';
$string['ingest:intro'] = 'Téléversez une ressource et associez-la à un ou plusieurs cours.';
$string['ingest:reference_trainer_title'] = 'Formateur de référence du cours';
$string['ingest:reference_trainer_valid'] = 'Défini : {$a}. Ce formateur servira au contexte trainerId pour le chat du cours.';
$string['ingest:reference_trainer_invalid'] = 'Invalide : le formateur enregistré n’est plus un editingteacher valide. Le chat utilisera le fallback course tant que ce réglage ne sera pas corrigé.';
$string['ingest:reference_trainer_missing'] = 'Non défini : le chat utilisera le fallback course tant qu’aucun formateur de référence n’est configuré.';
$string['ingest:file_label'] = 'Document à indexer';
$string['ingest:file_help'] = 'Formats acceptés par l’API : PDF, TXT, DOC, DOCX, Markdown, HTML (max 50 Mo).';
$string['ingest:courses_label'] = 'Cours à associer';
$string['ingest:courses_help'] = 'Vous pouvez sélectionner plusieurs cours.';
$string['ingest:courses_search_label'] = 'Rechercher un cours';
$string['ingest:courses_search_placeholder'] = 'Rechercher un cours...';
$string['ingest:submit_button'] = 'Indexer le document';
$string['ingest:save_ok'] = 'Document envoyé pour ingestion.';
$string['ingest:save_ok_job'] = 'Document envoyé pour ingestion. Job ID : {$a}';
$string['ingest:error_no_courses'] = 'Sélectionnez au moins un cours.';
$string['ingest:error_no_file'] = 'Sélectionnez un fichier à envoyer.';
$string['ingest:error_invalid_file'] = 'Le fichier transmis est invalide.';
$string['ingest:error_file_too_large'] = 'Le fichier dépasse la limite locale de 50 Mo.';
$string['ingest:error_upload'] = 'Échec du téléversement PHP (code {$a}).';
$string['ingest:error_submit'] = 'Impossible de lancer l’ingestion.';
$string['ingest:error_http_400'] = 'La requête d’ingestion est invalide. Vérifiez le fichier et les cours sélectionnés.';
$string['ingest:error_http_401'] = 'Votre session d’authentification ASTUSSE a expiré ou est invalide. Rechargez la page puis réessayez.';
$string['ingest:error_http_403'] = 'Vous n’êtes pas autorisé à lancer cette ingestion.';
$string['ingest:error_http_404'] = 'Le service d’ingestion est introuvable. Vérifiez la configuration de la gateway.';
$string['ingest:error_http_408'] = 'Le service a mis trop de temps à répondre. Réessayez dans quelques instants.';
$string['ingest:error_http_413'] = 'Le fichier est trop volumineux pour le service d’ingestion.';
$string['ingest:error_http_429'] = 'Trop de requêtes en peu de temps. Réessayez dans quelques minutes.';
$string['ingest:error_http_5xx'] = 'Le service ASTUSSE est temporairement indisponible. Réessayez plus tard.';
$string['ingest:error_http_unknown'] = 'Erreur HTTP {$a} lors de l’ingestion.';
$string['ingest:error_http_status_line'] = 'Code HTTP gateway : {$a}';
$string['ingest:error_traceid_line'] = 'Trace ID : {$a}';
$string['ingest:error_backend_message'] = 'Message backend : {$a}';
$string['ingest:error_exception_message'] = 'Détail technique : {$a}';
$string['ingest:error_no_available_courses'] = 'Aucun cours disponible pour l’ingestion avec votre rôle actuel.';
$string['ingest:result_status'] = 'Statut : {$a}';
$string['ingest:result_http_status'] = 'HTTP gateway : {$a}';
$string['ingest:result_jobid'] = 'Job ID : {$a}';
$string['ingest:result_traceid'] = 'Trace ID : {$a}';

$string['astusse:usechat'] = 'Utiliser le chat ASTUSSE depuis un cours';
$string['chat:menu'] = 'Assistant IA ASTUSSE';
$string['chat:global_menu'] = 'ASTUSSE';
$string['chat:title'] = 'Assistant IA ASTUSSE';
$string['chat:global_title'] = 'ASTUSSE';
$string['chat:heading'] = 'Discuter avec ASTUSSE';
$string['chat:global_heading'] = 'Discuter avec ASTUSSE';
$string['chat:intro'] = 'Posez une question sur votre cours et choisissez le mode d\'accompagnement adapté.';
$string['chat:global_intro'] = 'Sélectionnez un cours, choisissez le mode d\'accompagnement, puis démarrez une conversation avec ASTUSSE.';
$string['chat:brand_title'] = 'Vos conversations';
$string['chat:agent_label'] = 'Mode de réponse';
$string['chat:agent_explicatif'] = 'Explicatif';
$string['chat:agent_socratique'] = 'Socratique';
$string['chat:message_label'] = 'Votre message';
$string['chat:message_placeholder'] = 'Exemple : peux-tu m\'expliquer ce chapitre avec un exemple concret ?';
$string['chat:send_button'] = 'Envoyer';
$string['chat:new_session_button'] = 'Nouvelle conversation';
$string['chat:status_ready'] = 'Prêt à vous aider.';
$string['chat:status_loading'] = 'ASTUSSE prépare sa réponse...';
$string['chat:empty_state'] = 'La conversation apparaîtra ici après votre premier message.';
$string['chat:student_label'] = 'Vous';
$string['chat:assistant_label'] = 'ASTUSSE';
$string['chat:error_generic'] = 'Une erreur est survenue, veuillez réessayer.';
$string['chat:error_invalid_request'] = 'Requête chat invalide.';
$string['chat:error_invalid_sesskey'] = 'Sesskey invalide.';
$string['chat:error_message_required'] = 'Le message ne peut pas être vide.';
$string['chat:error_agent_invalid'] = 'Le type d\'agent sélectionné est invalide.';
$string['chat:error_session_required'] = 'Identifiant de session manquant.';
$string['chat:error_backend'] = 'Le backend ASTUSSE a retourné une erreur.';
$string['chat:error_invalid_json'] = 'La réponse AJAX de Moodle n\'est pas un JSON valide.';
$string['chat:traceid_label'] = 'Trace ID';
$string['chat:sessionid_label'] = 'Session';
$string['chat:course_context'] = 'Cours Moodle : {$a}';
$string['chat:course_context_label'] = 'Cours sélectionné';
$string['chat:reference_trainer_title'] = 'Formateur de référence';
$string['chat:reference_trainer_context'] = 'Formateur de référence : {$a}';
$string['chat:reference_trainer_missing'] = 'Aucun formateur de référence n\'est défini. ASTUSSE utilise le fallback course.';
$string['chat:reference_trainer_invalid'] = 'Le formateur de référence enregistré n\'est plus valide pour ce cours. ASTUSSE utilise le fallback course.';
$string['chat:history_notice'] = 'L\'historique est relu depuis ASTUSSE à chaque chargement de page.';
$string['chat:empty_state_detail'] = 'Le contexte du cours est déjà transmis à ASTUSSE. Posez directement votre question pour démarrer la conversation.';
$string['chat:course_selector_label'] = 'Cours';
$string['chat:course_selector_placeholder'] = 'Choisissez un cours';
$string['chat:error_course_required'] = 'Choisissez un cours avant d\'envoyer un message.';
$string['chat:course_locked_notice'] = 'Le cours est imposé depuis cette page.';
$string['chat:conversations_label'] = 'Conversations';
$string['chat:conversations_empty'] = 'Aucune conversation pour ce cours.';
$string['chat:conversations_empty_detail'] = 'Utilisez "Nouvelle conversation" ou envoyez directement un premier message.';
$string['chat:delete_conversation_label'] = 'Supprimer';
$string['chat:delete_conversation_confirm'] = 'Supprimer définitivement cette conversation de l’historique ASTUSSE et du cache local ?';
$string['chat:delete_conversation_status'] = 'Conversation supprimée.';
$string['chat:loading_history'] = 'Chargement de l’historique ASTUSSE...';
$string['chat:history_sync_failed'] = 'Impossible de synchroniser l’historique ASTUSSE.';
$string['chat:history_deleted_remote'] = 'Cette conversation n’existe plus côté ASTUSSE.';
$string['chat:no_course_title'] = 'Choisissez un cours pour commencer.';
$string['chat:no_course_detail'] = 'Le chat global ASTUSSE a besoin d\'un cours pour transmettre le bon contexte documentaire.';
$string['chat:untitled_conversation'] = 'Nouvelle conversation';
$string['chat:global_no_courses'] = 'Aucun cours n\'est actuellement disponible pour le chat ASTUSSE.';
$string['chat:agent_used_label'] = 'Mode utilisé';
$string['chat:technical_details_label'] = 'Détails techniques';
$string['chat:summary_aria'] = 'Résumé de la conversation';
$string['chat:summary_mode'] = 'Mode';
$string['chat:summary_session'] = 'Session';
$string['chat:summary_messages'] = 'Messages';
$string['chat:summary_none'] = 'Aucune';
$string['chat:pending_label'] = 'ASTUSSE est en train de préparer sa réponse...';
$string['chat:starter_label'] = 'Démarrages rapides';
$string['chat:starter_understand_chapter'] = 'Peux-tu m\'expliquer ce chapitre avec des mots simples ?';
$string['chat:starter_quiz_revision'] = 'Aide-moi à réviser avant le prochain quiz.';
$string['chat:starter_step_by_step'] = 'Guide-moi étape par étape sans me donner toute la réponse.';
$string['chat:input_hint'] = 'Entrée pour envoyer, Maj + Entrée pour aller à la ligne.';
