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

use mod_roomcalendar\local\category_manager;

/**
 * Tests for event categories.
 *
 * @package    mod_roomcalendar
 * @copyright  2026 Michael K.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_roomcalendar\local\category_manager
 */
final class category_manager_test extends \advanced_testcase {

    public function test_entries_store_only_own_categories_and_survive_deletion(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $calendar = $this->getDataGenerator()->create_module('roomcalendar', ['course' => $course->id]);
        $other = $this->getDataGenerator()->create_module('roomcalendar', ['course' => $course->id]);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_roomcalendar');

        $category = $generator->create_category(['roomcalendarid' => $calendar->id, 'name' => 'Lessons']);
        $foreign = $generator->create_category(['roomcalendarid' => $other->id]);
        $DB->set_field('roomcalendar', 'defaultcategoryid', $category->id, ['id' => $calendar->id]);

        $start = make_timestamp(2026, 11, 2, 9, 0);
        $entry = array_values($generator->create_entry(['roomcalendarid' => $calendar->id, 'timestart' => $start,
            'timeend' => $start + HOURSECS, 'categoryid' => $category->id]))[0];
        $this->assertEquals($category->id, $entry->categoryid);

        $entry2 = array_values($generator->create_entry(['roomcalendarid' => $calendar->id, 'timestart' => $start,
            'timeend' => $start + HOURSECS, 'categoryid' => $foreign->id]))[0];
        $this->assertEquals(0, $entry2->categoryid, 'Category of another calendar is not stored');

        $this->assertSame([$category->id => '1'], array_map('strval', category_manager::count_entries($calendar->id)));

        category_manager::delete_category($category);
        $this->assertEquals(0, $DB->get_field('roomcalendar_entries', 'categoryid', ['id' => $entry->id]));
        $this->assertEquals(0, $DB->get_field('roomcalendar', 'defaultcategoryid', ['id' => $calendar->id]));
    }
}
