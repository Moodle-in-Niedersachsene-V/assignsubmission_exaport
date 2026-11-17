<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace assignsubmission_exaport\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use mod_assign\privacy\assign_plugin_request_data;

/**
 * Privacy provider: the snapshot record and the PDF belong to the submission.
 *
 * Besides the submission, the record stores the owner of the submitted view.
 * For group submissions this can be a group member other than the one who
 * asks for export or deletion, so the owner is reported separately.
 *
 * @package    assignsubmission_exaport
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \mod_assign\privacy\assignsubmission_provider,
    \mod_assign\privacy\assignsubmission_user_provider {
    /**
     * Describe the stored personal data.
     *
     * @param collection $collection Collection to add to.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('assignsubmission_exaport', [
            'assignment' => 'privacy:metadata:assignment',
            'submission' => 'privacy:metadata:submission',
            'userid' => 'privacy:metadata:userid',
            'viewid' => 'privacy:metadata:viewid',
            'viewname' => 'privacy:metadata:viewname',
            'viewmodified' => 'privacy:metadata:viewmodified',
            'timesnapshot' => 'privacy:metadata:timesnapshot',
        ], 'privacy:metadata:tablesummary');
        $collection->link_subsystem('core_files', 'privacy:metadata:filepurpose');
        return $collection;
    }

    /**
     * Add contexts where the user is the owner of a submitted view.
     *
     * @param int $userid User id.
     * @param contextlist $contextlist Context list to add to.
     */
    public static function get_context_for_userid_within_submission(int $userid, contextlist $contextlist) {
        $sql = "SELECT ctx.id
                  FROM {assignsubmission_exaport} ae
                  JOIN {course_modules} cm ON cm.instance = ae.assignment
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
                 WHERE ae.userid = :userid";
        $contextlist->add_from_sql($sql, ['modname' => 'assign', 'contextlevel' => CONTEXT_MODULE, 'userid' => $userid]);
    }

    /**
     * Not needed, the owners are no students of a teacher's export.
     *
     * @param \mod_assign\privacy\useridlist $useridlist List of user ids.
     */
    public static function get_student_user_ids(\mod_assign\privacy\useridlist $useridlist) {
        // The submissions are found by mod_assign.
    }

    /**
     * Add owners of submitted views in an assignment context.
     *
     * @param userlist $userlist User list of a context.
     */
    public static function get_userids_from_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }
        $sql = "SELECT ae.userid
                  FROM {assignsubmission_exaport} ae
                  JOIN {course_modules} cm ON cm.instance = ae.assignment
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, ['modname' => 'assign', 'cmid' => $context->instanceid]);
    }

    /**
     * Export the snapshot record and PDF of a submission.
     *
     * @param assign_plugin_request_data $exportdata Data of the export request.
     */
    public static function export_submission_user_data(assign_plugin_request_data $exportdata) {
        global $DB;
        // Teachers do not get the submissions of their students in their own export.
        if ($exportdata->get_user() != null) {
            return;
        }
        $submission = $exportdata->get_pluginobject();
        $record = $DB->get_record('assignsubmission_exaport', [
            'assignment' => $exportdata->get_assign()->get_instance()->id,
            'submission' => $submission->id,
        ]);
        if (!$record) {
            return;
        }
        $subcontext = array_merge($exportdata->get_subcontext(), [get_string('privacy:path', 'assignsubmission_exaport')]);
        $writer = writer::with_context($exportdata->get_context());
        $writer->export_data($subcontext, (object)[
            'viewname' => format_string($record->viewname),
            'viewid' => $record->viewid,
            'owner' => transform::user($record->userid),
            'viewmodified' => transform::datetime($record->viewmodified),
            'timesnapshot' => transform::datetime($record->timesnapshot),
        ]);
        $files = get_file_storage()->get_area_files(
            $exportdata->get_context()->id,
            'assignsubmission_exaport',
            'snapshot',
            $submission->id,
            'id',
            false
        );
        foreach ($files as $file) {
            $writer->export_file($subcontext, $file);
        }
    }

    /**
     * Delete all snapshots of the assignment.
     *
     * @param assign_plugin_request_data $requestdata Data of the deletion request.
     */
    public static function delete_submission_for_context(assign_plugin_request_data $requestdata) {
        global $DB;
        get_file_storage()->delete_area_files($requestdata->get_context()->id, 'assignsubmission_exaport', 'snapshot');
        $DB->delete_records('assignsubmission_exaport', ['assignment' => $requestdata->get_assign()->get_instance()->id]);
    }

    /**
     * Delete the snapshot of one submission.
     *
     * @param assign_plugin_request_data $deletedata Data of the deletion request.
     */
    public static function delete_submission_for_userid(assign_plugin_request_data $deletedata) {
        global $DB;
        $submissionid = $deletedata->get_pluginobject()->id;
        get_file_storage()->delete_area_files(
            $deletedata->get_context()->id,
            'assignsubmission_exaport',
            'snapshot',
            $submissionid
        );
        $DB->delete_records('assignsubmission_exaport', [
            'assignment' => $deletedata->get_assignid(),
            'submission' => $submissionid,
        ]);
    }

    /**
     * Delete the snapshots of several submissions.
     *
     * @param assign_plugin_request_data $deletedata Data of the deletion request.
     */
    public static function delete_submissions(assign_plugin_request_data $deletedata) {
        global $DB;
        if (empty($deletedata->get_submissionids())) {
            return;
        }
        [$sql, $params] = $DB->get_in_or_equal($deletedata->get_submissionids(), SQL_PARAMS_NAMED);
        get_file_storage()->delete_area_files_select(
            $deletedata->get_context()->id,
            'assignsubmission_exaport',
            'snapshot',
            $sql,
            $params
        );
        $params['assignid'] = $deletedata->get_assignid();
        $DB->delete_records_select('assignsubmission_exaport', "assignment = :assignid AND submission $sql", $params);
    }
}
