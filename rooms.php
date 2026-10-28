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
 * Manage the bookable rooms of a room calendar.
 *
 * @package    mod_roomcalendar
 * @copyright  2026 Michael K.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_roomcalendar\local\group_manager;
use mod_roomcalendar\local\room_manager;

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$roomid = optional_param('roomid', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'roomcalendar');
$calendar = $DB->get_record('roomcalendar', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = \core\context\module::instance($cm->id);
require_capability('mod/roomcalendar:managerooms', $context);

$baseurl = new \core\url('/mod/roomcalendar/rooms.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($calendar->name) . ': ' . get_string('managerooms', 'mod_roomcalendar'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->activityheader->disable();
$PAGE->set_secondary_active_tab('roomcalendar_rooms');

if ($action === 'up' || $action === 'down') {
    require_sesskey();
    room_manager::move_room(room_manager::get_room($calendar->id, $roomid), $action === 'up');
    redirect($baseurl);
}

if ($action === 'delete') {
    $room = room_manager::get_room($calendar->id, $roomid);
    if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
        room_manager::delete_room($room);
        redirect($baseurl, get_string('roomdeleted', 'mod_roomcalendar'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    $count = $DB->count_records('roomcalendar_entries', ['roomid' => $room->id, 'roomcalendarid' => $calendar->id]);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('deleteroom', 'mod_roomcalendar'));
    $a = (object)['name' => format_string($room->name, true, ['context' => $context]), 'count' => $count];
    echo $OUTPUT->confirm(
        get_string('deleteroomconfirm', 'mod_roomcalendar', $a),
        new \core\url($baseurl, ['action' => 'delete', 'roomid' => $room->id, 'confirm' => 1, 'sesskey' => sesskey()]),
        $baseurl
    );
    echo $OUTPUT->footer();
    exit;
}

$rooms = room_manager::get_rooms($calendar->id);
$counts = $DB->get_records_sql_menu(
    "SELECT roomid, COUNT(1) FROM {roomcalendar_entries} WHERE roomcalendarid = :id AND roomid > 0 GROUP BY roomid",
    ['id' => $calendar->id]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managerooms', 'mod_roomcalendar'));

echo \core\output\html_writer::div(
    $OUTPUT->single_button(new \core\url('/mod/roomcalendar/roomedit.php', ['id' => $cm->id]),
        get_string('addroom', 'mod_roomcalendar'), 'get', ['type' => \core\output\single_button::BUTTON_PRIMARY]) .
    \core\output\html_writer::link(new \core\url('/mod/roomcalendar/view.php', ['id' => $cm->id]),
        get_string('backtocalendar', 'mod_roomcalendar'), ['class' => 'btn btn-secondary']),
    'd-flex flex-wrap mb-3', ['style' => 'gap: 0.5rem']
);

if (!$rooms) {
    echo $OUTPUT->notification(get_string('norooms', 'mod_roomcalendar'), 'info');
} else {
    $table = new \core_table\output\html_table();
    $table->attributes['class'] = 'generaltable mod-roomcalendar-rooms';
    $table->head = [
        get_string('roomname', 'mod_roomcalendar'),
        get_string('description'),
        get_string('roombookable', 'mod_roomcalendar'),
        get_string('roomgroups', 'mod_roomcalendar'),
        get_string('bookings', 'mod_roomcalendar'),
        get_string('actions'),
    ];
    $ids = array_keys($rooms);
    $groups = group_manager::get_groups($calendar->id);
    $roomgroups = group_manager::get_room_groupids($calendar->id);
    foreach (array_values($rooms) as $i => $room) {
        $name = format_string($room->name, true, ['context' => $context]);
        $actions = [];
        if ($i > 0) {
            $actions[] = $OUTPUT->action_icon(
                new \core\url($baseurl, ['action' => 'up', 'roomid' => $room->id, 'sesskey' => sesskey()]),
                new \core\output\pix_icon('t/up', get_string('moveup')));
        }
        if ($i < count($ids) - 1) {
            $actions[] = $OUTPUT->action_icon(
                new \core\url($baseurl, ['action' => 'down', 'roomid' => $room->id, 'sesskey' => sesskey()]),
                new \core\output\pix_icon('t/down', get_string('movedown')));
        }
        $actions[] = $OUTPUT->action_icon(
            new \core\url('/mod/roomcalendar/roomedit.php', ['id' => $cm->id, 'roomid' => $room->id]),
            new \core\output\pix_icon('t/edit', get_string('edit')));
        $actions[] = $OUTPUT->action_icon(
            new \core\url($baseurl, ['action' => 'delete', 'roomid' => $room->id]),
            new \core\output\pix_icon('t/delete', get_string('delete')));

        $table->data[] = [
            \core\output\html_writer::span('', 'mod-roomcalendar-swatch rc-color-' . $room->color) . $name,
            format_text($room->description, $room->descriptionformat, ['context' => $context]),
            $room->bookable ? get_string('yes') : get_string('no'),
            $room->allgroups ? get_string('allgroups', 'mod_roomcalendar') : (implode(', ', array_map(
                fn($gid) => format_string($groups[$gid]->name, true, ['context' => $context]),
                array_filter($roomgroups[(int)$room->id] ?? [], fn($gid) => isset($groups[$gid]))
            )) ?: get_string('nogroups', 'mod_roomcalendar')),
            $counts[$room->id] ?? 0,
            implode(' ', $actions),
        ];
    }
    echo \core\output\html_writer::table($table);
}

echo $OUTPUT->footer();
