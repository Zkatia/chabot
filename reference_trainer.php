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
 * Course reference trainer page for local_astusse.
 *
 * @package     local_astusse
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);
$coursecontext = context_course::instance($course->id);

require_login($course);
require_capability('local/astusse:managereferencetrainer', $coursecontext);

$PAGE->set_context($coursecontext);
$PAGE->set_url(new moodle_url('/local/astusse/reference_trainer.php', ['courseid' => $courseid]));
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('referencetrainer:title', 'local_astusse'));
$PAGE->set_heading(format_string($course->fullname));

$candidateoptions = \local_astusse\reference_trainer_service::get_candidate_options($courseid);
$status = \local_astusse\reference_trainer_service::get_status($courseid);

$notice = '';
$noticetype = '';
$error = '';

if (data_submitted() && confirm_sesskey()) {
    require_sesskey();

    $selectedtrainerid = optional_param('trainerid', 0, PARAM_INT);

    try {
        $traineridtosave = $selectedtrainerid > 0 ? $selectedtrainerid : null;
        \local_astusse\reference_trainer_service::save_reference_trainer($courseid, $traineridtosave, (int)$USER->id);
        $status = \local_astusse\reference_trainer_service::get_status($courseid);
        if ($traineridtosave === null) {
            $notice = get_string('referencetrainer:clear_ok', 'local_astusse');
        } else {
            $notice = get_string('referencetrainer:save_ok', 'local_astusse');
        }
        $noticetype = 'notifysuccess';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$selectoptions = [0 => get_string('referencetrainer:none', 'local_astusse')] + $candidateoptions;
$currenttrainerid = $status['state'] === 'missing' ? 0 : (int)($status['trainerid'] ?? 0);
$candidatecount = count($candidateoptions);
$currenttrainerlabel = get_string('referencetrainer:none', 'local_astusse');
if (!empty($status['user'])) {
    $currenttrainerlabel = fullname($status['user']);
} else if ($status['state'] === 'invalid' && !empty($status['trainerid'])) {
    $currenttrainerlabel = get_string('referencetrainer:state_invalid', 'local_astusse');
}

$statelabelkey = 'referencetrainer:state_missing';
if ($status['state'] === 'valid') {
    $statelabelkey = 'referencetrainer:state_valid';
} else if ($status['state'] === 'invalid') {
    $statelabelkey = 'referencetrainer:state_invalid';
}

echo $OUTPUT->header();

if ($notice !== '') {
    echo $OUTPUT->notification($notice, $noticetype);
}
if ($error !== '') {
    echo $OUTPUT->notification($error, 'notifyproblem');
}

$formurl = new moodle_url('/local/astusse/reference_trainer.php', ['courseid' => $courseid]);
echo html_writer::start_div('local-astusse-referencetrainer-page');

echo html_writer::start_div('local-astusse-referencetrainer-hero');
echo html_writer::start_div('local-astusse-referencetrainer-hero-copy');
echo html_writer::tag('span', 'ASTUSSE', ['class' => 'local-astusse-referencetrainer-kicker']);
echo html_writer::tag('h2', get_string('referencetrainer:heading', 'local_astusse'));
echo html_writer::tag('p', get_string('referencetrainer:intro', 'local_astusse'), ['class' => 'local-astusse-referencetrainer-intro']);
echo html_writer::end_div();

echo html_writer::start_div('local-astusse-referencetrainer-hero-meta');
echo html_writer::tag(
    'div',
    html_writer::tag('span', '', ['class' => 'local-astusse-referencetrainer-status-dot']) .
        html_writer::span(get_string($statelabelkey, 'local_astusse')),
    ['class' => 'local-astusse-referencetrainer-status local-astusse-referencetrainer-status--' . $status['state']]
);
echo html_writer::tag(
    'p',
    $status['state'] === 'valid'
        ? get_string('referencetrainer:status_valid', 'local_astusse', $currenttrainerlabel)
        : ($status['state'] === 'invalid'
            ? get_string('referencetrainer:status_invalid', 'local_astusse')
            : get_string('referencetrainer:status_missing', 'local_astusse')),
    ['class' => 'local-astusse-referencetrainer-global-note']
);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('local-astusse-referencetrainer-summary');
echo html_writer::tag(
    'div',
    html_writer::tag('span', get_string('referencetrainer:summary_course', 'local_astusse'), ['class' => 'local-astusse-referencetrainer-summary-label']) .
        html_writer::tag('strong', format_string($course->fullname)) .
        html_writer::tag('p', get_string('referencetrainer:summary_course_text', 'local_astusse'), ['class' => 'local-astusse-referencetrainer-summary-text']),
    ['class' => 'local-astusse-referencetrainer-summary-card']
);
echo html_writer::tag(
    'div',
    html_writer::tag('span', get_string('referencetrainer:summary_candidates', 'local_astusse'), ['class' => 'local-astusse-referencetrainer-summary-label']) .
        html_writer::tag('strong', (string)$candidatecount) .
        html_writer::tag('p', get_string('referencetrainer:summary_candidates_text', 'local_astusse'), ['class' => 'local-astusse-referencetrainer-summary-text']),
    ['class' => 'local-astusse-referencetrainer-summary-card']
);
echo html_writer::tag(
    'div',
    html_writer::tag('span', get_string('referencetrainer:summary_current', 'local_astusse'), ['class' => 'local-astusse-referencetrainer-summary-label']) .
        html_writer::tag('strong', s($currenttrainerlabel)) .
        html_writer::tag('p', get_string('referencetrainer:summary_current_text', 'local_astusse'), ['class' => 'local-astusse-referencetrainer-summary-text']),
    ['class' => 'local-astusse-referencetrainer-summary-card']
);
echo html_writer::end_div();

echo html_writer::start_div('local-astusse-referencetrainer-card');
echo html_writer::start_div('local-astusse-referencetrainer-card-body');
echo html_writer::tag('h3', get_string('referencetrainer:label', 'local_astusse'), ['class' => 'local-astusse-referencetrainer-form-title']);
echo html_writer::tag('p', get_string('referencetrainer:label_help', 'local_astusse'), ['class' => 'local-astusse-referencetrainer-form-text']);

if (empty($candidateoptions)) {
    echo html_writer::div(get_string('referencetrainer:no_candidates', 'local_astusse'), 'local-astusse-referencetrainer-inline-notice');
} else {
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $formurl->out(false),
        'class' => 'local-astusse-referencetrainer-form',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::start_div('local-astusse-referencetrainer-field');
    echo html_writer::tag('label', get_string('referencetrainer:label', 'local_astusse'), [
        'for' => 'id_trainerid',
        'class' => 'local-astusse-referencetrainer-field-label',
    ]);
    echo html_writer::div(
        html_writer::select(
            $selectoptions,
            'trainerid',
            $currenttrainerid,
            false,
            ['id' => 'id_trainerid', 'class' => 'custom-select local-astusse-referencetrainer-select']
        ),
        'local-astusse-referencetrainer-select-wrap'
    );
    echo html_writer::end_div();
    echo html_writer::start_div('local-astusse-referencetrainer-actions');
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'class' => 'btn btn-primary local-astusse-referencetrainer-submit',
        'value' => get_string('referencetrainer:save_button', 'local_astusse'),
    ]);
    echo html_writer::end_div();
    echo html_writer::end_tag('form');
}

echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();
