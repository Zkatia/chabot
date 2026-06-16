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

namespace local_astusse;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/astusse/lib.php');
require_once($CFG->dirroot . '/local/astusse/classes/reference_trainer_service.php');

/**
 * Tests for reference trainer service.
 *
 * @package     local_astusse
 * @copyright   2026 Ingenium Digital Learning
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \local_astusse\reference_trainer_service
 */
final class reference_trainer_service_test extends \advanced_testcase {
    public function test_get_reference_trainer_context_returns_valid_trainer_id(): void {
        global $DB;

        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $trainer = $this->getDataGenerator()->create_user();
        $admin = get_admin();
        $editingteacher = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
        role_assign($editingteacher->id, $trainer->id, \context_course::instance($course->id));

        reference_trainer_service::save_reference_trainer($course->id, (int)$trainer->id, (int)$admin->id);

        $context = local_astusse_get_reference_trainer_context($course->id);

        $this->assertSame('valid', $context['status']['state']);
        $this->assertSame((string)$trainer->id, $context['trainerid']);
    }

    public function test_get_reference_trainer_context_hides_invalid_trainer_id(): void {
        global $DB;

        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $trainer = $this->getDataGenerator()->create_user();
        $DB->insert_record('local_astusse_course_ref_trainer', (object)[
            'courseid' => $course->id,
            'trainerid' => $trainer->id,
            'updatedby' => get_admin()->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $context = local_astusse_get_reference_trainer_context($course->id);

        $this->assertSame('invalid', $context['status']['state']);
        $this->assertNull($context['trainerid']);
    }
}
