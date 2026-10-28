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

namespace mod_roomcalendar;

use mod_roomcalendar\local\group_manager;
use mod_roomcalendar\local\permissions;

/**
 * Tests for the permission group rules.
 *
 * @package    mod_roomcalendar
 * @copyright  2026 Michael K.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_roomcalendar\local\permissions
 * @covers     \mod_roomcalendar\local\group_manager
 */
final class permissions_test extends \advanced_testcase {

    /** @var \stdClass */
    protected $course;

    /** @var \stdClass */
    protected $calendar;

    /** @var \core\context\module */
    protected $context;

    /** @var \mod_roomcalendar_generator */
    protected $generator;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('roomcalendar', ['course' => $this->course->id]);
        $this->calendar = $DB->get_record('roomcalendar', ['id' => $module->id]);
        $this->context = \core\context\module::instance($module->cmid);
        $this->generator = $this->getDataGenerator()->get_plugin_generator('mod_roomcalendar');
    }

    /**
     * Permissions of a user, freshly loaded.
     *
     * @param \stdClass $user
     * @return permissions
     */
    protected function perms(\stdClass $user): permissions {
        global $DB;
        return new permissions($DB->get_record('roomcalendar', ['id' => $this->calendar->id]), $this->context, $user->id);
    }

    public function test_group_base_permissions_and_default_group(): void {
        global $DB;
        $viewers = $this->generator->create_group(['roomcalendarid' => $this->calendar->id, 'canview' => 1]);
        $editors = $this->generator->create_group(['roomcalendarid' => $this->calendar->id, 'canedit' => 1]);
        $viewer = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $editor = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $nobody = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->generator->add_members($viewers, [$viewer->id]);
        $this->generator->add_members($editors, [$editor->id]);

        $this->assertTrue($this->perms($viewer)->can_view());
        $this->assertFalse($this->perms($viewer)->can_add());
        $this->assertTrue($this->perms($editor)->can_add());
        $this->assertFalse($this->perms($nobody)->can_view());
        // Administrators need no membership.
        $this->assertTrue($this->perms($teacher)->is_admin());
        $this->assertTrue($this->perms($teacher)->can_add());

        // The default group applies to users without a membership.
        $DB->set_field('roomcalendar', 'defaultgroupid', $viewers->id, ['id' => $this->calendar->id]);
        $this->assertTrue($this->perms($nobody)->can_view());
        $this->assertTrue($this->perms($nobody)->is_default_group());
        $this->assertFalse($this->perms($nobody)->can_add());
    }

    public function test_user_is_in_one_group_only(): void {
        $a = $this->generator->create_group(['roomcalendarid' => $this->calendar->id]);
        $b = $this->generator->create_group(['roomcalendarid' => $this->calendar->id, 'canedit' => 1]);
        $user = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->assertSame(['added' => 1, 'moved' => 0], group_manager::add_members($a, [$user->id]));
        $this->assertSame(['added' => 0, 'moved' => 1], group_manager::add_members($b, [$user->id]));
        $this->assertSame((int)$b->id, group_manager::get_user_groupid($this->calendar->id, $user->id));
        $this->assertSame(1, count(group_manager::get_memberships($this->calendar->id)));
    }

    public function test_room_and_entry_permissions_must_all_allow(): void {
        $staff = $this->generator->create_group(['roomcalendarid' => $this->calendar->id, 'canedit' => 1]);
        $students = $this->generator->create_group(['roomcalendarid' => $this->calendar->id, 'canedit' => 1]);
        $readers = $this->generator->create_group(['roomcalendarid' => $this->calendar->id]);
        $s1 = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $t1 = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $r1 = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->generator->add_members($students, [$s1->id]);
        $this->generator->add_members($staff, [$t1->id]);
        $this->generator->add_members($readers, [$r1->id]);

        $open = $this->generator->create_room(['roomcalendarid' => $this->calendar->id]);
        $staffroom = $this->generator->create_room(['roomcalendarid' => $this->calendar->id, 'allgroups' => 0,
            'groupids' => [$staff->id]]);

        // Room permissions.
        $this->assertTrue($this->perms($s1)->can_use_room($open));
        $this->assertFalse($this->perms($s1)->can_use_room($staffroom));
        $this->assertTrue($this->perms($t1)->can_use_room($staffroom));
        $this->assertFalse($this->perms($r1)->can_use_room($open), 'Group without edit permission');
        $this->assertTrue($this->perms($s1)->can_use_room(null), 'Events without a room');

        // An entry in the open room that only staff may change.
        $this->setUser($t1);
        $start = make_timestamp(2026, 10, 12, 9, 0);
        $entry = array_values($this->generator->create_entry(['roomcalendarid' => $this->calendar->id, 'roomid' => $open->id,
            'timestart' => $start, 'timeend' => $start + HOURSECS, 'allgroups' => 0, 'groupids' => [$staff->id]]))[0];
        $this->assertTrue($this->perms($t1)->can_edit_entry($entry));
        $this->assertFalse($this->perms($s1)->can_edit_entry($entry), 'Entry does not allow the group');

        // An entry that allows students, but is in a room students may not use.
        $entry2 = array_values($this->generator->create_entry(['roomcalendarid' => $this->calendar->id,
            'roomid' => $staffroom->id, 'timestart' => $start, 'timeend' => $start + HOURSECS,
            'allgroups' => 0, 'groupids' => [$staff->id, $students->id]]))[0];
        $this->assertFalse($this->perms($s1)->can_edit_entry($entry2), 'Room does not allow the group');

        // Losing the base permission removes everything.
        group_manager::update_group($staff, (object)['name' => $staff->name, 'canview' => 1, 'canedit' => 0]);
        $this->assertFalse($this->perms($t1)->can_edit_entry($entry));
        $this->assertTrue($this->perms($t1)->can_view());
    }

    public function test_deleting_group_removes_its_links(): void {
        global $DB;
        $group = $this->generator->create_group(['roomcalendarid' => $this->calendar->id, 'canedit' => 1]);
        $user = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->generator->add_members($group, [$user->id]);
        $room = $this->generator->create_room(['roomcalendarid' => $this->calendar->id, 'allgroups' => 0,
            'groupids' => [$group->id]]);
        $DB->set_field('roomcalendar', 'defaultgroupid', $group->id, ['id' => $this->calendar->id]);

        group_manager::delete_group($group);
        $this->assertSame(0, $DB->count_records('roomcalendar_members'));
        $this->assertSame(0, $DB->count_records('roomcalendar_roomgroups', ['roomid' => $room->id]));
        $this->assertSame(0, (int)$DB->get_field('roomcalendar', 'defaultgroupid', ['id' => $this->calendar->id]));
    }
}
