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
 * Data access for permission groups, their members, and the groups allowed per room and per entry.
 *
 * @package    mod_roomcalendar
 * @copyright  2026 Michael K.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class group_manager {

    /**
     * All permission groups of a calendar.
     *
     * @param int $roomcalendarid
     * @return stdClass[] keyed by id
     */
    public static function get_groups(int $roomcalendarid): array {
        global $DB;
        return $DB->get_records('roomcalendar_groups', ['roomcalendarid' => $roomcalendarid], 'name ASC, id ASC');
    }

    /**
     * Groups whose members may add, change and delete events.
     *
     * @param int $roomcalendarid
     * @return stdClass[] keyed by id
     */
    public static function get_editing_groups(int $roomcalendarid): array {
        return array_filter(self::get_groups($roomcalendarid), fn($g) => !empty($g->canedit));
    }

    /**
     * Fetch a group, ensuring it belongs to the calendar.
     *
     * @param int $roomcalendarid
     * @param int $groupid
     * @param int $strictness
     * @return stdClass|false
     */
    public static function get_group(int $roomcalendarid, int $groupid, int $strictness = MUST_EXIST) {
        global $DB;
        return $DB->get_record('roomcalendar_groups', ['id' => $groupid, 'roomcalendarid' => $roomcalendarid], '*',
            $strictness);
    }

    /**
     * Create a group.
     *
     * @param int $roomcalendarid
     * @param stdClass $data name, canview, canedit
     * @return int
     */
    public static function create_group(int $roomcalendarid, stdClass $data): int {
        global $DB;
        $now = time();
        return $DB->insert_record('roomcalendar_groups', (object)[
            'roomcalendarid' => $roomcalendarid,
            'name' => $data->name,
            // Editing without viewing makes no sense.
            'canview' => (!empty($data->canview) || !empty($data->canedit)) ? 1 : 0,
            'canedit' => empty($data->canedit) ? 0 : 1,
            'sortorder' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Update a group.
     *
     * @param stdClass $group
     * @param stdClass $data name, canview, canedit
     */
    public static function update_group(stdClass $group, stdClass $data): void {
        global $DB;
        $group->name = $data->name;
        $group->canedit = empty($data->canedit) ? 0 : 1;
        $group->canview = (!empty($data->canview) || $group->canedit) ? 1 : 0;
        $group->timemodified = time();
        $DB->update_record('roomcalendar_groups', $group);
    }

    /**
     * Delete a group with its memberships and its room and entry permissions.
     *
     * @param stdClass $group
     */
    public static function delete_group(stdClass $group): void {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('roomcalendar_members', ['groupid' => $group->id]);
        $DB->delete_records('roomcalendar_roomgroups', ['groupid' => $group->id]);
        $DB->delete_records('roomcalendar_entrygroups', ['groupid' => $group->id]);
        $DB->set_field('roomcalendar', 'defaultgroupid', 0,
            ['id' => $group->roomcalendarid, 'defaultgroupid' => $group->id]);
        $DB->delete_records('roomcalendar_groups', ['id' => $group->id]);
        $transaction->allow_commit();
    }

    /**
     * Number of members per group.
     *
     * @param int $roomcalendarid
     * @return int[] groupid => count
     */
    public static function count_members(int $roomcalendarid): array {
        global $DB;
        return $DB->get_records_sql_menu(
            "SELECT groupid, COUNT(1) FROM {roomcalendar_members} WHERE roomcalendarid = :id GROUP BY groupid",
            ['id' => $roomcalendarid]);
    }

    /**
     * Members of a group with the fields needed to display their names.
     *
     * @param int $groupid
     * @return stdClass[] user records keyed by user id
     */
    public static function get_members(int $groupid): array {
        global $DB;
        $fields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        return $DB->get_records_sql(
            "SELECT u.id, u.email, $fields
               FROM {roomcalendar_members} m
               JOIN {user} u ON u.id = m.userid
              WHERE m.groupid = :groupid AND u.deleted = 0
           ORDER BY u.lastname, u.firstname, u.id",
            ['groupid' => $groupid]);
    }

    /**
     * The group a user explicitly belongs to.
     *
     * @param int $roomcalendarid
     * @param int $userid
     * @return int group id, 0 if none
     */
    public static function get_user_groupid(int $roomcalendarid, int $userid): int {
        global $DB;
        return (int)$DB->get_field('roomcalendar_members', 'groupid',
            ['roomcalendarid' => $roomcalendarid, 'userid' => $userid]);
    }

    /**
     * Map of all explicit memberships of a calendar.
     *
     * @param int $roomcalendarid
     * @return int[] userid => groupid
     */
    public static function get_memberships(int $roomcalendarid): array {
        global $DB;
        return $DB->get_records_menu('roomcalendar_members', ['roomcalendarid' => $roomcalendarid], '', 'userid, groupid');
    }

    /**
     * Put users into a group. Users in another group of the same calendar are moved, as a user can only
     * be in one group.
     *
     * @param stdClass $group
     * @param int[] $userids
     * @return array ['added' => int, 'moved' => int]
     */
    public static function add_members(stdClass $group, array $userids): array {
        global $DB;
        $existing = self::get_memberships($group->roomcalendarid);
        $result = ['added' => 0, 'moved' => 0];
        $transaction = $DB->start_delegated_transaction();
        foreach (array_unique(array_map('intval', $userids)) as $userid) {
            if (!$userid) {
                continue;
            }
            if (!isset($existing[$userid])) {
                $DB->insert_record('roomcalendar_members', (object)[
                    'roomcalendarid' => $group->roomcalendarid,
                    'groupid' => $group->id,
                    'userid' => $userid,
                    'timecreated' => time(),
                ]);
                $result['added']++;
            } else if ((int)$existing[$userid] !== (int)$group->id) {
                $DB->set_field('roomcalendar_members', 'groupid', $group->id,
                    ['roomcalendarid' => $group->roomcalendarid, 'userid' => $userid]);
                $result['moved']++;
            }
        }
        $transaction->allow_commit();
        return $result;
    }

    /**
     * Remove a user from a group.
     *
     * @param stdClass $group
     * @param int $userid
     */
    public static function remove_member(stdClass $group, int $userid): void {
        global $DB;
        $DB->delete_records('roomcalendar_members', ['groupid' => $group->id, 'userid' => $userid]);
    }

    /**
     * Groups allowed for each room of a calendar.
     *
     * @param int $roomcalendarid
     * @return array roomid => int[] group ids
     */
    public static function get_room_groupids(int $roomcalendarid): array {
        global $DB;
        $records = $DB->get_records_sql(
            "SELECT rg.id, rg.roomid, rg.groupid
               FROM {roomcalendar_roomgroups} rg
               JOIN {roomcalendar_rooms} r ON r.id = rg.roomid
              WHERE r.roomcalendarid = :id",
            ['id' => $roomcalendarid]);
        $map = [];
        foreach ($records as $r) {
            $map[(int)$r->roomid][] = (int)$r->groupid;
        }
        return $map;
    }

    /**
     * Groups allowed for the given entries.
     *
     * @param int[] $entryids
     * @return array entryid => int[] group ids
     */
    public static function get_entry_groupids(array $entryids): array {
        global $DB;
        if (!$entryids) {
            return [];
        }
        $map = [];
        foreach (array_chunk(array_values($entryids), 1000) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED);
            foreach ($DB->get_records_select('roomcalendar_entrygroups', "entryid $insql", $params, '', 'id, entryid, groupid')
                    as $r) {
                $map[(int)$r->entryid][] = (int)$r->groupid;
            }
        }
        return $map;
    }

    /**
     * Replace the groups allowed for a room.
     *
     * @param int $roomid
     * @param int[] $groupids
     */
    public static function set_room_groups(int $roomid, array $groupids): void {
        self::replace_links('roomcalendar_roomgroups', 'roomid', $roomid, $groupids);
    }

    /**
     * Replace the groups allowed for an entry.
     *
     * @param int $entryid
     * @param int[] $groupids
     */
    public static function set_entry_groups(int $entryid, array $groupids): void {
        self::replace_links('roomcalendar_entrygroups', 'entryid', $entryid, $groupids);
    }

    /**
     * Replace the group links of a room or entry.
     *
     * @param string $table
     * @param string $field
     * @param int $id
     * @param int[] $groupids
     */
    protected static function replace_links(string $table, string $field, int $id, array $groupids): void {
        global $DB;
        $DB->delete_records($table, [$field => $id]);
        $records = [];
        foreach (array_unique(array_filter(array_map('intval', $groupids))) as $groupid) {
            $records[] = [$field => $id, 'groupid' => $groupid];
        }
        if ($records) {
            $DB->insert_records($table, $records);
        }
    }
}
