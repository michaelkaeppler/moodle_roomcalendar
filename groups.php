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
 * Manage the permission groups of a room calendar.
 *
 * @package    mod_roomcalendar
 * @copyright  2026 Michael K.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_roomcalendar\local\group_manager;

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$groupid = optional_param('groupid', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'roomcalendar');
$calendar = $DB->get_record('roomcalendar', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = \core\context\module::instance($cm->id);
require_capability('mod/roomcalendar:managegroups', $context);

$baseurl = new \core\url('/mod/roomcalendar/groups.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($calendar->name) . ': ' . get_string('managegroups', 'mod_roomcalendar'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->activityheader->disable();
$PAGE->set_secondary_active_tab('roomcalendar_groups');

if ($action === 'setdefault') {
    require_sesskey();
    $default = optional_param('defaultgroupid', 0, PARAM_INT);
    if ($default && !group_manager::get_group($calendar->id, $default, IGNORE_MISSING)) {
        $default = 0;
    }
    $DB->set_field('roomcalendar', 'defaultgroupid', $default, ['id' => $calendar->id]);
    redirect($baseurl, get_string('defaultgroupsaved', 'mod_roomcalendar'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'delete') {
    $group = group_manager::get_group($calendar->id, $groupid);
    if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
        group_manager::delete_group($group);
        redirect($baseurl, get_string('groupdeleted', 'mod_roomcalendar'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    $count = $DB->count_records('roomcalendar_members', ['groupid' => $group->id]);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('deletegroup', 'mod_roomcalendar'));
    echo $OUTPUT->confirm(
        get_string('deletegroupconfirm', 'mod_roomcalendar',
            (object)['name' => format_string($group->name, true, ['context' => $context]), 'count' => $count]),
        new \core\url($baseurl, ['action' => 'delete', 'groupid' => $group->id, 'confirm' => 1, 'sesskey' => sesskey()]),
        $baseurl
    );
    echo $OUTPUT->footer();
    exit;
}

$groups = group_manager::get_groups($calendar->id);
$counts = group_manager::count_members($calendar->id);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managegroups', 'mod_roomcalendar'));
echo \core\output\html_writer::tag('p', get_string('managegroupsintro', 'mod_roomcalendar'));

echo \core\output\html_writer::div(
    $OUTPUT->single_button(new \core\url('/mod/roomcalendar/groupedit.php', ['id' => $cm->id]),
        get_string('addgroup', 'mod_roomcalendar'), 'get', ['type' => \core\output\single_button::BUTTON_PRIMARY]) .
    \core\output\html_writer::link(new \core\url('/mod/roomcalendar/view.php', ['id' => $cm->id]),
        get_string('backtocalendar', 'mod_roomcalendar'), ['class' => 'btn btn-secondary']),
    'd-flex flex-wrap mb-3', ['style' => 'gap: 0.5rem']
);

if (!$groups) {
    echo $OUTPUT->notification(get_string('nogroupsyet', 'mod_roomcalendar'), 'info');
} else {
    $yes = get_string('yes');
    $no = get_string('no');
    $table = new \core_table\output\html_table();
    $table->attributes['class'] = 'generaltable mod-roomcalendar-groups';
    $table->head = [
        get_string('groupname', 'mod_roomcalendar'),
        get_string('groupcanview', 'mod_roomcalendar'),
        get_string('groupcanedit', 'mod_roomcalendar'),
        get_string('members', 'mod_roomcalendar'),
        get_string('actions'),
    ];
    foreach ($groups as $group) {
        $name = format_string($group->name, true, ['context' => $context]);
        $membersurl = new \core\url('/mod/roomcalendar/members.php', ['id' => $cm->id, 'groupid' => $group->id]);
        if ((int)$calendar->defaultgroupid === (int)$group->id) {
            $name .= ' ' . \core\output\html_writer::span(get_string('isdefaultgroup', 'mod_roomcalendar'),
                'mod-roomcalendar-tag');
        }
        $actions = [
            $OUTPUT->action_icon($membersurl, new \core\output\pix_icon('i/users', get_string('members', 'mod_roomcalendar'))),
            $OUTPUT->action_icon(
                new \core\url('/mod/roomcalendar/groupedit.php', ['id' => $cm->id, 'groupid' => $group->id]),
                new \core\output\pix_icon('t/edit', get_string('edit'))),
            $OUTPUT->action_icon(new \core\url($baseurl, ['action' => 'delete', 'groupid' => $group->id]),
                new \core\output\pix_icon('t/delete', get_string('delete'))),
        ];
        $table->data[] = [
            $name,
            $group->canview ? $yes : $no,
            $group->canedit ? $yes : $no,
            \core\output\html_writer::link($membersurl, $counts[$group->id] ?? 0),
            implode(' ', $actions),
        ];
    }
    echo \core\output\html_writer::table($table);
}

// Default group for course participants without an explicit membership.
echo $OUTPUT->heading(get_string('defaultgroup', 'mod_roomcalendar'), 3);
echo \core\output\html_writer::tag('p', get_string('defaultgroup_help', 'mod_roomcalendar'));
$options = [0 => get_string('defaultgroupnone', 'mod_roomcalendar')];
foreach ($groups as $group) {
    $options[$group->id] = format_string($group->name, true, ['context' => $context]);
}
$setdefaulturl = new \core\url($baseurl, ['action' => 'setdefault', 'sesskey' => sesskey()]);
$select = new \core\output\single_select($setdefaulturl, 'defaultgroupid', $options, (int)$calendar->defaultgroupid, null);
$select->method = 'post';
$select->set_label(get_string('defaultgroup', 'mod_roomcalendar'), ['class' => 'mod-roomcalendar-sr-only']);
echo $OUTPUT->render($select);

echo $OUTPUT->footer();
