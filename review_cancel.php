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
 * T5 — AJAX endpoint : annule (cancel) les ressources listees dans le pop-up.
 *
 * Body: { "cmids": [N, N, ...] }
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
    echo json_encode(['ok' => false, 'reason' => 'guest']);
    die;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
$cmids = is_array($payload['cmids'] ?? null) ? $payload['cmids'] : [];

// Sanitization defensive : entiers positifs uniquement, max 50.
$cmids = array_values(array_filter(array_map('intval', $cmids), fn($v) => $v > 0));
$cmids = array_slice($cmids, 0, 50);

if (empty($cmids)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'cmids_required']);
    die;
}

try {
    $client = new \local_astusse\api_client();
    $result = $client->cancel_resources_for_user($USER, $cmids);
} catch (\Throwable $e) {
    debugging('review_cancel: API call failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
    http_response_code(502);
    echo json_encode(['ok' => false, 'reason' => 'api_error']);
    die;
}

$status = (int)($result['status'] ?? 0);
if ($status !== 200) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'reason' => 'api_status_' . $status]);
    die;
}

$affected = (int)($result['body_json']['affected'] ?? 0);
echo json_encode(['ok' => true, 'affected' => $affected]);
