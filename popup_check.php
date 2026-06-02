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

// Global opt-out (T5 preference ; default off).
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
// T3 etape 6 fix definitif : i18n est FR-only v1 (decision #11) et le cache
// strings Moodle pose probleme dans cet environnement -- on hardcode les
// libelles ici, plus aucune dependance a get_string/lang/cache.
$displayname = fullname($USER);
$consulted   = (int)($body['consultedCount'] ?? 0);
$courses     = (int)($body['courseCount'] ?? 0);
$reviewable  = (int)($body['reviewableCount'] ?? 0);
$fragile     = (int)($body['fragileCount'] ?? 0);

$consultedline = "Tu as consulté {$consulted} ressources sur {$courses} cours ces derniers jours.";
$reviewline = $fragile > 0
    ? "⚠ {$fragile} notions sont en dessous de 90 % de rétention prédite."
    : "{$reviewable} ressources gagneraient à être consolidées.";

// T3 : si la pre-generation a ete declenchee cote API, on propage l'ID au front.
$quizsessionid = isset($body['quizSessionId']) ? (string)$body['quizSessionId'] : null;

// Dict de strings FR pour le quiz (Etats 2, 3, 4). Hardcode.
// Templates : {placeholder} interpole cote JS via le helper fmt(tpl, params).
$quizstrings = [
    'loading'                 => 'Préparation des questions…',
    'waitingGeneration'       => 'Génération en cours, encore quelques secondes…',
    'librePlaceholder'        => 'Écris ta réponse…',
    'validate'                => 'Valider la réponse',
    'next'                    => 'Question suivante',
    'seeResult'               => 'Voir le bilan',
    'feedbackCorrect'         => 'Bonne réponse',
    'feedbackIncorrect'       => 'Réponse incorrecte',
    'feedbackPending'         => 'Réponse enregistrée, évaluation reportée au bilan.',
    'errorLoad'               => 'Impossible de charger le quiz pour le moment. Réessaie plus tard.',
    'errorSend'               => 'Erreur d\'envoi. Vérifie ta connexion et réessaie.',
    'errorGeneratingTimeout'  => 'La génération prend plus de temps que prévu. Réessaie dans un instant.',
    'errorExpired'            => 'Cette session de révision a expiré. Reviens demain.',
    'errorFailed'             => 'La génération a échoué côté serveur. Reviens un peu plus tard.',
    'bilanTitle'              => 'Bilan de la session',
    'bilanPartial'            => 'Tu maîtrises l\'essentiel. Une ressource gagnerait à être révisée.',
    'bilanWeak'               => 'Quelques points clés à retravailler. Le tuteur IA peut t\'aider.',
    'bilanSeeResource'        => 'Voir la ressource',
    'bilanAskTutor'           => 'Demander au tuteur',
    'bilanFinish'             => 'Terminer',
    'bilanPerresourceLabel'   => 'Détail par ressource :',
    'questionProgressTpl'     => 'Question {current} sur {total}',
    'correctAnswerQcmTpl'     => 'Bonne réponse : {answer}',
    'correctAnswerLibreTpl'   => 'Réponse attendue : {answer}',
    'bilanScoreTpl'           => '{correct} sur {total} bonnes réponses',
    'bilanConsolidationTpl'   => '✅ Mémoire consolidée. Prochaine révision dans {days} jours.',
    'bilanPerresourceLineTpl' => '{name} ({course}) — {correct}/{total}',
];

echo json_encode([
    'hasPending'    => true,
    'title'         => '💡 Révision suggérée',
    'greeting'      => "Bonjour {$displayname},",
    'consultedLine' => $consultedline,
    'reviewLine'    => $reviewline,
    'pitch'         => 'Un quiz interleavé (5 questions, ~3 min) consoliderait ta mémoire.',
    'btnLaunch'     => 'Lancer',
    'btnLater'      => 'Plus tard',
    'btnClose'      => 'Annuler',
    'quizSessionId' => $quizsessionid,
    'strings'       => $quizstrings,
]);
die;
