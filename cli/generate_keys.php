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
 * CLI script to generate ASTUSSE RSA keys.
 *
 * @package     local_astusse
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/local/astusse/lib.php');

[$options, $unrecognized] = cli_get_params(
    [
        'help' => false,
        'key-size' => 2048,
        'force' => false,
    ],
    [
        'h' => 'help',
    ]
);

if (!empty($unrecognized)) {
    cli_error('Unknown options: ' . implode(', ', $unrecognized));
}

if ($options['help']) {
    $help = <<<EOF
Generate RSA keys for local_astusse.

Options:
--key-size=2048|3072   RSA key size (default: 2048)
--force                Overwrite existing keys
-h, --help             Show this help

Example:
php local/astusse/cli/generate_keys.php --key-size=3072 --force

EOF;
    echo $help;
    exit(0);
}

$keysize = (int)$options['key-size'];
$force = (bool)$options['force'];

if (!in_array($keysize, [2048, 3072], true)) {
    cli_error('Invalid key size. Use 2048 or 3072.');
}

$ok = local_astusse_generate_keys($keysize, $force);
if (!$ok) {
    cli_error('Key generation failed.');
}

cli_writeln('Keys generated successfully.');
cli_writeln('Private key: ' . local_astusse_get_private_key_path());
cli_writeln('Public key: ' . local_astusse_get_public_key_path());
