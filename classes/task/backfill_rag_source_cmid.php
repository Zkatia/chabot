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
 * One-shot backfill : remplit source_cmid / source_type sur les anciens
 * rag_documents indexés avant l'ajout de ces colonnes (T1).
 *
 * Lit local_astusse_ingest_jobs (status='succeeded'), appelle l'endpoint admin
 * /api/admin/rag/documents/by-job/{backendjobid}/source. Idempotent côté API
 * (clause WHERE source_cmid IS NULL), donc les exécutions répétées sont des
 * no-ops après le premier passage complet.
 *
 * @package     local_astusse
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_astusse\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Walks succeeded ingestion jobs and fills source_cmid/source_type
 * on their corresponding rag_documents rows in the backend.
 */
class backfill_rag_source_cmid extends \core\task\scheduled_task {
    /** @var int Batch size per iteration to keep memory and API load bounded. */
    private const BATCH_SIZE = 50;

    /** @var int Micro-pause between API calls (microseconds). */
    private const SLEEP_BETWEEN_CALLS_US = 50000; // 50ms

    /**
     * Task human-readable name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task:backfill_rag_source_cmid', 'local_astusse');
    }

    /**
     * Iterate over eligible ingest jobs and trigger backend backfill.
     *
     * @return void
     */
    public function execute() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/astusse/lib.php');

        $admin = \local_astusse_get_scope_sync_user();
        if ($admin === null) {
            mtrace('local_astusse backfill: no admin user resolvable, skipping run');
            return;
        }

        $select = "status = 'succeeded'
            AND backendjobid IS NOT NULL
            AND backendjobid <> ''
            AND sourcecmid IS NOT NULL
            AND sourcecmid > 0
            AND sourcetype IS NOT NULL
            AND sourcetype <> ''";

        $client = new \local_astusse\api_client();

        $attempted = 0;
        $updated = 0;
        $already = 0;
        $notfound = 0;
        $errors = 0;
        $offset = 0;

        while (true) {
            $jobs = $DB->get_records_select(
                ingest_document_task::TABLE,
                $select,
                [],
                'id ASC',
                'id, backendjobid, sourcecmid, sourcetype',
                $offset,
                self::BATCH_SIZE
            );

            if (empty($jobs)) {
                break;
            }

            foreach ($jobs as $job) {
                try {
                    $result = $client->backfill_rag_source_for_user(
                        $admin,
                        (string)$job->backendjobid,
                        (int)$job->sourcecmid,
                        (string)$job->sourcetype
                    );
                    $status = (int)($result['status'] ?? 0);

                    if ($status === 204) {
                        $updated++;
                    } else if ($status === 200) {
                        $already++;
                    } else if ($status === 404) {
                        $notfound++;
                    } else {
                        $errors++;
                        mtrace('local_astusse backfill: unexpected HTTP ' . $status
                            . ' for job ' . $job->id . ' (backendjobid=' . $job->backendjobid . ')');
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    mtrace('local_astusse backfill: error on job ' . $job->id . ': ' . $e->getMessage());
                }
                $attempted++;
                usleep(self::SLEEP_BETWEEN_CALLS_US);
            }

            $offset += self::BATCH_SIZE;
        }

        mtrace(sprintf(
            'local_astusse backfill done: attempted=%d, updated=%d, already=%d, not_found=%d, errors=%d',
            $attempted, $updated, $already, $notfound, $errors
        ));
    }
}
