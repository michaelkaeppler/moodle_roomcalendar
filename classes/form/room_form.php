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

namespace mod_roomcalendar\form;

use mod_roomcalendar\local\group_manager;
use mod_roomcalendar\local\room_manager;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for adding and editing a room.
 *
 * Custom data: context (module context), roomcalendarid.
 *
 * @package    mod_roomcalendar
 * @copyright  2026 Michael K.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class room_form extends \moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'roomid');
        $mform->setType('roomid', PARAM_INT);

        $mform->addElement('text', 'name', get_string('roomname', 'mod_roomcalendar'), ['size' => 40]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('editor', 'description_editor', get_string('description'), ['rows' => 4],
            ['maxfiles' => 0, 'context' => $this->_customdata['context']]);
        $mform->setType('description_editor', PARAM_RAW);

        $mform->addElement('select', 'color', get_string('roomcolor', 'mod_roomcalendar'),
            room_manager::get_color_options());

        $mform->addElement('advcheckbox', 'bookable', get_string('roombookable', 'mod_roomcalendar'));
        $mform->setDefault('bookable', 1);
        $mform->addHelpButton('bookable', 'roombookable', 'mod_roomcalendar');

        // Which permission groups may add and delete events in this room.
        $mform->addElement('header', 'permissionshdr', get_string('roompermissions', 'mod_roomcalendar'));
        $mform->setExpanded('permissionshdr', true);
        $mform->addElement('advcheckbox', 'allgroups', get_string('roomallgroups', 'mod_roomcalendar'));
        $mform->setDefault('allgroups', 1);
        $mform->addHelpButton('allgroups', 'roomallgroups', 'mod_roomcalendar');
        $checkboxes = [];
        foreach (group_manager::get_groups($this->_customdata['roomcalendarid']) as $group) {
            $label = format_string($group->name, true, ['context' => $this->_customdata['context']]);
            if (!$group->canedit) {
                $label .= ' ' . get_string('groupcannotedit', 'mod_roomcalendar');
            }
            $checkboxes[] = $mform->createElement('advcheckbox', $group->id, '', $label);
        }
        if ($checkboxes) {
            $mform->addGroup($checkboxes, 'groupids', get_string('roomgroups', 'mod_roomcalendar'), '<br>', true);
            $mform->hideIf('groupids', 'allgroups', 'checked');
        }

        $this->add_action_buttons();
    }

    /**
     * Require at least one group unless all groups are allowed.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty($data['allgroups']) && !array_filter((array)($data['groupids'] ?? []))) {
            $errors[isset($data['groupids']) ? 'groupids' : 'allgroups'] = get_string('errornogroups', 'mod_roomcalendar');
        }
        return $errors;
    }
}
