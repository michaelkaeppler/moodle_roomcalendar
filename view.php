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
 * Display a room calendar.
 *
 * @package    mod_roomcalendar
 * @copyright  2026 Michael K.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);
$view = optional_param('view', '', PARAM_ALPHA);
$date = optional_param('date', '', PARAM_ALPHANUMEXT);
$roomid = optional_param('room', 0, PARAM_INT);
$hidecats = optional_param('hidecats', '', PARAM_SEQUENCE);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'roomcalendar');
$calendar = $DB->get_record('roomcalendar', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = \core\context\module::instance($cm->id);
\mod_roomcalendar\local\permissions::for_current_user($calendar, $context)->require_view();

$PAGE->set_url('/mod/roomcalendar/view.php', array_filter(['id' => $cm->id, 'view' => $view, 'date' => $date,
    'room' => $roomid, 'hidecats' => $hidecats]));
$PAGE->set_title(format_string($calendar->name));
$PAGE->set_heading(format_string($course->fullname));

roomcalendar_view($calendar, $course, $cm, $context);

$calendarview = new \mod_roomcalendar\output\calendar_view($cm, $calendar, $context, $view, $date, $roomid,
    $hidecats);

echo $OUTPUT->header();
echo $OUTPUT->render($calendarview);
echo $OUTPUT->footer();
