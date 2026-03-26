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
 * Public JWKS endpoint for ASTUSSE JWT verification.
 *
 * @package     local_astusse
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

header('Content-Type: application/json');

if (!local_astusse_keys_exist()) {
    http_response_code(500);
    echo json_encode([
        'error' => 'keys_not_configured',
        'error_description' => get_string('error:keysmissing', 'local_astusse'),
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

$keydetails = local_astusse_get_key_details();
if ($keydetails === false) {
    http_response_code(500);
    echo json_encode([
        'error' => 'key_retrieval_failed',
        'error_description' => 'Failed to retrieve public key details.',
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode([
    'keys' => [[
        'kty' => 'RSA',
        'use' => 'sig',
        'alg' => 'RS256',
        'kid' => local_astusse_get_active_kid(),
        'n' => $keydetails['n'],
        'e' => $keydetails['e'],
    ]],
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
