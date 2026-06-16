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
 * Login observer for the T2 spaced-repetition pop-up.
 *
 * @package     local_astusse
 * @copyright   2026 Ingenium Digital Learning
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_astusse\observer;

/**
 * Arms a per-session flag at login. The flag is consumed by
 * local_astusse_before_footer() to inject the pop-up loader on the first
 * page rendered after login (typically the dashboard /my).
 */
class login_observer {
    /** @var string Session flag name. */
    public const SESSION_FLAG = 'local_astusse_check_review';

    /**
     * Handle a user_loggedin event.
     *
     * @param \core\event\user_loggedin $event
     * @return void
     */
    public static function on_user_loggedin(\core\event\user_loggedin $event): void {
        global $SESSION;
        // Mark that the review pop-up should be evaluated on the next page render.
        $SESSION->{self::SESSION_FLAG} = true;
    }
}
