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
 * Add or edit an event category.
 *
 * @package    mod_roomcalendar
 * @copyright  2026 Michael K.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_roomcalendar\form\category_form;
use mod_roomcalendar\local\category_manager;

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$categoryid = optional_param('categoryid', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'roomcalendar');
$calendar = $DB->get_record('roomcalendar', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = \core\context\module::instance($cm->id);
require_capability('mod/roomcalendar:managegroups', $context);

$category = $categoryid ? category_manager::get_category($calendar->id, $categoryid) : null;
$returnurl = new \core\url('/mod/roomcalendar/categories.php', ['id' => $cm->id]);

$PAGE->set_url('/mod/roomcalendar/categoryedit.php', ['id' => $cm->id, 'categoryid' => $categoryid]);
$title = get_string($category ? 'editcategory' : 'addcategory', 'mod_roomcalendar');
$PAGE->set_title(format_string($calendar->name) . ': ' . $title);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->activityheader->disable();
$PAGE->set_secondary_active_tab('roomcalendar_categories');

$form = new category_form($PAGE->url);
$form->set_data($category ? [
    'id' => $cm->id,
    'categoryid' => $category->id,
    'name' => $category->name,
] : ['id' => $cm->id, 'categoryid' => 0]);

if ($form->is_cancelled()) {
    redirect($returnurl);
}
if ($data = $form->get_data()) {
    if ($category) {
        category_manager::update_category($category, $data);
    } else {
        category_manager::create_category($calendar->id, $data);
    }
    redirect($returnurl, get_string('categorysaved', 'mod_roomcalendar'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
$form->display();
echo $OUTPUT->footer();
