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

namespace mod_roomcalendar\local;

use stdClass;

/**
 * Data access for event categories. Each entry belongs to at most one category.
 *
 * @package    mod_roomcalendar
 * @copyright  2026 Michael K.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category_manager {

    /**
     * All categories of a calendar, ordered by name.
     *
     * @param int $roomcalendarid
     * @return stdClass[] keyed by id
     */
    public static function get_categories(int $roomcalendarid): array {
        global $DB;
        return $DB->get_records('roomcalendar_categories', ['roomcalendarid' => $roomcalendarid], 'name ASC, id ASC');
    }

    /**
     * Fetch a category, ensuring it belongs to the calendar.
     *
     * @param int $roomcalendarid
     * @param int $categoryid
     * @param int $strictness
     * @return stdClass|false
     */
    public static function get_category(int $roomcalendarid, int $categoryid, int $strictness = MUST_EXIST) {
        global $DB;
        return $DB->get_record('roomcalendar_categories', ['id' => $categoryid, 'roomcalendarid' => $roomcalendarid],
            '*', $strictness);
    }

    /**
     * The category id to store for an entry: 0 unless it is a category of the calendar.
     *
     * @param int $roomcalendarid
     * @param int|null $categoryid
     * @return int
     */
    public static function clean_categoryid(int $roomcalendarid, ?int $categoryid): int {
        if (empty($categoryid)) {
            return 0;
        }
        return self::get_category($roomcalendarid, $categoryid, IGNORE_MISSING) ? $categoryid : 0;
    }

    /**
     * Create a category.
     *
     * @param int $roomcalendarid
     * @param stdClass $data name
     * @return int
     */
    public static function create_category(int $roomcalendarid, stdClass $data): int {
        global $DB;
        $now = time();
        return $DB->insert_record('roomcalendar_categories', (object)[
            'roomcalendarid' => $roomcalendarid,
            'name' => $data->name,
            'sortorder' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Rename a category.
     *
     * @param stdClass $category
     * @param stdClass $data name
     */
    public static function update_category(stdClass $category, stdClass $data): void {
        global $DB;
        $category->name = $data->name;
        $category->timemodified = time();
        $DB->update_record('roomcalendar_categories', $category);
    }

    /**
     * Delete a category. Its entries are kept and lose their category.
     *
     * @param stdClass $category
     */
    public static function delete_category(stdClass $category): void {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $DB->set_field('roomcalendar_entries', 'categoryid', 0,
            ['roomcalendarid' => $category->roomcalendarid, 'categoryid' => $category->id]);
        $DB->set_field('roomcalendar', 'defaultcategoryid', 0,
            ['id' => $category->roomcalendarid, 'defaultcategoryid' => $category->id]);
        $DB->delete_records('roomcalendar_categories', ['id' => $category->id]);
        $transaction->allow_commit();
    }

    /**
     * Number of entries per category.
     *
     * @param int $roomcalendarid
     * @return int[] categoryid => count
     */
    public static function count_entries(int $roomcalendarid): array {
        global $DB;
        return $DB->get_records_sql_menu(
            "SELECT categoryid, COUNT(1) FROM {roomcalendar_entries}
              WHERE roomcalendarid = :id AND categoryid > 0
           GROUP BY categoryid",
            ['id' => $roomcalendarid]);
    }
}
