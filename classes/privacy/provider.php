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

namespace mod_roomcalendar\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for mod_roomcalendar.
 *
 * Entries created by a user are personal data of that user. When a user's data is deleted, their entries
 * are deleted; entries they merely modified are kept, but the reference to them is removed.
 *
 * @package    mod_roomcalendar
 * @copyright  2026 Michael K.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Describe the stored personal data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('roomcalendar_entries', [
            'userid' => 'privacy:metadata:entries:userid',
            'usermodified' => 'privacy:metadata:entries:usermodified',
            'name' => 'privacy:metadata:entries:name',
            'description' => 'privacy:metadata:entries:description',
            'contactname' => 'privacy:metadata:entries:contactname',
            'contactemail' => 'privacy:metadata:entries:contactemail',
            'roomid' => 'privacy:metadata:entries:roomid',
            'timestart' => 'privacy:metadata:entries:timestart',
            'timeend' => 'privacy:metadata:entries:timeend',
            'timecreated' => 'privacy:metadata:entries:timecreated',
            'timemodified' => 'privacy:metadata:entries:timemodified',
        ], 'privacy:metadata:entries');
        $collection->add_database_table('roomcalendar_series', [
            'userid' => 'privacy:metadata:series:userid',
            'timecreated' => 'privacy:metadata:series:timecreated',
        ], 'privacy:metadata:series');
        $collection->add_database_table('roomcalendar_members', [
            'userid' => 'privacy:metadata:members:userid',
            'groupid' => 'privacy:metadata:members:groupid',
            'timecreated' => 'privacy:metadata:members:timecreated',
        ], 'privacy:metadata:members');
        return $collection;
    }

    /**
     * Contexts containing data of a user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {roomcalendar_entries} e ON e.roomcalendarid = cm.instance
                 WHERE e.userid = :userid1 OR e.usermodified = :userid2";
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'roomcalendar',
            'userid1' => $userid,
            'userid2' => $userid,
        ]);
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {roomcalendar_members} gm ON gm.roomcalendarid = cm.instance
                 WHERE gm.userid = :userid";
        $contextlist->add_from_sql($sql, ['contextlevel' => CONTEXT_MODULE, 'modname' => 'roomcalendar', 'userid' => $userid]);
        return $contextlist;
    }

    /**
     * Users having data in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \core\context\module) {
            return;
        }
        $params = ['cmid' => $context->instanceid, 'modname' => 'roomcalendar'];
        foreach (['userid', 'usermodified'] as $field) {
            $sql = "SELECT e.$field
                      FROM {course_modules} cm
                      JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                      JOIN {roomcalendar_entries} e ON e.roomcalendarid = cm.instance
                     WHERE cm.id = :cmid";
            $userlist->add_from_sql($field, $sql, $params);
        }
        $sql = "SELECT gm.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {roomcalendar_members} gm ON gm.roomcalendarid = cm.instance
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export the entries created or modified by a user.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \core\context\module) {
                continue;
            }
            $cm = get_coursemodule_from_id('roomcalendar', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $sql = "SELECT e.*, r.name AS roomname
                      FROM {roomcalendar_entries} e
                 LEFT JOIN {roomcalendar_rooms} r ON r.id = e.roomid
                     WHERE e.roomcalendarid = :roomcalendarid AND (e.userid = :userid1 OR e.usermodified = :userid2)
                  ORDER BY e.timestart";
            $records = $DB->get_records_sql($sql,
                ['roomcalendarid' => $cm->instance, 'userid1' => $userid, 'userid2' => $userid]);
            $membership = $DB->get_record_sql(
                "SELECT g.name, gm.timecreated
                   FROM {roomcalendar_members} gm
                   JOIN {roomcalendar_groups} g ON g.id = gm.groupid
                  WHERE gm.roomcalendarid = :id AND gm.userid = :userid",
                ['id' => $cm->instance, 'userid' => $userid]);
            if ($membership) {
                writer::with_context($context)->export_data([get_string('privacy:membership', 'mod_roomcalendar')],
                    (object)[
                        'group' => format_string($membership->name, true, ['context' => $context]),
                        'timecreated' => transform::datetime($membership->timecreated),
                    ]);
            }
            if (!$records) {
                continue;
            }
            $entries = [];
            foreach ($records as $record) {
                $entries[] = (object)[
                    'name' => format_string($record->name, true, ['context' => $context]),
                    'description' => format_text($record->description, $record->descriptionformat, ['context' => $context]),
                    'room' => $record->roomname ?? '',
                    'contactname' => $record->contactname,
                    'contactemail' => $record->contactemail,
                    'timestart' => transform::datetime($record->timestart),
                    'timeend' => transform::datetime($record->timeend),
                    'allday' => transform::yesno($record->allday),
                    'createdbyyou' => transform::yesno($record->userid == $userid),
                    'timecreated' => transform::datetime($record->timecreated),
                    'timemodified' => transform::datetime($record->timemodified),
                ];
            }
            $data = helper::get_context_data($context, $contextlist->get_user());
            helper::export_context_files($context, $contextlist->get_user());
            writer::with_context($context)->export_data([], $data);
            writer::with_context($context)->export_data([get_string('entries', 'mod_roomcalendar')],
                (object)['entries' => $entries]);
        }
    }

    /**
     * Delete all user data in a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if (!$context instanceof \core\context\module) {
            return;
        }
        $cm = get_coursemodule_from_id('roomcalendar', $context->instanceid);
        if (!$cm) {
            return;
        }
        $DB->delete_records_select('roomcalendar_entrygroups',
            "entryid IN (SELECT id FROM {roomcalendar_entries} WHERE roomcalendarid = :id)", ['id' => $cm->instance]);
        $DB->delete_records('roomcalendar_entries', ['roomcalendarid' => $cm->instance]);
        $DB->delete_records('roomcalendar_series', ['roomcalendarid' => $cm->instance]);
        $DB->delete_records('roomcalendar_members', ['roomcalendarid' => $cm->instance]);
    }

    /**
     * Delete the data of one user in the approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            self::delete_users_in_context($context, [$userid]);
        }
    }

    /**
     * Delete the data of several users in one context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        self::delete_users_in_context($userlist->get_context(), $userlist->get_userids());
    }

    /**
     * Delete entries created by the given users and anonymise their modifications.
     *
     * @param \context $context
     * @param int[] $userids
     */
    protected static function delete_users_in_context(\context $context, array $userids): void {
        global $DB;
        if (!$context instanceof \core\context\module || empty($userids)) {
            return;
        }
        $cm = get_coursemodule_from_id('roomcalendar', $context->instanceid);
        if (!$cm) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params = ['roomcalendarid' => $cm->instance] + $inparams;

        $DB->delete_records_select('roomcalendar_entrygroups', "entryid IN (
            SELECT id FROM {roomcalendar_entries} WHERE roomcalendarid = :roomcalendarid AND userid $insql)", $params);
        $DB->delete_records_select('roomcalendar_entries', "roomcalendarid = :roomcalendarid AND userid $insql", $params);
        $DB->delete_records_select('roomcalendar_members', "roomcalendarid = :roomcalendarid AND userid $insql", $params);
        $DB->set_field_select('roomcalendar_entries', 'usermodified', 0,
            "roomcalendarid = :roomcalendarid AND usermodified $insql", $params);
        $DB->set_field_select('roomcalendar_series', 'userid', 0,
            "roomcalendarid = :roomcalendarid AND userid $insql", $params);
        // Remove series that no longer have entries.
        $DB->delete_records_select('roomcalendar_series',
            "roomcalendarid = :roomcalendarid AND id NOT IN (
                SELECT seriesid FROM {roomcalendar_entries} WHERE roomcalendarid = :roomcalendarid2)",
            ['roomcalendarid' => $cm->instance, 'roomcalendarid2' => $cm->instance]);
    }
}
