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
 * T5 — Page profil "Préférences de révision".
 *
 * Permet a l'apprenant de :
 *   - activer/desactiver globalement les rappels de revision
 *   - reactiver les ressources qu'il avait annulees
 *   - reactiver les ressources passees en maitrise
 *
 * Source de verite : API IA (cote serveur). Cette page est une vue de
 * /api/review/preferences + /api/review/overrides, avec actions POST.
 *
 * @package     local_astusse
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

global $USER, $PAGE, $OUTPUT;

if (isguestuser()) {
    redirect(new moodle_url('/'));
}

$PAGE->set_context(context_user::instance($USER->id));
$PAGE->set_url(new moodle_url('/local/astusse/review_preferences.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('prefs:title', 'local_astusse'));
$PAGE->set_heading(get_string('prefs:heading', 'local_astusse'));
$PAGE->requires->css(new moodle_url('/local/astusse/styles.css'));

$client = new \local_astusse\api_client();

// =====================================================================
// Traitement des actions POST (PRG pattern : redirect apres mutation)
// =====================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    // PARAM_ALPHAEXT autorise [a-zA-Z_-] : nos actions ('toggle_global',
    // 'reactivate') contiennent un underscore que PARAM_ALPHA strip.
    $action = optional_param('action', '', PARAM_ALPHAEXT);

    $notifkey = null;
    $notiftype = 'success';
    try {
        if ($action === 'toggle_global') {
            $enabled = optional_param('enabled', 0, PARAM_INT) === 1;
            $client->set_global_state_for_user($USER, $enabled);
            // Sync cache local Moodle (short-circuit de popup_check.php).
            // L'API IA reste la source de verite, mais ce cache evite un round-trip
            // a chaque connexion quand l'apprenant est disabled.
            set_user_preference('local_astusse_review_optout', $enabled ? 0 : 1);
            $notifkey = $enabled ? 'prefs:notif_enabled' : 'prefs:notif_disabled';
        } else if ($action === 'reactivate') {
            $cmid = required_param('cmid', PARAM_INT);
            if ($cmid > 0) {
                $client->reactivate_resource_for_user($USER, $cmid);
                $notifkey = 'prefs:notif_reactivated';
            }
        }
    } catch (\Throwable $e) {
        debugging('review_preferences action failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        $notifkey = 'prefs:notif_error';
        $notiftype = 'error';
    }

    // Notification flash : survit au redirect via \core\notification (auto-affichee
    // sur la page suivante par $OUTPUT->header()).
    if ($notifkey !== null) {
        \core\notification::add(
            get_string($notifkey, 'local_astusse'),
            $notiftype === 'success' ? \core\notification::SUCCESS : \core\notification::ERROR
        );
    }

    redirect(new moodle_url('/local/astusse/review_preferences.php'));
}

// =====================================================================
// Lecture etat courant via API IA (defensif : si API down, on degrade)
// =====================================================================

$prefsenabled = true;
$snoozeduntil = null;
$overrides = [];
$apiok = true;

try {
    $prefsresult = $client->get_preferences_for_user($USER);
    if (($prefsresult['status'] ?? 0) === 200 && is_array($prefsresult['body_json'] ?? null)) {
        $prefsenabled = (bool)($prefsresult['body_json']['enabled'] ?? true);
        $snoozeduntil = $prefsresult['body_json']['snoozedUntil'] ?? null;
    } else {
        $apiok = false;
    }

    $overridesresult = $client->list_overrides_for_user($USER);
    if (($overridesresult['status'] ?? 0) === 200 && is_array($overridesresult['body_json'] ?? null)) {
        $overrides = $overridesresult['body_json'];
    }
} catch (\Throwable $e) {
    debugging('review_preferences fetch failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
    $apiok = false;
}

// =====================================================================
// Resolution titres ressources cote Moodle (cmid → nom + cours).
// =====================================================================

$resolved = [];
foreach ($overrides as $entry) {
    $cmid = (int)($entry['cmid'] ?? 0);
    if ($cmid <= 0) continue;
    $name = '(cmid=' . $cmid . ')';
    $coursename = '';
    $courseurl = null;
    try {
        $cm = get_coursemodule_from_id(null, $cmid, 0, false, IGNORE_MISSING);
        if ($cm) {
            $name = format_string($cm->name);
            $course = get_course($cm->course);
            if ($course) {
                $coursename = format_string($course->shortname ?: $course->fullname);
                $courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
            }
        }
    } catch (\Throwable $e) {
        // Ressource probablement supprimee depuis. On garde le placeholder.
    }
    // updatedAt arrive de l'API en ISO-8601 (Instant Jackson). strtotime gere ce
    // format ; 0 si parsing echoue.
    $updatedts = isset($entry['updatedAt']) ? (int)strtotime((string)$entry['updatedAt']) : 0;
    $resolved[] = [
        'cmid' => $cmid,
        'state' => (string)($entry['state'] ?? ''),
        'name' => $name,
        'course' => $coursename,
        'course_url' => $courseurl,
        'updated_ts' => $updatedts ?: null,
    ];
}

// Group par etat pour l'affichage (cancelled separe de mastered : intentions
// apprenant differentes — "j'ai mis de cote" vs "j'ai termine").
$cancelled = array_values(array_filter($resolved, fn($r) => $r['state'] === 'cancelled'));
$mastered = array_values(array_filter($resolved, fn($r) => $r['state'] === 'mastered'));

// =====================================================================
// Render
// =====================================================================

/**
 * Render d'une mini-carte ressource (cancelled ou mastered).
 *
 * @param array $r entree resolue (cf. $resolved)
 * @return string HTML
 */
function local_astusse_render_resource_card(array $r): string {
    $statedotclass = $r['state'] === 'mastered'
        ? 'local-astusse-prefs-dot mastered'
        : 'local-astusse-prefs-dot cancelled';
    $stateicon = $r['state'] === 'mastered' ? 'fa-trophy' : 'fa-bookmark';

    $whenstr = !empty($r['updated_ts']) ? userdate($r['updated_ts'], '%d/%m/%Y à %Hh%M') : '';

    $coursehtml = '';
    if ($r['course'] !== '') {
        $coursetext = !empty($r['course_url'])
            ? html_writer::link($r['course_url'], $r['course'])
            : $r['course'];
        $coursehtml = html_writer::tag(
            'span',
            html_writer::tag('i', '', ['class' => 'fa fa-graduation-cap', 'aria-hidden' => 'true'])
                . ' ' . $coursetext,
            ['class' => 'local-astusse-prefs-resource-meta']
        );
    }
    $whenhtml = '';
    if ($whenstr !== '') {
        $whenhtml = html_writer::tag(
            'span',
            html_writer::tag('i', '', ['class' => 'fa fa-clock-o', 'aria-hidden' => 'true'])
                . ' ' . s($whenstr),
            ['class' => 'local-astusse-prefs-resource-meta']
        );
    }

    $form = html_writer::start_tag('form', [
        'method' => 'POST',
        'action' => (new moodle_url('/local/astusse/review_preferences.php'))->out(false),
        'class' => 'local-astusse-prefs-resource-action',
    ])
    . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()])
    . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'reactivate'])
    . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'cmid', 'value' => $r['cmid']])
    . html_writer::tag('button',
        html_writer::tag('i', '', ['class' => 'fa fa-undo', 'aria-hidden' => 'true'])
            . ' ' . get_string('prefs:reactivate_button', 'local_astusse'),
        ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-primary']
    )
    . html_writer::end_tag('form');

    return html_writer::div(
        html_writer::div(
            html_writer::tag('span', '', ['class' => $statedotclass, 'aria-hidden' => 'true'])
            . html_writer::tag('i', '', ['class' => 'fa ' . $stateicon . ' local-astusse-prefs-resource-icon', 'aria-hidden' => 'true'])
            . html_writer::div(
                html_writer::tag('strong', $r['name'], ['class' => 'local-astusse-prefs-resource-name'])
                . html_writer::div($coursehtml . $whenhtml, 'local-astusse-prefs-resource-metas'),
                'local-astusse-prefs-resource-info'
            ),
            'local-astusse-prefs-resource-body'
        )
        . $form,
        'local-astusse-prefs-resource-card ' . $r['state']
    );
}

echo $OUTPUT->header();

echo html_writer::start_div('local-astusse-prefs-page');

if (!$apiok) {
    echo $OUTPUT->notification(get_string('prefs:api_error', 'local_astusse'), 'error');
    // API down : on n'affiche rien d'autre. Le state local est inconnu, et
    // afficher des cartes avec des valeurs par defaut induirait l'apprenant
    // en erreur ("rappels actives" alors qu'il est peut-etre disabled en DB).
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    return;
}

// ---------------------------------------------------------------------------
// Hero card : etat global + action principale (toggle).
// ---------------------------------------------------------------------------

$kickerlabel = $prefsenabled
    ? get_string('prefs:status_active', 'local_astusse')
    : get_string('prefs:status_disabled', 'local_astusse');
$kickerclass = $prefsenabled
    ? 'local-astusse-prefs-kicker active'
    : 'local-astusse-prefs-kicker disabled';
$herodesc = $prefsenabled
    ? get_string('prefs:hero_desc_enabled', 'local_astusse')
    : get_string('prefs:hero_desc_disabled', 'local_astusse');

echo html_writer::start_div('local-astusse-prefs-hero' . ($prefsenabled ? '' : ' disabled'));
echo html_writer::start_div('local-astusse-prefs-hero-left');
echo html_writer::tag('span',
    html_writer::tag('i', '', ['class' => 'fa fa-' . ($prefsenabled ? 'bell' : 'bell-slash'), 'aria-hidden' => 'true'])
        . ' ' . $kickerlabel,
    ['class' => $kickerclass]
);
echo html_writer::tag('h3', get_string('prefs:hero_title', 'local_astusse'),
    ['class' => 'local-astusse-prefs-hero-title']);
echo html_writer::tag('p', $herodesc, ['class' => 'local-astusse-prefs-hero-desc']);
echo html_writer::end_div();

echo html_writer::start_tag('form', [
    'method' => 'POST',
    'action' => (new moodle_url('/local/astusse/review_preferences.php'))->out(false),
    'class' => 'local-astusse-prefs-hero-action',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'toggle_global']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'enabled', 'value' => $prefsenabled ? 0 : 1]);
$btnlabel = $prefsenabled
    ? get_string('prefs:disable_button', 'local_astusse')
    : get_string('prefs:enable_button', 'local_astusse');
$btnicon = $prefsenabled ? 'fa-pause-circle' : 'fa-play-circle';
$btnclass = $prefsenabled ? 'btn btn-outline-secondary' : 'btn btn-primary';
echo html_writer::tag('button',
    html_writer::tag('i', '', ['class' => 'fa ' . $btnicon, 'aria-hidden' => 'true']) . ' ' . $btnlabel,
    ['type' => 'submit', 'class' => $btnclass]
);
echo html_writer::end_tag('form');
echo html_writer::end_div();

// ---------------------------------------------------------------------------
// Bandeau snooze actif (visible UNIQUEMENT quand l'apprenant est encore actif
// mais a un snooze en cours — sinon ce signal n'a pas de sens).
// ---------------------------------------------------------------------------

if ($prefsenabled && $snoozeduntil) {
    $snoozedisplay = userdate(strtotime($snoozeduntil), '%A %d %B %Y à %Hh%M');
    echo html_writer::div(
        html_writer::tag('i', '', ['class' => 'fa fa-pause-circle local-astusse-prefs-snooze-icon', 'aria-hidden' => 'true'])
        . html_writer::div(
            html_writer::tag('strong', get_string('prefs:snooze_active_title', 'local_astusse'))
            . html_writer::tag('span', get_string('prefs:snooze_active_desc', 'local_astusse', $snoozedisplay),
                ['class' => 'local-astusse-prefs-snooze-desc']),
            'local-astusse-prefs-snooze-body'
        ),
        'local-astusse-prefs-snooze-banner'
    );
}

// ---------------------------------------------------------------------------
// Section ressources annulees (cancelled).
// ---------------------------------------------------------------------------

echo html_writer::start_div('local-astusse-prefs-card');
echo html_writer::tag('h3',
    html_writer::tag('i', '', ['class' => 'fa fa-bookmark-o', 'aria-hidden' => 'true'])
        . ' ' . get_string('prefs:cancelled_heading', 'local_astusse'),
    ['class' => 'local-astusse-prefs-section-title']
);
if (empty($cancelled)) {
    echo html_writer::tag('p',
        get_string('prefs:cancelled_empty', 'local_astusse'),
        ['class' => 'local-astusse-prefs-empty']
    );
} else {
    echo html_writer::start_div('local-astusse-prefs-resource-list');
    foreach ($cancelled as $r) {
        echo local_astusse_render_resource_card($r);
    }
    echo html_writer::end_div();
}
echo html_writer::end_div();

// ---------------------------------------------------------------------------
// Section ressources maitrisees (mastered).
// ---------------------------------------------------------------------------

echo html_writer::start_div('local-astusse-prefs-card');
echo html_writer::tag('h3',
    html_writer::tag('i', '', ['class' => 'fa fa-trophy', 'aria-hidden' => 'true'])
        . ' ' . get_string('prefs:mastered_heading', 'local_astusse'),
    ['class' => 'local-astusse-prefs-section-title']
);
if (empty($mastered)) {
    echo html_writer::tag('p',
        get_string('prefs:mastered_empty', 'local_astusse'),
        ['class' => 'local-astusse-prefs-empty']
    );
} else {
    echo html_writer::start_div('local-astusse-prefs-resource-list');
    foreach ($mastered as $r) {
        echo local_astusse_render_resource_card($r);
    }
    echo html_writer::end_div();
}
echo html_writer::end_div();

echo html_writer::end_div(); // local-astusse-prefs-page

echo $OUTPUT->footer();
