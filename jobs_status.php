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
 * JSON endpoint that reports the state of the current user's ingestion jobs
 * for a given course of origin. Used by the jobs page polling script.
 *
 * @package     local_astusse
 * @copyright   2026 Ingenium Digital Learning
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);
$coursecontext = context_course::instance($course->id);

require_login($course);
require_capability('local/astusse:ingestdocument', $coursecontext);

// Optional list of specific job IDs the client is currently displaying. When
// provided, return only those rows (scoped to the current user + course) —
// this keeps the polling payload minimal and paginated-aware.
$ids = optional_param_array('ids', [], PARAM_INT);
$ids = array_values(array_filter(array_map('intval', $ids), static function ($v) {
    return $v > 0;
}));

$columns = 'id, status, attempts, httpstatus, backendjobid, backendtraceid, '
    . 'errormessage, timecreated, timestarted, timecompleted, targetcourseids, sourcetype';

if (!empty($ids)) {
    if (count($ids) > 100) {
        $ids = array_slice($ids, 0, 100);
    }
    [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'jid');
    $where = 'userid = :userid AND courseid = :courseid AND id ' . $insql;
    $params = array_merge(['userid' => (int)$USER->id, 'courseid' => $courseid], $inparams);
    $jobs = $DB->get_records_select(
        'local_astusse_ingest_jobs',
        $where,
        $params,
        'timecreated DESC',
        $columns
    );
} else {
    $jobs = $DB->get_records(
        'local_astusse_ingest_jobs',
        ['userid' => (int)$USER->id, 'courseid' => $courseid],
        'timecreated DESC',
        $columns,
        0,
        100
    );
}

$payload = [];
foreach ($jobs as $job) {
    $desc = local_astusse_describe_ingest_job($job);
    $payload[] = [
        'id' => (int)$job->id,
        'status' => (string)$job->status,
        'statuslabel' => $desc['statuslabel'],
        'statusclass' => $desc['statusclass'],
        'attempts' => (int)$job->attempts,
        'httpstatus' => $job->httpstatus !== null ? (int)$job->httpstatus : null,
        'backendjobid' => $job->backendjobid !== null ? (string)$job->backendjobid : null,
        'backendtraceid' => $job->backendtraceid !== null ? (string)$job->backendtraceid : null,
        'errormessage' => $job->errormessage !== null ? (string)$job->errormessage : null,
        'timecompleted' => $job->timecompleted !== null ? (int)$job->timecompleted : null,
    ];
}

echo json_encode(['jobs' => $payload]);
