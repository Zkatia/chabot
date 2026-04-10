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
 * Unified chat page for local_astusse.
 *
 * With ?courseid=X  → course mode (locked to that course).
 * Without courseid  → global mode (all accessible courses).
 *
 * @package     local_astusse
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/chat_ui.php');

$courseid = optional_param('courseid', 0, PARAM_INT);

if ($courseid > 0) {
    // ── Course mode ──────────────────────────────────────────────────────────
    $course = get_course($courseid);
    $coursecontext = context_course::instance($course->id);

    require_login($course);
    require_capability('local/astusse:usechat', $coursecontext);

    $referencecontext   = local_astusse_get_reference_trainer_context($courseid);
    $referencestatus    = $referencecontext['status'];
    $showreferencecontext = has_capability('local/astusse:managereferencetrainer', $coursecontext);

    $PAGE->set_context($coursecontext);
    $PAGE->set_url(new moodle_url('/local/astusse/chat.php', ['courseid' => $courseid]));
    $PAGE->set_pagelayout('incourse');
    $PAGE->set_title(get_string('chat:title', 'local_astusse'));
    $PAGE->set_heading(format_string($course->fullname));
    $PAGE->requires->css(new moodle_url('/local/astusse/styles.css'));

    $courses = [local_astusse_build_chat_course_meta($course, $referencestatus, $showreferencecontext, true)];
    $config = [
        'pageMode'        => 'course',
        'lockedCourse'    => true,
        'selectedCourseId' => (string)$courseid,
        'courses'         => $courses,
    ];

} else {
    // ── Global mode ───────────────────────────────────────────────────────────
    require_login();

    $accessiblecourses = local_astusse_get_chat_accessible_courses($USER);
    if (!$accessiblecourses) {
        throw new moodle_exception('chat:global_no_courses', 'local_astusse');
    }

    $coursemetas = [];
    foreach ($accessiblecourses as $courseinfo) {
        $coursecontext = $courseinfo['context'];
        $course        = get_course((int)$courseinfo['id']);
        $referencecontext     = local_astusse_get_reference_trainer_context((int)$course->id);
        $referencestatus      = $referencecontext['status'];
        $showreferencecontext = has_capability('local/astusse:managereferencetrainer', $coursecontext);
        $coursemetas[] = local_astusse_build_chat_course_meta($course, $referencestatus, $showreferencecontext, false);
    }

    $PAGE->set_context(context_system::instance());
    $PAGE->set_url(new moodle_url('/local/astusse/chat.php'));
    $PAGE->set_pagelayout('mydashboard');
    $PAGE->set_title(get_string('chat:global_title', 'local_astusse'));
    $PAGE->set_heading(get_string('chat:global_heading', 'local_astusse'));
    $PAGE->requires->css(new moodle_url('/local/astusse/styles.css'));

    $config = [
        'pageMode'        => 'global',
        'lockedCourse'    => false,
        'selectedCourseId' => '',
        'courses'         => $coursemetas,
    ];
}

echo $OUTPUT->header();
local_astusse_render_chat_ui($config + [
    'endpoint'       => (new moodle_url('/local/astusse/chat_api.php'))->out(false),
    'streamEndpoint' => (new moodle_url('/local/astusse/chat_stream.php'))->out(false),
    'historyEndpoint' => (new moodle_url('/local/astusse/chat_history.php'))->out(false),
    'sesskey'        => sesskey(),
    'defaults'       => ['agentType' => 'explicatif'],
    'labels'         => [
        'agents' => [
            'explicatif' => get_string('chat:agent_explicatif', 'local_astusse'),
            'socratique'  => get_string('chat:agent_socratique', 'local_astusse'),
        ],
    ],
    'strings' => local_astusse_get_chat_ui_strings(),
]);
echo $OUTPUT->footer();
