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

namespace assignsubmission_exaport\local;

use moodle_exception;
use stdClass;

/**
 * Read access to the ePortfolio views (block_exaport) of one owner.
 *
 * The class only reads block_exaport tables and never writes to them. Every
 * method that returns content checks that it belongs to the view owner, so a
 * snapshot can never contain items of other users that were merely shared
 * with the owner.
 *
 * @package    assignsubmission_exaport
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class view_repository {
    /** @var string Block type of a portfolio item placed on a view. */
    public const BLOCK_ITEM = 'item';

    /**
     * List the views a user owns, sorted by name.
     *
     * @param int $userid Owner of the views.
     * @return array<int, string> View names indexed by view id (raw, not yet formatted).
     */
    public function list_views(int $userid): array {
        global $DB;
        return $DB->get_records_menu('block_exaportview', ['userid' => $userid], 'name ASC, id ASC', 'id, name');
    }

    /**
     * Return a view if, and only if, it belongs to the given user.
     *
     * @param int $viewid View id.
     * @param int $userid Expected owner.
     * @return stdClass The block_exaportview record.
     * @throws moodle_exception If the view does not exist or belongs to someone else.
     */
    public function get_owned_view(int $viewid, int $userid): stdClass {
        global $DB;
        $view = $DB->get_record('block_exaportview', ['id' => $viewid, 'userid' => $userid]);
        if (!$view) {
            throw new moodle_exception('error_viewnotavailable', 'assignsubmission_exaport');
        }
        return $view;
    }

    /**
     * Tell whether a view still exists and belongs to the given user.
     *
     * @param int $viewid View id.
     * @param int $userid Expected owner.
     * @return bool
     */
    public function is_owned_view(int $viewid, int $userid): bool {
        global $DB;
        return $DB->record_exists('block_exaportview', ['id' => $viewid, 'userid' => $userid]);
    }

    /**
     * Return the latest modification time of a view and of the owner's items on it.
     *
     * Editing an item does not touch the view record in block_exaport, so the
     * items and their content blocks are taken into account as well.
     *
     * @param stdClass $view The block_exaportview record.
     * @return int Unix timestamp.
     */
    public function get_last_modified(stdClass $view): int {
        global $DB;
        $params = ['viewid' => $view->id, 'type' => self::BLOCK_ITEM, 'userid' => $view->userid];
        $itemsql = "SELECT MAX(i.timemodified)
                      FROM {block_exaportviewblock} b
                      JOIN {block_exaportitem} i ON i.id = b.itemid
                     WHERE b.viewid = :viewid AND b.type = :type AND i.userid = :userid";
        $blocksql = "SELECT MAX(ib.timemodified)
                       FROM {block_exaportviewblock} b
                       JOIN {block_exaportitem} i ON i.id = b.itemid
                       JOIN {block_exaportitemblock} ib ON ib.itemid = i.id
                      WHERE b.viewid = :viewid AND b.type = :type AND i.userid = :userid";
        return max(
            (int)$view->timemodified,
            (int)$DB->get_field_sql($itemsql, $params),
            (int)$DB->get_field_sql($blocksql, $params)
        );
    }

    /**
     * Return the blocks of a view in reading order (column by column, top to bottom).
     *
     * Item blocks get the item record attached as property "item". Item blocks
     * whose item is missing or belongs to another user are left out.
     *
     * @param stdClass $view The block_exaportview record.
     * @return stdClass[] Block records.
     */
    public function get_blocks(stdClass $view): array {
        global $DB;
        $blocks = $DB->get_records('block_exaportviewblock', ['viewid' => $view->id], 'positionx ASC, positiony ASC, id ASC');
        $itemids = [];
        foreach ($blocks as $block) {
            if ($block->type === self::BLOCK_ITEM && !empty($block->itemid)) {
                $itemids[] = (int)$block->itemid;
            }
        }
        $items = [];
        if ($itemids) {
            [$insql, $params] = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED);
            $params['userid'] = $view->userid;
            $items = $DB->get_records_select('block_exaportitem', "id $insql AND userid = :userid", $params);
        }
        $result = [];
        foreach ($blocks as $block) {
            if ($block->type === self::BLOCK_ITEM) {
                if (empty($items[$block->itemid])) {
                    continue;
                }
                $block->item = $items[$block->itemid];
            }
            $result[] = $block;
        }
        return $result;
    }

    /**
     * Return the structured content blocks of an item in display order.
     *
     * @param int $itemid Item id; the caller has already checked the owner.
     * @return stdClass[] block_exaportitemblock records.
     */
    public function get_item_content_blocks(int $itemid): array {
        global $DB;
        return array_values($DB->get_records('block_exaportitemblock', ['itemid' => $itemid], 'sortorder ASC, id ASC'));
    }

    /**
     * Return a badge that has been issued to the given user.
     *
     * @param int $badgeid Badge id stored in the view block.
     * @param int $userid View owner.
     * @return stdClass|null Record with name and description, or null if the owner does not hold the badge.
     */
    public function get_issued_badge(int $badgeid, int $userid): ?stdClass {
        global $DB;
        $sql = "SELECT b.id, b.name, b.description
                  FROM {badge} b
                  JOIN {badge_issued} bi ON bi.badgeid = b.id
                 WHERE b.id = :badgeid AND bi.userid = :userid";
        $badge = $DB->get_record_sql($sql, ['badgeid' => $badgeid, 'userid' => $userid], IGNORE_MULTIPLE);
        return $badge ?: null;
    }
}
