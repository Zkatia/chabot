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
 * Ingestion jobs tracking page with filters and pagination.
 *
 * @package     local_astusse
 * @copyright   2026 Ingenium Digital Learning
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);
$coursecontext = context_course::instance($course->id);

require_login($course);
require_capability('local/astusse:ingestdocument', $coursecontext);

// Filter inputs.
// Free-text search: accepts either a local job # (integer) or a backend
// UUID (job id / trace id). PARAM_TEXT trims, keeps letters/digits/hyphens.
$filterjobid = trim(optional_param('filterjobid', '', PARAM_TEXT));
$filtertargetcourse = optional_param('filtertargetcourse', 0, PARAM_INT);
// PARAM_ALPHANUMEXT accepts letters + digits + _ / - so that values like "h5pactivity" pass.
$filtersourcetype = optional_param('filtersourcetype', '', PARAM_ALPHANUMEXT);
$filterstatus = optional_param('filterstatus', '', PARAM_ALPHANUMEXT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 25;

$sourcetypechoices = [
    'upload', 'resource', 'page', 'scorm', 'h5pactivity', 'url', 'book',
    'glossary', 'lesson', 'quiz', 'assign', 'wiki', 'folder',
];
$statuschoices = ['queued', 'running', 'succeeded', 'failed'];
if (!in_array($filtersourcetype, $sourcetypechoices, true)) {
    $filtersourcetype = '';
}
if (!in_array($filterstatus, $statuschoices, true)) {
    $filterstatus = '';
}
if ($page < 0) {
    $page = 0;
}

$pageurlparams = ['courseid' => $courseid];
if ($filterjobid !== '') {
    $pageurlparams['filterjobid'] = $filterjobid;
}
if ($filtertargetcourse > 0) {
    $pageurlparams['filtertargetcourse'] = $filtertargetcourse;
}
if ($filtersourcetype !== '') {
    $pageurlparams['filtersourcetype'] = $filtersourcetype;
}
if ($filterstatus !== '') {
    $pageurlparams['filterstatus'] = $filterstatus;
}

$PAGE->set_context($coursecontext);
$PAGE->set_url(new moodle_url('/local/astusse/jobs.php', $pageurlparams));
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('jobs:title', 'local_astusse'));
$PAGE->set_heading(format_string($course->fullname));
local_astusse_require_charte_assets($PAGE);

// Build WHERE dynamically.
$where = ['userid = :userid', 'courseid = :courseid'];
$params = ['userid' => (int)$USER->id, 'courseid' => $courseid];

if ($filterjobid !== '') {
    if (ctype_digit($filterjobid)) {
        // Pure integer: match the local Moodle job #.
        $where[] = 'id = :jobid';
        $params['jobid'] = (int)$filterjobid;
    } else {
        // Non-digit token: match against backend job id or trace id (partial, case-insensitive).
        $where[] = '(' . $DB->sql_like('backendjobid', ':bjid', false) . ' OR '
            . $DB->sql_like('backendtraceid', ':btid', false) . ')';
        $params['bjid'] = '%' . $DB->sql_like_escape($filterjobid) . '%';
        $params['btid'] = '%' . $DB->sql_like_escape($filterjobid) . '%';
    }
}
if ($filtersourcetype !== '') {
    $where[] = 'sourcetype = :sourcetype';
    $params['sourcetype'] = $filtersourcetype;
}
if ($filterstatus !== '') {
    $where[] = 'status = :status';
    $params['status'] = $filterstatus;
}
// CSV targetcourseids: 4 match positions (exact, start, middle, end).
if ($filtertargetcourse > 0) {
    $needle = (string)$filtertargetcourse;
    $where[] = '(targetcourseids = :tcexact
                OR ' . $DB->sql_like('targetcourseids', ':tcstart') . '
                OR ' . $DB->sql_like('targetcourseids', ':tcmiddle') . '
                OR ' . $DB->sql_like('targetcourseids', ':tcend') . ')';
    $params['tcexact'] = $needle;
    $params['tcstart'] = $needle . ',%';
    $params['tcmiddle'] = '%,' . $needle . ',%';
    $params['tcend'] = '%,' . $needle;
}

$whereclause = implode(' AND ', $where);
$totaljobs = (int)$DB->count_records_select('local_astusse_ingest_jobs', $whereclause, $params);
$totalpages = (int)max(1, (int)ceil($totaljobs / $perpage));
if ($page >= $totalpages) {
    $page = $totalpages - 1;
}
$jobs = $DB->get_records_select(
    'local_astusse_ingest_jobs',
    $whereclause,
    $params,
    'timecreated DESC',
    '*',
    $page * $perpage,
    $perpage
);

// Build the list of target courses the current user could pick from, same policy as ingest.php.
$availablecourses = [];
$courses = enrol_get_users_courses($USER->id, true, 'id,fullname,shortname');
foreach ($courses as $candidate) {
    $cid = (int)$candidate->id;
    if ($cid === SITEID) {
        continue;
    }
    $ctx = context_course::instance($cid);
    if (!has_capability('local/astusse:ingestdocument', $ctx)) {
        continue;
    }
    $availablecourses[$cid] = format_string($candidate->shortname ?: $candidate->fullname, true, ['context' => $ctx]);
}
if (!array_key_exists($courseid, $availablecourses)) {
    $availablecourses[$courseid] = format_string($course->shortname ?: $course->fullname);
}
asort($availablecourses, SORT_NATURAL | SORT_FLAG_CASE);

$ingesturl = new moodle_url('/local/astusse/ingest.php', ['courseid' => $courseid]);
$statusendpoint = new moodle_url('/local/astusse/jobs_status.php', ['courseid' => $courseid]);
$retrysesskey = sesskey();

echo $OUTPUT->header();

echo html_writer::start_div('local-astusse-charte local-astusse-ingest-page');
echo html_writer::start_div('local-astusse-ingest-hero');
echo html_writer::start_div('local-astusse-ingest-hero-copy');
echo html_writer::tag('span', 'ASTUSSE', ['class' => 'local-astusse-ingest-kicker']);
echo html_writer::tag('h2', get_string('jobs:heading', 'local_astusse'));
echo html_writer::tag('p', get_string('jobs:intro', 'local_astusse'), ['class' => 'local-astusse-ingest-intro']);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('local-astusse-ingest-actions mb-3');
echo html_writer::link(
    $ingesturl,
    get_string('jobs:link_back_to_ingest', 'local_astusse'),
    ['class' => 'btn btn-outline-primary mr-2']
);
echo html_writer::link(
    new moodle_url('/local/astusse/jobs.php', $pageurlparams),
    get_string('jobs:refresh_now', 'local_astusse'),
    ['class' => 'btn btn-outline-secondary']
);
echo html_writer::end_div();

// Filters bar.
$formaction = new moodle_url('/local/astusse/jobs.php');
echo html_writer::start_tag('form', [
    'id' => 'local-astusse-jobs-filters-form',
    'method' => 'get',
    'action' => $formaction->out(false),
    'class' => 'local-astusse-jobs-filters card mb-3',
]);
echo html_writer::start_div('card-body');
echo html_writer::tag(
    'h5',
    get_string('jobs:filters_heading', 'local_astusse'),
    ['class' => 'local-astusse-jobs-filters-title']
);
echo html_writer::start_div('form-row');

// Hidden courseid (stays constant).
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);

// ID search (local # or backend UUID).
echo html_writer::start_div('form-group col-md-3');
echo html_writer::tag(
    'label',
    get_string('jobs:filter_jobid', 'local_astusse'),
    ['for' => 'filterjobid']
);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'class' => 'form-control',
    'id' => 'filterjobid',
    'name' => 'filterjobid',
    'value' => $filterjobid,
    'placeholder' => get_string('jobs:filter_jobid_placeholder', 'local_astusse'),
    'autocomplete' => 'off',
    'spellcheck' => 'false',
]);
echo html_writer::end_div();

// Target course.
echo html_writer::start_div('form-group col-md-4');
echo html_writer::tag(
    'label',
    get_string('jobs:filter_targetcourse', 'local_astusse'),
    ['for' => 'filtertargetcourse']
);
$courseoptions = '<option value="0">' . get_string('jobs:filter_any', 'local_astusse') . '</option>';
foreach ($availablecourses as $cid => $cname) {
    $selected = ((int)$filtertargetcourse === (int)$cid) ? ' selected' : '';
    $courseoptions .= '<option value="' . (int)$cid . '"' . $selected . '>' . s($cname) . '</option>';
}
echo '<select class="form-control" id="filtertargetcourse" name="filtertargetcourse"'
    . ' data-autosubmit="1">' . $courseoptions . '</select>';
echo html_writer::end_div();

// Source type.
echo html_writer::start_div('form-group col-md-3');
echo html_writer::tag(
    'label',
    get_string('jobs:filter_sourcetype', 'local_astusse'),
    ['for' => 'filtersourcetype']
);
$sourceoptions = '<option value="">' . get_string('jobs:filter_any', 'local_astusse') . '</option>';
foreach ($sourcetypechoices as $sc) {
    $sclabel = $sc === 'upload'
        ? get_string('jobs:source_upload', 'local_astusse')
        : get_string('ingest:course_resources_type_' . $sc, 'local_astusse');
    $selected = ($filtersourcetype === $sc) ? ' selected' : '';
    $sourceoptions .= '<option value="' . $sc . '"' . $selected . '>' . s($sclabel) . '</option>';
}
echo '<select class="form-control" id="filtersourcetype" name="filtersourcetype"'
    . ' data-autosubmit="1">' . $sourceoptions . '</select>';
echo html_writer::end_div();

// Status.
echo html_writer::start_div('form-group col-md-3');
echo html_writer::tag(
    'label',
    get_string('jobs:filter_status', 'local_astusse'),
    ['for' => 'filterstatus']
);
$statusoptions = '<option value="">' . get_string('jobs:filter_any', 'local_astusse') . '</option>';
foreach ($statuschoices as $sc) {
    $selected = ($filterstatus === $sc) ? ' selected' : '';
    $statusoptions .= '<option value="' . $sc . '"' . $selected . '>'
        . s(get_string('jobs:status_' . $sc, 'local_astusse')) . '</option>';
}
echo '<select class="form-control" id="filterstatus" name="filterstatus"'
    . ' data-autosubmit="1">' . $statusoptions . '</select>';
echo html_writer::end_div();

echo html_writer::end_div(); // End of form-row.

echo html_writer::start_div('local-astusse-jobs-filters-actions');
echo html_writer::tag(
    'button',
    get_string('jobs:filter_apply', 'local_astusse'),
    ['type' => 'submit', 'class' => 'btn btn-primary btn-sm']
);
echo ' ';
echo html_writer::link(
    new moodle_url('/local/astusse/jobs.php', ['courseid' => $courseid]),
    get_string('jobs:filter_reset', 'local_astusse'),
    ['class' => 'btn btn-outline-secondary btn-sm']
);
echo html_writer::tag(
    'span',
    get_string('jobs:filter_total', 'local_astusse', (object)[
        'count' => $totaljobs,
        'from' => $totaljobs === 0 ? 0 : ($page * $perpage + 1),
        'to' => min(($page + 1) * $perpage, $totaljobs),
    ]),
    ['class' => 'text-muted small ml-3 align-middle']
);
echo html_writer::end_div();
echo html_writer::end_div(); // End of card-body.
echo html_writer::end_tag('form');

// Table.
if (empty($jobs)) {
    $emptykey = ($filterjobid !== '' || $filtertargetcourse || $filtersourcetype !== '' || $filterstatus !== '')
        ? 'jobs:empty_filtered' : 'jobs:empty';
    echo $OUTPUT->notification(get_string($emptykey, 'local_astusse'), 'info');
} else {
    echo html_writer::start_div('local-astusse-jobs-table-wrap table-responsive');
    echo html_writer::start_tag('table', [
        'class' => 'local-astusse-jobs-table table table-sm table-hover',
        'data-status-endpoint' => $statusendpoint->out(false),
        'data-retry-sesskey' => $retrysesskey,
    ]);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', '#');
    foreach (
        ['jobs:col_created', 'jobs:col_file', 'jobs:col_source',
              'jobs:col_targets', 'jobs:col_status', 'jobs:col_attempts',
              'jobs:col_details', 'jobs:col_actions'] as $key
    ) {
        echo html_writer::tag('th', get_string($key, 'local_astusse'));
    }
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    foreach ($jobs as $job) {
        $desc = local_astusse_describe_ingest_job($job);
        $targets = $desc['courses'];
        $targetlabels = [];
        foreach ($targets as $targetid) {
            try {
                $tcourse = get_course($targetid);
                $targetlabels[] = format_string($tcourse->shortname ?: $tcourse->fullname);
            } catch (\Throwable $e) {
                $targetlabels[] = '#' . $targetid;
            }
        }

        $details = '';
        if (!empty($job->backendtraceid)) {
            $details .= html_writer::tag(
                'div',
                get_string('jobs:cell_traceid', 'local_astusse', s($job->backendtraceid)),
                ['class' => 'small text-muted']
            );
        }
        if (!empty($job->backendjobid)) {
            $details .= html_writer::tag(
                'div',
                get_string('jobs:cell_backend_jobid', 'local_astusse', s($job->backendjobid)),
                ['class' => 'small text-muted']
            );
        }
        if ($job->status === 'failed' && !empty($job->errormessage)) {
            $details .= html_writer::tag(
                'div',
                s($job->errormessage),
                ['class' => 'small text-danger']
            );
        }
        if ($job->status === 'failed' && !empty($job->httpstatus)) {
            $details .= html_writer::tag(
                'div',
                get_string('jobs:cell_http_status', 'local_astusse', (string)$job->httpstatus),
                ['class' => 'small text-muted']
            );
        }

        $actions = '';
        if ($job->status === 'failed') {
            $retryurl = new moodle_url('/local/astusse/jobs_retry.php');
            $actions .= html_writer::start_tag('form', [
                'method' => 'post',
                'action' => $retryurl->out(false),
                'class' => 'd-inline',
            ]);
            $actions .= html_writer::empty_tag('input', [
                'type' => 'hidden',
                'name' => 'jobid',
                'value' => (int)$job->id,
            ]);
            $actions .= html_writer::empty_tag('input', [
                'type' => 'hidden',
                'name' => 'courseid',
                'value' => $courseid,
            ]);
            $actions .= html_writer::empty_tag('input', [
                'type' => 'hidden',
                'name' => 'sesskey',
                'value' => $retrysesskey,
            ]);
            $actions .= html_writer::tag(
                'button',
                get_string('jobs:action_retry', 'local_astusse'),
                ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-primary']
            );
            $actions .= html_writer::end_tag('form');
        }

        echo html_writer::start_tag('tr', [
            'data-job-id' => (int)$job->id,
            'data-job-status' => $job->status,
        ]);
        echo html_writer::tag('td', (int)$job->id, ['class' => 'local-astusse-jobs-col-id small text-muted']);
        echo html_writer::tag('td', userdate((int)$job->timecreated, get_string('strftimedatetimeshort', 'langconfig')));
        echo html_writer::tag('td', s($job->filename));
        echo html_writer::tag('td', $desc['sourcelabel']);
        echo html_writer::tag('td', empty($targetlabels) ? '—' : implode(', ', $targetlabels));
        echo html_writer::tag('td', html_writer::tag(
            'span',
            $desc['statuslabel'],
            ['class' => $desc['statusclass'], 'data-cell' => 'status']
        ));
        echo html_writer::tag('td', (int)$job->attempts, ['data-cell' => 'attempts']);
        echo html_writer::tag('td', $details !== '' ? $details : '—', ['data-cell' => 'details']);
        echo html_writer::tag('td', $actions !== '' ? $actions : '—', ['data-cell' => 'actions']);
        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_div();

    // Pagination bar (Moodle native).
    if ($totaljobs > $perpage) {
        echo $OUTPUT->paging_bar($totaljobs, $page, $perpage, $PAGE->url);
    }
}

echo html_writer::end_div();

$PAGE->requires->js_call_amd('local_astusse/jobs_page', 'init', [[
    'statusEndpoint' => $statusendpoint->out(false),
    'pollIntervalMs' => 3000,
]]);

echo $OUTPUT->footer();
