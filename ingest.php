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
            foreach (['all', 'resource', 'page', 'scorm'] as $ftype) {
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
        $mform->addElement('html', '<div class="local-astusse-upload-slot">');
        $mform->addElement(
            'filemanager',
            'resourcefile',
            get_string('ingest:file_label', 'local_astusse'),
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

/**
 * Return a user-friendly error message for gateway HTTP status.
 *
 * @param int $status
 * @return string
 */
function local_astusse_ingest_http_error_message(int $status): string {
    switch ($status) {
        case 400:
            return get_string('ingest:error_http_400', 'local_astusse');
        case 401:
            return get_string('ingest:error_http_401', 'local_astusse');
        case 403:
            return get_string('ingest:error_http_403', 'local_astusse');
        case 404:
            return get_string('ingest:error_http_404', 'local_astusse');
        case 408:
            return get_string('ingest:error_http_408', 'local_astusse');
        case 413:
            return get_string('ingest:error_http_413', 'local_astusse');
        case 429:
            return get_string('ingest:error_http_429', 'local_astusse');
        case 500:
        case 502:
        case 503:
        case 504:
            return get_string('ingest:error_http_5xx', 'local_astusse');
        default:
            if ($status > 0) {
                return get_string('ingest:error_http_unknown', 'local_astusse', (string)$status);
            }
            return get_string('ingest:error_submit', 'local_astusse');
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

$client = new \local_astusse\api_client();
$referencecontext = local_astusse_get_reference_trainer_context($courseid);
$referencestatus = $referencecontext['status'];
$error = '';
$success = '';
$errordetails = [];
$result = null;

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
$filemanageroptions = [
    'subdirs' => 0,
    'maxbytes' => 50 * 1024 * 1024,
    'maxfiles' => 1,
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
    ]
);
$formdefaults = [
    'resourcefile' => $draftitemid,
    'courseids' => $selectedcourseids,
];
$form->set_data((object)$formdefaults);

if ($formdata = $form->get_data()) {
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

    $okcount = 0;
    $failcount = 0;

    // --- 1. Ingest selected course resources ---
    $selectedcmids = optional_param_array('selectedresources', [], PARAM_INT);
    $selectedcmids = array_values(array_unique(array_filter(array_map('intval', $selectedcmids))));

    foreach ($selectedcmids as $selectedcmid) {
        $resourcename = '';
        foreach ($courseresources as $res) {
            if ((int)$res['cmid'] === $selectedcmid) {
                $resourcename = $res['name'];
                break;
            }
        }

        try {
            $extracted = local_astusse_extract_module_content($selectedcmid, $courseid);
            if ($extracted === null) {
                $failcount++;
                $errordetails[] = get_string('ingest:course_resources_extract_failed', 'local_astusse', $resourcename);
                continue;
            }

            $ingestresult = $client->ingest_document_for_user(
                $USER,
                $validatedcourseids,
                $extracted['filepath'],
                $extracted['filename'],
                $extracted['mimetype']
            );
            $ingeststatus = (int)($ingestresult['status'] ?? 0);
            if ($ingeststatus >= 200 && $ingeststatus < 300) {
                $okcount++;
            } else {
                $failcount++;
                $backmsg = $client->extract_error_message($ingestresult);
                $errordetails[] = $resourcename . ': HTTP ' . $ingeststatus . ' — ' . $backmsg;
            }
        } catch (\Throwable $e) {
            $failcount++;
            $errordetails[] = $resourcename . ': ' . $e->getMessage();
        }
    }

    // --- 2. Ingest uploaded file (if any) ---
    $fs = get_file_storage();
    $draftfiles = $fs->get_area_files(
        $usercontext->id,
        'user',
        'draft',
        (int)$formdata->resourcefile,
        'id DESC',
        false
    );

    if (!empty($draftfiles)) {
        $draftfile = reset($draftfiles);
        $filename = clean_param((string)$draftfile->get_filename(), PARAM_FILE);
        $mimetype = (string)$draftfile->get_mimetype();
        $filesize = (int)$draftfile->get_filesize();

        if ($filename !== '' && $filesize > 0 && $filesize <= 50 * 1024 * 1024) {
            $tmppath = make_request_directory() . DIRECTORY_SEPARATOR . $filename;
            if ($draftfile->copy_content_to($tmppath)) {
                try {
                    $result = $client->ingest_document_for_user(
                        $USER,
                        $validatedcourseids,
                        $tmppath,
                        $filename,
                        $mimetype
                    );
                    $status = (int)($result['status'] ?? 0);
                    if ($status >= 200 && $status < 300) {
                        $okcount++;
                    } else {
                        $failcount++;
                        $errordetails[] = $filename . ': ' . local_astusse_ingest_http_error_message($status);
                    }
                } catch (\Throwable $e) {
                    $failcount++;
                    $errordetails[] = $filename . ': ' . $e->getMessage();
                }
            } else {
                $failcount++;
                $errordetails[] = $filename . ': ' . get_string('ingest:error_invalid_file', 'local_astusse');
            }
        } else if ($filename !== '' && $filesize > 50 * 1024 * 1024) {
            $failcount++;
            $errordetails[] = $filename . ': ' . get_string('ingest:error_file_too_large', 'local_astusse');
        }
    }

    // --- Build result message ---
    if ($okcount === 0 && $failcount === 0) {
        $error = get_string('ingest:course_resources_none_selected', 'local_astusse');
    } else if ($failcount === 0) {
        $success = get_string('ingest:course_resources_success', 'local_astusse', (object)['ok' => $okcount]);
    } else if ($okcount > 0) {
        $error = get_string('ingest:course_resources_partial', 'local_astusse',
            (object)['ok' => $okcount, 'fail' => $failcount]);
    } else {
        $error = get_string('ingest:course_resources_all_failed', 'local_astusse');
    }
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
echo html_writer::end_div();
echo html_writer::end_div();

if ($error !== '') {
    echo $OUTPUT->notification($error, 'notifyproblem');
    if (!empty($errordetails)) {
        echo html_writer::start_div('alert alert-light border');
        echo html_writer::start_tag('ul', ['class' => 'mb-0']);
        foreach ($errordetails as $detail) {
            echo html_writer::tag('li', $detail);
        }
        echo html_writer::end_tag('ul');
        echo html_writer::end_div();
    }
}
if ($success !== '') {
    echo $OUTPUT->notification($success, 'notifysuccess');
}

if (empty($availablecourses)) {
    echo $OUTPUT->notification(get_string('ingest:error_no_available_courses', 'local_astusse'), 'notifyproblem');
    echo $OUTPUT->footer();
    exit;
}

if ($result !== null) {
    $httpstatus = (int)($result['status'] ?? 0);
    $json = $result['body_json'] ?? null;
    echo html_writer::start_div('alert alert-light border');
    echo html_writer::tag('p', get_string('ingest:result_http_status', 'local_astusse', (string)$httpstatus));
    if (is_array($json)) {
        echo html_writer::tag('p', get_string('ingest:result_status', 'local_astusse', (string)($json['status'] ?? '')));
        if (!empty($json['jobId'])) {
            echo html_writer::tag('p', get_string('ingest:result_jobid', 'local_astusse', s((string)$json['jobId'])));
        }
        if (!empty($json['traceId'])) {
            echo html_writer::tag('p', get_string('ingest:result_traceid', 'local_astusse', s((string)$json['traceId'])));
        }
    }
    echo html_writer::end_div();
}

echo html_writer::start_div('card local-astusse-ingest-card');
echo html_writer::start_div('card-body');
$form->display();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div(); // .local-astusse-ingest-page

$PAGE->requires->js_call_amd('local_astusse/ingest_page', 'init');

echo $OUTPUT->footer();