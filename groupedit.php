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
 * Add or edit a permission group.
 *
 * @package    mod_roomcalendar
 * @copyright  2026 Michael K.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_roomcalendar\form\group_form;
use mod_roomcalendar\local\group_manager;

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$groupid = optional_param('groupid', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'roomcalendar');
$calendar = $DB->get_record('roomcalendar', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = \core\context\module::instance($cm->id);
require_capability('mod/roomcalendar:managegroups', $context);

$group = $groupid ? group_manager::get_group($calendar->id, $groupid) : null;
$returnurl = new \core\url('/mod/roomcalendar/groups.php', ['id' => $cm->id]);

$PAGE->set_url('/mod/roomcalendar/groupedit.php', ['id' => $cm->id, 'groupid' => $groupid]);
$title = get_string($group ? 'editgroup' : 'addgroup', 'mod_roomcalendar');
$PAGE->set_title(format_string($calendar->name) . ': ' . $title);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->activityheader->disable();
$PAGE->set_secondary_active_tab('roomcalendar_groups');

$form = new group_form($PAGE->url);
$form->set_data($group ? [
    'id' => $cm->id,
    'groupid' => $group->id,
    'name' => $group->name,
    'canview' => $group->canview,
    'canedit' => $group->canedit,
] : ['id' => $cm->id, 'groupid' => 0]);

if ($form->is_cancelled()) {
    redirect($returnurl);
}
if ($data = $form->get_data()) {
    if ($group) {
        group_manager::update_group($group, $data);
    } else {
        group_manager::create_group($calendar->id, $data);
    }
    redirect($returnurl, get_string('groupsaved', 'mod_roomcalendar'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
$form->display();
echo $OUTPUT->footer();
