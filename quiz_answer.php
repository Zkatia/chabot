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

/**
 * AJAX proxy (T3 etape 6) : envoie une reponse per-question
 * (POST /api/review/record_quiz_answer cote API).
 *
 * @package     local_astusse
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
require_sesskey();

header('Content-Type: application/json; charset=utf-8');

global $USER;

if (isguestuser()) {
    http_response_code(403);
    echo json_encode(['error' => 'guest']);
    die;
}

$quizsessionid = required_param('quizSessionId', PARAM_RAW_TRIMMED);
$questionid    = required_param('questionId', PARAM_RAW_TRIMMED);

if (!preg_match('/^[0-9a-fA-F-]{36}$/', $quizsessionid)
    || !preg_match('/^[0-9a-fA-F-]{36}$/', $questionid)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_uuid']);
    die;
}

$useranswerindex = optional_param('userAnswerIndex', null, PARAM_INT);
$useranswertext  = optional_param('userAnswerText', null, PARAM_RAW);
$responsetimems  = optional_param('responseTimeMs', 0, PARAM_INT);

if ($responsetimems < 0) {
    $responsetimems = 0;
}

try {
    $client = new \local_astusse\api_client();
    $result = $client->send_quiz_answer_for_user(
        $USER,
        $quizsessionid,
        $questionid,
        $useranswerindex,
        $useranswertext,
        $responsetimems
    );
} catch (\Throwable $e) {
    error_log('local_astusse quiz_answer: ' . $e->getMessage());
    http_response_code(502);
    echo json_encode(['error' => 'gateway_unavailable']);
    die;
}

$status = (int)($result['status'] ?? 0);
$body = is_array($result['body_json'] ?? null) ? $result['body_json'] : null;

http_response_code(in_array($status, [200, 400, 404, 409, 410], true) ? $status : 502);
echo json_encode($body ?? ['error' => 'invalid_upstream_response']);
die;
