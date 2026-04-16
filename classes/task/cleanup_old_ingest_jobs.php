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
 * Scheduled cleanup of finalised ingestion jobs older than 30 days.
 *
 * @package     local_astusse
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_astusse\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Delete succeeded/failed ingestion jobs older than the retention window.
 */
class cleanup_old_ingest_jobs extends \core\task\scheduled_task {
    /** @var int Retention window in seconds for finalised jobs. */
    private const RETENTION_SECONDS = 30 * 24 * 3600;

    /**
     * Task human-readable name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task:cleanup_old_ingest_jobs', 'local_astusse');
    }

    /**
     * Delete stale rows and their persisted uploaded files.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        $cutoff = time() - self::RETENTION_SECONDS;

        $select = "status IN ('succeeded', 'failed')
            AND timecompleted IS NOT NULL
            AND timecompleted < :cutoff";
        $params = ['cutoff' => $cutoff];

        $stalejobs = $DB->get_records_select(
            ingest_document_task::TABLE,
            $select,
            $params,
            'id ASC',
            'id, userid, sourcetype, fileareaitemid'
        );

        if (empty($stalejobs)) {
            mtrace('local_astusse cleanup: no stale ingestion jobs to delete');
            return;
        }

        $fs = get_file_storage();
        $deleted = 0;
        foreach ($stalejobs as $job) {
            if ($job->sourcetype === 'upload' && (int)$job->fileareaitemid > 0) {
                try {
                    $usercontext = \context_user::instance((int)$job->userid);
                    $fs->delete_area_files(
                        $usercontext->id,
                        'local_astusse',
                        ingest_document_task::FILEAREA,
                        (int)$job->fileareaitemid
                    );
                } catch (\Throwable $e) {
                    mtrace('local_astusse cleanup: could not delete files for job ' . $job->id
                        . ': ' . $e->getMessage());
                }
            }
            $deleted++;
        }

        $DB->delete_records_select(ingest_document_task::TABLE, $select, $params);
        mtrace('local_astusse cleanup: removed ' . $deleted . ' stale ingestion jobs');
    }
}
