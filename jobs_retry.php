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
 * Re-queue a failed ingestion job belonging to the current user.
 *
 * @package     local_astusse
 * @copyright   2026 Ingenium Digital Learning
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$jobid = required_param('jobid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);

$course = get_course($courseid);
$coursecontext = context_course::instance($course->id);

require_login($course);
require_sesskey();
require_capability('local/astusse:ingestdocument', $coursecontext);

$returnurl = new moodle_url('/local/astusse/jobs.php', ['courseid' => $courseid]);

$job = $DB->get_record('local_astusse_ingest_jobs', ['id' => $jobid]);
if (!$job || (int)$job->userid !== (int)$USER->id || (int)$job->courseid !== $courseid) {
    \core\notification::error(get_string('jobs:retry_not_found', 'local_astusse'));
    redirect($returnurl);
}

if ($job->status !== 'failed') {
    \core\notification::warning(get_string('jobs:retry_not_failed', 'local_astusse'));
    redirect($returnurl);
}

if ($job->sourcetype === 'upload') {
    $usercontext = context_user::instance((int)$job->userid);
    $fs = get_file_storage();
    $files = $fs->get_area_files(
        $usercontext->id,
        'local_astusse',
        \local_astusse\task\ingest_document_task::FILEAREA,
        (int)$job->fileareaitemid,
        'id DESC',
        false
    );
    if (empty($files)) {
        \core\notification::error(get_string('jobs:retry_file_gone', 'local_astusse'));
        redirect($returnurl);
    }
}

$DB->update_record('local_astusse_ingest_jobs', (object)[
    'id' => (int)$job->id,
    'status' => 'queued',
    'attempts' => 0,
    'httpstatus' => null,
    'errormessage' => null,
    'timestarted' => null,
    'timecompleted' => null,
]);

$task = new \local_astusse\task\ingest_document_task();
$task->set_custom_data(['jobid' => (int)$job->id]);
$task->set_userid((int)$job->userid);
\core\task\manager::queue_adhoc_task($task);

\core\notification::success(get_string('jobs:retry_ok', 'local_astusse'));
redirect($returnurl);
