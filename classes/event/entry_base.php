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

namespace mod_roomcalendar\event;

use core\context;
use stdClass;

/**
 * Common base of the calendar entry events.
 *
 * @property-read array $other {
 *      - int roomid: booked room, 0 if none
 *      - int timestart
 *      - int timeend
 * }
 *
 * @package    mod_roomcalendar
 * @copyright  2026 Michael K.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class entry_base extends \core\event\base {

    /**
     * Create the event for an entry record.
     *
     * @param stdClass $entry
     * @param context $context module context
     * @return static
     */
    public static function create_from_entry(stdClass $entry, context $context) {
        $event = static::create([
            'objectid' => $entry->id,
            'context' => $context,
            'relateduserid' => $entry->userid,
            'other' => [
                'roomid' => (int)$entry->roomid,
                'timestart' => (int)$entry->timestart,
                'timeend' => (int)$entry->timeend,
            ],
        ]);
        $event->add_record_snapshot('roomcalendar_entries', $entry);
        return $event;
    }

    /**
     * Init method.
     */
    protected function init() {
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'roomcalendar_entries';
    }

    /**
     * Link to the calendar showing the day of the entry.
     *
     * @return \core\url
     */
    public function get_url() {
        return new \core\url('/mod/roomcalendar/view.php', [
            'id' => $this->contextinstanceid,
            'view' => 'day',
            'date' => userdate($this->other['timestart'], '%Y-%m-%d'),
        ]);
    }

    /**
     * Backup/restore mapping of the object id.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'roomcalendar_entries', 'restore' => 'roomcalendar_entry'];
    }

    /**
     * Backup/restore mapping of 'other'.
     *
     * @return array
     */
    public static function get_other_mapping() {
        return ['roomid' => ['db' => 'roomcalendar_rooms', 'restore' => 'roomcalendar_room']];
    }
}
