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
 * AJAX endpoint (T2) : tells the front-end whether to show the review pop-up,
 * and returns the ready-to-display (already localised) texts.
 *
 * Defensive by design : any error / slowness returns hasPending=false so the
 * apprenant never sees a glitch.
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

/**
 * Helper : emit a "no pop-up" response and stop.
 *
 * @param string $reason
 */
function local_astusse_popup_none(string $reason): void {
    echo json_encode(['hasPending' => false, 'reason' => $reason]);
    die;
}

// Guests never get the pop-up.
if (isguestuser()) {
    local_astusse_popup_none('guest');
}

// Global opt-out (T5 preference ; default off).
if (get_user_preferences('local_astusse_review_optout', 0)) {
    local_astusse_popup_none('opt_out');
}

$recencydays = (int)get_config('local_astusse', 'review_recency_days');
if ($recencydays < 1) {
    $recencydays = 60;
}
$mineligible = (int)get_config('local_astusse', 'review_min_eligible');
if ($mineligible < 1) {
    $mineligible = 1;
}

try {
    $client = new \local_astusse\api_client();
    $result = $client->get_pending_review_for_user($USER, $recencydays, $mineligible);
} catch (\Throwable $e) {
    // API down/slow/unreachable → silent, no pop-up (defensive behaviour).
    local_astusse_popup_none('api_error');
}

$status = (int)($result['status'] ?? 0);
$body = is_array($result['body_json'] ?? null) ? $result['body_json'] : null;

if ($status !== 200 || $body === null || empty($body['hasPending'])) {
    local_astusse_popup_none(is_array($body) && isset($body['reason']) ? (string)$body['reason'] : 'not_pending');
}

// Compose the (localised) texts server-side from the counters.
$a = (object)[
    'name'       => fullname($USER),
    'consulted'  => (int)($body['consultedCount'] ?? 0),
    'courses'    => (int)($body['courseCount'] ?? 0),
    'reviewable' => (int)($body['reviewableCount'] ?? 0),
    'fragile'    => (int)($body['fragileCount'] ?? 0),
];

$reviewline = $a->fragile > 0
    ? get_string('popup:fragile', 'local_astusse', $a)
    : get_string('popup:toconsolidate', 'local_astusse', $a);

echo json_encode([
    'hasPending'    => true,
    'title'         => get_string('popup:title', 'local_astusse'),
    'greeting'      => get_string('popup:greeting', 'local_astusse', $a),
    'consultedLine' => get_string('popup:consulted', 'local_astusse', $a),
    'reviewLine'    => $reviewline,
    'pitch'         => get_string('popup:pitch', 'local_astusse'),
    'btnLaunch'     => get_string('popup:launch', 'local_astusse'),
    'btnLater'      => get_string('popup:later', 'local_astusse'),
    'btnClose'      => get_string('popup:close', 'local_astusse'),
]);
die;
