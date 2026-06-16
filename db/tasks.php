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
 * Scheduled tasks declaration for local_astusse.
 *
 * @package     local_astusse
 * @copyright   2026 Ingenium Digital Learning
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'local_astusse\task\cleanup_old_ingest_jobs',
        'blocking' => 0,
        'minute' => '17',
        'hour' => '3',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*',
    ],
    // T1 backfill : run BEFORE cleanup (3:17) so we still see jobs that are
    // about to be purged. Idempotent : a 2nd pass is a no-op API-side.
    [
        'classname' => 'local_astusse\task\backfill_rag_source_cmid',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '3',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*',
    ],
    // T1 : purge processed consultation-queue rows (>7 days).
    [
        'classname' => 'local_astusse\task\cleanup_consultation_queue',
        'blocking' => 0,
        'minute' => '37',
        'hour' => '3',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*',
    ],
];
