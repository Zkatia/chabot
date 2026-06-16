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
 * Scheduled cleanup of processed consultation-queue rows (T1).
 *
 * @package     local_astusse
 * @copyright   2026 Ingenium Digital Learning
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_astusse\task;

use local_astusse\observer\consultation_observer;

/**
 * Delete consultation-queue rows in a final state (sent/ignored/failed)
 * older than the retention window. 'queued' rows are never purged.
 */
class cleanup_consultation_queue extends \core\task\scheduled_task {
    /** @var int Retention window in seconds for finalised queue rows. */
    private const RETENTION_SECONDS = 7 * 24 * 3600;

    /**
     * Task human-readable name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task:cleanup_consultation_queue', 'local_astusse');
    }

    /**
     * Purge processed rows older than the retention window.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        $cutoff = time() - self::RETENTION_SECONDS;
        $select = "status IN ('sent', 'ignored', 'failed')
            AND timecompleted IS NOT NULL
            AND timecompleted < :cutoff";
        $params = ['cutoff' => $cutoff];

        $count = $DB->count_records_select(consultation_observer::QUEUE_TABLE, $select, $params);
        if ($count === 0) {
            mtrace('local_astusse cleanup_consultation_queue: nothing to purge');
            return;
        }

        $DB->delete_records_select(consultation_observer::QUEUE_TABLE, $select, $params);
        mtrace('local_astusse cleanup_consultation_queue: removed ' . $count . ' processed rows');
    }
}
