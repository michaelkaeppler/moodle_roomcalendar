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
 * Add or edit a room.
 *
 * @package    mod_roomcalendar
 * @copyright  2026 Michael K.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_roomcalendar\form\room_form;
use mod_roomcalendar\local\group_manager;
use mod_roomcalendar\local\room_manager;

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$roomid = optional_param('roomid', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'roomcalendar');
$calendar = $DB->get_record('roomcalendar', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = \core\context\module::instance($cm->id);
require_capability('mod/roomcalendar:managerooms', $context);

$room = $roomid ? room_manager::get_room($calendar->id, $roomid) : null;
$returnurl = new \core\url('/mod/roomcalendar/rooms.php', ['id' => $cm->id]);

$PAGE->set_url('/mod/roomcalendar/roomedit.php', ['id' => $cm->id, 'roomid' => $roomid]);
$title = get_string($room ? 'editroom' : 'addroom', 'mod_roomcalendar');
$PAGE->set_title(format_string($calendar->name) . ': ' . $title);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->activityheader->disable();
$PAGE->set_secondary_active_tab('roomcalendar_rooms');

$form = new room_form($PAGE->url, ['context' => $context, 'roomcalendarid' => $calendar->id]);

if ($room) {
    $form->set_data([
        'id' => $cm->id,
        'roomid' => $room->id,
        'name' => $room->name,
        'description_editor' => ['text' => $room->description, 'format' => $room->descriptionformat],
        'color' => $room->color,
        'bookable' => $room->bookable,
        'allgroups' => $room->allgroups,
        'groupids' => array_fill_keys(group_manager::get_room_groupids($calendar->id)[(int)$room->id] ?? [], 1),
    ]);
} else {
    $form->set_data(['id' => $cm->id, 'roomid' => 0]);
}

if ($form->is_cancelled()) {
    redirect($returnurl);
}
if ($data = $form->get_data()) {
    $data->description = $data->description_editor['text'];
    $data->descriptionformat = $data->description_editor['format'];
    $validgroups = array_keys(group_manager::get_groups($calendar->id));
    $data->groupids = array_values(array_intersect(array_keys(array_filter((array)($data->groupids ?? []))), $validgroups));
    if ($room) {
        room_manager::update_room($room, $data);
    } else {
        room_manager::create_room($calendar->id, $data);
    }
    redirect($returnurl, get_string('roomsaved', 'mod_roomcalendar'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
$form->display();
echo $OUTPUT->footer();
