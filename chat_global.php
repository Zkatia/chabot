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
 * Global chat page for local_astusse.
 *
 * @package     local_astusse
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/chat_ui.php');

require_login();

$courses = local_astusse_get_chat_accessible_courses($USER);
if (!$courses) {
    throw new moodle_exception('chat:global_no_courses', 'local_astusse');
}

$coursemetas = [];
foreach ($courses as $courseinfo) {
    $coursecontext = $courseinfo['context'];
    $course = get_course((int)$courseinfo['id']);
    $referencestatus = \local_astusse\reference_trainer_service::get_status((int)$course->id);
    $showreferencecontext = has_capability('local/astusse:managereferencetrainer', $coursecontext);
    $coursemetas[] = local_astusse_build_chat_course_meta(
        $course,
        $referencestatus,
        $showreferencecontext,
        false
    );
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/astusse/chat_global.php'));
$PAGE->set_pagelayout('mydashboard');
$PAGE->set_title(get_string('chat:global_title', 'local_astusse'));
$PAGE->set_heading(get_string('chat:global_heading', 'local_astusse'));
$PAGE->requires->css(new moodle_url('/local/astusse/styles.css'));

echo $OUTPUT->header();
local_astusse_render_chat_ui([
    'pageMode' => 'global',
    'endpoint' => (new moodle_url('/local/astusse/chat_api.php'))->out(false),
    'sesskey' => sesskey(),
    'storageKey' => 'local_astusse_chat_threads_v4',
    'lockedCourse' => false,
    'selectedCourseId' => '',
    'courses' => $coursemetas,
    'defaults' => [
        'agentType' => 'explicatif',
    ],
    'labels' => [
        'agents' => [
            'explicatif' => get_string('chat:agent_explicatif', 'local_astusse'),
            'socratique' => get_string('chat:agent_socratique', 'local_astusse'),
        ],
    ],
    'strings' => local_astusse_get_chat_ui_strings(),
]);
echo $OUTPUT->footer();
