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
 * Language strings for local_astusse.
 *
 * @package     local_astusse
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'ASTUSSE';
$string['privacy:metadata'] = 'The ASTUSSE plugin does not store personal data.';
$string['settings'] = 'ASTUSSE settings';

$string['issuer'] = 'Issuer (iss)';
$string['issuer_desc'] = 'JWT issuer claim value. Usually your Moodle URL.';
$string['audience'] = 'Audience (aud)';
$string['audience_desc'] = 'JWT audience claim expected by ASTUSSE services.';
$string['ttl_seconds'] = 'Token lifetime (seconds)';
$string['ttl_seconds_desc'] = 'JWT validity in seconds. Default is 900 (15 minutes).';
$string['key_id'] = 'Key ID (kid)';
$string['key_id_desc'] = 'Optional key identifier used in JWT header and JWKS.';
$string['gateway_base_url'] = 'Gateway base URL';
$string['gateway_base_url_desc'] = 'ASTUSSE gateway base URL used by the API client (for example http://localhost:8888).';
$string['gateway_timeout_seconds'] = 'Gateway timeout (seconds)';
$string['gateway_timeout_seconds_desc'] = 'HTTP timeout in seconds for gateway API calls.';
$string['chat_history_ttl'] = 'ASTUSSE chat history retention';
$string['chat_history_ttl_desc'] = 'Requested backend retention for new chat conversations. Unlimited disables Redis expiration, but stored volume will keep growing over time.';
$string['chat_history_ttl_24h'] = '24 hours';
$string['chat_history_ttl_48h'] = '48 hours';
$string['chat_history_ttl_72h'] = '72 hours';
$string['chat_history_ttl_unlimited'] = 'Unlimited';
$string['key_directory'] = 'Key directory';
$string['key_directory_desc'] = 'Directory where RSA keys are stored.';
$string['rag_scope_heading'] = 'RAG scope policy';
$string['rag_scope_heading_desc'] = 'Global policy synced to ASTUSSE orchestration API. Admin defines the upper bound, trainers choose within that bound.';
$string['platform_scope_allowed'] = 'Enable platform-wide scope';
$string['platform_scope_allowed_desc'] = 'If disabled, trainers cannot select the platform scope.';
$string['delegation_enabled'] = 'Delegate scope choice to trainers';
$string['delegation_enabled_desc'] = 'If disabled, backend forces course scope for everyone.';
$string['rag_scope_sync_state_title'] = 'Synchronization and backend state';
$string['rag_scope_sync_status_pending'] = 'API synchronization: pending';
$string['rag_scope_sync_status_skipped'] = 'API synchronization: skipped (admin session required)';
$string['rag_scope_sync_status_ok'] = 'API synchronization: OK';
$string['rag_scope_sync_status_ko'] = 'API synchronization: failed';
$string['rag_scope_sync_never'] = 'never';
$string['rag_scope_sync_line'] = '{$a->status} — last attempt: {$a->time}.';
$string['rag_scope_backend_aligned'] = 'Backend is aligned with saved values.';
$string['rag_scope_backend_mismatch'] = 'Mismatch detected: local (platform={$a->localplatform}, delegation={$a->localdelegation}); backend (platform={$a->backendplatform}, delegation={$a->backenddelegation}). Save again to resynchronize.';
$string['rag_scope_backend_unavailable'] = 'Backend state unavailable: {$a}.';
$string['astusse:requesttoken'] = 'Request ASTUSSE JWT token';
$string['astusse:managetrainerscope'] = 'Manage ASTUSSE trainer scope from course';
$string['astusse:managereferencetrainer'] = 'Manage ASTUSSE reference trainer from course';
$string['astusse:ingestdocument'] = 'Ingest an ASTUSSE document from course';
$string['error:keysmissing'] = 'JWT keys are missing. Run the key generation CLI script.';
$string['error:tokenfailed'] = 'Token generation failed.';
$string['testpage:title'] = 'ASTUSSE JWT and API test';
$string['testpage:heading'] = 'ASTUSSE JWT and gateway validation';
$string['testpage:generate'] = 'Generate user JWT';
$string['testpage:ping'] = 'Call gateway /api/ping';
$string['testpage:intro'] = 'This page validates your JWT configuration and gateway connectivity.';
$string['testpage:jwks_label'] = 'JWKS endpoint';
$string['testpage:token_label'] = 'Token endpoint';
$string['testpage:gateway_label'] = 'Configured gateway';
$string['testpage:error_label'] = 'Error';
$string['testpage:result_label'] = 'Result';
$string['testpage:invalid_sesskey'] = 'Invalid sesskey.';
$string['trainerscope:menu'] = 'ASTUSSE IA scope';
$string['trainerscope:title'] = 'ASTUSSE trainer scope';
$string['trainerscope:heading'] = 'AI scope for your learners';
$string['trainerscope:intro'] = 'Choose which document perimeter ASTUSSE may use when answering your learners across your courses.';
$string['trainerscope:global_notice'] = 'This setting is trainer-global: changes apply to all your courses.';
$string['trainerscope:trainer_id'] = 'Trainer ID: {$a}';
$string['trainerscope:policy_title'] = 'Active policy';
$string['trainerscope:active_scope_line'] = 'Active scope for this trainer: {$a}';
$string['trainerscope:delegation_state'] = 'Delegation enabled: {$a}';
$string['trainerscope:platform_state'] = 'Platform scope allowed: {$a}';
$string['trainerscope:delegation_disabled'] = 'Admin delegation is disabled. Scope cannot be changed at trainer level.';
$string['trainerscope:label'] = 'AI scope for learners';
$string['trainerscope:label_help'] = 'Select the desired openness level. The backend then applies admin policy and the course reference trainer.';
$string['trainerscope:save_ok'] = 'Scope updated successfully.';
$string['trainerscope:save_button'] = 'Save';
$string['trainerscope:error_fetch'] = 'Unable to load trainer scope from API.';
$string['trainerscope:error_save'] = 'Unable to save trainer scope.';
$string['trainerscope:error_invalid_scope'] = 'Selected scope is not allowed by current admin policy.';
$string['trainerscope:available_options'] = 'Available options: {$a}';
$string['trainerscope:options_masked_note'] = 'Unauthorized options are hidden (never shown as disabled).';
$string['trainerscope:platform_hidden_note'] = '"Whole platform" option is hidden by admin policy.';
$string['trainerscope:scope_adjusted'] = 'Active scope was adjusted to "{$a}" because the previous value is no longer authorized.';
$string['trainerscope:scope_course_desc'] = 'Limits answers to the documents and resources of the current course.';
$string['trainerscope:scope_trainer_desc'] = 'Extends answers to all documents that you ingested for your courses.';
$string['trainerscope:scope_platform_desc'] = 'Also allows the use of documentary resources available at platform level.';
$string['scope:course'] = 'This course only';
$string['scope:trainer'] = 'All my courses';
$string['scope:platform'] = 'Whole platform';
$string['referencetrainer:menu'] = 'ASTUSSE reference trainer';
$string['referencetrainer:title'] = 'ASTUSSE reference trainer';
$string['referencetrainer:heading'] = 'Course reference trainer';
$string['referencetrainer:intro'] = 'Choose the reference trainer used by ASTUSSE to send the trainerId course context.';
$string['referencetrainer:label'] = 'Reference trainer';
$string['referencetrainer:label_help'] = 'Select the primary trainer for this course. ASTUSSE will use this identifier to send the trainerId context to the backend.';
$string['referencetrainer:none'] = 'No reference trainer';
$string['referencetrainer:state_valid'] = 'Trainer selected';
$string['referencetrainer:state_invalid'] = 'Invalid reference';
$string['referencetrainer:state_missing'] = 'No reference';
$string['referencetrainer:summary_course'] = 'Course';
$string['referencetrainer:summary_course_text'] = 'The selected trainer will be used only for this course.';
$string['referencetrainer:summary_candidates'] = 'Eligible trainers';
$string['referencetrainer:summary_candidates_text'] = 'Only users with the editingteacher role are listed.';
$string['referencetrainer:summary_current'] = 'Current trainer';
$string['referencetrainer:summary_current_text'] = 'This trainer is used as the ASTUSSE reference for learner chat.';
$string['referencetrainer:save_button'] = 'Save';
$string['referencetrainer:save_ok'] = 'The reference trainer was saved.';
$string['referencetrainer:clear_ok'] = 'The reference trainer was removed.';
$string['referencetrainer:error_invalid_selection'] = 'The selected trainer is not a valid editingteacher for this course.';
$string['referencetrainer:no_candidates'] = 'No editingteacher is currently available in this course.';
$string['referencetrainer:status_missing'] = 'No reference trainer is configured. Chat will use course fallback.';
$string['referencetrainer:status_invalid'] = 'The stored reference trainer is no longer valid for this course. Chat will use course fallback until the setting is fixed.';
$string['referencetrainer:status_valid'] = 'Current reference trainer: {$a}.';

$string['ingest:menu'] = 'Ingest ASTUSSE AI document';
$string['ingest:title'] = 'ASTUSSE document ingestion';
$string['ingest:heading'] = 'Ingest a document for RAG';
$string['ingest:intro'] = 'Upload a resource and link it to one or more courses. Trainer identity is automatically derived from your session.';
$string['ingest:reference_trainer_title'] = 'Course reference trainer';
$string['ingest:reference_trainer_valid'] = 'Configured: {$a}. This trainer will be used as trainerId context for course chat.';
$string['ingest:reference_trainer_invalid'] = 'Invalid: the stored trainer is no longer a valid editingteacher. Chat will use course fallback until this setting is fixed.';
$string['ingest:reference_trainer_missing'] = 'Not configured: chat will use course fallback until a reference trainer is set.';
$string['ingest:file_label'] = 'Document to ingest';
$string['ingest:file_help'] = 'Formats accepted by API: PDF, TXT, DOC, DOCX, Markdown, HTML (max 50 MB).';
$string['ingest:courses_label'] = 'Courses to link';
$string['ingest:courses_help'] = 'You can select multiple courses.';
$string['ingest:courses_search_label'] = 'Search a course';
$string['ingest:courses_search_placeholder'] = 'Search a course...';
$string['ingest:submit_button'] = 'Ingest document';
$string['ingest:save_ok'] = 'Document submitted for ingestion.';
$string['ingest:save_ok_job'] = 'Document submitted for ingestion. Job ID: {$a}';
$string['ingest:error_no_courses'] = 'Select at least one course.';
$string['ingest:error_no_file'] = 'Select a file to upload.';
$string['ingest:error_invalid_file'] = 'The uploaded file is invalid.';
$string['ingest:error_file_too_large'] = 'File exceeds local 50 MB limit.';
$string['ingest:error_upload'] = 'PHP upload failed (code {$a}).';
$string['ingest:error_submit'] = 'Unable to start ingestion.';
$string['ingest:error_http_400'] = 'The ingestion request is invalid. Check the file and selected courses.';
$string['ingest:error_http_401'] = 'Your ASTUSSE authentication session is expired or invalid. Reload the page and try again.';
$string['ingest:error_http_403'] = 'You are not allowed to run this ingestion.';
$string['ingest:error_http_404'] = 'Ingestion service was not found. Check gateway configuration.';
$string['ingest:error_http_408'] = 'The service took too long to respond. Try again in a moment.';
$string['ingest:error_http_413'] = 'The file is too large for the ingestion service.';
$string['ingest:error_http_429'] = 'Too many requests in a short time. Try again in a few minutes.';
$string['ingest:error_http_5xx'] = 'ASTUSSE service is temporarily unavailable. Please try again later.';
$string['ingest:error_http_unknown'] = 'HTTP {$a} error while running ingestion.';
$string['ingest:error_http_status_line'] = 'Gateway HTTP status: {$a}';
$string['ingest:error_traceid_line'] = 'Trace ID: {$a}';
$string['ingest:error_backend_message'] = 'Backend message: {$a}';
$string['ingest:error_exception_message'] = 'Technical detail: {$a}';
$string['ingest:error_no_available_courses'] = 'No course is available for ingestion with your current role.';
$string['ingest:result_status'] = 'Status: {$a}';
$string['ingest:result_http_status'] = 'Gateway HTTP: {$a}';
$string['ingest:result_jobid'] = 'Job ID: {$a}';
$string['ingest:result_traceid'] = 'Trace ID: {$a}';

$string['astusse:usechat'] = 'Use ASTUSSE chat from a course';
$string['chat:menu'] = 'ASTUSSE AI assistant';
$string['chat:global_menu'] = 'ASTUSSE';
$string['chat:title'] = 'ASTUSSE AI assistant';
$string['chat:global_title'] = 'ASTUSSE';
$string['chat:heading'] = 'Chat with ASTUSSE';
$string['chat:global_heading'] = 'Chat with ASTUSSE';
$string['chat:intro'] = 'Ask a question about your course and choose the tutoring mode that fits best.';
$string['chat:global_intro'] = 'Select a course, choose a tutoring mode, then start a conversation with ASTUSSE.';
$string['chat:brand_title'] = 'Your conversations';
$string['chat:agent_label'] = 'Response mode';
$string['chat:agent_explicatif'] = 'Explanatory';
$string['chat:agent_socratique'] = 'Socratic';
$string['chat:message_label'] = 'Your message';
$string['chat:message_placeholder'] = 'Example: can you explain this chapter with a concrete example?';
$string['chat:send_button'] = 'Send';
$string['chat:new_session_button'] = 'New conversation';
$string['chat:status_ready'] = 'Ready to help.';
$string['chat:status_loading'] = 'ASTUSSE is preparing a reply...';
$string['chat:empty_state'] = 'The conversation will appear here after your first message.';
$string['chat:student_label'] = 'You';
$string['chat:assistant_label'] = 'ASTUSSE';
$string['chat:error_generic'] = 'An error occurred. Please try again.';
$string['chat:error_invalid_request'] = 'Invalid chat request.';
$string['chat:error_invalid_sesskey'] = 'Invalid sesskey.';
$string['chat:error_message_required'] = 'Message must not be empty.';
$string['chat:error_agent_invalid'] = 'Selected agent type is invalid.';
$string['chat:error_session_required'] = 'Missing session ID.';
$string['chat:error_backend'] = 'ASTUSSE backend returned an error.';
$string['chat:error_invalid_json'] = 'The Moodle AJAX response is not valid JSON.';
$string['chat:traceid_label'] = 'Trace ID';
$string['chat:sessionid_label'] = 'Session';
$string['chat:course_context'] = 'Moodle course: {$a}';
$string['chat:course_context_label'] = 'Selected course';
$string['chat:reference_trainer_title'] = 'Reference trainer';
$string['chat:reference_trainer_context'] = 'Reference trainer: {$a}';
$string['chat:reference_trainer_missing'] = 'No reference trainer is configured. ASTUSSE is using course fallback.';
$string['chat:reference_trainer_invalid'] = 'The stored reference trainer is no longer valid for this course. ASTUSSE is using course fallback.';
$string['chat:history_notice'] = 'History is loaded from ASTUSSE on each page load.';
$string['chat:empty_state_detail'] = 'Course context is already sent to ASTUSSE. Ask your question directly to start the conversation.';
$string['chat:course_selector_label'] = 'Course';
$string['chat:course_selector_placeholder'] = 'Choose a course';
$string['chat:error_course_required'] = 'Choose a course before sending a message.';
$string['chat:course_locked_notice'] = 'This page keeps the current course locked.';
$string['chat:conversations_label'] = 'Conversations';
$string['chat:conversations_empty'] = 'No conversation yet for this course.';
$string['chat:conversations_empty_detail'] = 'Use "New conversation" or send a first message directly.';
$string['chat:delete_conversation_label'] = 'Delete';
$string['chat:delete_conversation_confirm'] = 'Delete this conversation permanently from ASTUSSE history and local cache?';
$string['chat:delete_conversation_status'] = 'Conversation deleted.';
$string['chat:loading_history'] = 'Loading ASTUSSE history...';
$string['chat:history_sync_failed'] = 'Unable to synchronize ASTUSSE history.';
$string['chat:history_deleted_remote'] = 'This conversation no longer exists in ASTUSSE.';
$string['chat:no_course_title'] = 'Choose a course to get started.';
$string['chat:no_course_detail'] = 'Global ASTUSSE chat needs a course to send the right documentary context.';
$string['chat:untitled_conversation'] = 'New conversation';
$string['chat:global_no_courses'] = 'No course is currently available for ASTUSSE chat.';
$string['chat:agent_used_label'] = 'Mode used';
$string['chat:technical_details_label'] = 'Technical details';
$string['chat:summary_aria'] = 'Conversation summary';
$string['chat:summary_mode'] = 'Mode';
$string['chat:summary_session'] = 'Session';
$string['chat:summary_messages'] = 'Messages';
$string['chat:summary_none'] = 'None';
$string['chat:pending_label'] = 'ASTUSSE is preparing a response...';
$string['chat:starter_label'] = 'Quick starters';
$string['chat:starter_understand_chapter'] = 'Can you explain this chapter with simple words?';
$string['chat:starter_quiz_revision'] = 'Help me revise before the next quiz.';
$string['chat:starter_step_by_step'] = 'Guide me step by step without giving the full answer.';
$string['chat:input_hint'] = 'Press Enter to send, Shift + Enter for a new line.';
