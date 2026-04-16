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
 * Ad-hoc task that sends one queued document to the ASTUSSE ingest gateway.
 *
 * @package     local_astusse
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_astusse\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Process a single ingestion job previously inserted in local_astusse_ingest_jobs.
 *
 * Custom data payload: {"jobid": int}.
 */
class ingest_document_task extends \core\task\adhoc_task {
    /** @var string */
    public const TABLE = 'local_astusse_ingest_jobs';

    /** @var string File area used to persist uploaded files between the HTTP request and the cron run. */
    public const FILEAREA = 'ingestqueue';

    /** @var int Number of retries allowed for transient failures (network/5xx/408/429). */
    private const MAX_ATTEMPTS_TRANSIENT = 5;

    /**
     * Execute the ingestion for the job referenced by custom data.
     *
     * @return void
     */
    public function execute() {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/local/astusse/lib.php');

        $data = (object)$this->get_custom_data();
        $jobid = isset($data->jobid) ? (int)$data->jobid : 0;
        if ($jobid <= 0) {
            mtrace('local_astusse ingest task: missing jobid in custom data');
            return;
        }

        $job = $DB->get_record(self::TABLE, ['id' => $jobid]);
        if (!$job) {
            mtrace('local_astusse ingest task: job ' . $jobid . ' not found');
            return;
        }
        if ($job->status === 'succeeded' || $job->status === 'failed') {
            mtrace('local_astusse ingest task: job ' . $jobid . ' already in final state (' . $job->status . ')');
            return;
        }

        $now = time();
        $attempts = (int)$job->attempts + 1;
        $DB->update_record(self::TABLE, (object)[
            'id' => $job->id,
            'status' => 'running',
            'timestarted' => $job->timestarted !== null ? $job->timestarted : $now,
            'attempts' => $attempts,
        ]);
        mtrace('local_astusse ingest task: job ' . $jobid . ' starting (attempt ' . $attempts . ')');

        $transient = false;
        $errormessage = null;
        $httpstatus = null;
        $backendjobid = null;
        $backendtraceid = null;
        $filepath = null;

        try {
            $user = $DB->get_record('user', ['id' => (int)$job->userid], '*', MUST_EXIST);

            $targets = array_values(array_filter(array_map('intval', explode(',', (string)$job->targetcourseids))));
            if (empty($targets)) {
                throw new \Exception('No target course IDs stored in job ' . $job->id);
            }

            $resolved = $this->resolve_source_file($job);
            $filepath = $resolved['filepath'];
            $filename = $resolved['filename'];
            $mimetype = $resolved['mimetype'];

            $client = new \local_astusse\api_client();
            $result = $client->ingest_document_for_user($user, $targets, $filepath, $filename, $mimetype);

            $httpstatus = (int)($result['status'] ?? 0);
            $json = $result['body_json'] ?? null;
            if (is_array($json)) {
                $backendjobid = isset($json['jobId']) ? (string)$json['jobId'] : null;
                $backendtraceid = isset($json['traceId']) ? (string)$json['traceId'] : null;
            }

            if ($httpstatus >= 200 && $httpstatus < 300) {
                $this->finalize($job->id, 'succeeded', $httpstatus, $backendjobid, $backendtraceid, null);
                $this->cleanup_filearea($job);
                mtrace('local_astusse ingest task: job ' . $jobid . ' succeeded (HTTP ' . $httpstatus . ')');
                return;
            }

            if ($httpstatus === 0 || $httpstatus === 408 || $httpstatus === 429 ||
                    ($httpstatus >= 500 && $httpstatus < 600)) {
                $transient = true;
            }
            $errormessage = $client->extract_error_message($result);
        } catch (\local_astusse\exception\permanent_extraction_exception $e) {
            // Extraction cannot succeed even with a retry (e.g. SCORM-proxy
            // loading content from a remote server). Mark as failed immediately.
            $transient = false;
            $errormessage = $e->getMessage();
        } catch (\Throwable $e) {
            $transient = true;
            $errormessage = $e->getMessage();
        }

        if ($transient && $attempts < self::MAX_ATTEMPTS_TRANSIENT) {
            $this->partial_update($job->id, $httpstatus, $backendjobid, $backendtraceid, $errormessage, true);
            throw new \Exception('local_astusse ingest job ' . $jobid
                . ' transient failure (attempt ' . $attempts . '): ' . $errormessage);
        }

        $this->finalize($job->id, 'failed', $httpstatus, $backendjobid, $backendtraceid, $errormessage);
        // NOTE: do not cleanup filearea on failure — the user may retry.
        // The scheduled cleanup task will remove stale files after the retention window.
        mtrace('local_astusse ingest task: job ' . $jobid . ' failed (HTTP ' . ($httpstatus ?? 'n/a')
            . ') — ' . ($errormessage ?? ''));
    }

    /**
     * Resolve a local readable filepath for the job source, extracting content when needed.
     *
     * @param \stdClass $job
     * @return array {filepath, filename, mimetype}
     */
    private function resolve_source_file(\stdClass $job): array {
        if ($job->sourcetype === 'upload') {
            $itemid = (int)$job->fileareaitemid;
            if ($itemid <= 0) {
                throw new \Exception('Upload job ' . $job->id . ' is missing fileareaitemid');
            }

            $usercontext = \context_user::instance((int)$job->userid);
            $fs = get_file_storage();
            $files = $fs->get_area_files($usercontext->id, 'local_astusse', self::FILEAREA, $itemid,
                'id DESC', false);
            $file = reset($files);
            if (!$file) {
                throw new \Exception('No stored file found for job ' . $job->id
                    . ' in local_astusse/' . self::FILEAREA . ' itemid ' . $itemid);
            }

            $filename = $file->get_filename();
            $mimetype = $file->get_mimetype();
            $tmppath = make_request_directory() . DIRECTORY_SEPARATOR . clean_filename($filename);
            if (!$file->copy_content_to($tmppath)) {
                throw new \Exception('Unable to copy queued file to temp path for job ' . $job->id);
            }

            return [
                'filepath' => $tmppath,
                'filename' => $filename,
                'mimetype' => $mimetype,
            ];
        }

        if (in_array($job->sourcetype, ['resource', 'page', 'scorm', 'h5pactivity'], true)) {
            $cmid = (int)$job->sourcecmid;
            if ($cmid <= 0) {
                throw new \Exception('Module job ' . $job->id . ' is missing sourcecmid');
            }

            $extracted = \local_astusse_extract_module_content($cmid, (int)$job->courseid);
            if ($extracted === null) {
                throw new \Exception('Failed to extract content from cmid ' . $cmid
                    . ' for job ' . $job->id);
            }
            return $extracted;
        }

        throw new \Exception('Unsupported sourcetype "' . $job->sourcetype . '" for job ' . $job->id);
    }

    /**
     * Write a final state to the job row.
     *
     * @param int $jobid
     * @param string $status
     * @param int|null $httpstatus
     * @param string|null $backendjobid
     * @param string|null $backendtraceid
     * @param string|null $errormessage
     * @return void
     */
    private function finalize(int $jobid, string $status, ?int $httpstatus,
            ?string $backendjobid, ?string $backendtraceid, ?string $errormessage): void {
        global $DB;
        $DB->update_record(self::TABLE, (object)[
            'id' => $jobid,
            'status' => $status,
            'httpstatus' => $httpstatus,
            'backendjobid' => $backendjobid !== null ? substr($backendjobid, 0, 64) : null,
            'backendtraceid' => $backendtraceid !== null ? substr($backendtraceid, 0, 64) : null,
            'errormessage' => $errormessage !== null ? substr($errormessage, 0, 2000) : null,
            'timecompleted' => time(),
        ]);
    }

    /**
     * Persist progress between retry attempts without marking the job as final.
     *
     * @param int $jobid
     * @param int|null $httpstatus
     * @param string|null $backendjobid
     * @param string|null $backendtraceid
     * @param string|null $errormessage
     * @param bool $requeue If true, reset status to 'queued' so the next attempt will run.
     * @return void
     */
    private function partial_update(int $jobid, ?int $httpstatus, ?string $backendjobid,
            ?string $backendtraceid, ?string $errormessage, bool $requeue): void {
        global $DB;
        $DB->update_record(self::TABLE, (object)[
            'id' => $jobid,
            'status' => $requeue ? 'queued' : 'running',
            'httpstatus' => $httpstatus,
            'backendjobid' => $backendjobid !== null ? substr($backendjobid, 0, 64) : null,
            'backendtraceid' => $backendtraceid !== null ? substr($backendtraceid, 0, 64) : null,
            'errormessage' => $errormessage !== null ? substr($errormessage, 0, 2000) : null,
        ]);
    }

    /**
     * Delete the persisted uploaded file once the job is final (either succeeded or failed).
     *
     * @param \stdClass $job
     * @return void
     */
    private function cleanup_filearea(\stdClass $job): void {
        if ($job->sourcetype !== 'upload') {
            return;
        }
        $itemid = (int)($job->fileareaitemid ?? 0);
        if ($itemid <= 0) {
            return;
        }
        try {
            $usercontext = \context_user::instance((int)$job->userid);
        } catch (\Throwable $e) {
            return;
        }
        $fs = get_file_storage();
        $fs->delete_area_files($usercontext->id, 'local_astusse', self::FILEAREA, $itemid);
    }
}
