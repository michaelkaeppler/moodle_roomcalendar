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
 * List all room calendars of a course.
 *
 * @package    mod_roomcalendar
 * @copyright  2026 Michael K.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$course = get_course($id);
require_course_login($course);

$PAGE->set_url('/mod/roomcalendar/index.php', ['id' => $id]);
$PAGE->set_title(format_string($course->shortname) . ': ' . get_string('modulenameplural', 'mod_roomcalendar'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

\mod_roomcalendar\event\course_module_instance_list_viewed::create([
    'context' => \core\context\course::instance($course->id),
])->trigger();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_roomcalendar'));

$table = new \core_table\output\html_table();
$table->head = [get_string('name'), get_string('moduleintro')];
foreach (get_all_instances_in_course('roomcalendar', $course) as $calendar) {
    $url = new \core\url('/mod/roomcalendar/view.php', ['id' => $calendar->coursemodule]);
    $attributes = $calendar->visible ? [] : ['class' => 'dimmed'];
    $table->data[] = [
        \core\output\html_writer::link($url, format_string($calendar->name), $attributes),
        format_module_intro('roomcalendar', $calendar, $calendar->coursemodule),
    ];
}

if (empty($table->data)) {
    echo $OUTPUT->notification(get_string('thereareno', 'moodle', get_string('modulenameplural', 'mod_roomcalendar')),
        'info');
} else {
    echo \core\output\html_writer::table($table);
}
echo $OUTPUT->footer();
