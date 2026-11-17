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
 * Test data for block_exaport views, items and files.
 *
 * @package    assignsubmission_exaport
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Creates block_exaport records the way the ePortfolio stores them.
 *
 * @package    assignsubmission_exaport
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assignsubmission_exaport_test_data {
    /**
     * Create a view.
     *
     * @param int $userid Owner.
     * @param string $name View name.
     * @param string $description HTML description.
     * @return stdClass block_exaportview record.
     */
    public static function create_view(int $userid, string $name, string $description = ''): stdClass {
        global $DB;
        $view = (object)[
            'userid' => $userid,
            'name' => $name,
            'description' => $description,
            'timemodified' => time() - 100,
            'shareall' => 0,
            'externaccess' => 0,
            'externcomment' => 0,
            'hash' => random_string(8),
            'layout' => 2,
            'creatorid' => $userid,
        ];
        $view->id = $DB->insert_record('block_exaportview', $view);
        return $view;
    }

    /**
     * Add a block to a view.
     *
     * @param stdClass $view The view.
     * @param string $type Block type (text, headline, item, media ...).
     * @param array $fields Further fields of block_exaportviewblock.
     * @return stdClass block_exaportviewblock record.
     */
    public static function add_block(stdClass $view, string $type, array $fields = []): stdClass {
        global $DB;
        $block = (object)array_merge([
            'viewid' => $view->id,
            'positionx' => 1,
            'positiony' => $DB->count_records('block_exaportviewblock', ['viewid' => $view->id]) + 1,
            'type' => $type,
        ], $fields);
        $block->id = $DB->insert_record('block_exaportviewblock', $block);
        return $block;
    }

    /**
     * Create an item.
     *
     * @param int $userid Owner.
     * @param string $name Item name.
     * @param string $intro HTML description.
     * @param string $type Item type (note, link, file).
     * @param string $url Address for link items.
     * @return stdClass block_exaportitem record.
     */
    public static function create_item(
        int $userid,
        string $name,
        string $intro = '',
        string $type = 'note',
        string $url = ''
    ): stdClass {
        global $DB;
        $item = (object)[
            'userid' => $userid,
            'type' => $type,
            'categoryid' => 0,
            'name' => $name,
            'url' => $url,
            'intro' => $intro,
            'attachment' => '',
            'timecreated' => time() - 100,
            'timemodified' => time() - 100,
            'courseid' => 0,
        ];
        $item->id = $DB->insert_record('block_exaportitem', $item);
        return $item;
    }

    /**
     * Add a structured content block to an item.
     *
     * @param stdClass $item The item.
     * @param string $type Content type (text, link, file).
     * @param string $title Block title.
     * @param string $content HTML content.
     * @param string $url Address of link blocks.
     * @return stdClass block_exaportitemblock record.
     */
    public static function add_content_block(
        stdClass $item,
        string $type,
        string $title,
        string $content = '',
        string $url = ''
    ): stdClass {
        global $DB;
        $block = (object)[
            'itemid' => $item->id,
            'type' => $type,
            'sortorder' => $DB->count_records('block_exaportitemblock', ['itemid' => $item->id]),
            'title' => $title,
            'content' => $content,
            'contentformat' => FORMAT_HTML,
            'url' => $url,
            'timecreated' => time() - 100,
            'timemodified' => time() - 100,
        ];
        $block->id = $DB->insert_record('block_exaportitemblock', $block);
        return $block;
    }

    /**
     * Store a file in a block_exaport file area of the user context.
     *
     * @param int $userid Owner.
     * @param string $filearea File area.
     * @param int $itemid Item id of the area.
     * @param string $filename File name.
     * @param string $content File content.
     * @return stored_file
     */
    public static function add_file(int $userid, string $filearea, int $itemid, string $filename, string $content): stored_file {
        return get_file_storage()->create_file_from_string([
            'contextid' => context_user::instance($userid)->id,
            'component' => 'block_exaport',
            'filearea' => $filearea,
            'itemid' => $itemid,
            'filepath' => '/',
            'filename' => $filename,
            'userid' => $userid,
        ], $content);
    }

    /**
     * A small PNG image.
     *
     * @param int $width Width in pixels.
     * @param int $height Height in pixels.
     * @return string PNG data.
     */
    public static function png(int $width = 20, int $height = 10): string {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 31, 56, 100));
        ob_start();
        imagepng($image);
        return ob_get_clean();
    }
}
