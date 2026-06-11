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
 * AJAX endpoint (T2) : tells the front-end whether to show the review pop-up,
 * and returns the ready-to-display (already localised) texts.
 *
 * Defensive by design : any error / slowness returns hasPending=false so the
 * apprenant never sees a glitch.
 *
 * @package     local_astusse
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
require_sesskey();

header('Content-Type: application/json; charset=utf-8');

global $USER;

/**
 * Helper : emit a "no pop-up" response and stop.
 *
 * @param string $reason
 */
function local_astusse_popup_none(string $reason): void {
    echo json_encode(['hasPending' => false, 'reason' => $reason]);
    die;
}

// Guests never get the pop-up.
if (isguestuser()) {
    local_astusse_popup_none('guest');
}

// T5 — Cache local du opt-out global, posé par la page profil au moment
// du toggle. Court-circuit rapide pour éviter un appel API si on sait deja
// qu'on est désactivé. Source de vérité finale : l'API IA, qui appliquera
// son propre filtre (snooze + disabled + cancelled/mastered cmids).
if (get_user_preferences('local_astusse_review_optout', 0)) {
    local_astusse_popup_none('opt_out');
}

$recencydays = (int)get_config('local_astusse', 'review_recency_days');
if ($recencydays < 1) {
    $recencydays = 60;
}
$mineligible = (int)get_config('local_astusse', 'review_min_eligible');
if ($mineligible < 1) {
    $mineligible = 1;
}
$maxresources = (int)get_config('local_astusse', 'review_max_resources_per_quiz');
if ($maxresources < 2) {
    // Plancher dur : la spec T3 impose au moins 2 ressources alternees.
    $maxresources = 3;
}

try {
    $client = new \local_astusse\api_client();
    $result = $client->get_pending_review_for_user($USER, $recencydays, $mineligible, $maxresources);
} catch (\Throwable $e) {
    // API down/slow/unreachable → silent, no pop-up (defensive behaviour).
    local_astusse_popup_none('api_error');
}

$status = (int)($result['status'] ?? 0);
$body = is_array($result['body_json'] ?? null) ? $result['body_json'] : null;

if ($status !== 200 || $body === null || empty($body['hasPending'])) {
    local_astusse_popup_none(is_array($body) && isset($body['reason']) ? (string)$body['reason'] : 'not_pending');
}

// Compose the (localised) texts server-side from the counters.
$a = (object)[
    'name'       => fullname($USER),
    'consulted'  => (int)($body['consultedCount'] ?? 0),
    'courses'    => (int)($body['courseCount'] ?? 0),
    'reviewable' => (int)($body['reviewableCount'] ?? 0),
    'fragile'    => (int)($body['fragileCount'] ?? 0),
];

$reviewline = $a->fragile > 0
    ? get_string('popup:fragile', 'local_astusse', $a)
    : get_string('popup:toconsolidate', 'local_astusse', $a);

// T3 : si la pre-generation a ete declenchee cote API, on propage l'ID au front.
$quizsessionid = isset($body['quizSessionId']) ? (string)$body['quizSessionId'] : null;

// T5 : cmids des ressources proposees au pop-up. Utilises par le bouton "Annuler"
// pour les marquer comme cancelled cote API IA.
$cmids = [];
if (isset($body['cmids']) && is_array($body['cmids'])) {
    $cmids = array_values(array_filter(array_map('intval', $body['cmids']), fn($v) => $v > 0));
}

// Dict de strings inlinees pour le quiz (Etats 2, 3, 4). Le JS consomme via s(key)
// et fmt(tpl, params) ; cela evite la dependance a M.str / cache navigateur sur le
// bundle strings JS, et garde un seul aller-retour HTTP au login.
//
// Templates : on passe un placeholder {key} comme valeur a get_string, Moodle
// remplace les $a->key par {key} et on conserve la traduction.
$quizstrings = [
    'cancelConfirm'           => get_string('popup:cancel_confirm', 'local_astusse'),
    'loading'                 => get_string('quiz:loading', 'local_astusse'),
    'waitingGeneration'       => get_string('quiz:waiting_generation', 'local_astusse'),
    'librePlaceholder'        => get_string('quiz:libre_placeholder', 'local_astusse'),
    'validate'                => get_string('quiz:validate', 'local_astusse'),
    'next'                    => get_string('quiz:next', 'local_astusse'),
    'seeResult'               => get_string('quiz:see_result', 'local_astusse'),
    'feedbackCorrect'         => get_string('quiz:feedback_correct', 'local_astusse'),
    'feedbackIncorrect'       => get_string('quiz:feedback_incorrect', 'local_astusse'),
    'feedbackPending'         => get_string('quiz:feedback_pending', 'local_astusse'),
    'errorLoad'               => get_string('quiz:error_load', 'local_astusse'),
    'errorSend'               => get_string('quiz:error_send', 'local_astusse'),
    'errorGeneratingTimeout'  => get_string('quiz:error_generating_timeout', 'local_astusse'),
    'errorExpired'            => get_string('quiz:error_expired', 'local_astusse'),
    'errorFailed'             => get_string('quiz:error_failed', 'local_astusse'),
    'bilanTitle'              => get_string('bilan:title', 'local_astusse'),
    'bilanPartial'            => get_string('bilan:partial', 'local_astusse'),
    'bilanWeak'               => get_string('bilan:weak', 'local_astusse'),
    'bilanSeeResource'        => get_string('bilan:see_resource', 'local_astusse'),
    'bilanAskTutor'           => get_string('bilan:ask_tutor', 'local_astusse'),
    'bilanFinish'             => get_string('bilan:finish', 'local_astusse'),
    'bilanPerresourceLabel'   => get_string('bilan:perresource_label', 'local_astusse'),

    'questionProgressTpl'     => get_string('quiz:question_progress', 'local_astusse',
        (object)['current' => '{current}', 'total' => '{total}']),
    'correctAnswerQcmTpl'     => get_string('quiz:correct_answer_qcm', 'local_astusse', '{answer}'),
    'correctAnswerLibreTpl'   => get_string('quiz:correct_answer_libre', 'local_astusse', '{answer}'),
    'bilanScoreTpl'           => get_string('bilan:score', 'local_astusse',
        (object)['correct' => '{correct}', 'total' => '{total}']),
    'bilanConsolidationTpl'   => get_string('bilan:consolidation', 'local_astusse', '{days}'),
    'bilanPerresourceLineTpl' => get_string('bilan:perresource_line', 'local_astusse',
        (object)['name' => '{name}', 'course' => '{course}',
                 'correct' => '{correct}', 'total' => '{total}']),
];

echo json_encode([
    'hasPending'    => true,
    'title'         => get_string('popup:title', 'local_astusse'),
    'greeting'      => get_string('popup:greeting', 'local_astusse', $a),
    'consultedLine' => get_string('popup:consulted', 'local_astusse', $a),
    'reviewLine'    => $reviewline,
    'pitch'         => get_string('popup:pitch', 'local_astusse'),
    'btnLaunch'     => get_string('popup:launch', 'local_astusse'),
    'btnLater'      => get_string('popup:later', 'local_astusse'),
    'btnClose'      => get_string('popup:close', 'local_astusse'),
    'quizSessionId' => $quizsessionid,
    'cmids'         => $cmids,
    'strings'       => $quizstrings,
]);
die;
