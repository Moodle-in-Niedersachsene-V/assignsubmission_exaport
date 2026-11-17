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

use assignsubmission_exaport\local\snapshot_manager;
use assignsubmission_exaport_test_data as data;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/submission/exaport/tests/fixtures/exaport_data.php');

/**
 * Tests for storing, copying and removing PDF snapshots.
 *
 * @package    assignsubmission_exaport
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\assignsubmission_exaport\local\snapshot_manager::class)]
final class snapshot_manager_test extends \advanced_testcase {
    /** @var \context_module Context of the assignment created by create_manager(). */
    private \context_module $context;

    /**
     * Snapshot manager for a new assignment.
     *
     * @return snapshot_manager
     */
    private function create_manager(): snapshot_manager {
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $this->context = \context_module::instance($assign->cmid);
        return new snapshot_manager($this->context, (int)$assign->id);
    }

    /**
     * Store snapshot creates pdf and record.
     */
    public function test_store_snapshot_creates_pdf_and_record(): void {
        $this->resetAfterTest();
        $manager = $this->create_manager();
        $owner = $this->getDataGenerator()->create_user();
        $view = data::create_view($owner->id, 'Praktikumsmappe');
        data::add_block($view, 'text', ['block_title' => 'Start', 'text' => '<p>Hallo Welt</p>']);

        $recordid = $manager->store_snapshot(501, $view->id, $owner->id);

        $record = $manager->get_record(501);
        $this->assertSame($recordid, (int)$record->id);
        $this->assertEquals($view->id, $record->viewid);
        $this->assertEquals($owner->id, $record->userid);
        $this->assertSame('Praktikumsmappe', $record->viewname);
        $file = $manager->get_file(501);
        $this->assertSame('ePortfolio_Praktikumsmappe.pdf', $file->get_filename());
        $this->assertSame('application/pdf', $file->get_mimetype());
        $this->assertStringStartsWith('%PDF', $file->get_content());
    }

    /**
     * Store snapshot rejects view of another user.
     */
    public function test_store_snapshot_rejects_view_of_another_user(): void {
        $this->resetAfterTest();
        $manager = $this->create_manager();
        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $view = data::create_view($owner->id, 'Fremde Mappe');

        try {
            $manager->store_snapshot(502, $view->id, $other->id);
            $this->fail('A view of another user must not be stored.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_viewnotavailable', $e->errorcode);
        }
        $this->assertNull($manager->get_record(502));
        $this->assertNull($manager->get_file(502));
    }

    /**
     * Store snapshot replaces previous snapshot.
     */
    public function test_store_snapshot_replaces_previous_snapshot(): void {
        $this->resetAfterTest();
        $manager = $this->create_manager();
        $owner = $this->getDataGenerator()->create_user();
        $first = data::create_view($owner->id, 'Erste');
        $second = data::create_view($owner->id, 'Zweite');

        $firstid = $manager->store_snapshot(503, $first->id, $owner->id);
        $secondid = $manager->store_snapshot(503, $second->id, $owner->id);

        $this->assertSame($firstid, $secondid);
        $this->assertEquals($second->id, $manager->get_record(503)->viewid);
        $files = get_file_storage()->get_area_files(
            $this->context->id,
            snapshot_manager::COMPONENT,
            snapshot_manager::FILEAREA,
            503,
            'id',
            false
        );
        $this->assertCount(1, $files);
        $this->assertSame('ePortfolio_Zweite.pdf', reset($files)->get_filename());
    }

    /**
     * Is view changed reports later edits and removed views.
     */
    public function test_is_view_changed_reports_later_edits_and_removed_views(): void {
        global $DB;
        $this->resetAfterTest();
        $manager = $this->create_manager();
        $owner = $this->getDataGenerator()->create_user();
        $view = data::create_view($owner->id, 'Mappe');
        $item = data::create_item($owner->id, 'Eintrag');
        data::add_block($view, 'item', ['itemid' => $item->id]);
        $manager->store_snapshot(504, $view->id, $owner->id);
        $record = $manager->get_record(504);

        $this->assertFalse($manager->is_view_changed($record));

        $DB->set_field('block_exaportitem', 'timemodified', time() + 10, ['id' => $item->id]);
        $this->assertTrue($manager->is_view_changed($record));

        $DB->delete_records('block_exaportview', ['id' => $view->id]);
        $this->assertNull($manager->is_view_changed($record));
    }

    /**
     * Copy and delete.
     */
    public function test_copy_and_delete(): void {
        $this->resetAfterTest();
        $manager = $this->create_manager();
        $owner = $this->getDataGenerator()->create_user();
        $view = data::create_view($owner->id, 'Mappe');
        $manager->store_snapshot(505, $view->id, $owner->id);

        $manager->copy(505, 506);
        $this->assertEquals($view->id, $manager->get_record(506)->viewid);
        $this->assertSame($manager->get_file(505)->get_contenthash(), $manager->get_file(506)->get_contenthash());

        $manager->delete_for_submission(505);
        $this->assertNull($manager->get_record(505));
        $this->assertNull($manager->get_file(505));
        $this->assertNotNull($manager->get_record(506));

        $manager->delete_for_assignment();
        $this->assertNull($manager->get_record(506));
        $this->assertNull($manager->get_file(506));
    }
}
