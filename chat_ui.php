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
 * Shared chat UI helpers for local_astusse.
 *
 * @package     local_astusse
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Build shared course metadata consumed by the chat UI.
 *
 * @param stdClass $course
 * @param array $referencestatus
 * @param bool $showreferencecontext
 * @param bool $locked
 * @return array
 */
function local_astusse_build_chat_course_meta(
    stdClass $course,
    array $referencestatus,
    bool $showreferencecontext,
    bool $locked
): array {
    $referencetext = '';
    if ($showreferencecontext) {
        if ($referencestatus['state'] === 'valid') {
            $referencetext = get_string('chat:reference_trainer_context', 'local_astusse', fullname($referencestatus['user']));
        } else if ($referencestatus['state'] === 'invalid') {
            $referencetext = get_string('chat:reference_trainer_invalid', 'local_astusse');
        } else {
            $referencetext = get_string('chat:reference_trainer_missing', 'local_astusse');
        }
    }

    return [
        'id' => (string)$course->id,
        'fullname' => format_string($course->fullname),
        'shortname' => format_string($course->shortname),
        'locked' => $locked,
        'referenceVisible' => $showreferencecontext,
        'referenceState' => (string)$referencestatus['state'],
        'referenceText' => $referencetext,
        'referenceTrainerId' => $referencestatus['state'] === 'valid' ? (string)$referencestatus['trainerid'] : '',
        'courseChatUrl' => (new moodle_url('/local/astusse/chat.php', ['courseid' => $course->id]))->out(false),
    ];
}

/**
 * Return common UI strings for local chat pages.
 *
 * @return array
 */
function local_astusse_get_chat_ui_strings(): array {
    return [
        'heading' => get_string('chat:heading', 'local_astusse'),
        'globalHeading' => get_string('chat:global_heading', 'local_astusse'),
        'intro' => get_string('chat:intro', 'local_astusse'),
        'globalIntro' => get_string('chat:global_intro', 'local_astusse'),
        'ready' => get_string('chat:status_ready', 'local_astusse'),
        'loading' => get_string('chat:status_loading', 'local_astusse'),
        'empty' => get_string('chat:empty_state', 'local_astusse'),
        'emptyDetail' => get_string('chat:empty_state_detail', 'local_astusse'),
        'studentLabel' => get_string('chat:student_label', 'local_astusse'),
        'assistantLabel' => get_string('chat:assistant_label', 'local_astusse'),
        'genericError' => get_string('chat:error_generic', 'local_astusse'),
        'traceIdLabel' => get_string('chat:traceid_label', 'local_astusse'),
        'sessionIdLabel' => get_string('chat:sessionid_label', 'local_astusse'),
        'agentUsedLabel' => get_string('chat:agent_used_label', 'local_astusse'),
        'technicalDetailsLabel' => get_string('chat:technical_details_label', 'local_astusse'),
        'pending' => get_string('chat:pending_label', 'local_astusse'),
        'invalidJson' => get_string('chat:error_invalid_json', 'local_astusse'),
        'messageLabel' => get_string('chat:message_label', 'local_astusse'),
        'messagePlaceholder' => get_string('chat:message_placeholder', 'local_astusse'),
        'sendButton' => get_string('chat:send_button', 'local_astusse'),
        'newSessionButton' => get_string('chat:new_session_button', 'local_astusse'),
        'historyNotice' => get_string('chat:history_notice', 'local_astusse'),
        'inputHint' => get_string('chat:input_hint', 'local_astusse'),
        'courseLabel' => get_string('chat:course_selector_label', 'local_astusse'),
        'coursePlaceholder' => get_string('chat:course_selector_placeholder', 'local_astusse'),
        'courseRequired' => get_string('chat:error_course_required', 'local_astusse'),
        'courseLocked' => get_string('chat:course_locked_notice', 'local_astusse'),
        'conversationsLabel' => get_string('chat:conversations_label', 'local_astusse'),
        'conversationsEmpty' => get_string('chat:conversations_empty', 'local_astusse'),
        'conversationsEmptyDetail' => get_string('chat:conversations_empty_detail', 'local_astusse'),
        'deleteConversationLabel' => get_string('chat:delete_conversation_label', 'local_astusse'),
        'deleteConversationConfirm' => get_string('chat:delete_conversation_confirm', 'local_astusse'),
        'deleteConversationStatus' => get_string('chat:delete_conversation_status', 'local_astusse'),
        'loadingHistory' => get_string('chat:loading_history', 'local_astusse'),
        'historySyncFailed' => get_string('chat:history_sync_failed', 'local_astusse'),
        'historyDeletedRemote' => get_string('chat:history_deleted_remote', 'local_astusse'),
        'noCourseTitle' => get_string('chat:no_course_title', 'local_astusse'),
        'noCourseDetail' => get_string('chat:no_course_detail', 'local_astusse'),
        'untitledConversation' => get_string('chat:untitled_conversation', 'local_astusse'),
        'courseContextLabel' => get_string('chat:course_context_label', 'local_astusse'),
        'referenceTrainerTitle' => get_string('chat:reference_trainer_title', 'local_astusse'),
    ];
}

/**
 * Render the shared chat UI.
 *
 * @param array $config
 * @return void
 */
function local_astusse_render_chat_ui(array $config): void {
    global $OUTPUT;

    $jsonflags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

    $isGlobal = ($config['pageMode'] === 'global');

    $templatecontext = [
        'pageMode'             => $config['pageMode'],
        'brandTitle'           => get_string('chat:brand_title', 'local_astusse'),
        'brandIntro'           => $isGlobal ? $config['strings']['globalIntro'] : $config['strings']['historyNotice'],
        'newSessionButton'     => $config['strings']['newSessionButton'],
        'conversationsLabel'   => $config['strings']['conversationsLabel'],
        'heading'              => $isGlobal ? $config['strings']['globalHeading'] : $config['strings']['heading'],
        'intro'                => $isGlobal ? $config['strings']['globalIntro'] : $config['strings']['intro'],
        'statusReady'          => $config['strings']['ready'],
        'courseContextLabel'   => $config['strings']['courseContextLabel'],
        'referenceTrainerTitle' => $config['strings']['referenceTrainerTitle'],
        'courseLabel'          => $config['strings']['courseLabel'],
        'agentLabel'           => get_string('chat:agent_label', 'local_astusse'),
        'agentExplicatif'      => $config['labels']['agents']['explicatif'],
        'agentSocratique'      => $config['labels']['agents']['socratique'],
        'messageLabel'         => $config['strings']['messageLabel'],
        'messagePlaceholder'   => $config['strings']['messagePlaceholder'],
        'inputHint'            => $config['strings']['inputHint'],
        'sendButton'           => $config['strings']['sendButton'],
        'jsonConfig'           => json_encode($config, $jsonflags),
        'markedCdnUrl'         => 'https://cdn.jsdelivr.net/npm/marked@9/marked.min.js',
        'chatAppJsUrl'         => (new moodle_url('/local/astusse/js/chat_app.js'))->out(false),
    ];

    echo $OUTPUT->render_from_template('local_astusse/chat_app', $templatecontext);
}
