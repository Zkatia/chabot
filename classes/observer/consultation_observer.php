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
 * Consultation observer for the T1 spaced-repetition capture.
 *
 * @package     local_astusse
 * @copyright   2026 Ingenium Digital Learning
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_astusse\observer;

use local_astusse\task\log_consultation_task;

/**
 * Captures course_module_viewed events and enqueues them for async send to the AI API.
 *
 * Fast and side-effect-light : no HTTP here, just a staging-table insert + adhoc task.
 * Dedup (30s window per user+cmid) is enforced by the unique key on the queue table.
 */
class consultation_observer {
    /** @var string Staging table. */
    public const QUEUE_TABLE = 'local_astusse_consultation_queue';

    /** @var int Dedup window in seconds (T1 spec: 30s). */
    private const DEDUP_WINDOW = 30;

    /**
     * Module types tracked by T1 (decision #8). Must mirror the ingestible types.
     * Keyed by the 'mod_xxx' component suffix.
     *
     * @var string[]
     */
    private const TRACKED_TYPES = [
        'page', 'resource', 'url', 'book', 'lesson', 'glossary',
        'h5pactivity', 'scorm', 'quiz', 'assign', 'wiki', 'folder',
    ];

    /**
     * Handle a course_module_viewed event (any module subclass).
     *
     * @param \core\event\course_module_viewed $event
     * @return void
     */
    public static function on_course_module_viewed(\core\event\course_module_viewed $event): void {
        global $DB;

        // Resolve module type from the frankenstyle component, e.g. 'mod_page' -> 'page'.
        $component = (string)$event->component;
        if (strpos($component, 'mod_') !== 0) {
            return;
        }
        $sourcetype = substr($component, 4);
        if (!in_array($sourcetype, self::TRACKED_TYPES, true)) {
            return;
        }

        $userid = (int)$event->userid;
        $cmid = (int)$event->contextinstanceid;
        $courseid = (int)$event->courseid;

        // Guard against system/guest/non-module contexts.
        if ($userid <= 0 || $cmid <= 0 || $courseid <= 0) {
            return;
        }
        if (isguestuser($userid)) {
            return;
        }

        $now = time();
        $bucket = intdiv($now, self::DEDUP_WINDOW);

        // Client-side dedup : if a row already exists for this (user, cm, bucket),
        // the consultation was already enqueued in this 30s window -> skip.
        $params = ['userid' => $userid, 'cmid' => $cmid, 'dedupbucket' => $bucket];
        if ($DB->record_exists(self::QUEUE_TABLE, $params)) {
            return;
        }

        $row = (object)[
            'userid' => $userid,
            'cmid' => $cmid,
            'courseid' => $courseid,
            'sourcetype' => $sourcetype,
            'dedupbucket' => $bucket,
            'viewedat' => $now,
            'status' => 'queued',
            'attempts' => 0,
            'timecreated' => $now,
        ];

        try {
            $id = $DB->insert_record(self::QUEUE_TABLE, $row);
        } catch (\dml_write_exception $e) {
            // Lost a race against a concurrent identical event : the unique key fired.
            // That's exactly the dedup we want -> swallow silently.
            return;
        }

        // Enqueue the async send. The page render never waits for the HTTP call.
        $task = new log_consultation_task();
        $task->set_custom_data(['queueid' => $id]);
        \core\task\manager::queue_adhoc_task($task);
    }
}
