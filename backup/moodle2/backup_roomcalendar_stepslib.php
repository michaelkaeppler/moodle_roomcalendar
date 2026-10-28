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
 * Backup structure for mod_roomcalendar.
 *
 * Rooms are always included; entries and series only when user data is included.
 *
 * @package    mod_roomcalendar
 * @copyright  2026 Michael K.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Backup structure step for mod_roomcalendar.
 */
class backup_roomcalendar_activity_structure_step extends backup_activity_structure_step {

    /**
     * Define the backup structure.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $calendar = new backup_nested_element('roomcalendar', ['id'], [
            'name', 'intro', 'introformat', 'firsthour', 'lasthour', 'defaultview', 'defaultgroupid', 'defaultcategoryid',
            'timecreated', 'timemodified',
        ]);

        $groups = new backup_nested_element('groups');
        $group = new backup_nested_element('group', ['id'], [
            'name', 'canview', 'canedit', 'sortorder', 'timecreated', 'timemodified',
        ]);

        $categories = new backup_nested_element('categories');
        $category = new backup_nested_element('category', ['id'], ['name', 'sortorder', 'timecreated', 'timemodified']);

        $members = new backup_nested_element('members');
        $member = new backup_nested_element('member', ['id'], ['groupid', 'userid', 'timecreated']);

        $rooms = new backup_nested_element('rooms');
        $room = new backup_nested_element('room', ['id'], [
            'name', 'description', 'descriptionformat', 'color', 'bookable', 'allgroups', 'sortorder',
            'timecreated', 'timemodified',
        ]);
        $roomgroups = new backup_nested_element('roomgroups');
        $roomgroup = new backup_nested_element('roomgroup', ['id'], ['groupid']);

        $allseries = new backup_nested_element('allseries');
        $series = new backup_nested_element('series', ['id'], [
            'frequency', 'repeatuntil', 'userid', 'timecreated',
        ]);

        $entries = new backup_nested_element('entries');
        $entry = new backup_nested_element('entry', ['id'], [
            'roomid', 'seriesid', 'name', 'description', 'descriptionformat', 'contactname', 'contactemail',
            'timestart', 'timeend', 'allday', 'allgroups', 'categoryid',
            'userid', 'usermodified', 'timecreated', 'timemodified',
        ]);

        $entrygroups = new backup_nested_element('entrygroups');
        $entrygroup = new backup_nested_element('entrygroup', ['id'], ['groupid']);

        // Groups and categories come first, so that they are restored before the rooms and entries referring to them.
        $calendar->add_child($groups);
        $groups->add_child($group);
        $calendar->add_child($categories);
        $categories->add_child($category);
        $calendar->add_child($members);
        $members->add_child($member);
        $calendar->add_child($rooms);
        $rooms->add_child($room);
        $room->add_child($roomgroups);
        $roomgroups->add_child($roomgroup);
        $calendar->add_child($allseries);
        $allseries->add_child($series);
        $calendar->add_child($entries);
        $entries->add_child($entry);
        $entry->add_child($entrygroups);
        $entrygroups->add_child($entrygroup);

        $calendar->set_source_table('roomcalendar', ['id' => backup::VAR_ACTIVITYID]);
        $group->set_source_table('roomcalendar_groups', ['roomcalendarid' => backup::VAR_PARENTID], 'id ASC');
        $category->set_source_table('roomcalendar_categories', ['roomcalendarid' => backup::VAR_PARENTID], 'id ASC');
        $room->set_source_table('roomcalendar_rooms', ['roomcalendarid' => backup::VAR_PARENTID], 'sortorder ASC');
        $roomgroup->set_source_table('roomcalendar_roomgroups', ['roomid' => backup::VAR_PARENTID], 'id ASC');
        if ($userinfo) {
            $member->set_source_table('roomcalendar_members', ['roomcalendarid' => backup::VAR_PARENTID], 'id ASC');
            $entrygroup->set_source_table('roomcalendar_entrygroups', ['entryid' => backup::VAR_PARENTID], 'id ASC');
            $series->set_source_table('roomcalendar_series', ['roomcalendarid' => backup::VAR_PARENTID], 'id ASC');
            $entry->set_source_table('roomcalendar_entries', ['roomcalendarid' => backup::VAR_PARENTID], 'id ASC');
        }

        $member->annotate_ids('user', 'userid');
        $series->annotate_ids('user', 'userid');
        $entry->annotate_ids('user', 'userid');
        $entry->annotate_ids('user', 'usermodified');

        $calendar->annotate_files('mod_roomcalendar', 'intro', null);

        return $this->prepare_activity_structure($calendar);
    }
}
