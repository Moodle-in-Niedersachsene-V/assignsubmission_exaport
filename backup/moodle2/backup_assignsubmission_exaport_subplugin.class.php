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
 * Backup of the ePortfolio view snapshots.
 *
 * @package    assignsubmission_exaport
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds the snapshot record and the PDF to the backup of a submission.
 *
 * @package    assignsubmission_exaport
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_assignsubmission_exaport_subplugin extends backup_subplugin {
    /**
     * Structure attached to the submission element.
     *
     * @return backup_subplugin_element
     */
    protected function define_submission_subplugin_structure() {
        $subplugin = $this->get_subplugin_element();
        $wrapper = new backup_nested_element($this->get_recommended_name());
        $element = new backup_nested_element(
            'submission_exaport',
            null,
            ['submission', 'userid', 'viewid', 'viewname', 'viewmodified', 'timesnapshot']
        );

        $subplugin->add_child($wrapper);
        $wrapper->add_child($element);

        $element->set_source_table('assignsubmission_exaport', ['submission' => backup::VAR_PARENTID]);
        $element->annotate_ids('user', 'userid');
        $element->annotate_files('assignsubmission_exaport', 'snapshot', 'submission');
        return $subplugin;
    }
}
