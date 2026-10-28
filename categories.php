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
 * Manage the event categories of a room calendar.
 *
 * @package    mod_roomcalendar
 * @copyright  2026 Michael K.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_roomcalendar\local\category_manager;

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$categoryid = optional_param('categoryid', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'roomcalendar');
$calendar = $DB->get_record('roomcalendar', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = \core\context\module::instance($cm->id);
require_capability('mod/roomcalendar:managegroups', $context);

$baseurl = new \core\url('/mod/roomcalendar/categories.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($calendar->name) . ': ' . get_string('managecategories', 'mod_roomcalendar'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->activityheader->disable();
$PAGE->set_secondary_active_tab('roomcalendar_categories');

if ($action === 'setdefault') {
    require_sesskey();
    $default = optional_param('defaultcategoryid', 0, PARAM_INT);
    $default = category_manager::clean_categoryid($calendar->id, $default);
    $DB->set_field('roomcalendar', 'defaultcategoryid', $default, ['id' => $calendar->id]);
    redirect($baseurl, get_string('defaultcategorysaved', 'mod_roomcalendar'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'delete') {
    $category = category_manager::get_category($calendar->id, $categoryid);
    if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
        category_manager::delete_category($category);
        redirect($baseurl, get_string('categorydeleted', 'mod_roomcalendar'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
    $count = $DB->count_records('roomcalendar_entries', ['roomcalendarid' => $calendar->id, 'categoryid' => $category->id]);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('deletecategory', 'mod_roomcalendar'));
    echo $OUTPUT->confirm(
        get_string('deletecategoryconfirm', 'mod_roomcalendar',
            (object)['name' => format_string($category->name, true, ['context' => $context]), 'count' => $count]),
        new \core\url($baseurl, ['action' => 'delete', 'categoryid' => $category->id, 'confirm' => 1,
            'sesskey' => sesskey()]),
        $baseurl
    );
    echo $OUTPUT->footer();
    exit;
}

$categories = category_manager::get_categories($calendar->id);
$counts = category_manager::count_entries($calendar->id);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managecategories', 'mod_roomcalendar'));
echo \core\output\html_writer::tag('p', get_string('managecategoriesintro', 'mod_roomcalendar'));

echo \core\output\html_writer::div(
    $OUTPUT->single_button(new \core\url('/mod/roomcalendar/categoryedit.php', ['id' => $cm->id]),
        get_string('addcategory', 'mod_roomcalendar'), 'get', ['type' => \core\output\single_button::BUTTON_PRIMARY]) .
    \core\output\html_writer::link(new \core\url('/mod/roomcalendar/view.php', ['id' => $cm->id]),
        get_string('backtocalendar', 'mod_roomcalendar'), ['class' => 'btn btn-secondary']),
    'd-flex flex-wrap mb-3', ['style' => 'gap: 0.5rem']
);

if (!$categories) {
    echo $OUTPUT->notification(get_string('nocategoriesyet', 'mod_roomcalendar'), 'info');
} else {
    $table = new \core_table\output\html_table();
    $table->attributes['class'] = 'generaltable mod-roomcalendar-categories';
    $table->head = [
        get_string('categoryname', 'mod_roomcalendar'),
        get_string('entries', 'mod_roomcalendar'),
        get_string('actions'),
    ];
    foreach ($categories as $category) {
        $name = format_string($category->name, true, ['context' => $context]);
        if ((int)$calendar->defaultcategoryid === (int)$category->id) {
            $name .= ' ' . \core\output\html_writer::span(get_string('isdefaultcategory', 'mod_roomcalendar'),
                'mod-roomcalendar-tag');
        }
        $actions = [
            $OUTPUT->action_icon(
                new \core\url('/mod/roomcalendar/categoryedit.php', ['id' => $cm->id, 'categoryid' => $category->id]),
                new \core\output\pix_icon('t/edit', get_string('edit'))),
            $OUTPUT->action_icon(new \core\url($baseurl, ['action' => 'delete', 'categoryid' => $category->id]),
                new \core\output\pix_icon('t/delete', get_string('delete'))),
        ];
        $table->data[] = [$name, $counts[$category->id] ?? 0, implode(' ', $actions)];
    }
    echo \core\output\html_writer::table($table);
}

// Category preselected for new events.
echo $OUTPUT->heading(get_string('defaultcategory', 'mod_roomcalendar'), 3);
echo \core\output\html_writer::tag('p', get_string('defaultcategory_help', 'mod_roomcalendar'));
$options = [0 => get_string('nocategory', 'mod_roomcalendar')];
foreach ($categories as $category) {
    $options[$category->id] = format_string($category->name, true, ['context' => $context]);
}
$setdefaulturl = new \core\url($baseurl, ['action' => 'setdefault', 'sesskey' => sesskey()]);
$select = new \core\output\single_select($setdefaulturl, 'defaultcategoryid', $options, (int)$calendar->defaultcategoryid, null);
$select->method = 'post';
$select->set_label(get_string('defaultcategory', 'mod_roomcalendar'), ['class' => 'mod-roomcalendar-sr-only']);
echo $OUTPUT->render($select);

echo $OUTPUT->footer();
