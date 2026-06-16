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
 * Privacy Subsystem implementation for local_astusse.
 *
 * @package     local_astusse
 * @copyright   2026 Ingenium Digital Learning
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_astusse\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use context;
use context_course;
use context_user;

/**
 * Privacy provider for local_astusse.
 *
 * The plugin stores ingestion jobs, resource consultation events and the
 * reference-trainer configuration, and forwards user identity plus learning
 * interactions (chat, quizzes, documents) to the external ASTUSSE AI gateway.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\user_preference_provider {
    /**
     * Describe all data stored or transmitted by the plugin.
     *
     * @param collection $collection the metadata collection to add items to.
     * @return collection the populated collection.
     */
    public static function get_metadata(collection $collection): collection {
        // Async document-ingestion jobs queued by trainers.
        $collection->add_database_table(
            'local_astusse_ingest_jobs',
            [
                'userid' => 'privacy:metadata:local_astusse_ingest_jobs:userid',
                'courseid' => 'privacy:metadata:local_astusse_ingest_jobs:courseid',
                'filename' => 'privacy:metadata:local_astusse_ingest_jobs:filename',
                'status' => 'privacy:metadata:local_astusse_ingest_jobs:status',
                'errormessage' => 'privacy:metadata:local_astusse_ingest_jobs:errormessage',
                'timecreated' => 'privacy:metadata:local_astusse_ingest_jobs:timecreated',
            ],
            'privacy:metadata:local_astusse_ingest_jobs'
        );

        // Resource-consultation staging queue (T1).
        $collection->add_database_table(
            'local_astusse_consultation_queue',
            [
                'userid' => 'privacy:metadata:local_astusse_consultation_queue:userid',
                'cmid' => 'privacy:metadata:local_astusse_consultation_queue:cmid',
                'courseid' => 'privacy:metadata:local_astusse_consultation_queue:courseid',
                'viewedat' => 'privacy:metadata:local_astusse_consultation_queue:viewedat',
                'status' => 'privacy:metadata:local_astusse_consultation_queue:status',
            ],
            'privacy:metadata:local_astusse_consultation_queue'
        );

        // Reference-trainer configuration per course.
        $collection->add_database_table(
            'local_astusse_course_ref_trainer',
            [
                'courseid' => 'privacy:metadata:local_astusse_course_ref_trainer:courseid',
                'trainerid' => 'privacy:metadata:local_astusse_course_ref_trainer:trainerid',
                'updatedby' => 'privacy:metadata:local_astusse_course_ref_trainer:updatedby',
                'timemodified' => 'privacy:metadata:local_astusse_course_ref_trainer:timemodified',
            ],
            'privacy:metadata:local_astusse_course_ref_trainer'
        );

        // User preferences controlling the spaced-repetition pop-up.
        $collection->add_user_preference(
            'local_astusse_review_snooze_until',
            'privacy:metadata:preference:local_astusse_review_snooze_until'
        );
        $collection->add_user_preference(
            'local_astusse_review_optout',
            'privacy:metadata:preference:local_astusse_review_optout'
        );

        // Data forwarded to the external ASTUSSE AI gateway.
        $collection->add_external_location_link(
            'astusse_gateway',
            [
                'userid' => 'privacy:metadata:astusse_gateway:userid',
                'username' => 'privacy:metadata:astusse_gateway:username',
                'email' => 'privacy:metadata:astusse_gateway:email',
                'fullname' => 'privacy:metadata:astusse_gateway:fullname',
                'roles' => 'privacy:metadata:astusse_gateway:roles',
                'chatmessages' => 'privacy:metadata:astusse_gateway:chatmessages',
                'quizanswers' => 'privacy:metadata:astusse_gateway:quizanswers',
                'documentcontent' => 'privacy:metadata:astusse_gateway:documentcontent',
            ],
            'privacy:metadata:astusse_gateway'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the given user.
     *
     * @param int $userid the user to search.
     * @return contextlist the contexts containing the user's data.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        // Ingestion jobs are owned by the queuing user, scoped to their course.
        $sql = "SELECT ctx.id
                  FROM {local_astusse_ingest_jobs} j
                  JOIN {context} ctx ON ctx.instanceid = j.courseid AND ctx.contextlevel = :courselevel
                 WHERE j.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'courselevel' => CONTEXT_COURSE,
            'userid' => $userid,
        ]);

        // Resource-consultation events.
        $sql = "SELECT ctx.id
                  FROM {local_astusse_consultation_queue} q
                  JOIN {context} ctx ON ctx.instanceid = q.courseid AND ctx.contextlevel = :courselevel
                 WHERE q.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'courselevel' => CONTEXT_COURSE,
            'userid' => $userid,
        ]);

        // Reference-trainer rows where the user is either the designated trainer or the editor.
        $sql = "SELECT ctx.id
                  FROM {local_astusse_course_ref_trainer} r
                  JOIN {context} ctx ON ctx.instanceid = r.courseid AND ctx.contextlevel = :courselevel
                 WHERE r.trainerid = :trainerid OR r.updatedby = :updatedby";
        $contextlist->add_from_sql($sql, [
            'courselevel' => CONTEXT_COURSE,
            'trainerid' => $userid,
            'updatedby' => $userid,
        ]);

        // Preferences live in the user context.
        $contextlist->add_user_context($userid);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist the userlist to add users to.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if ($context instanceof context_user) {
            // Preferences belong to the owning user of the user context.
            $userlist->add_user($context->instanceid);
            return;
        }

        if (!($context instanceof context_course)) {
            return;
        }

        $params = ['courseid' => $context->instanceid];

        $userlist->add_from_sql('userid', "
                SELECT userid FROM {local_astusse_ingest_jobs} WHERE courseid = :courseid", $params);
        $userlist->add_from_sql('userid', "
                SELECT userid FROM {local_astusse_consultation_queue} WHERE courseid = :courseid", $params);
        $userlist->add_from_sql('trainerid', "
                SELECT trainerid FROM {local_astusse_course_ref_trainer} WHERE courseid = :courseid", $params);
        $userlist->add_from_sql('updatedby', "
                SELECT updatedby FROM {local_astusse_course_ref_trainer} WHERE courseid = :courseid", $params);
    }

    /**
     * Export the user's stored preferences.
     *
     * @param int $userid the user whose preferences should be exported.
     */
    public static function export_user_preferences(int $userid): void {
        $snooze = get_user_preferences('local_astusse_review_snooze_until', null, $userid);
        if ($snooze !== null) {
            writer::export_user_preference(
                'local_astusse',
                'local_astusse_review_snooze_until',
                $snooze,
                get_string('privacy:metadata:preference:local_astusse_review_snooze_until', 'local_astusse')
            );
        }

        $optout = get_user_preferences('local_astusse_review_optout', null, $userid);
        if ($optout !== null) {
            writer::export_user_preference(
                'local_astusse',
                'local_astusse_review_optout',
                $optout,
                get_string('privacy:metadata:preference:local_astusse_review_optout', 'local_astusse')
            );
        }
    }

    /**
     * Export all stored user data for the approved contexts.
     *
     * @param approved_contextlist $contextlist the approved contexts to export.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!($context instanceof context_course)) {
                continue;
            }
            $courseid = $context->instanceid;

            // Ingestion jobs.
            $jobs = $DB->get_records(
                'local_astusse_ingest_jobs',
                ['userid' => $userid, 'courseid' => $courseid],
                'timecreated ASC'
            );
            if ($jobs) {
                $data = [];
                foreach ($jobs as $job) {
                    $data[] = [
                        'filename' => $job->filename,
                        'sourcetype' => $job->sourcetype,
                        'status' => $job->status,
                        'errormessage' => $job->errormessage,
                        'timecreated' => $job->timecreated
                            ? \core_privacy\local\request\transform::datetime($job->timecreated) : null,
                        'timecompleted' => $job->timecompleted
                            ? \core_privacy\local\request\transform::datetime($job->timecompleted) : null,
                    ];
                }
                writer::with_context($context)->export_data(
                    [get_string('privacy:path:ingestjobs', 'local_astusse')],
                    (object) ['jobs' => $data]
                );
            }

            // Resource-consultation events.
            $events = $DB->get_records(
                'local_astusse_consultation_queue',
                ['userid' => $userid, 'courseid' => $courseid],
                'viewedat ASC'
            );
            if ($events) {
                $data = [];
                foreach ($events as $event) {
                    $data[] = [
                        'cmid' => $event->cmid,
                        'sourcetype' => $event->sourcetype,
                        'status' => $event->status,
                        'viewedat' => \core_privacy\local\request\transform::datetime($event->viewedat),
                    ];
                }
                writer::with_context($context)->export_data(
                    [get_string('privacy:path:consultations', 'local_astusse')],
                    (object) ['consultations' => $data]
                );
            }

            // Reference-trainer configuration (as trainer or as editor).
            $reftrainers = $DB->get_records_select(
                'local_astusse_course_ref_trainer',
                'courseid = :courseid AND (trainerid = :trainerid OR updatedby = :updatedby)',
                ['courseid' => $courseid, 'trainerid' => $userid, 'updatedby' => $userid]
            );
            if ($reftrainers) {
                $data = [];
                foreach ($reftrainers as $row) {
                    $data[] = [
                        'trainerid' => $row->trainerid,
                        'updatedby' => $row->updatedby,
                        'timemodified' => \core_privacy\local\request\transform::datetime($row->timemodified),
                    ];
                }
                writer::with_context($context)->export_data(
                    [get_string('privacy:path:referencetrainer', 'local_astusse')],
                    (object) ['referencetrainer' => $data]
                );
            }
        }
    }

    /**
     * Delete all user data for all users in the given context.
     *
     * @param context $context the context to delete in.
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if (!($context instanceof context_course)) {
            return;
        }

        $courseid = $context->instanceid;
        $DB->delete_records('local_astusse_ingest_jobs', ['courseid' => $courseid]);
        $DB->delete_records('local_astusse_consultation_queue', ['courseid' => $courseid]);
        $DB->delete_records('local_astusse_course_ref_trainer', ['courseid' => $courseid]);
    }

    /**
     * Delete all data for the user in the approved contexts.
     *
     * @param approved_contextlist $contextlist the approved contexts to delete in.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_user && $context->instanceid == $userid) {
                unset_user_preference('local_astusse_review_snooze_until', $userid);
                unset_user_preference('local_astusse_review_optout', $userid);
                continue;
            }

            if (!($context instanceof context_course)) {
                continue;
            }
            $courseid = $context->instanceid;

            $DB->delete_records('local_astusse_ingest_jobs', ['userid' => $userid, 'courseid' => $courseid]);
            $DB->delete_records('local_astusse_consultation_queue', ['userid' => $userid, 'courseid' => $courseid]);
            // Remove the reference-trainer row when the user is the designated trainer or editor.
            $DB->delete_records_select(
                'local_astusse_course_ref_trainer',
                'courseid = :courseid AND (trainerid = :trainerid OR updatedby = :updatedby)',
                ['courseid' => $courseid, 'trainerid' => $userid, 'updatedby' => $userid]
            );
        }
    }

    /**
     * Delete data for multiple users within a single context.
     *
     * @param approved_userlist $userlist the approved users to delete.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();

        if ($context instanceof context_user) {
            unset_user_preference('local_astusse_review_snooze_until', $context->instanceid);
            unset_user_preference('local_astusse_review_optout', $context->instanceid);
            return;
        }

        if (!($context instanceof context_course)) {
            return;
        }

        $courseid = $context->instanceid;
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params = array_merge(['courseid' => $courseid], $inparams);

        $DB->delete_records_select(
            'local_astusse_ingest_jobs',
            "courseid = :courseid AND userid $insql",
            $params
        );
        $DB->delete_records_select(
            'local_astusse_consultation_queue',
            "courseid = :courseid AND userid $insql",
            $params
        );

        [$trsql, $trparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        [$upsql, $upparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params = array_merge(['courseid' => $courseid], $trparams, $upparams);
        $DB->delete_records_select(
            'local_astusse_course_ref_trainer',
            "courseid = :courseid AND (trainerid $trsql OR updatedby $upsql)",
            $params
        );
    }
}
