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
 * Restore of the ePortfolio view snapshots.
 *
 * @package    assignsubmission_exaport
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restores the snapshot record and the PDF of a submission.
 *
 * The view id is kept as it is: on another site it points to no view of the
 * owner, and the plugin then shows that the view is no longer available.
 *
 * @package    assignsubmission_exaport
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_assignsubmission_exaport_subplugin extends restore_subplugin {
    /**
     * Paths handled by this subplugin.
     *
     * @return restore_path_element[]
     */
    protected function define_submission_subplugin_structure() {
        return [
            new restore_path_element($this->get_namefor('submission'), $this->get_pathfor('/submission_exaport')),
        ];
    }

    /**
     * Restore one snapshot record.
     *
     * @param array $data Backed up record.
     */
    public function process_assignsubmission_exaport_submission($data) {
        global $DB;

        $data = (object)$data;
        $oldsubmissionid = $data->submission;
        $data->assignment = $this->get_new_parentid('assign');
        $data->submission = $this->get_mappingid('submission', $data->submission);
        $data->userid = $this->get_mappingid('user', $data->userid);
        if (!$data->submission || !$data->userid) {
            // Submission or owner were not part of the restore, so there is nothing to attach the snapshot to.
            return;
        }
        // Runs inside Moodle's restore controller without an HTTP request, so there is no sesskey to check.
        // phpcs:ignore moodle.Commenting.InlineComment
        $DB->insert_record('assignsubmission_exaport', $data); // nosemgrep: moodle-fehlender-sesskey

        $this->add_related_files('assignsubmission_exaport', 'snapshot', 'submission', null, $oldsubmissionid);
    }
}
