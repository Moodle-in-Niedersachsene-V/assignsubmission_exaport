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

/**
 * Moodle hooks of the ePortfolio view submission plugin.
 *
 * @package    assignsubmission_exaport
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Serve the PDF snapshots of submissions.
 *
 * Only users who may view the submission (the student, group members or
 * graders) get the file, and it is always sent as download.
 *
 * @param stdClass $course Course record.
 * @param stdClass $cm Course module record.
 * @param context $context Context of the assignment.
 * @param string $filearea File area.
 * @param array $args Remaining path: submission id, then file path.
 * @param bool $forcedownload Ignored, downloads are always forced.
 * @param array $options Options for send_stored_file().
 * @return bool False if the file cannot be served; does not return otherwise.
 */
function assignsubmission_exaport_pluginfile(
    $course,
    $cm,
    context $context,
    $filearea,
    $args,
    $forcedownload,
    array $options = []
) {
    global $CFG, $DB;

    if ($context->contextlevel != CONTEXT_MODULE || $filearea !== 'snapshot') {
        return false;
    }

    require_login($course, false, $cm);
    // The checks below (can_view_submission) test grading rights and ownership but not that the assignment is visible.
    if (!has_capability('mod/assign:view', $context)) {
        return false;
    }
    $itemid = (int)array_shift($args);
    $record = $DB->get_record('assign_submission', ['id' => $itemid], 'userid, assignment, groupid', MUST_EXIST);

    require_once($CFG->dirroot . '/mod/assign/locallib.php');
    $assign = new assign($context, $cm, $course);
    if ($assign->get_instance()->id != $record->assignment) {
        return false;
    }
    if ($assign->get_instance()->teamsubmission && !$assign->can_view_group_submission($record->groupid)) {
        return false;
    }
    if (!$assign->get_instance()->teamsubmission && !$assign->can_view_submission($record->userid)) {
        return false;
    }

    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $file = get_file_storage()->get_file($context->id, 'assignsubmission_exaport', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, true, $options);
}
