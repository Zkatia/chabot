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
 * Protected token endpoint for ASTUSSE user JWT.
 *
 * @package     local_astusse
 * @copyright   2026 Ingenium Digital Learning
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

header('Content-Type: application/json');

require_login();

$sesskey = optional_param('sesskey', '', PARAM_RAW);
if (empty($sesskey)) {
    $sesskey = sesskey();
}

if (empty($sesskey) || !confirm_sesskey($sesskey)) {
    http_response_code(400);
    echo json_encode([
        'error' => 'missing_or_invalid_sesskey',
        'error_description' => 'A valid sesskey is required.',
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

$context = context_system::instance();
if (!has_capability('local/astusse:requesttoken', $context)) {
    http_response_code(403);
    echo json_encode([
        'error' => 'forbidden',
        'error_description' => 'You do not have permission to request a token.',
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

if (!local_astusse_keys_exist()) {
    http_response_code(500);
    echo json_encode([
        'error' => 'keys_not_configured',
        'error_description' => get_string('error:keysmissing', 'local_astusse'),
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

global $USER;
$token = local_astusse_generate_user_token($USER);
if ($token === false) {
    http_response_code(500);
    echo json_encode([
        'error' => 'token_generation_failed',
        'error_description' => get_string('error:tokenfailed', 'local_astusse'),
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

$ttl = (int)(get_config('local_astusse', 'ttl_seconds') ?: 900);
echo json_encode([
    'access_token' => $token,
    'token_type' => 'Bearer',
    'expires_in' => $ttl,
], JSON_UNESCAPED_SLASHES);
