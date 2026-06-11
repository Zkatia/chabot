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
 * T5 — AJAX endpoint : snooze pop-ups pour 4h cote API IA.
 *
 * Source de verite serveur (spec T5) : la duree est figee cote API
 * (4h). Le plugin envoie juste l'action.
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
    echo json_encode(['ok' => false, 'reason' => 'guest']);
    die;
}

try {
    $client = new \local_astusse\api_client();
    $result = $client->snooze_for_user($USER);
} catch (\Throwable $e) {
    debugging('review_snooze: API call failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
    http_response_code(502);
    echo json_encode(['ok' => false, 'reason' => 'api_error']);
    die;
}

$status = (int)($result['status'] ?? 0);
if ($status !== 204 && $status !== 200) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'reason' => 'api_status_' . $status]);
    die;
}

// T5 (bypass post-snooze) : on garde un timestamp local d'expiration du snooze
// pour permettre au hook before_footer (lib.php) de re-injecter le JS quand
// l'apprenant continue de naviguer apres l'expiration des 4h. Sans cette pref,
// le garde-fou T2 "1 popup par login" empecherait le pop-up de reapparaitre
// sans logout/login. Source de verite reelle : l'API IA. Cette pref est juste
// un hint local pour eviter un round-trip API a chaque page.
//
// La duree 4h doit rester synchronisee avec ReviewControlService.DEFAULT_SNOOZE
// cote Java. Une desynchro de <1 sec est acceptable car l'API IA fait la
// verification finale (checkGlobalBlock).
set_user_preference('local_astusse_review_snooze_until', time() + 4 * 3600);

echo json_encode(['ok' => true]);
