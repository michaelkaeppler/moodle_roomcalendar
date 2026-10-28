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
 * Upgrade steps for mod_roomcalendar.
 *
 * @package    mod_roomcalendar
 * @copyright  2026 Michael K.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute mod_roomcalendar upgrade steps.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_roomcalendar_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026102000) {
        // Add the responsible person (name and email address) to entries.
        $table = new xmldb_table('roomcalendar_entries');
        $field = new xmldb_field('contactname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '', 'descriptionformat');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('contactemail', XMLDB_TYPE_CHAR, '254', null, XMLDB_NOTNULL, null, '', 'contactname');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Existing entries: the creator is the responsible person.
        $userids = $DB->get_fieldset_sql("SELECT DISTINCT userid FROM {roomcalendar_entries} WHERE contactemail = ''");
        foreach ($userids as $userid) {
            $user = $DB->get_record('user', ['id' => $userid]);
            if (!$user || $user->deleted) {
                continue;
            }
            $DB->execute("UPDATE {roomcalendar_entries} SET contactname = :name, contactemail = :email
                           WHERE userid = :userid AND contactemail = ''",
                ['name' => fullname($user), 'email' => $user->email, 'userid' => $userid]);
        }

        upgrade_mod_savepoint(true, 2026102000, 'roomcalendar');
    }

    if ($oldversion < 2026102100) {
        // Permission groups, memberships, and the groups allowed per room and per entry.
        $table = new xmldb_table('roomcalendar_groups');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('roomcalendarid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('canview', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('canedit', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('roomcalendarid', XMLDB_KEY_FOREIGN, ['roomcalendarid'], 'roomcalendar', ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('roomcalendar_members');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('roomcalendarid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('roomcalendarid', XMLDB_KEY_FOREIGN, ['roomcalendarid'], 'roomcalendar', ['id']);
        $table->add_key('groupid', XMLDB_KEY_FOREIGN, ['groupid'], 'roomcalendar_groups', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('calendaruser', XMLDB_INDEX_UNIQUE, ['roomcalendarid', 'userid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('roomcalendar_roomgroups');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('roomid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('roomid', XMLDB_KEY_FOREIGN, ['roomid'], 'roomcalendar_rooms', ['id']);
        $table->add_key('groupid', XMLDB_KEY_FOREIGN, ['groupid'], 'roomcalendar_groups', ['id']);
        $table->add_index('roomgroup', XMLDB_INDEX_UNIQUE, ['roomid', 'groupid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('roomcalendar_entrygroups');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('entryid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('entryid', XMLDB_KEY_FOREIGN, ['entryid'], 'roomcalendar_entries', ['id']);
        $table->add_key('groupid', XMLDB_KEY_FOREIGN, ['groupid'], 'roomcalendar_groups', ['id']);
        $table->add_index('entrygroup', XMLDB_INDEX_UNIQUE, ['entryid', 'groupid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('roomcalendar');
        $field = new xmldb_field('defaultgroupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'defaultview');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Existing rooms and entries stay open to all groups, so nothing changes until groups are configured.
        $table = new xmldb_table('roomcalendar_rooms');
        $field = new xmldb_field('allgroups', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'bookable');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            $DB->set_field('roomcalendar_rooms', 'allgroups', 1);
        }
        $table = new xmldb_table('roomcalendar_entries');
        $field = new xmldb_field('allgroups', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'allday');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            $DB->set_field('roomcalendar_entries', 'allgroups', 1);
        }

        upgrade_mod_savepoint(true, 2026102100, 'roomcalendar');
    }

    if ($oldversion < 2026102200) {
        // Event categories.
        $table = new xmldb_table('roomcalendar_categories');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('roomcalendarid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('roomcalendarid', XMLDB_KEY_FOREIGN, ['roomcalendarid'], 'roomcalendar', ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('roomcalendar');
        $field = new xmldb_field('defaultcategoryid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'defaultgroupid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('roomcalendar_entries');
        $field = new xmldb_field('categoryid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'allgroups');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $index = new xmldb_index('categoryid', XMLDB_INDEX_NOTUNIQUE, ['categoryid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_mod_savepoint(true, 2026102200, 'roomcalendar');
    }

    return true;
}
