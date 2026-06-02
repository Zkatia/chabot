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
 * AJAX proxy (T3 etape 6) : recupere les questions du quiz pre-genere a la
 * connexion (cf. POST /api/review/generate_interleaved_quiz cote API).
 *
 * Reverse-proxy minimaliste : valide la session Moodle, mint un JWT user, relaie
 * vers l'API, renvoie le JSON tel quel.
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
if ($quizsessionid === '' || !preg_match('/^[0-9a-fA-F-]{36}$/', $quizsessionid)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_quiz_session_id']);
    die;
}

try {
    $client = new \local_astusse\api_client();
    $result = $client->fetch_quiz_for_user($USER, $quizsessionid);
} catch (\Throwable $e) {
    error_log('local_astusse quiz_generate: ' . $e->getMessage());
    http_response_code(502);
    echo json_encode(['error' => 'gateway_unavailable']);
    die;
}

$status = (int)($result['status'] ?? 0);
$body = is_array($result['body_json'] ?? null) ? $result['body_json'] : null;

// Enrichit chaque question avec le nom du module et du cours pour affichage cote JS
// (sous-titre de l'etat 2 "Cours : <X>"). Une seule resolution batch des cmids.
if ($status === 200 && is_array($body) && !empty($body['questions'])) {
    $cmids = array_map(function ($q) { return (int)($q['resourceCmid'] ?? 0); }, $body['questions']);
    $titles = local_astusse_resolve_cmid_titles($cmids);
    foreach ($body['questions'] as &$q) {
        $cmid = (int)($q['resourceCmid'] ?? 0);
        if (isset($titles[$cmid])) {
            $q['resourceName'] = $titles[$cmid]['name'];
            $q['courseName']   = $titles[$cmid]['course'];
        }
    }
    unset($q);
}

// Propage le statut HTTP pour que le JS distingue 404 vs 200 GENERATING etc.
http_response_code(in_array($status, [200, 400, 404, 410], true) ? $status : 502);
echo json_encode($body ?? ['error' => 'invalid_upstream_response']);
die;
