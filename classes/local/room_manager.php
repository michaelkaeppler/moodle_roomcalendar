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
 * Data access for the rooms of a room calendar.
 *
 * @package    mod_roomcalendar
 * @copyright  2026 Michael K.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class room_manager {

    /** @var string[] Available colour keys, each matching a CSS class in styles.css. */
    public const COLORS = ['blue', 'green', 'orange', 'purple', 'red', 'teal', 'pink', 'yellow', 'gray'];

    /** @var string Colour used for entries without a room. */
    public const NOROOM_COLOR = 'gray';

    /**
     * Colour options for a select element.
     *
     * @return array colour key => localised name
     */
    public static function get_color_options(): array {
        $options = [];
        foreach (self::COLORS as $color) {
            $options[$color] = get_string('color_' . $color, 'mod_roomcalendar');
        }
        return $options;
    }

    /**
     * All rooms of a calendar, ordered for display.
     *
     * @param int $roomcalendarid
     * @param bool $bookableonly only return rooms accepting new bookings
     * @return stdClass[] keyed by room id
     */
    public static function get_rooms(int $roomcalendarid, bool $bookableonly = false): array {
        global $DB;
        $params = ['roomcalendarid' => $roomcalendarid];
        if ($bookableonly) {
            $params['bookable'] = 1;
        }
        return $DB->get_records('roomcalendar_rooms', $params, 'sortorder ASC, id ASC');
    }

    /**
     * Fetch a single room, ensuring it belongs to the given calendar.
     *
     * @param int $roomcalendarid
     * @param int $roomid
     * @param int $strictness MUST_EXIST or IGNORE_MISSING
     * @return stdClass|false
     */
    public static function get_room(int $roomcalendarid, int $roomid, int $strictness = MUST_EXIST) {
        global $DB;
        return $DB->get_record('roomcalendar_rooms', ['id' => $roomid, 'roomcalendarid' => $roomcalendarid], '*', $strictness);
    }

    /**
     * Create a room.
     *
     * @param int $roomcalendarid
     * @param stdClass $data name, description, descriptionformat, color, bookable, allgroups,
     *                       groupids (int[], used unless allgroups)
     * @return int new room id
     */
    public static function create_room(int $roomcalendarid, stdClass $data): int {
        global $DB;
        $maxsort = (int)$DB->get_field('roomcalendar_rooms', 'MAX(sortorder)', ['roomcalendarid' => $roomcalendarid]);
        $room = (object)[
            'roomcalendarid' => $roomcalendarid,
            'name' => $data->name,
            'description' => $data->description ?? '',
            'descriptionformat' => $data->descriptionformat ?? FORMAT_HTML,
            'color' => self::clean_color($data->color ?? ''),
            'bookable' => empty($data->bookable) ? 0 : 1,
            'allgroups' => empty($data->allgroups) ? 0 : 1,
            'sortorder' => $maxsort + 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $room->id = $DB->insert_record('roomcalendar_rooms', $room);
        group_manager::set_room_groups($room->id, $room->allgroups ? [] : ($data->groupids ?? []));
        return $room->id;
    }

    /**
     * Update a room.
     *
     * @param stdClass $room existing record
     * @param stdClass $data new values, see create_room()
     */
    public static function update_room(stdClass $room, stdClass $data): void {
        global $DB;
        $room->name = $data->name;
        $room->description = $data->description ?? '';
        $room->descriptionformat = $data->descriptionformat ?? FORMAT_HTML;
        $room->color = self::clean_color($data->color ?? '');
        $room->bookable = empty($data->bookable) ? 0 : 1;
        $room->allgroups = empty($data->allgroups) ? 0 : 1;
        $room->timemodified = time();
        $transaction = $DB->start_delegated_transaction();
        $DB->update_record('roomcalendar_rooms', $room);
        group_manager::set_room_groups($room->id, $room->allgroups ? [] : ($data->groupids ?? []));
        $transaction->allow_commit();
    }

    /**
     * Delete a room and all of its bookings.
     *
     * @param stdClass $room
     */
    public static function delete_room(stdClass $room): void {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records_select('roomcalendar_entrygroups',
            "entryid IN (SELECT id FROM {roomcalendar_entries} WHERE roomid = :roomid AND roomcalendarid = :calid)",
            ['roomid' => $room->id, 'calid' => $room->roomcalendarid]);
        $DB->delete_records('roomcalendar_roomgroups', ['roomid' => $room->id]);
        $DB->delete_records('roomcalendar_entries', ['roomid' => $room->id, 'roomcalendarid' => $room->roomcalendarid]);
        $DB->delete_records('roomcalendar_rooms', ['id' => $room->id]);
        $transaction->allow_commit();
    }

    /**
     * Swap the room with its neighbour in the display order.
     *
     * @param stdClass $room
     * @param bool $up true to move towards the top
     */
    public static function move_room(stdClass $room, bool $up): void {
        global $DB;
        $rooms = array_values(self::get_rooms($room->roomcalendarid));
        // Normalise the sort order first, so swapping always works.
        foreach ($rooms as $i => $r) {
            if ((int)$r->sortorder !== $i) {
                $DB->set_field('roomcalendar_rooms', 'sortorder', $i, ['id' => $r->id]);
                $r->sortorder = $i;
            }
        }
        foreach ($rooms as $i => $r) {
            if ((int)$r->id !== (int)$room->id) {
                continue;
            }
            $j = $up ? $i - 1 : $i + 1;
            if (!isset($rooms[$j])) {
                return;
            }
            $DB->set_field('roomcalendar_rooms', 'sortorder', $j, ['id' => $r->id]);
            $DB->set_field('roomcalendar_rooms', 'sortorder', $i, ['id' => $rooms[$j]->id]);
            return;
        }
    }

    /**
     * Ensure a colour key is valid.
     *
     * @param string $color
     * @return string
     */
    public static function clean_color(string $color): string {
        return in_array($color, self::COLORS, true) ? $color : self::COLORS[0];
    }
}
