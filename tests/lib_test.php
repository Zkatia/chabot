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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/astusse/lib.php');

/**
 * Tests for local_astusse lib helpers.
 *
 * @package     local_astusse
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class local_astusse_lib_test extends advanced_testcase {
    public function test_get_scope_sync_user_prefers_current_site_admin(): void {
        $this->resetAfterTest(true);

        $this->setAdminUser();
        $admin = get_admin();

        $resolved = local_astusse_get_scope_sync_user();

        $this->assertNotNull($resolved);
        $this->assertSame((int)$admin->id, (int)$resolved->id);
    }

    public function test_get_scope_sync_user_falls_back_to_admin_when_current_user_is_not_admin(): void {
        $this->resetAfterTest(true);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $resolved = local_astusse_get_scope_sync_user();
        $admin = get_admin();

        $this->assertNotNull($resolved);
        $this->assertSame((int)$admin->id, (int)$resolved->id);
        $this->assertNotSame((int)$user->id, (int)$resolved->id);
    }
}
