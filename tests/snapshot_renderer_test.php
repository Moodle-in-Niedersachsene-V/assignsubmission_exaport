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

use assignsubmission_exaport\local\snapshot_renderer;
use assignsubmission_exaport\local\view_repository;
use assignsubmission_exaport_test_data as data;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/submission/exaport/tests/fixtures/exaport_data.php');

/**
 * Tests for the HTML that goes into the PDF snapshot.
 *
 * @package    assignsubmission_exaport
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\assignsubmission_exaport\local\snapshot_renderer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\assignsubmission_exaport\local\view_repository::class)]
final class snapshot_renderer_test extends \advanced_testcase {
    /**
     * Render a view of the given owner.
     *
     * @param \stdClass $view The view.
     * @param \stdClass $owner The owner.
     * @return string
     */
    private function render(\stdClass $view, \stdClass $owner): string {
        return (new snapshot_renderer(new view_repository()))->render($view, $owner, time());
    }

    /**
     * Render shows view name headline text and item.
     */
    public function test_render_shows_view_name_headline_text_and_item(): void {
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user(['firstname' => 'Ada', 'lastname' => 'Lovelace']);
        $view = data::create_view($owner->id, 'Praktikumsmappe', '<p>Meine Beschreibung</p>');
        data::add_block($view, 'headline', ['text' => 'Woche 1']);
        data::add_block($view, 'text', ['block_title' => 'Rückblick', 'text' => '<p>Gut gelaufen</p>']);
        $item = data::create_item($owner->id, 'Bericht', '<p>Einleitung</p>');
        data::add_content_block($item, 'text', 'Abschnitt A', '<p>Inhalt A</p>');
        data::add_content_block($item, 'link', 'Quelle', '', 'https://example.org/quelle');
        data::add_block($view, 'item', ['itemid' => $item->id]);

        $html = $this->render($view, $owner);

        $this->assertStringContainsString('Praktikumsmappe', $html);
        $this->assertStringContainsString('Ada Lovelace', $html);
        $this->assertStringContainsString('Meine Beschreibung', $html);
        $this->assertStringContainsString('Woche 1', $html);
        $this->assertStringContainsString('Rückblick', $html);
        $this->assertStringContainsString('Gut gelaufen', $html);
        $this->assertStringContainsString('Bericht', $html);
        $this->assertStringContainsString('Inhalt A', $html);
        $this->assertStringContainsString('href="https://example.org/quelle"', $html);
    }

    /**
     * Render embeds owner images and drops external images.
     */
    public function test_render_embeds_owner_images_and_drops_external_images(): void {
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $view = data::create_view($owner->id, 'Bilder');
        data::add_file($owner->id, 'view_content', $view->id, 'foto.png', data::png());
        data::add_block($view, 'text', [
            'text' => '<p><img src="@@PLUGINFILE@@/foto.png" alt="Foto"></p>' .
                '<p><img src="https://tracker.example/pixel.png" alt="Pixel"></p>',
        ]);

        $html = $this->render($view, $owner);

        $this->assertMatchesRegularExpression('~<img src="@[A-Za-z0-9+/=]+" width="20"~', $html);
        $this->assertStringNotContainsString('tracker.example', $html);
        $this->assertStringNotContainsString('assignsubmission-exaport.invalid', $html);
        $this->assertStringContainsString(get_string('pdfimageomitted', 'assignsubmission_exaport', 'Pixel'), $html);
    }

    /**
     * Render scales down large images.
     */
    public function test_render_scales_down_large_images(): void {
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $view = data::create_view($owner->id, 'Groß');
        $item = data::create_item($owner->id, 'Plakat');
        data::add_file($owner->id, 'item_file', $item->id, 'plakat.png', data::png(2400, 600));
        data::add_block($view, 'item', ['itemid' => $item->id]);

        $html = $this->render($view, $owner);

        preg_match('~<img src="@([A-Za-z0-9+/=]+)" width="(\d+)"~', $html, $match);
        $this->assertSame('500', $match[2]);
        $size = getimagesizefromstring(base64_decode($match[1]));
        $this->assertSame(1200, $size[0]);
    }

    /**
     * Render skips items of other users.
     */
    public function test_render_skips_items_of_other_users(): void {
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $view = data::create_view($owner->id, 'Meine Mappe');
        $foreign = data::create_item($other->id, 'Fremder Eintrag', '<p>Geheim</p>');
        data::add_block($view, 'item', ['itemid' => $foreign->id]);

        $html = $this->render($view, $owner);

        $this->assertStringNotContainsString('Fremder Eintrag', $html);
        $this->assertStringNotContainsString('Geheim', $html);
    }

    /**
     * Render removes scripts and media.
     */
    public function test_render_removes_scripts_and_media(): void {
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $view = data::create_view($owner->id, 'Unsicher');
        data::add_block($view, 'text', ['text' => '<p>Text</p><script>alert(1)</script><a href="javascript:alert(2)">Link</a>']);
        data::add_block($view, 'media', [
            'block_title' => 'Video',
            'contentmedia' => '<iframe src="https://video.example"></iframe>',
        ]);

        $html = $this->render($view, $owner);

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('video.example', $html);
        $this->assertStringContainsString('Link', $html);
        $this->assertStringContainsString(get_string('pdfmedia', 'assignsubmission_exaport'), $html);
    }

    /**
     * Render lists files that are no images.
     */
    public function test_render_lists_files_that_are_no_images(): void {
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $view = data::create_view($owner->id, 'Dateien');
        $item = data::create_item($owner->id, 'Material', '', 'file');
        $content = data::add_content_block($item, 'file', 'Anhang');
        data::add_file($owner->id, 'item_content_file', $content->id, 'notizen.txt', 'Hallo');
        data::add_block($view, 'item', ['itemid' => $item->id]);

        $html = $this->render($view, $owner);

        $this->assertStringContainsString('notizen.txt', $html);
        $this->assertStringContainsString(get_string('pdffiles', 'assignsubmission_exaport'), $html);
    }
}
