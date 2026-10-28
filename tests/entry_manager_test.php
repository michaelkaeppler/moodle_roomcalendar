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

use mod_roomcalendar\local\entry_manager;

/**
 * Tests for the entry manager.
 *
 * @package    mod_roomcalendar
 * @copyright  2026 Michael K.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_roomcalendar\local\entry_manager
 */
final class entry_manager_test extends \advanced_testcase {

    /** @var \stdClass */
    protected $course;

    /** @var \stdClass */
    protected $calendar;

    /** @var \core\context\module */
    protected $context;

    /** @var \mod_roomcalendar_generator */
    protected $generator;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
        $this->calendar = $this->getDataGenerator()->create_module('roomcalendar', ['course' => $this->course->id]);
        $this->context = \core\context\module::instance($this->calendar->cmid);
        $this->generator = $this->getDataGenerator()->get_plugin_generator('mod_roomcalendar');
    }

    public function test_conflicting_booking_is_rejected(): void {
        $room = $this->generator->create_room(['roomcalendarid' => $this->calendar->id]);
        $start = make_timestamp(2026, 10, 14, 10, 0);
        $this->generator->create_entry(['roomcalendarid' => $this->calendar->id, 'roomid' => $room->id,
            'timestart' => $start, 'timeend' => $start + HOURSECS]);

        // Touching intervals do not overlap.
        $this->assertEmpty(entry_manager::find_conflicts($this->calendar->id, $room->id,
            [[$start + HOURSECS, $start + 2 * HOURSECS]]));
        // Overlapping intervals do.
        $this->assertCount(1, entry_manager::find_conflicts($this->calendar->id, $room->id,
            [[$start + 30 * MINSECS, $start + 2 * HOURSECS]]));
        // Without a room there is never a conflict.
        $this->assertEmpty(entry_manager::find_conflicts($this->calendar->id, 0, [[$start, $start + HOURSECS]]));

        $this->expectException(\moodle_exception::class);
        $this->generator->create_entry(['roomcalendarid' => $this->calendar->id, 'roomid' => $room->id,
            'timestart' => $start + 30 * MINSECS, 'timeend' => $start + 90 * MINSECS]);
    }

    public function test_other_room_does_not_conflict(): void {
        $room1 = $this->generator->create_room(['roomcalendarid' => $this->calendar->id]);
        $room2 = $this->generator->create_room(['roomcalendarid' => $this->calendar->id]);
        $start = make_timestamp(2026, 10, 14, 10, 0);
        $this->generator->create_entry(['roomcalendarid' => $this->calendar->id, 'roomid' => $room1->id,
            'timestart' => $start, 'timeend' => $start + HOURSECS]);
        $entries = $this->generator->create_entry(['roomcalendarid' => $this->calendar->id, 'roomid' => $room2->id,
            'timestart' => $start, 'timeend' => $start + HOURSECS]);
        $this->assertCount(1, $entries);
    }

    public function test_weekly_series_keeps_local_time_across_dst(): void {
        $this->setTimezone('Europe/Berlin');
        // 20 October 2026 is before, 27 October after the end of daylight saving time.
        $start = make_timestamp(2026, 10, 20, 10, 0, 0, 'Europe/Berlin');
        $until = make_timestamp(2026, 11, 3, 0, 0, 0, 'Europe/Berlin');
        $occurrences = entry_manager::compute_occurrences($start, $start + HOURSECS, entry_manager::REPEAT_WEEKLY, $until);
        $this->assertCount(3, $occurrences);
        foreach ($occurrences as [$s, $e]) {
            $this->assertSame('10:00', userdate($s, '%H:%M', 'Europe/Berlin'));
            $this->assertSame('11:00', userdate($e, '%H:%M', 'Europe/Berlin'));
        }
    }

    public function test_series_with_one_conflict_creates_nothing(): void {
        global $DB;
        $room = $this->generator->create_room(['roomcalendarid' => $this->calendar->id]);
        $start = make_timestamp(2026, 10, 12, 9, 0);
        $this->generator->create_entry(['roomcalendarid' => $this->calendar->id, 'roomid' => $room->id,
            'timestart' => $start + 2 * DAYSECS, 'timeend' => $start + 2 * DAYSECS + HOURSECS]);
        try {
            $this->generator->create_entry(['roomcalendarid' => $this->calendar->id, 'roomid' => $room->id,
                'timestart' => $start, 'timeend' => $start + HOURSECS,
                'repeat' => entry_manager::REPEAT_DAILY, 'repeatuntil' => $start + 4 * DAYSECS]);
            $this->fail('Expected a conflict');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorconflictdetails', $e->errorcode);
        }
        $this->assertSame(1, $DB->count_records('roomcalendar_entries'));
        $this->assertSame(0, $DB->count_records('roomcalendar_series'));
    }

    public function test_delete_series_respects_permissions(): void {
        global $DB;
        $editors = $this->generator->create_group(['roomcalendarid' => $this->calendar->id, 'canedit' => 1]);
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->generator->add_members($editors, [$teacher->id]);

        $this->setUser($teacher);
        $start = make_timestamp(2026, 10, 12, 9, 0);
        $entries = $this->generator->create_entry(['roomcalendarid' => $this->calendar->id,
            'timestart' => $start, 'timeend' => $start + HOURSECS,
            'repeat' => entry_manager::REPEAT_WEEKLY, 'repeatuntil' => $start + 3 * WEEKSECS]);
        $this->assertCount(4, $entries);
        // Restrict one occurrence to nobody but administrators.
        $locked = array_values($entries)[3];
        $DB->set_field('roomcalendar_entries', 'allgroups', 0, ['id' => $locked->id]);

        $permissions = new \mod_roomcalendar\local\permissions(
            $DB->get_record('roomcalendar', ['id' => $this->calendar->id]), $this->context);
        $first = reset($entries);
        $this->assertSame(1, entry_manager::delete_entry($first, false, $this->context, $permissions));
        $second = $DB->get_record('roomcalendar_entries', ['id' => next($entries)->id]);
        // The locked occurrence is skipped.
        $this->assertSame(2, entry_manager::delete_entry($second, true, $this->context, $permissions));
        $this->assertSame(1, $DB->count_records('roomcalendar_entries'));
        $this->assertSame(1, $DB->count_records('roomcalendar_series'));
    }
}
