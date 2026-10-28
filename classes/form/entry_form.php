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

use core\context;
use core\url;
use core_date;
use core_form\dynamic_form;
use DateTimeImmutable;
use mod_roomcalendar\local\category_manager;
use mod_roomcalendar\local\entry_manager;
use mod_roomcalendar\local\group_manager;
use mod_roomcalendar\local\permissions;
use mod_roomcalendar\local\room_manager;
use stdClass;

/**
 * Modal form for creating and editing calendar entries.
 *
 * Expected arguments: cmid, and either id (edit) or optional prefillstart, prefillroom and prefillallday (create).
 *
 * @package    mod_roomcalendar
 * @copyright  2026 Michael K.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class entry_form extends dynamic_form {

    /** @var stdClass|null */
    protected $cm = null;

    /** @var stdClass|null */
    protected $calendar = null;

    /** @var stdClass|null entry being edited */
    protected $entry = null;

    /**
     * Load course module, instance and entry from the form arguments.
     */
    protected function load(): void {
        global $DB;
        if ($this->cm !== null) {
            return;
        }
        $cmid = $this->optional_param('cmid', 0, PARAM_INT);
        $this->cm = get_coursemodule_from_id('roomcalendar', $cmid, 0, false, MUST_EXIST);
        $this->calendar = $DB->get_record('roomcalendar', ['id' => $this->cm->instance], '*', MUST_EXIST);
        $id = $this->optional_param('id', 0, PARAM_INT);
        if ($id) {
            $this->entry = entry_manager::get_entry($this->calendar->id, $id);
        }
    }

    /**
     * Module context.
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        $this->load();
        return \core\context\module::instance($this->cm->id);
    }

    /**
     * Permissions of the current user.
     *
     * @return permissions
     */
    protected function permissions(): permissions {
        $this->load();
        return permissions::for_current_user($this->calendar, $this->get_context_for_dynamic_submission());
    }

    /**
     * Check that the user may create, or edit this particular, entry.
     */
    protected function check_access_for_dynamic_submission(): void {
        $permissions = $this->permissions();
        $permissions->require_view();
        $allowed = $this->entry ? $permissions->can_edit_entry($this->entry) : $permissions->can_add();
        if (!$allowed) {
            throw new \moodle_exception('errornopermission', 'mod_roomcalendar');
        }
    }

    /**
     * Page the form belongs to.
     *
     * @return url
     */
    protected function get_page_url_for_dynamic_submission(): url {
        $this->load();
        return new url('/mod/roomcalendar/view.php', ['id' => $this->cm->id]);
    }

    /**
     * Form definition.
     */
    public function definition() {
        $this->load();
        $mform = $this->_form;
        $context = $this->get_context_for_dynamic_submission();

        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'name', get_string('entryname', 'mod_roomcalendar'), ['size' => 40]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('text', 'contactname', get_string('contactname', 'mod_roomcalendar'), ['size' => 40]);
        $mform->setType('contactname', PARAM_TEXT);
        $mform->addRule('contactname', null, 'required', null, 'client');
        $mform->addRule('contactname', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('contactname', 'contactname', 'mod_roomcalendar');

        $mform->addElement('text', 'contactemail', get_string('contactemail', 'mod_roomcalendar'), ['size' => 40]);
        $mform->setType('contactemail', PARAM_RAW_TRIMMED);
        $mform->addRule('contactemail', null, 'required', null, 'client');
        $mform->addRule('contactemail', null, 'email', null, 'client');
        $mform->addRule('contactemail', get_string('maximumchars', '', 254), 'maxlength', 254, 'client');

        $categories = category_manager::get_categories($this->calendar->id);
        if ($categories) {
            $options = [0 => get_string('nocategory', 'mod_roomcalendar')];
            foreach ($categories as $category) {
                $options[$category->id] = format_string($category->name, true, ['context' => $context]);
            }
            $mform->addElement('select', 'categoryid', get_string('category', 'mod_roomcalendar'), $options);
            $mform->setType('categoryid', PARAM_INT);
        }

        $permissions = $this->permissions();
        $rooms = [0 => get_string('noroom', 'mod_roomcalendar')];
        foreach (room_manager::get_rooms($this->calendar->id) as $room) {
            $keep = $this->entry && (int)$this->entry->roomid === (int)$room->id;
            if ($keep || ($room->bookable && $permissions->can_use_room($room))) {
                $rooms[$room->id] = format_string($room->name, true, ['context' => $context]);
            }
        }
        $mform->addElement('select', 'roomid', get_string('room', 'mod_roomcalendar'), $rooms);
        $mform->setType('roomid', PARAM_INT);

        $mform->addElement('advcheckbox', 'allday', get_string('allday', 'mod_roomcalendar'));

        $mform->addElement('date_time_selector', 'timestart', get_string('timestart', 'mod_roomcalendar'), ['step' => 5]);
        $mform->addElement('date_time_selector', 'timeend', get_string('timeend', 'mod_roomcalendar'), ['step' => 5]);
        $mform->hideIf('timestart', 'allday', 'checked');
        $mform->hideIf('timeend', 'allday', 'checked');

        $mform->addElement('date_selector', 'startdate', get_string('startdate', 'mod_roomcalendar'));
        $mform->addElement('date_selector', 'enddate', get_string('enddate', 'mod_roomcalendar'));
        $mform->hideIf('startdate', 'allday', 'notchecked');
        $mform->hideIf('enddate', 'allday', 'notchecked');

        $mform->addElement('editor', 'description', get_string('description'), ['rows' => 4],
            ['maxfiles' => 0, 'context' => $context]);
        $mform->setType('description', PARAM_RAW);

        // Which permission groups may change and delete this event later.
        $mform->addElement('header', 'permissionshdr', get_string('entrypermissions', 'mod_roomcalendar'));
        $mform->setExpanded('permissionshdr', true);
        $mform->addElement('advcheckbox', 'allgroups', get_string('entryallgroups', 'mod_roomcalendar'));
        $mform->addHelpButton('allgroups', 'entryallgroups', 'mod_roomcalendar');
        $checkboxes = [];
        foreach (group_manager::get_editing_groups($this->calendar->id) as $group) {
            $checkboxes[] = $mform->createElement('advcheckbox', $group->id, '',
                format_string($group->name, true, ['context' => $context]));
        }
        if ($checkboxes) {
            $mform->addGroup($checkboxes, 'groupids', get_string('entrygroups', 'mod_roomcalendar'), '<br>', true);
            $mform->hideIf('groupids', 'allgroups', 'checked');
        } else {
            $mform->addElement('static', 'nogroupsnote', '', get_string('noeditinggroups', 'mod_roomcalendar'));
        }

        if (!$this->entry) {
            $mform->addElement('header', 'repeathdr', get_string('repeat', 'mod_roomcalendar'));
            $options = [
                entry_manager::REPEAT_NONE => get_string('repeatnone', 'mod_roomcalendar'),
                entry_manager::REPEAT_DAILY => get_string('repeatdaily', 'mod_roomcalendar'),
                entry_manager::REPEAT_WEEKLY => get_string('repeatweekly', 'mod_roomcalendar'),
            ];
            $mform->addElement('select', 'repeat', get_string('repeat', 'mod_roomcalendar'), $options);
            $mform->addElement('date_selector', 'repeatuntil', get_string('repeatuntil', 'mod_roomcalendar'));
            $mform->hideIf('repeatuntil', 'repeat', 'eq', entry_manager::REPEAT_NONE);
        } else if ($this->entry->seriesid) {
            $mform->addElement('static', 'seriesnote', '', get_string('editseriesnote', 'mod_roomcalendar'));
        }
    }

    /**
     * Populate the form for a new or existing entry.
     */
    public function set_data_for_dynamic_submission(): void {
        $this->load();
        if ($this->entry) {
            $e = $this->entry;
            $data = [
                'id' => $e->id,
                'name' => $e->name,
                'contactname' => $e->contactname,
                'contactemail' => $e->contactemail,
                'roomid' => $e->roomid,
                'allday' => $e->allday,
                'timestart' => $e->timestart,
                'timeend' => $e->timeend,
                'startdate' => $e->timestart,
                'enddate' => $e->allday ? $e->timeend - 1 : $e->timeend,
                'description' => ['text' => $e->description, 'format' => $e->descriptionformat],
                'allgroups' => $e->allgroups,
                'categoryid' => $e->categoryid,
                'groupids' => array_fill_keys(group_manager::get_entry_groupids([$e->id])[$e->id] ?? [], 1),
            ];
        } else {
            $start = $this->optional_param('prefillstart', 0, PARAM_INT);
            if ($start <= 0) {
                // Default to the next full hour.
                $start = (int)(ceil(time() / HOURSECS) * HOURSECS);
            }
            $data = [
                'id' => 0,
                'roomid' => $this->optional_param('prefillroom', 0, PARAM_INT),
                'allday' => $this->optional_param('prefillallday', 0, PARAM_BOOL),
                'timestart' => $start,
                'timeend' => $start + HOURSECS,
                'startdate' => $start,
                'enddate' => $start,
                'repeat' => entry_manager::REPEAT_NONE,
                'repeatuntil' => $start + 4 * WEEKSECS,
                'description' => ['text' => '', 'format' => FORMAT_HTML],
                'categoryid' => (int)$this->calendar->defaultcategoryid,
            ];
            // Preselect every group that may edit; if there is none yet, open the event to all groups.
            $editinggroups = group_manager::get_editing_groups($this->calendar->id);
            $data['allgroups'] = $editinggroups ? 0 : 1;
            $data['groupids'] = array_fill_keys(array_keys($editinggroups), 1);
        }
        $data['cmid'] = $this->cm->id;
        $this->set_data($data);
    }

    /**
     * Convert submitted form values into entry start and end timestamps.
     *
     * @param array|stdClass $data
     * @return int[] [timestart, timeend]
     */
    protected static function get_times($data): array {
        $data = (array)$data;
        if (!empty($data['allday'])) {
            $tz = core_date::get_user_timezone_object();
            $start = (new DateTimeImmutable('@' . $data['startdate']))->setTimezone($tz)->setTime(0, 0);
            $end = (new DateTimeImmutable('@' . $data['enddate']))->setTimezone($tz)->setTime(0, 0)->modify('+1 day');
            return [$start->getTimestamp(), $end->getTimestamp()];
        }
        return [(int)$data['timestart'], (int)$data['timeend']];
    }

    /**
     * Validate times, room and conflicts.
     *
     * @param array $data
     * @param array $files
     * @return array errors
     */
    public function validation($data, $files) {
        $this->load();
        $errors = parent::validation($data, $files);
        $context = $this->get_context_for_dynamic_submission();
        [$start, $end] = self::get_times($data);
        $endfield = empty($data['allday']) ? 'timeend' : 'enddate';

        if (trim($data['contactname'] ?? '') === '') {
            $errors['contactname'] = get_string('required');
        }
        $email = trim($data['contactemail'] ?? '');
        if ($email === '') {
            $errors['contactemail'] = get_string('required');
        } else if (!validate_email($email)) {
            $errors['contactemail'] = get_string('invalidemail');
        }
        if ($errors) {
            return $errors;
        }

        if ($end <= $start) {
            $errors[$endfield] = get_string('errorendbeforestart', 'mod_roomcalendar');
            return $errors;
        }

        // Values not offered by the select element (e.g. a non-bookable room) are dropped by the form.
        $roomid = (int)($data['roomid'] ?? 0);
        if ($roomid) {
            $room = room_manager::get_room($this->calendar->id, $roomid, IGNORE_MISSING);
            $unchanged = $this->entry && (int)$this->entry->roomid === $roomid;
            if (!$room || (!$room->bookable && !$unchanged)) {
                $errors['roomid'] = get_string('errorroomnotbookable', 'mod_roomcalendar');
                return $errors;
            }
            if (!$this->permissions()->can_use_room($room)) {
                $errors['roomid'] = get_string('errorroomnotallowed', 'mod_roomcalendar');
                return $errors;
            }
        }

        if (empty($data['allgroups']) && !self::selected_groupids($data)) {
            $errors[isset($data['groupids']) ? 'groupids' : 'allgroups'] = get_string('errornogroups', 'mod_roomcalendar');
            return $errors;
        }

        $intervals = [[$start, $end]];
        if (!$this->entry && !empty($data['repeat'])) {
            if ($data['repeatuntil'] < $start - DAYSECS) {
                $errors['repeatuntil'] = get_string('errorrepeatuntil', 'mod_roomcalendar');
                return $errors;
            }
            $intervals = entry_manager::compute_occurrences($start, $end, $data['repeat'], $data['repeatuntil']);
            if (count($intervals) > entry_manager::MAX_OCCURRENCES) {
                $errors['repeatuntil'] = get_string('errortoomanyoccurrences', 'mod_roomcalendar',
                    entry_manager::MAX_OCCURRENCES);
                return $errors;
            }
        }

        $exclude = $this->entry ? [$this->entry->id] : [];
        $conflicts = entry_manager::find_conflicts($this->calendar->id, $roomid, $intervals, $exclude);
        if ($conflicts) {
            $errors['roomid'] = entry_manager::describe_conflicts($conflicts, $context);
        }
        return $errors;
    }

    /**
     * Ticked group ids of the submitted form.
     *
     * @param array|stdClass $data
     * @return int[]
     */
    protected static function selected_groupids($data): array {
        $data = (array)$data;
        return array_map('intval', array_keys(array_filter((array)($data['groupids'] ?? []))));
    }

    /**
     * Save the entry (or series).
     *
     * @return array result passed back to the JavaScript
     */
    public function process_dynamic_submission() {
        $this->load();
        $context = $this->get_context_for_dynamic_submission();
        $formdata = $this->get_data();
        [$start, $end] = self::get_times($formdata);

        $data = (object)[
            'name' => $formdata->name,
            'contactname' => trim($formdata->contactname),
            'contactemail' => clean_param(trim($formdata->contactemail), PARAM_EMAIL),
            'roomid' => (int)($formdata->roomid ?? 0),
            'allday' => (int)$formdata->allday,
            'timestart' => $start,
            'timeend' => $end,
            'description' => $formdata->description['text'],
            'descriptionformat' => $formdata->description['format'],
            'allgroups' => (int)$formdata->allgroups,
            'categoryid' => (int)($formdata->categoryid ?? 0),
            'groupids' => array_values(array_intersect(self::selected_groupids($formdata),
                array_keys(group_manager::get_editing_groups($this->calendar->id)))),
        ];

        if ($this->entry) {
            entry_manager::update_entry($this->entry, $data, $context);
            return ['ids' => [(int)$this->entry->id]];
        }
        $data->repeat = $formdata->repeat ?? entry_manager::REPEAT_NONE;
        $data->repeatuntil = $formdata->repeatuntil ?? 0;
        return ['ids' => entry_manager::create_entries($this->calendar, $data, $context)];
    }
}
