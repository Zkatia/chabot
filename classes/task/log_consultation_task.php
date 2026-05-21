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
 * Ad-hoc task : send one queued consultation to the AI API (T1).
 *
 * @package     local_astusse
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_astusse\task;

defined('MOODLE_INTERNAL') || die();

use local_astusse\observer\consultation_observer;

/**
 * Process a single consultation queued in local_astusse_consultation_queue.
 *
 * Custom data payload : {"queueid": int}.
 */
class log_consultation_task extends \core\task\adhoc_task {

    /** @var int Max attempts for transient failures (network / 5xx / timeout). */
    private const MAX_ATTEMPTS_TRANSIENT = 5;

    /**
     * Send the queued consultation to the backend.
     *
     * @return void
     */
    public function execute() {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/local/astusse/lib.php');

        $data = (object)$this->get_custom_data();
        $queueid = isset($data->queueid) ? (int)$data->queueid : 0;
        if ($queueid <= 0) {
            mtrace('local_astusse log_consultation: missing queueid in custom data');
            return;
        }

        $row = $DB->get_record(consultation_observer::QUEUE_TABLE, ['id' => $queueid]);
        if (!$row) {
            mtrace('local_astusse log_consultation: queue row ' . $queueid . ' not found');
            return;
        }
        if ($row->status !== 'queued') {
            mtrace('local_astusse log_consultation: row ' . $queueid . ' already in state ' . $row->status);
            return;
        }

        $attempts = (int)$row->attempts + 1;
        $DB->set_field(consultation_observer::QUEUE_TABLE, 'attempts', $attempts, ['id' => $queueid]);

        try {
            $user = $DB->get_record('user', ['id' => (int)$row->userid], '*', MUST_EXIST);

            $client = new \local_astusse\api_client();
            $result = $client->log_consultation_for_user(
                $user,
                (int)$row->cmid,
                (string)$row->sourcetype,
                (int)$row->courseid,
                (int)$row->viewedat
            );

            $httpstatus = (int)($result['status'] ?? 0);

            if ($httpstatus === 202) {
                // Stored or deduplicated server-side : success.
                $this->finalize($queueid, 'sent', $httpstatus, null);
                mtrace('local_astusse log_consultation: row ' . $queueid . ' sent (202)');
                return;
            }

            if ($httpstatus === 204) {
                // Resource not indexed in the knowledge base : silently ignored (T1 spec).
                $this->finalize($queueid, 'ignored', $httpstatus, null);
                mtrace('local_astusse log_consultation: row ' . $queueid . ' ignored (204, not indexed)');
                return;
            }

            if ($httpstatus >= 500 || $httpstatus === 408 || $httpstatus === 429 || $httpstatus === 0) {
                // Transient : let Moodle retry (bounded by MAX_ATTEMPTS_TRANSIENT).
                $msg = 'transient HTTP ' . $httpstatus;
                if ($attempts >= self::MAX_ATTEMPTS_TRANSIENT) {
                    $this->finalize($queueid, 'failed', $httpstatus,
                        'max transient attempts reached: ' . $msg);
                    mtrace('local_astusse log_consultation: row ' . $queueid . ' failed after '
                        . $attempts . ' attempts (' . $msg . ')');
                    return;
                }
                $DB->set_field(consultation_observer::QUEUE_TABLE, 'httpstatus', $httpstatus, ['id' => $queueid]);
                throw new \Exception('Transient failure for consultation row ' . $queueid . ': ' . $msg);
            }

            // Permanent client error (4xx other than 204) : do not retry.
            $body = is_array($result['body_json'] ?? null) ? json_encode($result['body_json']) : null;
            $this->finalize($queueid, 'failed', $httpstatus, 'permanent HTTP ' . $httpstatus
                . ($body !== null ? ' : ' . substr($body, 0, 500) : ''));
            mtrace('local_astusse log_consultation: row ' . $queueid . ' failed (HTTP ' . $httpstatus . ')');
        } catch (\Throwable $e) {
            // Network exception / MUST_EXIST miss / re-thrown transient.
            if ($attempts >= self::MAX_ATTEMPTS_TRANSIENT) {
                $this->finalize($queueid, 'failed', null,
                    'max attempts reached: ' . $e->getMessage());
                mtrace('local_astusse log_consultation: row ' . $queueid
                    . ' failed after ' . $attempts . ' attempts: ' . $e->getMessage());
                return;
            }
            // Re-throw so Moodle re-queues with exponential backoff.
            throw $e;
        }
    }

    /**
     * Write a final state to the queue row.
     *
     * @param int $queueid
     * @param string $status
     * @param int|null $httpstatus
     * @param string|null $errormessage
     * @return void
     */
    private function finalize(int $queueid, string $status, ?int $httpstatus, ?string $errormessage): void {
        global $DB;
        $DB->update_record(consultation_observer::QUEUE_TABLE, (object)[
            'id' => $queueid,
            'status' => $status,
            'httpstatus' => $httpstatus,
            'errormessage' => $errormessage !== null ? substr($errormessage, 0, 2000) : null,
            'timecompleted' => time(),
        ]);
    }
}
