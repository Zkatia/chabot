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
$string['ingest:upload_heading'] = 'Documents to index';
$string['ingest:upload_hint_multi'] = 'Drag and drop your files directly into the area below to send several at once. The Moodle file picker only allows one file at a time.';
$string['ingest:upload_hint_size'] = 'Maximum size per file: {$a} MB. Up to 10 files per submission.';
$string['ingest:file_help'] = 'Formats accepted by API: PDF, TXT, DOC, DOCX, Markdown, HTML (max 50 MB).';
$string['ingest:courses_label'] = 'Courses to link';
$string['ingest:courses_help'] = 'You can select multiple courses.';
$string['ingest:courses_search_label'] = 'Search a course';
$string['ingest:courses_search_placeholder'] = 'Search a course...';
$string['ingest:submit_button'] = 'Index';
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

$string['ingest:course_resources_heading'] = 'Course resources';
$string['ingest:course_resources_intro'] = 'Select course resources to index for RAG. Content will be extracted and sent to the backend.';
$string['ingest:course_resources_empty'] = 'No indexable resource found in this course (file, page, or SCORM).';
$string['ingest:course_resources_filter_all'] = 'All';
$string['ingest:course_resources_filter_resource'] = 'Files';
$string['ingest:course_resources_filter_page'] = 'Pages';
$string['ingest:course_resources_filter_scorm'] = 'SCORM';
$string['ingest:course_resources_filter_h5pactivity'] = 'H5P';
$string['ingest:course_resources_filter_url'] = 'URL';
$string['ingest:course_resources_filter_book'] = 'Books';
$string['ingest:course_resources_filter_glossary'] = 'Glossaries';
$string['ingest:course_resources_filter_lesson'] = 'Lessons';
$string['ingest:course_resources_filter_quiz'] = 'Quizzes';
$string['ingest:course_resources_filter_assign'] = 'Assignments';
$string['ingest:course_resources_filter_wiki'] = 'Wikis';
$string['ingest:course_resources_filter_folder'] = 'Folders';
$string['ingest:course_resources_select_all'] = 'Select all';
$string['ingest:course_resources_col_name'] = 'Resource';
$string['ingest:course_resources_col_type'] = 'Type';
$string['ingest:course_resources_col_section'] = 'Section';
$string['ingest:course_resources_submit'] = 'Index selected resources';
$string['ingest:course_resources_none_selected'] = 'Please select at least one resource to index.';
$string['ingest:course_resources_success'] = '{$a->ok} resource(s) indexed successfully.';
$string['ingest:course_resources_partial'] = '{$a->ok} resource(s) indexed, {$a->fail} failed.';
$string['ingest:course_resources_all_failed'] = 'All selected resources failed to index.';
$string['ingest:course_resources_extract_failed'] = 'Could not extract content from "{$a}".';
$string['ingest:course_resources_type_resource'] = 'File';
$string['ingest:course_resources_type_page'] = 'Page';
$string['ingest:course_resources_type_scorm'] = 'SCORM';
$string['ingest:course_resources_type_h5pactivity'] = 'H5P';
$string['ingest:course_resources_type_url'] = 'URL';
$string['ingest:course_resources_type_book'] = 'Book';
$string['ingest:course_resources_type_glossary'] = 'Glossary';
$string['ingest:course_resources_type_lesson'] = 'Lesson';
$string['ingest:course_resources_type_quiz'] = 'Quiz';
$string['ingest:course_resources_type_assign'] = 'Assignment';
$string['ingest:course_resources_type_wiki'] = 'Wiki';
$string['ingest:course_resources_type_folder'] = 'Folder';
$string['ingest:folder_empty_skipped'] = 'The folder contains no indexable file.';
$string['lesson:correctanswerlabel'] = 'Expected answer(s):';
$string['quiz:correctanswerlabel'] = 'Expected answer(s):';
$string['quiz:questionnumber'] = 'Question {$a}';
$string['book:empty_no_chapters'] = 'The book contains no visible chapter. Indexing is impossible: add at least one chapter and retry.';
$string['glossary:empty_no_entries'] = 'The glossary contains no approved entry. Indexing is impossible: add and approve at least one entry, then retry.';
$string['lesson:empty_no_pages'] = 'The lesson contains no page. Indexing is impossible: add at least one page, then retry.';
$string['quiz:empty_no_questions'] = 'The quiz contains no question. Indexing is impossible: add at least one question to the question bank, then retry.';
$string['quiz:empty_no_usable_questions'] = 'The quiz only contains random or description questions, which cannot be indexed. Add multiple-choice, true/false, matching or similar question types.';
$string['assign:empty_no_instructions'] = 'The assignment has no description and no activity instructions. Indexing is impossible: add text in the description or instructions, then retry.';
$string['wiki:empty_no_pages'] = 'The wiki contains no page with content. Indexing is impossible: create at least one page with content, then retry.';
$string['folder:missing_fileid'] = 'The folder job has no file reference (missing fileareaitemid).';
$string['folder:file_missing'] = 'The file "{$a}" no longer exists in the source folder. Indexing is impossible.';
$string['ingest:scorm_proxy_detected'] = 'This package (SCORM or AICC) loads its content from a remote server ({$a}) and contains no pedagogical text locally. Indexing is not possible. Ask the content provider for a textual export.';
$string['ingest:aicc_remote_hint'] = 'Note: the main pedagogical content (video, reading) is hosted on {$a} and is not included in the package. Only metadata (title, description) has been indexed.';

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
$string['chat:back_to_moodle'] = 'Back';
$string['chat:agent_label'] = 'Mode:';
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
$string['chat:empty_heading'] = 'Welcome to ASTUSSE';
$string['chat:empty_intro'] = 'ASTUSSE supports your online learning progress.';
$string['chat:empty_explain'] = 'You are not currently enrolled in any course on this platform. Once enrolled, ASTUSSE will be able to help you understand course content, review and ask questions.';
$string['chat:empty_cta_dashboard'] = 'Back to dashboard';
$string['chat:empty_cta_browse'] = 'Browse courses';
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
$string['chat:brand_subtitle'] = 'Tutor · Ingenium';
$string['chat:empty_greeting'] = 'Hello {$a}, where shall we start?';
$string['chat:empty_course_loaded'] = 'Context loaded for {$a}. Pick a tutoring mode, then ask your question.';
$string['chat:empty_course_needed'] = 'Select a course above to give ASTUSSE the right documentary context, then pick your mode.';
$string['chat:agent_explicatif_role'] = 'The Expert';
$string['chat:agent_explicatif_desc'] = 'Provides clear, structured answers and explains concepts directly.';
$string['chat:agent_socratique_role'] = 'The Guide';
$string['chat:agent_socratique_desc'] = 'Guides you through progressive questioning so you can reason by yourself.';
$string['chat:copy_label'] = 'Copy';
$string['chat:copied_label'] = 'Copied!';
$string['chat:theme_toggle'] = 'Toggle theme';
$string['chat:open_sidebar'] = 'Open sidebar';
$string['chat:close_sidebar'] = 'Close sidebar';
$string['chat:courses_available'] = 'Available courses';
$string['chat:suggestions_label'] = 'Suggestions to get started';
$string['chat:suggestion_explain_example'] = 'Explain this chapter to me with a concrete example';
$string['chat:suggestion_primary_foreign_key'] = 'What is the difference between a primary and a foreign key?';
$string['chat:suggestion_quiz_revision'] = 'Help me revise for the next quiz';
$string['chat:suggestion_reformulate'] = 'Rephrase this concept more simply';
$string['chat:key_enter'] = 'Enter';
$string['chat:key_shift'] = 'Shift';
$string['chat:hint_send'] = 'to send';
$string['chat:hint_newline'] = 'for a new line';
$string['chat:search_placeholder'] = 'Search a conversation…';
$string['chat:search_no_results'] = 'No conversation matches your search.';

// Ingestion jobs tracking page.
$string['jobs:title'] = 'ASTUSSE ingestion queue';
$string['jobs:heading'] = 'Ingestion tracking';
$string['jobs:intro'] = 'State of the documents you have sent to the ASTUSSE RAG.';
$string['jobs:empty'] = 'No ingestion job to track for this course yet.';
$string['jobs:empty_filtered'] = 'No job matches the applied filters. Try broadening your search.';
$string['jobs:filters_heading'] = 'Search and filters';
$string['jobs:filter_jobid'] = 'ID / Trace ID';
$string['jobs:filter_jobid_placeholder'] = 'local #, backend UUID or trace';
$string['jobs:filter_targetcourse'] = 'Target course';
$string['jobs:filter_sourcetype'] = 'Source type';
$string['jobs:filter_status'] = 'Status';
$string['jobs:filter_any'] = 'Any';
$string['jobs:filter_apply'] = 'Filter';
$string['jobs:filter_reset'] = 'Reset';
$string['jobs:filter_total'] = '{$a->count} result(s) — showing {$a->from} to {$a->to}';
$string['jobs:link_from_ingest'] = 'View my ingestion queue';
$string['jobs:hero_active_count'] = '{$a} ingestion(s) in progress';
$string['jobs:hero_failed_count'] = '{$a} failed ingestion(s)';
$string['jobs:hero_idle'] = 'No ingestion in progress';
$string['jobs:hero_cta'] = 'View tracking →';
$string['jobs:link_back_to_ingest'] = 'Back to ingestion page';
$string['jobs:refresh_now'] = 'Refresh';
$string['jobs:col_created'] = 'Submitted at';
$string['jobs:col_file'] = 'Document';
$string['jobs:col_source'] = 'Source';
$string['jobs:col_targets'] = 'Target courses';
$string['jobs:col_status'] = 'Status';
$string['jobs:col_attempts'] = 'Attempts';
$string['jobs:col_details'] = 'Details';
$string['jobs:col_actions'] = 'Actions';
$string['jobs:status_queued'] = 'Queued';
$string['jobs:status_running'] = 'Running';
$string['jobs:status_succeeded'] = 'Succeeded';
$string['jobs:status_failed'] = 'Failed';
$string['jobs:source_upload'] = 'Uploaded file';
$string['jobs:cell_traceid'] = 'Trace ID: {$a}';
$string['jobs:cell_backend_jobid'] = 'Backend job ID: {$a}';
$string['jobs:cell_http_status'] = 'HTTP {$a}';
$string['jobs:action_retry'] = 'Retry';
$string['jobs:queued_notification'] = '{$a->count} document(s) queued for ingestion. Cron will process them.';
$string['jobs:skipped_notification'] = 'Some documents could not be queued:';
$string['jobs:retry_ok'] = 'Ingestion re-queued.';
$string['jobs:retry_not_found'] = 'Ingestion job not found or not authorised.';
$string['jobs:retry_not_failed'] = 'This job is not in a failed state; nothing to retry.';
$string['jobs:retry_file_gone'] = 'The file associated to this job is no longer available. Cannot retry.';

// Scheduled task names.
$string['task:cleanup_old_ingest_jobs'] = 'ASTUSSE — Purge finalised ingestion jobs (>30 days)';
$string['task:backfill_rag_source_cmid'] = 'ASTUSSE — Backfill source cmid on legacy RAG documents (T1)';
$string['task:cleanup_consultation_queue'] = 'ASTUSSE — Purge processed consultation queue rows (T1, >7 days)';

// T2 — Spaced repetition settings.
$string['review_heading'] = 'Spaced repetition';
$string['review_heading_desc'] = 'Settings for the review pop-up proposed at the learner\'s login.';
$string['review_recency_days'] = 'Recency window (days)';
$string['review_recency_days_desc'] = 'A resource consulted within the last N days becomes a candidate for a first review quiz (bootstrap). Does not limit long-term review, which FSRS schedules over months/years. Default: 60 (~2 months).';
$string['review_min_eligible'] = 'Minimum number of resources';
$string['review_min_eligible_desc'] = 'Minimum number of eligible resources to trigger the pop-up. Default: 1.';

$string['review_max_resources_per_quiz'] = 'Maximum resources per quiz';
$string['review_max_resources_per_quiz_desc'] = 'Maximum number of resources covered by one quiz (5 questions spread across them). Above the cap, only the most recently consulted are kept. Server clamps to [2, 5]. Default: 3.';

// T2 — Proposal pop-up (State 1).
$string['popup:title'] = '💡 Suggested review';
$string['popup:greeting'] = 'Hello {$a->name},';
$string['popup:consulted'] = 'You consulted {$a->consulted} resources across {$a->courses} courses recently.';
$string['popup:fragile'] = '⚠ {$a->fragile} concepts are below 90% predicted retention.';
$string['popup:toconsolidate'] = '{$a->reviewable} resources would benefit from consolidation.';
$string['popup:pitch'] = 'An interleaved quiz (5 questions, ~3 min) would strengthen your memory.';
$string['popup:launch'] = 'Start';
$string['popup:later'] = 'Later';
$string['popup:close'] = 'Cancel';

// T5 — Confirmation modal "Cancel these resources".
$string['popup:cancel_confirm'] = 'Do you really want to stop seeing these resources in your future review reminders? You can reactivate them at any time from your profile.';

// T5 — Review preferences profile page.
$string['prefs:title'] = 'Review preferences';
$string['prefs:heading'] = 'My spaced-repetition review preferences';
$string['prefs:api_error'] = 'The review service is currently unreachable. Your preferences will appear as soon as it is back.';
$string['prefs:global_heading'] = 'Enable or disable reminders';
$string['prefs:global_intro'] = 'When reminders are disabled, you will never see a review pop-up again. Your consultations keep being tracked silently: upon re-enabling, the system resumes where it left off.';
$string['prefs:snoozed_until'] = 'Reminders are paused until {$a}.';
$string['prefs:disable_button'] = 'Disable reminders';
$string['prefs:enable_button'] = 'Enable reminders';
$string['prefs:resources_heading'] = 'My set-aside resources';
$string['prefs:resources_empty'] = 'No resource is currently cancelled or mastered. All your consulted resources may appear in a future quiz.';
$string['prefs:col_resource'] = 'Resource';
$string['prefs:col_course'] = 'Course';
$string['prefs:col_state'] = 'Status';
$string['prefs:col_action'] = 'Action';
$string['prefs:state_mastered'] = 'Mastered';
$string['prefs:state_cancelled'] = 'Cancelled by me';
$string['prefs:state_mastered_at'] = 'Mastered on {$a}';
$string['prefs:state_cancelled_at'] = 'Cancelled on {$a}';
$string['prefs:reactivate_button'] = 'Reactivate';
// T5 — Profile page UX refresh (v2026060925).
$string['prefs:status_active'] = 'Reminders on';
$string['prefs:status_disabled'] = 'Reminders off';
$string['prefs:hero_title'] = 'Review pop-ups at login';
$string['prefs:hero_desc_enabled'] = 'You receive a reminder at login to review your recently consulted resources. You can disable reminders at any time — your consultations will still be tracked silently for when you turn them back on.';
$string['prefs:hero_desc_disabled'] = 'No review pop-ups are shown to you. Your consultations are still recorded: when you re-enable reminders, the system picks up where it left off.';
$string['prefs:snooze_active_title'] = 'Reminders paused';
$string['prefs:snooze_active_desc'] = 'Automatic resume on {$a}.';
$string['prefs:cancelled_heading'] = 'Resources set aside';
$string['prefs:cancelled_empty'] = 'You haven\'t set aside any resource from the review cycle yet.';
$string['prefs:mastered_heading'] = 'Mastered resources';
$string['prefs:mastered_empty'] = 'No resource marked as mastered yet. Three consecutive successful quiz sessions on a resource will move it here automatically.';
$string['prefs:notif_enabled'] = 'Review reminders have been turned back on.';
$string['prefs:notif_disabled'] = 'Review reminders have been turned off.';
$string['prefs:notif_reactivated'] = 'This resource is back in your review cycle.';
$string['prefs:notif_error'] = 'Something went wrong. Please try again in a moment.';

// T3 — Quiz (States 2, 3, 4).
$string['quiz:loading'] = 'Preparing questions…';
$string['quiz:waiting_generation'] = 'Generating, a few more seconds…';
$string['quiz:question_progress'] = 'Question {$a->current} of {$a->total}';
$string['quiz:libre_placeholder'] = 'Write your answer…';
$string['quiz:validate'] = 'Submit answer';
$string['quiz:next'] = 'Next question';
$string['quiz:see_result'] = 'See the summary';
$string['quiz:feedback_correct'] = 'Correct';
$string['quiz:feedback_incorrect'] = 'Incorrect';
$string['quiz:feedback_pending'] = 'Answer saved, evaluation deferred to the final summary.';
$string['quiz:correct_answer_qcm'] = 'Correct answer: {$a}';
$string['quiz:correct_answer_libre'] = 'Expected answer: {$a}';
$string['quiz:error_load'] = 'Unable to load the quiz right now. Please try again later.';
$string['quiz:error_send'] = 'Submission error. Check your connection and retry.';
$string['quiz:error_generating_timeout'] = 'Generation is taking longer than expected. Please retry shortly.';
$string['quiz:error_expired'] = 'This review session has expired. Come back tomorrow.';
$string['quiz:error_failed'] = 'Generation failed on the server side. Please retry later.';

// T3 — Final summary (State 4).
$string['bilan:title'] = 'Session summary';
$string['bilan:score'] = '{$a->correct} out of {$a->total} correct answers';
$string['bilan:consolidation'] = '✅ Memory consolidated. Next review in {$a} days.';
$string['bilan:partial'] = 'You have grasped the essentials. One resource would benefit from a review.';
$string['bilan:weak'] = 'Several key points need rework. The AI tutor can help.';
$string['bilan:see_resource'] = 'View the resource';
$string['bilan:ask_tutor'] = 'Ask the tutor';
$string['bilan:finish'] = 'Finish';
$string['bilan:perresource_label'] = 'Per resource:';
$string['bilan:perresource_line'] = '{$a->name} ({$a->course}) — {$a->correct}/{$a->total}';

// T3 — "Ask the tutor" pre-filled draft.
$string['tutor:draft_intro'] = 'Help me understand these points I struggled with during my quiz:';
$string['tutor:draft_intro_allcorrect'] = 'I just completed a review quiz. Could you suggest deeper material on the key concepts?';
$string['tutor:draft_my_answer'] = 'My answer: {$a->answer}';
$string['tutor:draft_verdict_incorrect'] = '(Incorrect answer)';
$string['tutor:draft_verdict_pending'] = '(Answer pending evaluation)';
