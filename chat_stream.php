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
 * SSE streaming proxy for ASTUSSE chat.
 *
 * @package     local_astusse
 * @copyright   2026 Ingenium Digital Learning
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
define('NO_MOODLE_COOKIES', false);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Set SSE headers before any Moodle output.
if (!headers_sent()) {
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache, no-store');
    header('X-Accel-Buffering: no'); // Tell nginx not to buffer.
    header('Connection: keep-alive');
}

// Disable output buffering so tokens reach the browser immediately.
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);
while (ob_get_level() > 0) {
    @ob_end_clean();
}
@ob_implicit_flush(true);

/**
 * Send one SSE data line and flush.
 *
 * @param string $jsondata JSON-encoded payload for the SSE data line.
 * @return void
 */
function local_astusse_stream_send(string $jsondata): void {
    echo 'data: ' . $jsondata . "\n\n";
    @ob_flush();
    @flush();
}

/**
 * JSON flags used by stream events.
 *
 * @return int
 */
function local_astusse_stream_json_flags(): int {
    return JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;
}

/**
 * Send an error event and terminate.
 *
 * @param string $message Human-readable error message to stream to the client.
 * @return void
 */
function local_astusse_stream_error(string $message): void {
    local_astusse_stream_send(json_encode(
        ['type' => 'error', 'message' => $message],
        local_astusse_stream_json_flags()
    ));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    local_astusse_stream_error('Method not allowed');
}

$courseid = required_param('courseid', PARAM_INT);
$course   = get_course($courseid);
$coursecontext = context_course::instance($course->id);

require_login($course);
require_capability('local/astusse:usechat', $coursecontext);

$sesskey = optional_param('sesskey', '', PARAM_RAW);
if ($sesskey === '' || !confirm_sesskey($sesskey)) {
    local_astusse_stream_error(get_string('chat:error_invalid_sesskey', 'local_astusse'));
}

$message   = trim((string)optional_param('message', '', PARAM_RAW));
$agenttype = trim((string)optional_param('agenttype', '', PARAM_ALPHA));
$sessionid = trim((string)optional_param('sessionid', '', PARAM_ALPHANUMEXT));

if ($message === '') {
    local_astusse_stream_error(get_string('chat:error_message_required', 'local_astusse'));
}
if (!in_array($agenttype, ['explicatif', 'socratique'], true)) {
    local_astusse_stream_error(get_string('chat:error_agent_invalid', 'local_astusse'));
}
if ($sessionid === '') {
    local_astusse_stream_error(get_string('chat:error_session_required', 'local_astusse'));
}

global $USER;

$referencecontext = local_astusse_get_reference_trainer_context($courseid);
$referencetrainerid = $referencecontext['trainerid'];

try {
    $client = new \local_astusse\api_client();
    $result = $client->stream_message_for_user(
        $USER,
        $message,
        $agenttype,
        $sessionid,
        (string)$courseid,
        $referencetrainerid,
        function (string $data): bool {
            if (connection_aborted()) {
                return false;
            }

            echo $data;
            @ob_flush();
            @flush();
            return true;
        }
    );

    if (!empty($result['aborted_by_callback']) || connection_aborted()) {
        exit;
    }

    $status = (int)($result['status'] ?? 0);
    $contenttype = (string)($result['content_type'] ?? '');
    $bodypreview = (string)($result['body_preview'] ?? '');

    if ($status < 200 || $status >= 300) {
        debugging('local_astusse stream upstream HTTP error: status=' . $status
            . ', body=' . substr($bodypreview, 0, 500), DEBUG_DEVELOPER);
        local_astusse_stream_send(json_encode([
            'type' => 'error',
            'status' => $status,
            'message' => 'Backend streaming request failed',
        ], local_astusse_stream_json_flags()));
        exit;
    }

    if (stripos($contenttype, 'text/event-stream') === false) {
        debugging('local_astusse stream unexpected content type: ' . $contenttype
            . ', body=' . substr($bodypreview, 0, 500), DEBUG_DEVELOPER);
        local_astusse_stream_send(json_encode([
            'type' => 'error',
            'message' => 'Backend did not return an event stream',
        ], local_astusse_stream_json_flags()));
        exit;
    }
} catch (\Throwable $e) {
    debugging('local_astusse stream failed: ' . get_class($e) . ' - ' . $e->getMessage(), DEBUG_DEVELOPER);
    local_astusse_stream_send(json_encode([
        'type' => 'error',
        'message' => 'Backend connection failed',
    ], local_astusse_stream_json_flags()));
}
