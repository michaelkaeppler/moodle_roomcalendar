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

namespace mod_roomcalendar\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_roomcalendar\local\entry_manager;
use mod_roomcalendar\local\permissions;

/**
 * Delete a calendar entry, or the whole series it belongs to.
 *
 * @package    mod_roomcalendar
 * @copyright  2026 Michael K.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_entry extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'entryid' => new external_value(PARAM_INT, 'Entry id'),
            'series' => new external_value(PARAM_BOOL, 'Delete all entries of the series', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Delete the entry.
     *
     * @param int $cmid
     * @param int $entryid
     * @param bool $series
     * @return array
     */
    public static function execute(int $cmid, int $entryid, bool $series = false): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(),
            ['cmid' => $cmid, 'entryid' => $entryid, 'series' => $series]);

        $cm = get_coursemodule_from_id('roomcalendar', $params['cmid'], 0, false, MUST_EXIST);
        $context = \core\context\module::instance($cm->id);
        self::validate_context($context);
        $calendar = $DB->get_record('roomcalendar', ['id' => $cm->instance], '*', MUST_EXIST);
        $permissions = permissions::for_current_user($calendar, $context);
        $permissions->require_view();

        $entry = entry_manager::get_entry($calendar->id, $params['entryid']);
        if (!$permissions->can_edit_entry($entry)) {
            throw new \moodle_exception('errornopermission', 'mod_roomcalendar');
        }

        return ['deleted' => entry_manager::delete_entry($entry, $params['series'], $context, $permissions)];
    }

    /**
     * Return value definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'deleted' => new external_value(PARAM_INT, 'Number of deleted entries'),
        ]);
    }
}
