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
 * RAG ingest page (course entry point, multi-course selection).
 *
 * @package     local_astusse
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->libdir . '/formslib.php');

/**
 * Moodle form for ASTUSSE ingestion.
 */
class local_astusse_ingest_form extends moodleform {
    /**
     * Build form elements.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;
        $availablecourses = $this->_customdata['availablecourses'];
        $filemanageroptions = $this->_customdata['filemanageroptions'];
        $courseresources = $this->_customdata['courseresources'] ?? [];

        // === Course resources slot (moved to left column by JS) ===
        $mform->addElement('html', '<div class="local-astusse-resources-slot">');
        $mform->addElement('html', '<h4>' . get_string('ingest:course_resources_heading', 'local_astusse') . '</h4>');
        $mform->addElement('html', '<p class="text-muted small">'
            . get_string('ingest:course_resources_intro', 'local_astusse') . '</p>');

        if (!empty($courseresources)) {
            // Type filter buttons.
            $filterhtml = '<div class="local-astusse-resource-filters mb-2">';
            foreach (['all', 'resource', 'page', 'scorm', 'h5pactivity'] as $ftype) {
                $label = get_string('ingest:course_resources_filter_' . $ftype, 'local_astusse');
                $active = $ftype === 'all' ? ' active' : '';
                $filterhtml .= '<button type="button" class="btn btn-sm btn-outline-secondary local-astusse-filter-btn'
                    . $active . '" data-filter="' . $ftype . '">' . $label . '</button>';
            }
            $filterhtml .= '</div>';
            $mform->addElement('html', $filterhtml);

            // Resource table.
            $tablehtml = '<div class="local-astusse-resource-table-wrap">';
            $tablehtml .= '<table class="local-astusse-resource-table table table-sm table-hover">';
            $tablehtml .= '<thead><tr>';
            $tablehtml .= '<th class="local-astusse-col-check">'
                . '<input type="checkbox" id="local-astusse-select-all"></th>';
            $tablehtml .= '<th>' . get_string('ingest:course_resources_col_name', 'local_astusse') . '</th>';
            $tablehtml .= '<th>' . get_string('ingest:course_resources_col_type', 'local_astusse') . '</th>';
            $tablehtml .= '<th>' . get_string('ingest:course_resources_col_section', 'local_astusse') . '</th>';
            $tablehtml .= '</tr></thead><tbody>';

            foreach ($courseresources as $resource) {
                $typelabel = get_string('ingest:course_resources_type_' . $resource['modname'], 'local_astusse');
                $iconhtml = !empty($resource['icon'])
                    ? '<img src="' . s($resource['icon']) . '" class="icon iconsmall mr-1" alt="">' : '';
                $badgeclass = 'badge badge-secondary local-astusse-type-badge';
                $tablehtml .= '<tr class="local-astusse-resource-row" data-modname="' . $resource['modname'] . '">';
                $tablehtml .= '<td class="local-astusse-col-check">'
                    . '<input type="checkbox" name="selectedresources[]" value="' . $resource['cmid']
                    . '" class="local-astusse-resource-check"></td>';
                $tablehtml .= '<td>' . $iconhtml . s($resource['name']) . '</td>';
                $tablehtml .= '<td><span class="' . $badgeclass . '">' . $typelabel . '</span></td>';
                $tablehtml .= '<td>' . s($resource['sectionname']) . '</td>';
                $tablehtml .= '</tr>';
            }

            $tablehtml .= '</tbody></table></div>';
            $mform->addElement('html', $tablehtml);
        } else {
            $mform->addElement('html', '<p class="alert alert-info">'
                . get_string('ingest:course_resources_empty', 'local_astusse') . '</p>');
        }
        $mform->addElement('html', '</div>');

        // === File upload slot (moved below resources by JS) ===
        $maxuploadmb = (int)($this->_customdata['maxuploadmb'] ?? 50);
        $mform->addElement('html', '<div class="local-astusse-upload-slot">');
        $mform->addElement('html', '<h4>' . get_string('ingest:upload_heading', 'local_astusse') . '</h4>');
        $mform->addElement('html', '<p class="text-muted small mb-1">'
            . get_string('ingest:upload_hint_multi', 'local_astusse') . '</p>');
        $mform->addElement('html', '<p class="text-muted small">'
            . get_string('ingest:upload_hint_size', 'local_astusse', (string)$maxuploadmb) . '</p>');
        $mform->addElement(
            'filemanager',
            'resourcefile',
            '',
            null,
            $filemanageroptions
        );
        $mform->addElement('html', '</div>');

        // Single submit button.
        $this->add_action_buttons(false, get_string('ingest:submit_button', 'local_astusse'));

        // === Course selector (moved to sidebar by JS) ===
        $mform->addElement('html', '<div class="local-astusse-course-sidebar-slot">');
        $mform->addElement('html', '<h4>' . get_string('ingest:courses_label', 'local_astusse') . '</h4>');
        $mform->addElement('html', '<p class="text-muted small">'
            . get_string('ingest:courses_help', 'local_astusse') . '</p>');

        $autocompleteoptions = [
            'multiple' => true,
            'noselectionstring' => get_string('ingest:courses_search_placeholder', 'local_astusse'),
        ];
        $mform->addElement(
            'autocomplete',
            'courseids',
            '',
            $availablecourses,
            $autocompleteoptions
        );
        $mform->setType('courseids', PARAM_RAW);
        $mform->addElement('html', '</div>');
    }

    /**
     * Validate form values.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        return parent::validation($data, $files);
    }
}

$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);
$coursecontext = context_course::instance($course->id);

require_login($course);
require_capability('local/astusse:ingestdocument', $coursecontext);

$PAGE->set_context($coursecontext);
$PAGE->set_url(new moodle_url('/local/astusse/ingest.php', ['courseid' => $courseid]));
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('ingest:title', 'local_astusse'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->css(new moodle_url('/local/astusse/styles.css'));

$referencecontext = local_astusse_get_reference_trainer_context($courseid);
$referencestatus = $referencecontext['status'];

$activejobscount = (int)$DB->count_records_select(
    'local_astusse_ingest_jobs',
    'userid = :userid AND courseid = :courseid AND status IN (:queued, :running)',
    [
        'userid' => (int)$USER->id,
        'courseid' => $courseid,
        'queued' => 'queued',
        'running' => 'running',
    ]
);
$failedjobscount = (int)$DB->count_records(
    'local_astusse_ingest_jobs',
    ['userid' => (int)$USER->id, 'courseid' => $courseid, 'status' => 'failed']
);

$availablecourses = [];
$courses = enrol_get_users_courses($USER->id, true, 'id,fullname');
foreach ($courses as $candidatecourse) {
    $candidateid = (int)$candidatecourse->id;
    if ($candidateid === SITEID) {
        continue;
    }
    $candidatecontext = context_course::instance($candidateid);
    if (!has_capability('local/astusse:ingestdocument', $candidatecontext)) {
        continue;
    }
    $availablecourses[$candidateid] = format_string($candidatecourse->fullname, true, ['context' => $candidatecontext]);
}

if (!array_key_exists((int)$course->id, $availablecourses)) {
    $availablecourses[(int)$course->id] = format_string($course->fullname, true, ['context' => $coursecontext]);
}
asort($availablecourses, SORT_NATURAL | SORT_FLAG_CASE);

$usercontext = context_user::instance($USER->id);
$maxuploadbytes = local_astusse_get_ingest_max_upload_bytes();
$filemanageroptions = [
    'subdirs' => 0,
    'maxbytes' => $maxuploadbytes,
    'maxfiles' => 10,
    'accepted_types' => ['.pdf', '.txt', '.doc', '.docx', '.md', '.markdown', '.html', '.htm'],
];

$draftitemid = file_get_submitted_draft_itemid('resourcefile');
file_prepare_draft_area(
    $draftitemid,
    $usercontext->id,
    'local_astusse',
    'ingestfile',
    0,
    $filemanageroptions
);

$selectedcourseids = optional_param_array('courseids', [], PARAM_INT);
$selectedcourseids = array_values(array_unique(array_map('intval', $selectedcourseids)));
if (empty($selectedcourseids)) {
    $selectedcourseids = [(int)$course->id];
}

$courseresources = local_astusse_get_course_ingestable_resources($courseid);

$form = new local_astusse_ingest_form(
    new moodle_url('/local/astusse/ingest.php', ['courseid' => $courseid]),
    [
        'availablecourses' => $availablecourses,
        'filemanageroptions' => $filemanageroptions,
        'courseresources' => $courseresources,
        'maxuploadmb' => (int)round($maxuploadbytes / (1024 * 1024)),
    ]
);
$formdefaults = [
    'resourcefile' => $draftitemid,
    'courseids' => $selectedcourseids,
];
$form->set_data((object)$formdefaults);

if ($formdata = $form->get_data()) {
    require_sesskey();

    // Resolve target course IDs.
    $allowedids = array_fill_keys(array_map('intval', array_keys($availablecourses)), true);
    $validatedcourseids = [];
    $requestedcourseids = $formdata->courseids ?? [];
    if (!is_array($requestedcourseids)) {
        $requestedcourseids = [$requestedcourseids];
    }
    foreach ($requestedcourseids as $requestedcourseid) {
        $requestedcourseid = (int)$requestedcourseid;
        if ($requestedcourseid > 0 && isset($allowedids[$requestedcourseid])) {
            $validatedcourseids[] = $requestedcourseid;
        }
    }
    if (empty($validatedcourseids)) {
        $validatedcourseids = [(int)$course->id];
    }

    $queued = 0;
    $skipped = [];

    // --- 1. Queue selected course resources ---
    $selectedcmids = optional_param_array('selectedresources', [], PARAM_INT);
    $selectedcmids = array_values(array_unique(array_filter(array_map('intval', $selectedcmids))));

    $cmindex = [];
    foreach ($courseresources as $res) {
        $cmindex[(int)$res['cmid']] = $res;
    }

    foreach ($selectedcmids as $selectedcmid) {
        $meta = $cmindex[$selectedcmid] ?? null;
        if ($meta === null) {
            continue;
        }

        try {
            local_astusse_create_ingest_job([
                'userid' => (int)$USER->id,
                'courseid' => $courseid,
                'targetcourseids' => $validatedcourseids,
                'sourcetype' => (string)$meta['modname'],
                'sourcecmid' => $selectedcmid,
                'filename' => (string)$meta['name'],
                'mimetype' => $meta['mimetype'] ?? '',
                'filesize' => 0,
            ]);
            $queued++;
        } catch (\Throwable $e) {
            $skipped[] = $meta['name'] . ': ' . $e->getMessage();
        }
    }

    // --- 2. Queue uploaded files ---
    $fs = get_file_storage();
    $draftfiles = $fs->get_area_files(
        $usercontext->id,
        'user',
        'draft',
        (int)$formdata->resourcefile,
        'id DESC',
        false
    );

    foreach ($draftfiles as $draftfile) {
        $filename = clean_param((string)$draftfile->get_filename(), PARAM_FILE);
        $mimetype = (string)$draftfile->get_mimetype();
        $filesize = (int)$draftfile->get_filesize();

        if ($filename === '' || $filesize <= 0) {
            continue;
        }
        if ($filesize > $maxuploadbytes) {
            $skipped[] = $filename . ': ' . get_string('ingest:error_file_too_large', 'local_astusse');
            continue;
        }

        try {
            $jobid = local_astusse_create_ingest_job([
                'userid' => (int)$USER->id,
                'courseid' => $courseid,
                'targetcourseids' => $validatedcourseids,
                'sourcetype' => 'upload',
                'filename' => $filename,
                'mimetype' => $mimetype ?: 'application/octet-stream',
                'filesize' => $filesize,
            ]);
            local_astusse_store_ingest_upload($draftfile, $jobid, (int)$USER->id);
            $queued++;
        } catch (\Throwable $e) {
            $skipped[] = $filename . ': ' . $e->getMessage();
        }
    }

    if ($queued === 0 && empty($skipped)) {
        \core\notification::warning(get_string('ingest:course_resources_none_selected', 'local_astusse'));
    } else {
        if ($queued > 0) {
            \core\notification::success(
                get_string('jobs:queued_notification', 'local_astusse', (object)['count' => $queued])
            );
        }
        if (!empty($skipped)) {
            \core\notification::warning(
                get_string('jobs:skipped_notification', 'local_astusse') . ' ' . implode(' | ', $skipped)
            );
        }
    }

    redirect(new moodle_url('/local/astusse/jobs.php', ['courseid' => $courseid]));
}

echo $OUTPUT->header();
echo html_writer::start_div('local-astusse-ingest-page');
echo html_writer::start_div('local-astusse-ingest-hero');
echo html_writer::start_div('local-astusse-ingest-hero-copy');
echo html_writer::tag('span', 'ASTUSSE', ['class' => 'local-astusse-ingest-kicker']);
echo html_writer::tag('h2', get_string('ingest:heading', 'local_astusse'));
echo html_writer::tag('p', get_string('ingest:intro', 'local_astusse'), ['class' => 'local-astusse-ingest-intro']);
echo html_writer::end_div();

$referencestatetext = get_string('ingest:reference_trainer_missing', 'local_astusse');
$referencestateclass = 'is-missing';
if ($referencestatus['state'] === 'valid') {
    $referencestatetext = get_string('ingest:reference_trainer_valid', 'local_astusse', fullname($referencestatus['user']));
    $referencestateclass = 'is-valid';
} else if ($referencestatus['state'] === 'invalid') {
    $referencestatetext = get_string('ingest:reference_trainer_invalid', 'local_astusse');
    $referencestateclass = 'is-invalid';
}

echo html_writer::start_div('local-astusse-ingest-hero-meta');
echo html_writer::tag(
    'div',
    html_writer::tag('span', '', ['class' => 'local-astusse-ingest-status-dot']) .
        html_writer::span(get_string('ingest:reference_trainer_title', 'local_astusse')),
    ['class' => 'local-astusse-ingest-status ' . $referencestateclass]
);
echo html_writer::tag('p', $referencestatetext, ['class' => 'local-astusse-ingest-global-note']);

$jobsurl = new moodle_url('/local/astusse/jobs.php', ['courseid' => $courseid]);
$jobscardclass = 'local-astusse-ingest-jobs-card';
if ($activejobscount > 0) {
    $jobscardclass .= ' is-active';
} else if ($failedjobscount > 0) {
    $jobscardclass .= ' is-failed';
}
$jobsmainlabel = $activejobscount > 0
    ? get_string('jobs:hero_active_count', 'local_astusse', (string)$activejobscount)
    : ($failedjobscount > 0
        ? get_string('jobs:hero_failed_count', 'local_astusse', (string)$failedjobscount)
        : get_string('jobs:hero_idle', 'local_astusse'));
echo html_writer::link(
    $jobsurl,
    html_writer::tag('span', $jobsmainlabel, ['class' => 'local-astusse-ingest-jobs-card-label']) .
        html_writer::tag('span', get_string('jobs:hero_cta', 'local_astusse'),
            ['class' => 'local-astusse-ingest-jobs-card-cta']),
    ['class' => $jobscardclass]
);

echo html_writer::end_div();
echo html_writer::end_div();

if (empty($availablecourses)) {
    echo $OUTPUT->notification(get_string('ingest:error_no_available_courses', 'local_astusse'), 'notifyproblem');
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::start_div('card local-astusse-ingest-card');
echo html_writer::start_div('card-body');
$form->display();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div(); // .local-astusse-ingest-page

$PAGE->requires->js_call_amd('local_astusse/ingest_page', 'init');

echo $OUTPUT->footer();