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
 * Trainer scope configuration page (course access point, trainer-global setting).
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
require_capability('local/astusse:managetrainerscope', $coursecontext);

$PAGE->set_context($coursecontext);
$PAGE->set_url(new moodle_url('/local/astusse/trainer_scope.php', ['courseid' => $courseid]));
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('trainerscope:title', 'local_astusse'));
$PAGE->set_heading(format_string($course->fullname));

$error = '';
$success = '';
$trainerid = (string)$USER->id;
$client = new \local_astusse\api_client();

try {
    $state = $client->get_trainer_scope_for_user($USER, $trainerid);
} catch (\Throwable $e) {
    $state = null;
    $error = get_string('trainerscope:error_fetch', 'local_astusse') . ' ' . $e->getMessage();
}

if ($state !== null && optional_param('action', '', PARAM_ALPHA) === 'save') {
    require_sesskey();

    $statejson = $state['body_json'] ?? [];
    $delegationenabled = !empty($statejson['delegationEnabled']);
    $platformscopeallowed = !empty($statejson['platformScopeAllowed']);
    $scope = optional_param('scope', '', PARAM_ALPHA);

    $allowedscopes = ['course', 'trainer'];
    if ($platformscopeallowed) {
        $allowedscopes[] = 'platform';
    }

    if (!$delegationenabled) {
        $error = get_string('trainerscope:delegation_disabled', 'local_astusse');
    } else if (!in_array($scope, $allowedscopes, true)) {
        $error = get_string('trainerscope:error_invalid_scope', 'local_astusse');
    } else {
        try {
            $updateresponse = $client->update_trainer_scope_for_user($USER, $trainerid, $scope);
            $status = (int)($updateresponse['status'] ?? 0);
            if ($status >= 200 && $status < 300) {
                $success = get_string('trainerscope:save_ok', 'local_astusse');
                $state = $updateresponse;
            } else {
                $error = get_string('trainerscope:error_save', 'local_astusse') . ' ' .
                    $client->extract_error_message($updateresponse);
            }
        } catch (\Throwable $e) {
            $error = get_string('trainerscope:error_save', 'local_astusse') . ' ' . $e->getMessage();
        }
    }
}

echo $OUTPUT->header();

if ($error !== '') {
    echo $OUTPUT->notification($error, 'notifyproblem');
}
if ($success !== '') {
    echo $OUTPUT->notification($success, 'notifysuccess');
}

if ($state === null) {
    echo $OUTPUT->footer();
    exit;
}

$json = $state['body_json'] ?? [];
$delegationenabled = !empty($json['delegationEnabled']);
$platformscopeallowed = !empty($json['platformScopeAllowed']);
$currentscope = (string)($json['scope'] ?? 'course');

$allscopelabels = [
    'course' => get_string('scope:course', 'local_astusse'),
    'trainer' => get_string('scope:trainer', 'local_astusse'),
    'platform' => get_string('scope:platform', 'local_astusse'),
];

$scopedescriptions = [
    'course' => get_string('trainerscope:scope_course_desc', 'local_astusse'),
    'trainer' => get_string('trainerscope:scope_trainer_desc', 'local_astusse'),
    'platform' => get_string('trainerscope:scope_platform_desc', 'local_astusse'),
];

$effectivescope = $currentscope;
if (!$delegationenabled) {
    $effectivescope = 'course';
} else if ($currentscope === 'platform' && !$platformscopeallowed) {
    // Runtime fallback in backend when platform is no longer allowed.
    $effectivescope = 'trainer';
}
if (!array_key_exists($effectivescope, $allscopelabels)) {
    $effectivescope = 'course';
}

$options = [
    'course' => get_string('scope:course', 'local_astusse'),
    'trainer' => get_string('scope:trainer', 'local_astusse'),
];
if ($platformscopeallowed) {
    $options['platform'] = get_string('scope:platform', 'local_astusse');
}
if (!array_key_exists($currentscope, $options)) {
    if ($currentscope === 'platform' && !$platformscopeallowed && $delegationenabled) {
        $currentscope = 'trainer';
    } else {
        $currentscope = 'course';
    }
    echo $OUTPUT->notification(
        get_string('trainerscope:scope_adjusted', 'local_astusse', $options[$currentscope]),
        'notifywarning'
    );
}

echo html_writer::start_div('local-astusse-trainerscope-page');

echo html_writer::start_div('local-astusse-trainerscope-hero');
echo html_writer::start_div('local-astusse-trainerscope-hero-copy');
echo html_writer::tag('span', 'ASTUSSE', ['class' => 'local-astusse-trainerscope-kicker']);
echo html_writer::tag('h2', get_string('trainerscope:heading', 'local_astusse'));
echo html_writer::tag('p', get_string('trainerscope:intro', 'local_astusse'), ['class' => 'local-astusse-trainerscope-intro']);
echo html_writer::end_div();

echo html_writer::start_div('local-astusse-trainerscope-hero-meta');
echo html_writer::tag(
    'div',
    html_writer::tag('span', '', ['class' => 'local-astusse-trainerscope-status-dot']) .
        html_writer::span(get_string('trainerscope:active_scope_line', 'local_astusse', $allscopelabels[$effectivescope])),
    ['class' => 'local-astusse-trainerscope-status']
);
echo html_writer::tag('p', get_string('trainerscope:global_notice', 'local_astusse'), ['class' => 'local-astusse-trainerscope-global-note']);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('local-astusse-trainerscope-summary');
echo html_writer::tag(
    'div',
    html_writer::tag('span', get_string('trainerscope:policy_title', 'local_astusse'), ['class' => 'local-astusse-trainerscope-summary-label']) .
        html_writer::tag('strong', get_string('trainerscope:delegation_state', 'local_astusse', $delegationenabled ? get_string('yes') : get_string('no'))) .
        html_writer::tag('p', get_string('trainerscope:platform_state', 'local_astusse', $platformscopeallowed ? get_string('yes') : get_string('no')), ['class' => 'local-astusse-trainerscope-summary-text']),
    ['class' => 'local-astusse-trainerscope-summary-card']
);
echo html_writer::tag(
    'div',
    html_writer::tag('span', get_string('trainerscope:trainer_id', 'local_astusse', $trainerid), ['class' => 'local-astusse-trainerscope-summary-label']) .
        html_writer::tag('strong', $allscopelabels[$effectivescope]) .
        html_writer::tag('p', $scopedescriptions[$effectivescope] ?? '', ['class' => 'local-astusse-trainerscope-summary-text']),
    ['class' => 'local-astusse-trainerscope-summary-card']
);
echo html_writer::end_div();

$formurl = new moodle_url('/local/astusse/trainer_scope.php', ['courseid' => $courseid]);
echo html_writer::start_div('local-astusse-trainerscope-card');
echo html_writer::start_div('local-astusse-trainerscope-card-body');
echo html_writer::tag('h3', get_string('trainerscope:label', 'local_astusse'), ['class' => 'local-astusse-trainerscope-form-title']);
echo html_writer::tag('p', get_string('trainerscope:label_help', 'local_astusse'), ['class' => 'local-astusse-trainerscope-form-text']);

if (!$delegationenabled) {
    echo html_writer::div(get_string('trainerscope:delegation_disabled', 'local_astusse'), 'local-astusse-trainerscope-inline-notice');
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $formurl->out(false),
    'class' => 'local-astusse-trainerscope-form',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'save']);

echo html_writer::start_tag('fieldset', ['class' => 'local-astusse-trainerscope-fieldset']);
echo html_writer::tag('legend', get_string('trainerscope:label', 'local_astusse'), ['class' => 'sr-only']);
echo html_writer::start_div('local-astusse-trainerscope-options');
foreach ($options as $value => $label) {
    $checked = $value === $currentscope;
    $optionid = 'scope-' . $value;
    $inputattrs = [
        'type' => 'radio',
        'name' => 'scope',
        'id' => $optionid,
        'value' => $value,
    ];
    if ($checked) {
        $inputattrs['checked'] = 'checked';
    }
    echo html_writer::start_tag('label', [
        'class' => 'local-astusse-trainerscope-option' . ($checked ? ' is-selected' : ''),
        'for' => $optionid,
    ]);
    echo html_writer::empty_tag('input', $inputattrs);
    echo html_writer::start_div('local-astusse-trainerscope-option-copy');
    echo html_writer::tag('strong', $label, ['class' => 'local-astusse-trainerscope-option-title']);
    echo html_writer::tag('span', $scopedescriptions[$value] ?? '', ['class' => 'local-astusse-trainerscope-option-text']);
    echo html_writer::end_div();
    echo html_writer::end_tag('label');
}
echo html_writer::end_div();
echo html_writer::end_tag('fieldset');

echo html_writer::start_div('local-astusse-trainerscope-actions');
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => get_string('trainerscope:save_button', 'local_astusse'),
    'class' => 'btn btn-primary local-astusse-trainerscope-submit',
]);
echo html_writer::end_div();
echo html_writer::end_tag('form');
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();
