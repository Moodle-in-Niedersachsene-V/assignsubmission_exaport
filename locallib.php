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
 * Submission plugin class: hand in an ePortfolio view (block_exaport) as PDF snapshot.
 *
 * @package    assignsubmission_exaport
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use assignsubmission_exaport\local\snapshot_manager;
use assignsubmission_exaport\local\view_repository;
use core_external\external_value;

/**
 * Lets a student choose one of their ePortfolio views and stores a PDF snapshot of it.
 *
 * The snapshot is taken when the submission is saved and taken again when it
 * is submitted for grading, so the graded PDF shows the view as it was handed
 * in. The PDF is returned by get_files(), so it can be annotated with
 * assignfeedback_editpdf and is included in "Download all submissions".
 *
 * @package    assignsubmission_exaport
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_submission_exaport extends assign_submission_plugin {
    /** @var string Name of the form element and external parameter. */
    private const FIELD = 'exaport_viewid';

    /**
     * Name of the plugin as shown to users.
     *
     * @return string
     */
    public function get_name() {
        return get_string('exaport', 'assignsubmission_exaport');
    }

    /**
     * Add the view selector to the submission form.
     *
     * @param stdClass|null $submission The submission or null.
     * @param MoodleQuickForm $mform The form.
     * @param stdClass $data Form data.
     * @param int $userid User whose submission is edited.
     * @return bool True, the plugin added elements.
     */
    public function get_form_elements_for_user($submission, MoodleQuickForm $mform, stdClass $data, $userid) {
        // Use the same owner rule as save(): only fall back to $userid when there is no submission yet.
        $ownerid = $this->get_owner_id($submission, $submission ? 0 : (int)$userid);
        $repository = new view_repository();
        $views = $repository->list_views($ownerid);
        $usercontext = context_user::instance($ownerid);

        if (!$views) {
            $link = html_writer::link(
                new moodle_url('/blocks/exaport/views_list.php', ['courseid' => $this->assignment->get_course()->id]),
                get_string('gotoportfolio', 'assignsubmission_exaport')
            );
            $mform->addElement(
                'static',
                'exaport_noviews',
                get_string('selectview', 'assignsubmission_exaport'),
                get_string('noviews', 'assignsubmission_exaport') . ' ' . $link
            );
            return true;
        }

        $options = [0 => get_string('chooseview', 'assignsubmission_exaport')];
        foreach ($views as $id => $name) {
            $options[$id] = format_string($name, true, ['context' => $usercontext]);
        }
        $mform->addElement('select', self::FIELD, get_string('selectview', 'assignsubmission_exaport'), $options);
        $mform->setType(self::FIELD, PARAM_INT);
        $mform->addHelpButton(self::FIELD, 'selectview', 'assignsubmission_exaport');

        if ($submission) {
            $record = $this->get_manager()->get_record((int)$submission->id);
            if ($record && isset($options[$record->viewid])) {
                $data->{self::FIELD} = (int)$record->viewid;
            }
        }
        return true;
    }

    /**
     * Store the chosen view as PDF snapshot.
     *
     * @param stdClass $submission The submission.
     * @param stdClass $data Submitted form data.
     * @return bool False with an error message if the snapshot could not be stored.
     */
    public function save(stdClass $submission, stdClass $data) {
        $viewid = (int)($data->{self::FIELD} ?? 0);
        $manager = $this->get_manager();
        if ($viewid <= 0) {
            $manager->delete_for_submission((int)$submission->id);
            return true;
        }

        $existing = $manager->get_record((int)$submission->id);
        try {
            $recordid = $manager->store_snapshot((int)$submission->id, $viewid, $this->get_owner_id($submission, 0));
        } catch (moodle_exception $e) {
            $this->set_error($e->getMessage());
            return false;
        }
        $this->trigger_submission_event($submission, $recordid, $viewid, $existing !== null);
        return true;
    }

    /**
     * Take the snapshot again when the student submits for grading.
     *
     * A failure stops the submission with the cause (for example a deleted view).
     *
     * @param stdClass $submission The submission.
     * @return void
     */
    public function submit_for_grading($submission) {
        $manager = $this->get_manager();
        $record = $manager->get_record((int)$submission->id);
        if (!$record) {
            return;
        }
        $recordid = $manager->store_snapshot((int)$submission->id, (int)$record->viewid, (int)$record->userid);
        $this->trigger_submission_event($submission, $recordid, (int)$record->viewid, true);
    }

    /**
     * Warn before submitting if the chosen view no longer exists.
     *
     * @param stdClass $submission The submission.
     * @return bool|string True if ok, otherwise a message.
     */
    public function precheck_submission($submission) {
        $manager = $this->get_manager();
        $record = $manager->get_record((int)$submission->id);
        if ($record && $manager->is_view_changed($record) === null) {
            return get_string('error_viewnotavailable', 'assignsubmission_exaport');
        }
        return true;
    }

    /**
     * Return the PDF snapshot for editpdf, downloads and portfolio exports.
     *
     * @param stdClass $submission The submission.
     * @param stdClass $user The user record (unused).
     * @return array<string, stored_file> Files indexed by file name.
     */
    public function get_files(stdClass $submission, stdClass $user) {
        $file = $this->get_manager()->get_file((int)$submission->id);
        return $file ? [$file->get_filename() => $file] : [];
    }

    /**
     * Summary in the submission status and grading table.
     *
     * @param stdClass $submission The submission.
     * @param bool $showviewlink Set to false, the summary is complete.
     * @return string HTML.
     */
    public function view_summary(stdClass $submission, &$showviewlink) {
        $showviewlink = false;
        return $this->view($submission);
    }

    /**
     * Full view: view name, snapshot time, PDF and a note if the view changed later.
     *
     * @param stdClass $submission The submission.
     * @return string HTML.
     */
    public function view(stdClass $submission) {
        $manager = $this->get_manager();
        $record = $manager->get_record((int)$submission->id);
        if (!$record) {
            return '';
        }
        $html = html_writer::div(
            get_string('submittedview', 'assignsubmission_exaport', format_string($record->viewname)) . ' · ' .
            get_string('snapshottime', 'assignsubmission_exaport', userdate($record->timesnapshot))
        );
        $html .= $this->assignment->render_area_files(
            snapshot_manager::COMPONENT,
            snapshot_manager::FILEAREA,
            $submission->id
        );
        $changed = $manager->is_view_changed($record);
        if ($changed === null) {
            $html .= html_writer::div(get_string('viewremoved', 'assignsubmission_exaport'), 'text-muted small');
        } else if ($changed) {
            $html .= html_writer::div(get_string('viewchanged', 'assignsubmission_exaport'), 'text-muted small');
        }
        return $html;
    }

    /**
     * Tell whether the submission holds a snapshot.
     *
     * @param stdClass $submission The submission.
     * @return bool
     */
    public function is_empty(stdClass $submission) {
        return $this->get_manager()->get_record((int)$submission->id) === null;
    }

    /**
     * Tell whether submitted form data chooses no view.
     *
     * @param stdClass $data Submitted form data.
     * @return bool
     */
    public function submission_is_empty(stdClass $data) {
        return empty($data->{self::FIELD});
    }

    /**
     * File areas of this plugin.
     *
     * @return array<string, string>
     */
    public function get_file_areas() {
        return [snapshot_manager::FILEAREA => $this->get_name()];
    }

    /**
     * Copy the snapshot into a new attempt.
     *
     * @param stdClass $sourcesubmission Previous submission.
     * @param stdClass $destsubmission New submission.
     * @return bool
     */
    public function copy_submission(stdClass $sourcesubmission, stdClass $destsubmission) {
        $this->get_manager()->copy((int)$sourcesubmission->id, (int)$destsubmission->id);
        return true;
    }

    /**
     * Remove the snapshot of a submission.
     *
     * @param stdClass $submission The submission.
     * @return bool
     */
    public function remove(stdClass $submission) {
        $this->get_manager()->delete_for_submission((int)$submission->id);
        return true;
    }

    /**
     * Clean up when the assignment is deleted.
     *
     * @return bool
     */
    public function delete_instance() {
        $this->get_manager()->delete_for_assignment();
        return true;
    }

    /**
     * Parameters for saving a submission through mod_assign_save_submission.
     *
     * @return array
     */
    public function get_external_parameters() {
        return [
            self::FIELD => new external_value(PARAM_INT, 'The id of the ePortfolio view to submit.', VALUE_OPTIONAL),
        ];
    }

    /**
     * Snapshot manager for this assignment.
     *
     * @return snapshot_manager
     */
    private function get_manager(): snapshot_manager {
        return new snapshot_manager($this->assignment->get_context(), (int)$this->assignment->get_instance()->id);
    }

    /**
     * Whose views may be submitted: the submission's user, or the acting user for group submissions.
     *
     * @param stdClass|null $submission The submission.
     * @param int $fallback User id to use if the submission has no user.
     * @return int
     */
    private function get_owner_id(?stdClass $submission, int $fallback): int {
        global $USER;
        if ($submission && !empty($submission->userid)) {
            return (int)$submission->userid;
        }
        return $fallback > 0 ? $fallback : (int)$USER->id;
    }

    /**
     * Log the creation or update of the snapshot.
     *
     * @param stdClass $submission The submission.
     * @param int $recordid Id of the assignsubmission_exaport record.
     * @param int $viewid Submitted view.
     * @param bool $updated True if a snapshot existed before.
     */
    private function trigger_submission_event(stdClass $submission, int $recordid, int $viewid, bool $updated): void {
        global $DB;
        $groupid = 0;
        $groupname = null;
        $params = [
            'context' => $this->assignment->get_context(),
            'courseid' => $this->assignment->get_course()->id,
            'objectid' => $recordid,
        ];
        if (empty($submission->userid) && !empty($submission->groupid)) {
            $groupid = (int)$submission->groupid;
            $groupname = $DB->get_field('groups', 'name', ['id' => $groupid], MUST_EXIST);
        } else {
            $params['relateduserid'] = $submission->userid;
        }
        if ($this->assignment->is_blind_marking()) {
            $params['anonymous'] = 1;
        }
        $params['other'] = [
            'submissionid' => $submission->id,
            'submissionattempt' => $submission->attemptnumber,
            'submissionstatus' => $submission->status,
            'viewid' => $viewid,
            'groupid' => $groupid,
            'groupname' => $groupname,
        ];
        if ($updated) {
            $event = \assignsubmission_exaport\event\submission_updated::create($params);
        } else {
            $event = \assignsubmission_exaport\event\submission_created::create($params);
        }
        $event->set_assign($this->assignment);
        $event->trigger();
    }
}
