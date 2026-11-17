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

namespace assignsubmission_exaport\event;

/**
 * The PDF snapshot of a submitted ePortfolio view was taken again or another view was chosen.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - int viewid: The submitted block_exaport view.
 * }
 *
 * @package    assignsubmission_exaport
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission_updated extends \mod_assign\event\submission_updated {
    /**
     * Init method.
     */
    protected function init() {
        parent::init();
        $this->data['objecttable'] = 'assignsubmission_exaport';
    }

    /**
     * Returns non-localised description of what happened.
     *
     * @return string
     */
    public function get_description() {
        $description = "The user with id '$this->userid' updated the submitted ePortfolio view (view id " .
            "'{$this->other['viewid']}') in the assignment with course module id '$this->contextinstanceid'";
        if (!empty($this->other['groupid'])) {
            return $description . " for the group with id '{$this->other['groupid']}'.";
        }
        return $description . '.';
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['viewid'])) {
            throw new \coding_exception('The \'viewid\' value must be set in other.');
        }
    }

    /**
     * Mapping of the object id for restore.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'assignsubmission_exaport', 'restore' => \core\event\base::NOT_MAPPED];
    }

    /**
     * Mapping of the other fields for restore.
     *
     * @return array
     */
    public static function get_other_mapping() {
        $mapping = parent::get_other_mapping();
        $mapping['viewid'] = \core\event\base::NOT_MAPPED;
        return $mapping;
    }
}
