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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for adding course participants to a permission group, individually or from a Moodle course group.
 *
 * Custom data: users (userid => label), coursegroups (groupid => name).
 *
 * @package    mod_roomcalendar
 * @copyright  2026 Michael K.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class members_form extends \moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'groupid');
        $mform->setType('groupid', PARAM_INT);

        $mform->addElement('autocomplete', 'userids', get_string('addusers', 'mod_roomcalendar'),
            $this->_customdata['users'], [
                'multiple' => true,
                'noselectionstring' => get_string('nousersselected', 'mod_roomcalendar'),
            ]);
        $mform->addHelpButton('userids', 'addusers', 'mod_roomcalendar');

        if ($this->_customdata['coursegroups']) {
            $options = [0 => get_string('choosedots')] + $this->_customdata['coursegroups'];
            $mform->addElement('select', 'coursegroupid', get_string('addcoursegroup', 'mod_roomcalendar'), $options);
            $mform->addHelpButton('coursegroupid', 'addcoursegroup', 'mod_roomcalendar');
        }

        $this->add_action_buttons(false, get_string('addmembers', 'mod_roomcalendar'));
    }
}
