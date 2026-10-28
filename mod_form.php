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
 * Activity settings form for mod_roomcalendar.
 *
 * @package    mod_roomcalendar
 * @copyright  2026 Michael K.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Activity settings form for mod_roomcalendar.
 */
class mod_roomcalendar_mod_form extends moodleform_mod {

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        $mform->addElement('header', 'displaysettings', get_string('displaysettings', 'mod_roomcalendar'));

        $views = [
            'day' => get_string('viewday', 'mod_roomcalendar'),
            'week' => get_string('viewweek', 'mod_roomcalendar'),
            'month' => get_string('viewmonth', 'mod_roomcalendar'),
        ];
        $mform->addElement('select', 'defaultview', get_string('defaultview', 'mod_roomcalendar'), $views);
        $mform->setDefault('defaultview', 'week');

        $hours = [];
        for ($h = 0; $h <= 24; $h++) {
            $hours[$h] = sprintf('%02d:00', $h);
        }
        $firsthours = $hours;
        unset($firsthours[24]);
        $mform->addElement('select', 'firsthour', get_string('firsthour', 'mod_roomcalendar'), $firsthours);
        $mform->setDefault('firsthour', 7);
        $mform->addHelpButton('firsthour', 'firsthour', 'mod_roomcalendar');

        $lasthours = $hours;
        unset($lasthours[0]);
        $mform->addElement('select', 'lasthour', get_string('lasthour', 'mod_roomcalendar'), $lasthours);
        $mform->setDefault('lasthour', 20);

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Validate the display hours.
     *
     * @param array $data
     * @param array $files
     * @return array errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ((int)$data['lasthour'] <= (int)$data['firsthour']) {
            $errors['lasthour'] = get_string('errorlasthour', 'mod_roomcalendar');
        }
        return $errors;
    }
}
