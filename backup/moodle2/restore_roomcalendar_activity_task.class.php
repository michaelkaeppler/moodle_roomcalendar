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
 * Restore task for mod_roomcalendar.
 *
 * @package    mod_roomcalendar
 * @copyright  2026 Michael K.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/roomcalendar/backup/moodle2/restore_roomcalendar_stepslib.php');

/**
 * Restore task for mod_roomcalendar.
 */
class restore_roomcalendar_activity_task extends restore_activity_task {

    /**
     * No specific settings.
     */
    protected function define_my_settings() {
    }

    /**
     * Define the restore steps.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_roomcalendar_activity_structure_step('roomcalendar_structure', 'roomcalendar.xml'));
    }

    /**
     * Contents to decode.
     *
     * @return restore_decode_content[]
     */
    public static function define_decode_contents() {
        return [
            new restore_decode_content('roomcalendar', ['intro'], 'roomcalendar'),
            new restore_decode_content('roomcalendar_rooms', ['description'], 'roomcalendar_room'),
            new restore_decode_content('roomcalendar_entries', ['description'], 'roomcalendar_entry'),
        ];
    }

    /**
     * Decoding rules for links.
     *
     * @return restore_decode_rule[]
     */
    public static function define_decode_rules() {
        return [
            new restore_decode_rule('ROOMCALENDARVIEWBYID', '/mod/roomcalendar/view.php?id=$1', 'course_module'),
            new restore_decode_rule('ROOMCALENDARINDEX', '/mod/roomcalendar/index.php?id=$1', 'course'),
        ];
    }

    /**
     * Log restore rules.
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules() {
        return [];
    }

    /**
     * Course level log restore rules.
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules_for_course() {
        return [];
    }
}
