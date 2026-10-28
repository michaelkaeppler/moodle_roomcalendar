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
 * Manage the members of a permission group.
 *
 * @package    mod_roomcalendar
 * @copyright  2026 Michael K.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_roomcalendar\form\members_form;
use mod_roomcalendar\local\group_manager;

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/grouplib.php');

$id = required_param('id', PARAM_INT);
$groupid = required_param('groupid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'roomcalendar');
$calendar = $DB->get_record('roomcalendar', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = \core\context\module::instance($cm->id);
require_capability('mod/roomcalendar:managegroups', $context);
$coursecontext = \core\context\course::instance($course->id);

$group = group_manager::get_group($calendar->id, $groupid);
$groupname = format_string($group->name, true, ['context' => $context]);
$baseurl = new \core\url('/mod/roomcalendar/members.php', ['id' => $cm->id, 'groupid' => $group->id]);

$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($calendar->name) . ': ' . get_string('membersof', 'mod_roomcalendar', $groupname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->activityheader->disable();
$PAGE->set_secondary_active_tab('roomcalendar_groups');

if ($action === 'remove') {
    require_sesskey();
    group_manager::remove_member($group, required_param('userid', PARAM_INT));
    redirect($baseurl, get_string('memberremoved', 'mod_roomcalendar'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Course participants that can be added, labelled with their current permission group.
$groups = group_manager::get_groups($calendar->id);
$memberships = group_manager::get_memberships($calendar->id);
$identityfields = \core_user\fields::for_identity($coursecontext, false)->get_required_fields();
$userfields = \core_user\fields::for_name()->with_identity($coursecontext, false)->get_sql('u', false, '', '', false)->selects;
$enrolled = get_enrolled_users($coursecontext, '', 0, 'u.id, ' . $userfields, 'u.lastname, u.firstname');
$useroptions = [];
foreach ($enrolled as $user) {
    if (isset($memberships[$user->id]) && (int)$memberships[$user->id] === (int)$group->id) {
        continue;
    }
    $label = fullname($user);
    $identity = array_filter(array_map(fn($f) => $user->$f ?? '', $identityfields));
    if ($identity) {
        $label .= ' (' . implode(', ', $identity) . ')';
    }
    if (isset($memberships[$user->id], $groups[$memberships[$user->id]])) {
        $label .= ' – ' . get_string('currentlyin', 'mod_roomcalendar',
            format_string($groups[$memberships[$user->id]]->name, true, ['context' => $context]));
    }
    $useroptions[$user->id] = $label;
}
$coursegroups = [];
foreach (groups_get_all_groups($course->id) as $coursegroup) {
    $coursegroups[$coursegroup->id] = format_string($coursegroup->name, true, ['context' => $coursecontext]);
}

$form = new members_form($baseurl, ['users' => $useroptions, 'coursegroups' => $coursegroups]);
$form->set_data(['id' => $cm->id, 'groupid' => $group->id]);
if ($data = $form->get_data()) {
    // Only course participants can be added.
    $userids = array_intersect(array_map('intval', (array)($data->userids ?? [])), array_keys($enrolled));
    if (!empty($data->coursegroupid) && isset($coursegroups[$data->coursegroupid])) {
        $groupmembers = array_keys(groups_get_members($data->coursegroupid, 'u.id'));
        $userids = array_merge($userids, array_intersect($groupmembers, array_keys($enrolled)));
    }
    $result = group_manager::add_members($group, $userids);
    redirect($baseurl, get_string('membersadded', 'mod_roomcalendar', (object)$result), null,
        $result['moved'] ? \core\output\notification::NOTIFY_WARNING : \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('membersof', 'mod_roomcalendar', $groupname));
$permissionlabels = [];
if ($group->canview) {
    $permissionlabels[] = get_string('groupcanview', 'mod_roomcalendar');
}
if ($group->canedit) {
    $permissionlabels[] = get_string('groupcanedit', 'mod_roomcalendar');
}
echo \core\output\html_writer::tag('p', get_string('grouppermissions', 'mod_roomcalendar',
    $permissionlabels ? implode(', ', $permissionlabels) : get_string('none')));

$form->display();

$members = group_manager::get_members($group->id);
if (!$members) {
    echo $OUTPUT->notification(get_string('nomembers', 'mod_roomcalendar'), 'info');
} else {
    $table = new \core_table\output\html_table();
    $table->head = [get_string('fullnameuser'), get_string('actions')];
    foreach ($members as $member) {
        $removeurl = new \core\url($baseurl, ['action' => 'remove', 'userid' => $member->id, 'sesskey' => sesskey()]);
        $name = fullname($member);
        if (!isset($enrolled[$member->id])) {
            $name .= ' ' . \core\output\html_writer::span(get_string('notenrolled', 'mod_roomcalendar'), 'mod-roomcalendar-tag');
        }
        $table->data[] = [
            $name,
            $OUTPUT->action_icon($removeurl, new \core\output\pix_icon('t/delete', get_string('removemember', 'mod_roomcalendar'))),
        ];
    }
    echo \core\output\html_writer::table($table);
}

echo \core\output\html_writer::link(new \core\url('/mod/roomcalendar/groups.php', ['id' => $cm->id]),
    get_string('backtogroups', 'mod_roomcalendar'), ['class' => 'btn btn-secondary']);
echo $OUTPUT->footer();
