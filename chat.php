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
$quizsessionid = optional_param('quizSessionId', '', PARAM_RAW_TRIMMED);
if ($quizsessionid !== '' && !preg_match('/^[0-9a-fA-F-]{36}$/', $quizsessionid)) {
    $quizsessionid = '';
}

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
        $PAGE->set_context(context_system::instance());
        $PAGE->set_url(new moodle_url('/local/astusse/chat.php'));
        $PAGE->set_pagelayout('standard');
        $PAGE->set_title(get_string('chat:global_title', 'local_astusse'));
        $PAGE->set_heading(get_string('chat:global_heading', 'local_astusse'));
        $PAGE->requires->css(new moodle_url('/local/astusse/styles.css'));

        echo $OUTPUT->header();
        echo html_writer::start_div('local-astusse-chat-empty');
        echo html_writer::start_div('local-astusse-chat-empty-card');
        echo html_writer::tag('span', 'ASTUSSE', ['class' => 'local-astusse-chat-empty-kicker']);
        echo html_writer::tag('h2', get_string('chat:empty_heading', 'local_astusse'),
            ['class' => 'local-astusse-chat-empty-title']);
        echo html_writer::tag('p', get_string('chat:empty_intro', 'local_astusse'),
            ['class' => 'local-astusse-chat-empty-intro']);
        echo html_writer::tag('p', get_string('chat:empty_explain', 'local_astusse'),
            ['class' => 'local-astusse-chat-empty-explain']);

        echo html_writer::start_div('local-astusse-chat-empty-actions');
        echo html_writer::link(
            new moodle_url('/my/'),
            get_string('chat:empty_cta_dashboard', 'local_astusse'),
            ['class' => 'btn btn-primary']
        );
        echo html_writer::link(
            new moodle_url('/course/'),
            get_string('chat:empty_cta_browse', 'local_astusse'),
            ['class' => 'btn btn-outline-secondary']
        );
        echo html_writer::end_div();

        echo html_writer::end_div(); // card
        echo html_writer::end_div(); // empty
        echo $OUTPUT->footer();
        exit;
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
    $PAGE->set_pagelayout('embedded');
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

// T3 etape 7 : si on arrive depuis le bouton "Demander au tuteur" du pop-up,
// on pre-remplit le textarea avec un brouillon construit a partir du contexte
// du quiz (questions ratees / imparfaites). Le tuteur saura l'enrichir cote API.
$defaults = ['agentType' => 'explicatif'];
if ($quizsessionid !== '') {
    $draft = local_astusse_build_quiz_tutor_draft($USER, $quizsessionid);
    if ($draft !== '') {
        $defaults['draftMessage'] = $draft;
        $defaults['quizSessionId'] = $quizsessionid;
    }
}

echo $OUTPUT->header();
local_astusse_render_chat_ui($config + [
    'endpoint'       => (new moodle_url('/local/astusse/chat_api.php'))->out(false),
    'streamEndpoint' => (new moodle_url('/local/astusse/chat_stream.php'))->out(false),
    'historyEndpoint' => (new moodle_url('/local/astusse/chat_history.php'))->out(false),
    'sesskey'        => sesskey(),
    'defaults'       => $defaults,
    'labels'         => [
        'agents' => [
            'explicatif' => get_string('chat:agent_explicatif', 'local_astusse'),
            'socratique'  => get_string('chat:agent_socratique', 'local_astusse'),
        ],
    ],
    'strings' => local_astusse_get_chat_ui_strings(),
]);
echo $OUTPUT->footer();
