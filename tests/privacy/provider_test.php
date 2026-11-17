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

use assignsubmission_exaport_test_data as data;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use mod_assign\privacy\assign_plugin_request_data;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/submission/exaport/tests/fixtures/exaport_data.php');

/**
 * Privacy provider tests (contract test, change only with the privacy contract).
 *
 * @package    assignsubmission_exaport
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\assignsubmission_exaport\privacy\provider::class)]
final class provider_test extends \mod_assign\tests\provider_testcase {
    /**
     * Submit a new view of the student.
     *
     * @param \assign $assign The assignment.
     * @param \stdClass $student The student.
     * @return \stdClass The submission.
     */
    private function submit_view(\assign $assign, \stdClass $student): \stdClass {
        $view = data::create_view($student->id, 'Mappe ' . $student->id);
        $this->setUser($student);
        $submission = $assign->get_user_submission($student->id, true);
        $assign->get_submission_plugin_by_type('exaport')->save($submission, (object)['exaport_viewid' => $view->id]);
        return $submission;
    }

    /**
     * New assignment with the plugin enabled.
     *
     * @param \stdClass $course The course.
     * @return \assign
     */
    private function create_exaport_assign(\stdClass $course): \assign {
        return $this->create_instance(['course' => $course, 'assignsubmission_exaport_enabled' => 1]);
    }

    /**
     * Get metadata names table and files.
     */
    public function test_get_metadata_names_table_and_files(): void {
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('assignsubmission_exaport'));
        $names = array_map(fn($item) => $item->get_name(), $collection->get_collection());
        $this->assertContains('assignsubmission_exaport', $names);
        $this->assertContains('core_files', $names);
    }

    /**
     * Export submission user data contains record and pdf.
     */
    public function test_export_submission_user_data_contains_record_and_pdf(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign = $this->create_exaport_assign($course);
        $submission = $this->submit_view($assign, $student);

        $exportdata = new assign_plugin_request_data($assign->get_context(), $assign, $submission, ['Attempt 1']);
        provider::export_submission_user_data($exportdata);

        $path = ['Attempt 1', get_string('privacy:path', 'assignsubmission_exaport')];
        $writer = writer::with_context($assign->get_context());
        $this->assertSame('Mappe ' . $student->id, $writer->get_data($path)->viewname);
        $this->assertArrayHasKey('ePortfolio_Mappe ' . $student->id . '.pdf', $writer->get_files($path));
    }

    /**
     * Get userids from context finds view owners.
     */
    public function test_get_userids_from_context_finds_view_owners(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign = $this->create_exaport_assign($course);
        $this->submit_view($assign, $student);

        $userlist = new userlist($assign->get_context(), 'assignsubmission_exaport');
        provider::get_userids_from_context($userlist);

        $this->assertSame([(int)$student->id], array_map('intval', $userlist->get_userids()));
    }

    /**
     * Delete submission for context removes everything.
     */
    public function test_delete_submission_for_context_removes_everything(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign = $this->create_exaport_assign($course);
        $this->submit_view($assign, $student1);
        $this->submit_view($assign, $student2);

        provider::delete_submission_for_context(new assign_plugin_request_data($assign->get_context(), $assign));

        $this->assertSame(0, $DB->count_records('assignsubmission_exaport'));
        $this->assertSame(0, $DB->count_records_select(
            'files',
            "component = 'assignsubmission_exaport' AND filename <> '.'"
        ));
    }

    /**
     * Delete submission for userid keeps other students.
     */
    public function test_delete_submission_for_userid_keeps_other_students(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign = $this->create_exaport_assign($course);
        $submission1 = $this->submit_view($assign, $student1);
        $submission2 = $this->submit_view($assign, $student2);

        provider::delete_submission_for_userid(
            new assign_plugin_request_data($assign->get_context(), $assign, $submission1, [], $student1)
        );

        $plugin = $assign->get_submission_plugin_by_type('exaport');
        $this->assertTrue($plugin->is_empty($submission1));
        $this->assertSame([], $plugin->get_files($submission1, $student1));
        $this->assertFalse($plugin->is_empty($submission2));
    }

    /**
     * Delete submissions removes selected users only.
     */
    public function test_delete_submissions_removes_selected_users_only(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign = $this->create_exaport_assign($course);
        $submission1 = $this->submit_view($assign, $student1);
        $submission2 = $this->submit_view($assign, $student2);

        $deletedata = new assign_plugin_request_data($assign->get_context(), $assign);
        $deletedata->set_userids([$student1->id]);
        $deletedata->populate_submissions_and_grades();
        provider::delete_submissions($deletedata);

        $plugin = $assign->get_submission_plugin_by_type('exaport');
        $this->assertTrue($plugin->is_empty($submission1));
        $this->assertSame([], $plugin->get_files($submission1, $student1));
        $this->assertFalse($plugin->is_empty($submission2));
    }
}
