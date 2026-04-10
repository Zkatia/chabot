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
 * AJAX endpoint for ASTUSSE backend-backed chat history.
 *
 * @package     local_astusse
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

/**
 * JSON flags used by the history AJAX endpoint.
 *
 * @return int
 */
function local_astusse_chat_history_json_flags(): int {
    return JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;
}

/**
 * Send one JSON response and terminate execution.
 *
 * @param array $payload
 * @param int $statuscode
 * @return void
 */
function local_astusse_chat_history_send_json(array $payload, int $statuscode = 200): void {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($statuscode);
    }

    $json = json_encode($payload, local_astusse_chat_history_json_flags());
    if ($json === false) {
        error_log('local_astusse chat history JSON encoding failed: ' . json_last_error_msg());
        $json = '{"ok":false,"message":"Unexpected JSON encoding error."}';
    }

    echo $json;
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    local_astusse_chat_history_send_json([
        'ok' => false,
        'message' => get_string('chat:error_invalid_request', 'local_astusse'),
    ], 405);
}

$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);
$coursecontext = context_course::instance($course->id);

require_login($course);
require_capability('local/astusse:usechat', $coursecontext);

global $USER;

try {
    $client = new \local_astusse\api_client();

    if ($method === 'GET') {
        $sessionid = trim((string)optional_param('sessionid', '', PARAM_ALPHANUMEXT));
        if ($sessionid !== '') {
            $response = $client->get_chat_history_for_user($USER, $sessionid);
            $bodyjson = is_array($response['body_json'] ?? null) ? $response['body_json'] : [];

            $messages = [];
            if (!empty($bodyjson['messages']) && is_array($bodyjson['messages'])) {
                foreach ($bodyjson['messages'] as $message) {
                    if (!is_array($message)) {
                        continue;
                    }
                    $messages[] = [
                        'role' => (string)($message['role'] ?? ''),
                        'text' => (string)($message['text'] ?? ''),
                    ];
                }
            }

            local_astusse_chat_history_send_json([
                'ok' => true,
                'status' => (string)($bodyjson['status'] ?? 'OK'),
                'sessionId' => (string)($bodyjson['sessionId'] ?? $sessionid),
                'courseId' => (string)($bodyjson['courseId'] ?? $courseid),
                'title' => (string)($bodyjson['title'] ?? ''),
                'agentUsed' => (string)($bodyjson['agentUsed'] ?? ''),
                'updatedAt' => (int)($bodyjson['updatedAt'] ?? 0),
                'messageCount' => (int)($bodyjson['messageCount'] ?? count($messages)),
                'historyTtlSeconds' => (int)($bodyjson['historyTtlSeconds'] ?? 0),
                'traceId' => (string)($bodyjson['traceId'] ?? ''),
                'messages' => $messages,
            ]);
        }

        $response = $client->list_chat_sessions_for_user($USER, (string)$courseid);
        $bodyjson = is_array($response['body_json'] ?? null) ? $response['body_json'] : [];
        $sessions = [];
        if (!empty($bodyjson['sessions']) && is_array($bodyjson['sessions'])) {
            foreach ($bodyjson['sessions'] as $session) {
                if (!is_array($session)) {
                    continue;
                }
                $sessions[] = [
                    'sessionId' => (string)($session['sessionId'] ?? ''),
                    'courseId' => (string)($session['courseId'] ?? $courseid),
                    'agentUsed' => (string)($session['agentUsed'] ?? ''),
                    'title' => (string)($session['title'] ?? ''),
                    'updatedAt' => (int)($session['updatedAt'] ?? 0),
                    'messageCount' => (int)($session['messageCount'] ?? 0),
                ];
            }
        }

        local_astusse_chat_history_send_json([
            'ok' => true,
            'status' => (string)($bodyjson['status'] ?? 'OK'),
            'courseId' => (string)($bodyjson['courseId'] ?? $courseid),
            'historyTtlSeconds' => (int)($bodyjson['historyTtlSeconds'] ?? 0),
            'traceId' => (string)($bodyjson['traceId'] ?? ''),
            'sessions' => $sessions,
        ]);
    }

    $sesskey = optional_param('sesskey', '', PARAM_RAW);
    if ($sesskey === '' || !confirm_sesskey($sesskey)) {
        local_astusse_chat_history_send_json([
            'ok' => false,
            'message' => get_string('chat:error_invalid_sesskey', 'local_astusse'),
        ], 400);
    }

    $action = trim((string)optional_param('action', '', PARAM_ALPHA));
    $sessionid = trim((string)optional_param('sessionid', '', PARAM_ALPHANUMEXT));

    if ($action !== 'delete' || $sessionid === '') {
        local_astusse_chat_history_send_json([
            'ok' => false,
            'message' => get_string('chat:error_invalid_request', 'local_astusse'),
        ], 400);
    }

    $client->delete_chat_history_for_user($USER, $sessionid);
    local_astusse_chat_history_send_json([
        'ok' => true,
        'status' => 'DELETED',
        'sessionId' => $sessionid,
    ]);
} catch (\Throwable $e) {
    error_log('local_astusse chat history failed: ' . get_class($e) . ' - ' . $e->getMessage());
    $message = trim((string)$e->getMessage());
    if ($message === '') {
        $message = get_string('chat:error_generic', 'local_astusse');
    }
    $statuscode = strpos($message, 'HTTP 404') !== false ? 404 : 502;
    local_astusse_chat_history_send_json([
        'ok' => false,
        'message' => $message,
    ], $statuscode);
}
