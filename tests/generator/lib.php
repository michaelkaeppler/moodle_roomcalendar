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
 * Data generator for mod_roomcalendar.
 *
 * @package    mod_roomcalendar
 * @copyright  2026 Michael K.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_roomcalendar_generator extends testing_module_generator {

    /**
     * Create an instance with sensible defaults.
     *
     * @param array|stdClass|null $record
     * @param array|null $options
     * @return stdClass
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (array)$record + [
            'firsthour' => 7,
            'lasthour' => 20,
            'defaultview' => 'week',
        ];
        return parent::create_instance($record, (array)$options);
    }

    /**
     * Create a room.
     *
     * @param array $record must contain roomcalendarid
     * @return stdClass
     */
    public function create_room(array $record): stdClass {
        global $DB;
        $record += ['name' => 'Room ' . random_string(4), 'color' => 'blue', 'bookable' => 1, 'allgroups' => 1];
        $id = \mod_roomcalendar\local\room_manager::create_room($record['roomcalendarid'], (object)$record);
        return $DB->get_record('roomcalendar_rooms', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Create a permission group.
     *
     * @param array $record must contain roomcalendarid; optional name, canview, canedit
     * @return stdClass
     */
    public function create_group(array $record): stdClass {
        global $DB;
        $record += ['name' => 'Group ' . random_string(4), 'canview' => 1, 'canedit' => 0];
        $id = \mod_roomcalendar\local\group_manager::create_group($record['roomcalendarid'], (object)$record);
        return $DB->get_record('roomcalendar_groups', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Create an event category.
     *
     * @param array $record roomcalendarid, name
     * @return stdClass
     */
    public function create_category(array $record): stdClass {
        global $DB;
        $record += ['name' => 'Category ' . random_string(4)];
        $id = \mod_roomcalendar\local\category_manager::create_category($record['roomcalendarid'], (object)$record);
        return $DB->get_record('roomcalendar_categories', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Add users to a permission group.
     *
     * @param stdClass $group
     * @param int[] $userids
     */
    public function add_members(stdClass $group, array $userids): void {
        \mod_roomcalendar\local\group_manager::add_members($group, $userids);
        \mod_roomcalendar\local\permissions::reset_caches();
    }

    /**
     * Create an entry (or series) through the entry manager, as the current user.
     *
     * @param array $record must contain roomcalendarid, timestart and timeend
     * @return stdClass[] created entries
     */
    public function create_entry(array $record): array {
        global $DB;
        $calendar = $DB->get_record('roomcalendar', ['id' => $record['roomcalendarid']], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('roomcalendar', $calendar->id, 0, false, MUST_EXIST);
        $record += ['name' => 'Event ' . random_string(4), 'roomid' => 0, 'allday' => 0,
            'contactname' => 'Responsible Person', 'contactemail' => 'responsible@example.com', 'allgroups' => 1];
        $ids = \mod_roomcalendar\local\entry_manager::create_entries($calendar, (object)$record,
            \core\context\module::instance($cm->id));
        return $DB->get_records_list('roomcalendar_entries', 'id', $ids, 'timestart');
    }
}
