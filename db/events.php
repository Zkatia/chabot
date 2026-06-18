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
 * Event observers for local_astusse.
 *
 * @package     local_astusse
 * @copyright   2026 Ingenium Digital Learning
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    // T1 : capture des consultations de ressources. Observer le type de base
    // \core\event\course_module_viewed catche TOUTES les sous-classes des modules
    // (mod_page, mod_resource, mod_url, mod_book, mod_lesson, mod_glossary,
    // mod_scorm, mod_quiz, mod_assign, mod_wiki, mod_folder...).
    // H5P a son propre observer xAPI (etape 6).
    //
    // internal=false : le callback s'execute APRES commit de la transaction,
    // ce qui est sur pour enfiler un adhoc task.
    [
        'eventname' => '\core\event\course_module_viewed',
        'callback'  => '\local_astusse\observer\consultation_observer::on_course_module_viewed',
        'internal'  => false,
        'priority'  => 200,
    ],
    // T2 : a la connexion, on arme un flag de session pour proposer le pop-up
    // de revision sur la premiere page rendue (cf. local_astusse_inject_review_popup).
    [
        'eventname' => '\core\event\user_loggedin',
        'callback'  => '\local_astusse\observer\login_observer::on_user_loggedin',
        'internal'  => false,
    ],
];
