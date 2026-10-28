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
 * Restore structure for mod_roomcalendar.
 *
 * @package    mod_roomcalendar
 * @copyright  2026 Michael K.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restore structure step for mod_roomcalendar.
 */
class restore_roomcalendar_activity_structure_step extends restore_activity_structure_step {

    /**
     * Paths to process.
     *
     * @return restore_path_element[]
     */
    protected function define_structure() {
        $paths = [
            new restore_path_element('roomcalendar', '/activity/roomcalendar'),
            new restore_path_element('roomcalendar_group', '/activity/roomcalendar/groups/group'),
            new restore_path_element('roomcalendar_category', '/activity/roomcalendar/categories/category'),
            new restore_path_element('roomcalendar_room', '/activity/roomcalendar/rooms/room'),
            new restore_path_element('roomcalendar_roomgroup', '/activity/roomcalendar/rooms/room/roomgroups/roomgroup'),
        ];
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('roomcalendar_member', '/activity/roomcalendar/members/member');
            $paths[] = new restore_path_element('roomcalendar_entrygroup',
                '/activity/roomcalendar/entries/entry/entrygroups/entrygroup');
            $paths[] = new restore_path_element('roomcalendar_series', '/activity/roomcalendar/allseries/series');
            $paths[] = new restore_path_element('roomcalendar_entry', '/activity/roomcalendar/entries/entry');
        }
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restore the instance.
     *
     * @param array $data
     */
    protected function process_roomcalendar($data) {
        global $DB;
        $data = (object)$data;
        $data->course = $this->get_courseid();
        $data->timemodified = time();
        $newid = $DB->insert_record('roomcalendar', $data);
        $this->apply_activity_instance($newid);
    }

    /**
     * Restore a permission group.
     *
     * @param array $data
     */
    protected function process_roomcalendar_group($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->roomcalendarid = $this->get_new_parentid('roomcalendar');
        $newid = $DB->insert_record('roomcalendar_groups', $data);
        $this->set_mapping('roomcalendar_group', $oldid, $newid);
    }

    /**
     * Restore an event category.
     *
     * @param array $data
     */
    protected function process_roomcalendar_category($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->roomcalendarid = $this->get_new_parentid('roomcalendar');
        $newid = $DB->insert_record('roomcalendar_categories', $data);
        $this->set_mapping('roomcalendar_category', $oldid, $newid);
    }

    /**
     * Restore a group membership.
     *
     * @param array $data
     */
    protected function process_roomcalendar_member($data) {
        global $DB;
        $data = (object)$data;
        $data->roomcalendarid = $this->get_new_parentid('roomcalendar');
        $data->groupid = $this->get_mappingid('roomcalendar_group', $data->groupid);
        $data->userid = $this->get_mappingid('user', $data->userid);
        if (!$data->groupid || !$data->userid) {
            return;
        }
        if ($DB->record_exists('roomcalendar_members', ['roomcalendarid' => $data->roomcalendarid, 'userid' => $data->userid])) {
            return;
        }
        $DB->insert_record('roomcalendar_members', $data);
    }

    /**
     * Restore a group allowed for a room.
     *
     * @param array $data
     */
    protected function process_roomcalendar_roomgroup($data) {
        global $DB;
        $data = (object)$data;
        $data->roomid = $this->get_new_parentid('roomcalendar_room');
        $data->groupid = $this->get_mappingid('roomcalendar_group', $data->groupid);
        if ($data->groupid) {
            $DB->insert_record('roomcalendar_roomgroups', $data);
        }
    }

    /**
     * Restore a group allowed for an entry.
     *
     * @param array $data
     */
    protected function process_roomcalendar_entrygroup($data) {
        global $DB;
        $data = (object)$data;
        $data->entryid = $this->get_new_parentid('roomcalendar_entry');
        $data->groupid = $this->get_mappingid('roomcalendar_group', $data->groupid);
        if ($data->groupid) {
            $DB->insert_record('roomcalendar_entrygroups', $data);
        }
    }

    /**
     * Restore a room.
     *
     * @param array $data
     */
    protected function process_roomcalendar_room($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->roomcalendarid = $this->get_new_parentid('roomcalendar');
        $newid = $DB->insert_record('roomcalendar_rooms', $data);
        $this->set_mapping('roomcalendar_room', $oldid, $newid);
    }

    /**
     * Restore a series.
     *
     * @param array $data
     */
    protected function process_roomcalendar_series($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->roomcalendarid = $this->get_new_parentid('roomcalendar');
        $data->userid = $this->get_mappingid('user', $data->userid, 0);
        $data->repeatuntil = $this->apply_date_offset($data->repeatuntil);
        $newid = $DB->insert_record('roomcalendar_series', $data);
        $this->set_mapping('roomcalendar_series', $oldid, $newid);
    }

    /**
     * Restore an entry.
     *
     * @param array $data
     */
    protected function process_roomcalendar_entry($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->roomcalendarid = $this->get_new_parentid('roomcalendar');
        $data->roomid = $data->roomid ? (int)$this->get_mappingid('roomcalendar_room', $data->roomid, 0) : 0;
        $data->seriesid = $data->seriesid ? (int)$this->get_mappingid('roomcalendar_series', $data->seriesid, 0) : 0;
        $data->categoryid = !empty($data->categoryid)
            ? (int)$this->get_mappingid('roomcalendar_category', $data->categoryid, 0) : 0;
        $data->userid = $this->get_mappingid('user', $data->userid, 0);
        $data->usermodified = $this->get_mappingid('user', $data->usermodified, 0);
        $data->timestart = $this->apply_date_offset($data->timestart);
        $data->timeend = $this->apply_date_offset($data->timeend);
        $newid = $DB->insert_record('roomcalendar_entries', $data);
        $this->set_mapping('roomcalendar_entry', $oldid, $newid);
    }

    /**
     * Restore the intro files.
     */
    protected function after_execute() {
        global $DB;
        $this->add_related_files('mod_roomcalendar', 'intro', null);
        // Point the default group to the restored group.
        $calendarid = $this->task->get_activityid();
        $olddefault = (int)$DB->get_field('roomcalendar', 'defaultgroupid', ['id' => $calendarid]);
        if ($olddefault) {
            $DB->set_field('roomcalendar', 'defaultgroupid',
                (int)$this->get_mappingid('roomcalendar_group', $olddefault, 0), ['id' => $calendarid]);
        }
        $olddefault = (int)$DB->get_field('roomcalendar', 'defaultcategoryid', ['id' => $calendarid]);
        if ($olddefault) {
            $DB->set_field('roomcalendar', 'defaultcategoryid',
                (int)$this->get_mappingid('roomcalendar_category', $olddefault, 0), ['id' => $calendarid]);
        }
    }
}
