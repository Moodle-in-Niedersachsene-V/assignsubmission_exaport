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

namespace assignsubmission_exaport;

use assignsubmission_exaport_test_data as data;
use mod_assign_test_generator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/mod/assign/submission/exaport/locallib.php');
require_once($CFG->dirroot . '/mod/assign/tests/generator.php');
require_once($CFG->dirroot . '/mod/assign/submission/exaport/tests/fixtures/exaport_data.php');

/**
 * Tests for the submission plugin as mod_assign uses it.
 *
 * @package    assignsubmission_exaport
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\assign_submission_exaport::class)]
final class locallib_test extends \advanced_testcase {
    use mod_assign_test_generator;

    /**
     * Course, student and an assignment with this plugin enabled.
     *
     * @return array [assign, student]
     */
    private function setup_assignment(): array {
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign = $this->create_instance($course, [
            'assignsubmission_exaport_enabled' => 1,
            'assignsubmission_onlinetext_enabled' => 0,
            'assignsubmission_file_enabled' => 0,
        ]);
        return [$assign, $student];
    }

    /**
     * Save stores pdf and logs created then updated.
     */
    public function test_save_stores_pdf_and_logs_created_then_updated(): void {
        $this->resetAfterTest();
        [$assign, $student] = $this->setup_assignment();
        $view = data::create_view($student->id, 'Lerntagebuch');
        $this->setUser($student);
        $submission = $assign->get_user_submission($student->id, true);
        $plugin = $assign->get_submission_plugin_by_type('exaport');

        $sink = $this->redirectEvents();
        $this->assertTrue($plugin->save($submission, (object)['exaport_viewid' => $view->id]));
        $this->assertTrue($plugin->save($submission, (object)['exaport_viewid' => $view->id]));
        $events = $sink->get_events();
        $sink->close();

        $files = $plugin->get_files($submission, $student);
        $this->assertSame(['ePortfolio_Lerntagebuch.pdf'], array_keys($files));
        $this->assertFalse($plugin->is_empty($submission));
        $this->assertInstanceOf(event\submission_created::class, $events[0]);
        $this->assertInstanceOf(event\submission_updated::class, $events[1]);
        $this->assertEquals($view->id, $events[1]->other['viewid']);
        $this->assertStringContainsString('Lerntagebuch', $plugin->view($submission));
    }

    /**
     * Save refuses view of another user.
     */
    public function test_save_refuses_view_of_another_user(): void {
        $this->resetAfterTest();
        [$assign, $student] = $this->setup_assignment();
        $other = $this->getDataGenerator()->create_user();
        $foreign = data::create_view($other->id, 'Fremd');
        $this->setUser($student);
        $submission = $assign->get_user_submission($student->id, true);
        $plugin = $assign->get_submission_plugin_by_type('exaport');

        $this->assertFalse($plugin->save($submission, (object)['exaport_viewid' => $foreign->id]));

        $this->assertSame(get_string('error_viewnotavailable', 'assignsubmission_exaport'), $plugin->get_error());
        $this->assertTrue($plugin->is_empty($submission));
    }

    /**
     * Save without view removes snapshot.
     */
    public function test_save_without_view_removes_snapshot(): void {
        $this->resetAfterTest();
        [$assign, $student] = $this->setup_assignment();
        $view = data::create_view($student->id, 'Mappe');
        $this->setUser($student);
        $submission = $assign->get_user_submission($student->id, true);
        $plugin = $assign->get_submission_plugin_by_type('exaport');
        $plugin->save($submission, (object)['exaport_viewid' => $view->id]);

        $this->assertTrue($plugin->save($submission, (object)['exaport_viewid' => 0]));

        $this->assertTrue($plugin->is_empty($submission));
        $this->assertSame([], $plugin->get_files($submission, $student));
        $this->assertTrue($plugin->submission_is_empty((object)['exaport_viewid' => 0]));
        $this->assertFalse($plugin->submission_is_empty((object)['exaport_viewid' => $view->id]));
    }

    /**
     * Submit for grading takes snapshot again.
     */
    public function test_submit_for_grading_takes_snapshot_again(): void {
        global $DB;
        $this->resetAfterTest();
        [$assign, $student] = $this->setup_assignment();
        $view = data::create_view($student->id, 'Alter Name');
        $this->setUser($student);
        $submission = $assign->get_user_submission($student->id, true);
        $plugin = $assign->get_submission_plugin_by_type('exaport');
        $plugin->save($submission, (object)['exaport_viewid' => $view->id]);
        $DB->set_field('block_exaportview', 'name', 'Neuer Name', ['id' => $view->id]);

        $plugin->submit_for_grading($submission);

        $this->assertSame(['ePortfolio_Neuer Name.pdf'], array_keys($plugin->get_files($submission, $student)));
    }

    /**
     * Precheck blocks submission of removed view.
     */
    public function test_precheck_blocks_submission_of_removed_view(): void {
        global $DB;
        $this->resetAfterTest();
        [$assign, $student] = $this->setup_assignment();
        $view = data::create_view($student->id, 'Mappe');
        $this->setUser($student);
        $submission = $assign->get_user_submission($student->id, true);
        $plugin = $assign->get_submission_plugin_by_type('exaport');
        $plugin->save($submission, (object)['exaport_viewid' => $view->id]);
        $this->assertTrue($plugin->precheck_submission($submission));

        $DB->delete_records('block_exaportview', ['id' => $view->id]);

        $message = get_string('error_viewnotavailable', 'assignsubmission_exaport');
        $this->assertSame($message, $plugin->precheck_submission($submission));
        $this->assertStringContainsString(get_string('viewremoved', 'assignsubmission_exaport'), $plugin->view($submission));
    }

    /**
     * Copy submission into new attempt.
     */
    public function test_copy_submission_into_new_attempt(): void {
        $this->resetAfterTest();
        [$assign, $student] = $this->setup_assignment();
        $view = data::create_view($student->id, 'Mappe');
        $this->setUser($student);
        $submission = $assign->get_user_submission($student->id, true);
        $plugin = $assign->get_submission_plugin_by_type('exaport');
        $plugin->save($submission, (object)['exaport_viewid' => $view->id]);
        $newsubmission = $assign->get_user_submission($student->id, true, 1);

        $plugin->copy_submission($submission, $newsubmission);

        $this->assertFalse($plugin->is_empty($newsubmission));
        $this->assertCount(1, $plugin->get_files($newsubmission, $student));
    }
}
