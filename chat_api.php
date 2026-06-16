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
 * Shared AJAX endpoint for ASTUSSE chat pages.
 *
 * @package     local_astusse
 * @copyright   2026 Ingenium Digital Learning
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

/**
 * Return JSON encoding flags used by the chat AJAX endpoint.
 *
 * @return int
 */
function local_astusse_chat_json_flags(): int {
    return JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;
}

/**
 * Send a JSON response for the chat AJAX endpoint and terminate execution.
 *
 * @param array $payload
 * @param int $statuscode
 * @return void
 */
function local_astusse_chat_send_json(array $payload, int $statuscode = 200): void {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($statuscode);
    }

    $json = json_encode($payload, local_astusse_chat_json_flags());
    if ($json === false) {
        debugging('local_astusse chat JSON encoding failed: ' . json_last_error_msg(), DEBUG_DEVELOPER);
        $json = '{"ok":false,"message":"Unexpected JSON encoding error."}';
    }

    echo $json;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    local_astusse_chat_send_json([
        'ok' => false,
        'message' => get_string('chat:error_invalid_request', 'local_astusse'),
    ], 405);
}

$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);
$coursecontext = context_course::instance($course->id);

require_login($course);
require_capability('local/astusse:usechat', $coursecontext);

$sesskey = optional_param('sesskey', '', PARAM_RAW);
if ($sesskey === '' || !confirm_sesskey($sesskey)) {
    local_astusse_chat_send_json([
        'ok' => false,
        'message' => get_string('chat:error_invalid_sesskey', 'local_astusse'),
    ], 400);
}

$message = trim((string)optional_param('message', '', PARAM_RAW));
$agenttype = trim((string)optional_param('agenttype', '', PARAM_ALPHA));
$sessionid = trim((string)optional_param('sessionid', '', PARAM_ALPHANUMEXT));

if ($message === '') {
    local_astusse_chat_send_json([
        'ok' => false,
        'message' => get_string('chat:error_message_required', 'local_astusse'),
    ], 400);
}

if (!in_array($agenttype, ['explicatif', 'socratique'], true)) {
    local_astusse_chat_send_json([
        'ok' => false,
        'message' => get_string('chat:error_agent_invalid', 'local_astusse'),
    ], 400);
}

if ($sessionid === '') {
    local_astusse_chat_send_json([
        'ok' => false,
        'message' => get_string('chat:error_session_required', 'local_astusse'),
    ], 400);
}

$referencecontext = local_astusse_get_reference_trainer_context($courseid);
$referencetrainerid = $referencecontext['trainerid'];

try {
    global $USER;

    $client = new \local_astusse\api_client();
    $response = $client->send_message_for_user(
        $USER,
        $message,
        $agenttype,
        $sessionid,
        (string)$courseid,
        $referencetrainerid
    );

    $bodyjson = $response['body_json'] ?? null;
    $assistantmessage = '';
    if (is_array($bodyjson) && !empty($bodyjson['echo'])) {
        $assistantmessage = (string)$bodyjson['echo'];
    } else if (is_array($bodyjson) && !empty($bodyjson['response'])) {
        $assistantmessage = (string)$bodyjson['response'];
    }

    if ($assistantmessage === '') {
        throw new moodle_exception('chat:error_backend', 'local_astusse');
    }

    local_astusse_chat_send_json([
        'ok' => true,
        'status' => is_array($bodyjson) ? (string)($bodyjson['status'] ?? 'OK') : 'OK',
        'assistantMessage' => $assistantmessage,
        'sessionId' => is_array($bodyjson) ? (string)($bodyjson['sessionId'] ?? $sessionid) : $sessionid,
        'traceId' => is_array($bodyjson) ? (string)($bodyjson['traceId'] ?? '') : '',
        'agentUsed' => is_array($bodyjson) ? (string)($bodyjson['agentUsed'] ?? $agenttype) : $agenttype,
    ]);
} catch (\Throwable $e) {
    debugging('local_astusse chat send failed: ' . get_class($e) . ' - ' . $e->getMessage(), DEBUG_DEVELOPER);

    $errormessage = trim((string)$e->getMessage());
    if ($e instanceof moodle_exception && $errormessage !== '') {
        $errormessage = $e->getMessage();
    }

    if ($errormessage === '') {
        $errormessage = get_string('chat:error_generic', 'local_astusse');
    }

    local_astusse_chat_send_json([
        'ok' => false,
        'message' => $errormessage,
    ], 502);
}
