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
 * AJAX proxy (T3 etape 6) : finalise la session et recupere le bilan
 * (POST /api/review/record_quiz_result cote API).
 *
 * Le serveur enrichit le bilan avec les titres Moodle des modules (le plugin
 * connait les cmid mais le bilan UX a besoin de "Cours X — Ressource Y").
 *
 * @package     local_astusse
 * @copyright   2026 Ingenium Digital Learning
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
if (!preg_match('/^[0-9a-fA-F-]{36}$/', $quizsessionid)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_quiz_session_id']);
    die;
}

try {
    $client = new \local_astusse\api_client();
    $result = $client->finalize_quiz_for_user($USER, $quizsessionid);
} catch (\Throwable $e) {
    debugging('local_astusse quiz_result: ' . $e->getMessage(), DEBUG_DEVELOPER);
    http_response_code(502);
    echo json_encode(['error' => 'gateway_unavailable']);
    die;
}

$status = (int)($result['status'] ?? 0);
$body = is_array($result['body_json'] ?? null) ? $result['body_json'] : null;

// Enrichissement titres : les cmid -> "Cours X / Ressource Y" pour l'UX bilan.
if ($status === 200 && is_array($body) && !empty($body['perResource'])) {
    $titles = local_astusse_resolve_cmid_titles(array_map(function ($r) {
        return (int)($r['resourceCmid'] ?? 0);
    }, $body['perResource']));
    foreach ($body['perResource'] as &$r) {
        $cmid = (int)($r['resourceCmid'] ?? 0);
        if (isset($titles[$cmid])) {
            $r['resourceName'] = $titles[$cmid]['name'];
            $r['courseName']   = $titles[$cmid]['course'];
            $r['viewUrl']      = $titles[$cmid]['url'];
            $r['courseUrl']    = $titles[$cmid]['courseUrl'];
        }
    }
    unset($r);
    // Egalement pour la ressource fragile (raccourci UX).
    if (!empty($body['fragileResourceCmid']) && isset($titles[(int)$body['fragileResourceCmid']])) {
        $t = $titles[(int)$body['fragileResourceCmid']];
        $body['fragileResourceName'] = $t['name'];
        $body['fragileCourseName']   = $t['course'];
        $body['fragileViewUrl']      = $t['url'];
        $body['fragileCourseUrl']    = $t['courseUrl'];
    }
}

http_response_code(in_array($status, [200, 400, 404, 409, 410], true) ? $status : 502);
echo json_encode($body ?? ['error' => 'invalid_upstream_response']);
die;
