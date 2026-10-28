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
 * Library of interface functions for mod_roomcalendar.
 *
 * @package    mod_roomcalendar
 * @copyright  2026 Michael K.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Report which Moodle features this module supports.
 *
 * @param string $feature FEATURE_xx constant
 * @return mixed true/false if supported, a value for FEATURE_MOD_PURPOSE, null if unknown
 */
function roomcalendar_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_GRADE_OUTCOMES:
        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_COLLABORATION;
        default:
            return null;
    }
}

/**
 * Add a new room calendar instance.
 *
 * @param stdClass $data form data
 * @param mod_roomcalendar_mod_form|null $mform
 * @return int new instance id
 */
function roomcalendar_add_instance($data, $mform = null) {
    global $DB;
    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    return $DB->insert_record('roomcalendar', $data);
}

/**
 * Update an existing room calendar instance.
 *
 * @param stdClass $data form data
 * @param mod_roomcalendar_mod_form|null $mform
 * @return bool
 */
function roomcalendar_update_instance($data, $mform = null) {
    global $DB;
    $data->id = $data->instance;
    $data->timemodified = time();
    return $DB->update_record('roomcalendar', $data);
}

/**
 * Delete a room calendar instance with all of its rooms and entries.
 *
 * @param int $id instance id
 * @return bool
 */
function roomcalendar_delete_instance($id) {
    global $DB;
    if (!$DB->record_exists('roomcalendar', ['id' => $id])) {
        return false;
    }
    $entries = "SELECT id FROM {roomcalendar_entries} WHERE roomcalendarid = :id";
    $DB->delete_records_select('roomcalendar_entrygroups', "entryid IN ($entries)", ['id' => $id]);
    $rooms = "SELECT id FROM {roomcalendar_rooms} WHERE roomcalendarid = :id";
    $DB->delete_records_select('roomcalendar_roomgroups', "roomid IN ($rooms)", ['id' => $id]);
    $DB->delete_records('roomcalendar_entries', ['roomcalendarid' => $id]);
    $DB->delete_records('roomcalendar_series', ['roomcalendarid' => $id]);
    $DB->delete_records('roomcalendar_rooms', ['roomcalendarid' => $id]);
    $DB->delete_records('roomcalendar_members', ['roomcalendarid' => $id]);
    $DB->delete_records('roomcalendar_groups', ['roomcalendarid' => $id]);
    $DB->delete_records('roomcalendar_categories', ['roomcalendarid' => $id]);
    $DB->delete_records('roomcalendar', ['id' => $id]);
    return true;
}

/**
 * Add "Permission groups", "Categories" and "Manage rooms" to the activity's secondary navigation.
 *
 * @param settings_navigation $settingsnav
 * @param navigation_node $node the module settings node
 */
function roomcalendar_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $node) {
    $cm = $settingsnav->get_page()->cm;
    if (!$cm) {
        return;
    }
    if (has_capability('mod/roomcalendar:managegroups', $cm->context)) {
        $node->add(
            get_string('managegroups', 'mod_roomcalendar'),
            new \core\url('/mod/roomcalendar/groups.php', ['id' => $cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'roomcalendar_groups',
            new \core\output\pix_icon('i/users', '')
        );
        $node->add(
            get_string('managecategories', 'mod_roomcalendar'),
            new \core\url('/mod/roomcalendar/categories.php', ['id' => $cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'roomcalendar_categories',
            new \core\output\pix_icon('i/categoryevent', '')
        );
    }
    if (!has_capability('mod/roomcalendar:managerooms', $cm->context)) {
        return;
    }
    $node->add(
        get_string('managerooms', 'mod_roomcalendar'),
        new \core\url('/mod/roomcalendar/rooms.php', ['id' => $cm->id]),
        navigation_node::TYPE_SETTING,
        null,
        'roomcalendar_rooms',
        new \core\output\pix_icon('i/settings', '')
    );
}

/**
 * Add elements to the course reset form.
 *
 * @param MoodleQuickForm $mform
 */
function roomcalendar_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'roomcalendarheader', get_string('modulenameplural', 'mod_roomcalendar'));
    $mform->addElement('advcheckbox', 'reset_roomcalendar_entries', get_string('resetentries', 'mod_roomcalendar'));
    $mform->addElement('advcheckbox', 'reset_roomcalendar_members', get_string('resetmembers', 'mod_roomcalendar'));
}

/**
 * Default values for the course reset form.
 *
 * @param stdClass $course
 * @return array
 */
function roomcalendar_reset_course_form_defaults($course) {
    return ['reset_roomcalendar_entries' => 1, 'reset_roomcalendar_members' => 0];
}

/**
 * Remove user data (entries) during course reset. Rooms are kept.
 *
 * @param stdClass $data reset form data
 * @return array status messages
 */
function roomcalendar_reset_userdata($data) {
    global $DB;
    $status = [];
    if (!empty($data->reset_roomcalendar_entries)) {
        $instances = "SELECT id FROM {roomcalendar} WHERE course = :courseid";
        $params = ['courseid' => $data->courseid];
        $DB->delete_records_select('roomcalendar_entrygroups',
            "entryid IN (SELECT id FROM {roomcalendar_entries} WHERE roomcalendarid IN ($instances))", $params);
        $DB->delete_records_select('roomcalendar_entries', "roomcalendarid IN ($instances)", $params);
        $DB->delete_records_select('roomcalendar_series', "roomcalendarid IN ($instances)", $params);
        $status[] = [
            'component' => get_string('modulenameplural', 'mod_roomcalendar'),
            'item' => get_string('resetentries', 'mod_roomcalendar'),
            'error' => false,
        ];
    }
    if (!empty($data->reset_roomcalendar_members)) {
        $DB->delete_records_select('roomcalendar_members',
            "roomcalendarid IN (SELECT id FROM {roomcalendar} WHERE course = :courseid)", ['courseid' => $data->courseid]);
        $status[] = [
            'component' => get_string('modulenameplural', 'mod_roomcalendar'),
            'item' => get_string('resetmembers', 'mod_roomcalendar'),
            'error' => false,
        ];
    }
    return $status;
}

/**
 * Trigger the course_module_viewed event and mark the activity as viewed for completion.
 *
 * @param stdClass $roomcalendar
 * @param stdClass $course
 * @param cm_info|stdClass $cm
 * @param \core\context\module $context
 */
function roomcalendar_view($roomcalendar, $course, $cm, $context) {
    $event = \mod_roomcalendar\event\course_module_viewed::create([
        'objectid' => $roomcalendar->id,
        'context' => $context,
    ]);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('roomcalendar', $roomcalendar);
    $event->trigger();

    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}
