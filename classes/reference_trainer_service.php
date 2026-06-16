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
 * Course reference trainer service for local_astusse.
 *
 * @package     local_astusse
 * @copyright   2026 Ingenium Digital Learning
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_astusse;

/**
 * Stores and validates the reference trainer configured for a course.
 */
class reference_trainer_service {
    /** @var string */
    private const TABLE = 'local_astusse_course_ref_trainer';

    /**
     * Return the raw stored reference trainer record for a course.
     *
     * @param int $courseid
     * @return \stdClass|null
     */
    public static function get_record(int $courseid): ?\stdClass {
        global $DB;

        if ($courseid <= 0) {
            return null;
        }

        $record = $DB->get_record(self::TABLE, ['courseid' => $courseid]);
        return $record ?: null;
    }

    /**
     * Return the valid reference trainer id for a course, or null when absent/invalid.
     *
     * @param int $courseid
     * @return string|null
     */
    public static function get_effective_trainer_id(int $courseid): ?string {
        $record = self::get_record($courseid);
        if ($record === null) {
            return null;
        }

        $trainerid = (int)$record->trainerid;
        if (!self::is_valid_candidate($courseid, $trainerid)) {
            return null;
        }

        return (string)$trainerid;
    }

    /**
     * Return a state array used by UI pages and the chat page.
     *
     * @param int $courseid
     * @return array
     */
    public static function get_status(int $courseid): array {
        global $DB;

        $record = self::get_record($courseid);
        if ($record === null) {
            return [
                'state' => 'missing',
                'trainerid' => null,
                'record' => null,
                'user' => null,
            ];
        }

        $trainerid = (int)$record->trainerid;
        $user = $DB->get_record('user', ['id' => $trainerid]);
        if (!self::is_valid_candidate($courseid, $trainerid) || empty($user)) {
            return [
                'state' => 'invalid',
                'trainerid' => (string)$trainerid,
                'record' => $record,
                'user' => $user ?: null,
            ];
        }

        return [
            'state' => 'valid',
            'trainerid' => (string)$trainerid,
            'record' => $record,
            'user' => $user,
        ];
    }

    /**
     * Return selectable editing teachers for the course.
     *
     * @param int $courseid
     * @return array<int,string>
     */
    public static function get_candidate_options(int $courseid): array {
        global $DB;

        $context = \context_course::instance($courseid);
        $editingteacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], 'id', IGNORE_MISSING);
        if (!$editingteacherrole) {
            return [];
        }

        // Include every name field fullname() needs, otherwise it emits a debugging() notice.
        $select = 'u.id, u.email';
        foreach (\core_user\fields::get_name_fields() as $namefield) {
            $select .= ', u.' . $namefield;
        }
        $users = get_role_users($editingteacherrole->id, $context, false, $select);
        if (!$users) {
            return [];
        }

        $options = [];
        foreach ($users as $user) {
            $label = fullname($user);
            $email = trim((string)($user->email ?? ''));
            if ($email !== '') {
                $label .= ' (' . $email . ')';
            }
            $options[(int)$user->id] = $label;
        }

        natcasesort($options);
        return $options;
    }

    /**
     * Persist or clear the reference trainer for a course.
     *
     * @param int $courseid
     * @param int|null $trainerid
     * @param int $updatedby
     * @return void
     */
    public static function save_reference_trainer(int $courseid, ?int $trainerid, int $updatedby): void {
        global $DB;

        if ($courseid <= 0) {
            throw new \coding_exception('courseid must be positive');
        }

        if ($trainerid !== null && !self::is_valid_candidate($courseid, $trainerid)) {
            throw new \moodle_exception('referencetrainer:error_invalid_selection', 'local_astusse');
        }

        $existing = self::get_record($courseid);
        if ($trainerid === null) {
            if ($existing) {
                $DB->delete_records(self::TABLE, ['courseid' => $courseid]);
            }
            return;
        }

        $now = time();
        if ($existing) {
            $existing->trainerid = $trainerid;
            $existing->updatedby = $updatedby;
            $existing->timemodified = $now;
            $DB->update_record(self::TABLE, $existing);
            return;
        }

        $record = (object)[
            'courseid' => $courseid,
            'trainerid' => $trainerid,
            'updatedby' => $updatedby,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Check whether a user is currently eligible as course reference trainer.
     *
     * @param int $courseid
     * @param int $trainerid
     * @return bool
     */
    public static function is_valid_candidate(int $courseid, int $trainerid): bool {
        if ($courseid <= 0 || $trainerid <= 0) {
            return false;
        }

        $options = self::get_candidate_options($courseid);
        return array_key_exists($trainerid, $options);
    }
}
