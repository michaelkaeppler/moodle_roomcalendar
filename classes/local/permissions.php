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

namespace mod_roomcalendar\local;

use core\context;
use stdClass;

/**
 * Access decisions of a room calendar for one user.
 *
 * Rules:
 * - Users with mod/roomcalendar:managegroups are administrators and may do everything.
 * - Every other user acts with the permissions of their permission group (an explicit membership, otherwise
 *   the calendar's default group). Without a group they have no access.
 * - Viewing needs the group's "view" permission (and the Moodle capability mod/roomcalendar:view).
 * - Adding an event needs the group's "add/change/delete" permission and, if a room is booked, the room must
 *   allow the group.
 * - Changing or deleting an event needs the group's "add/change/delete" permission, the event's room (if any)
 *   must allow the group, and the event itself must allow the group.
 *
 * @package    mod_roomcalendar
 * @copyright  2026 Michael K.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class permissions {

    /** @var permissions[] instances per calendar and user */
    protected static $instances = [];

    /** @var stdClass */
    protected $calendar;

    /** @var context */
    protected $context;

    /** @var int */
    protected $userid;

    /** @var bool */
    protected $isadmin;

    /** @var stdClass|null the effective permission group */
    protected $group = null;

    /** @var bool whether the group comes from the calendar default instead of a membership */
    protected $isdefaultgroup = false;

    /** @var array|null roomid => group ids */
    protected $roomgroups = null;

    /** @var stdClass[]|null rooms of the calendar */
    protected $rooms = null;

    /** @var array entryid => group ids, filled on demand */
    protected $entrygroups = [];

    /**
     * Constructor.
     *
     * @param stdClass $calendar roomcalendar record
     * @param context $context module context
     * @param int|null $userid defaults to the current user
     */
    public function __construct(stdClass $calendar, context $context, ?int $userid = null) {
        global $USER;
        $this->calendar = $calendar;
        $this->context = $context;
        $this->userid = $userid ?? (int)$USER->id;
        $this->isadmin = has_capability('mod/roomcalendar:managegroups', $context, $this->userid);

        $groupid = group_manager::get_user_groupid($calendar->id, $this->userid);
        if (!$groupid && !empty($calendar->defaultgroupid)) {
            $groupid = (int)$calendar->defaultgroupid;
            $this->isdefaultgroup = true;
        }
        if ($groupid) {
            $this->group = group_manager::get_group($calendar->id, $groupid, IGNORE_MISSING) ?: null;
        }
    }

    /**
     * Shared instance for the current user, so that lookups are cached within a request.
     *
     * @param stdClass $calendar
     * @param context $context
     * @return self
     */
    public static function for_current_user(stdClass $calendar, context $context): self {
        global $USER;
        $key = $calendar->id . ':' . $USER->id;
        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new self($calendar, $context);
        }
        return self::$instances[$key];
    }

    /**
     * Forget cached instances, e.g. after permissions changed.
     */
    public static function reset_caches(): void {
        self::$instances = [];
    }

    /**
     * Whether the user is an administrator of the calendar.
     *
     * @return bool
     */
    public function is_admin(): bool {
        return $this->isadmin;
    }

    /**
     * The effective permission group.
     *
     * @return stdClass|null
     */
    public function get_group(): ?stdClass {
        return $this->group;
    }

    /**
     * Whether the effective group is the calendar's default group.
     *
     * @return bool
     */
    public function is_default_group(): bool {
        return $this->isdefaultgroup;
    }

    /**
     * May the user see the calendar and its events?
     *
     * @return bool
     */
    public function can_view(): bool {
        if (!has_capability('mod/roomcalendar:view', $this->context, $this->userid)) {
            return false;
        }
        return $this->isadmin || ($this->group && $this->group->canview);
    }

    /**
     * Throw an exception unless the user may view the calendar.
     */
    public function require_view(): void {
        require_capability('mod/roomcalendar:view', $this->context, $this->userid);
        if (!$this->can_view()) {
            throw new \moodle_exception('errornoaccess', 'mod_roomcalendar');
        }
    }

    /**
     * Base permission to add, change and delete events.
     *
     * @return bool
     */
    public function can_add(): bool {
        return $this->isadmin || ($this->can_view() && $this->group && $this->group->canedit);
    }

    /**
     * May the user add events to, or change and delete events in, this room?
     *
     * @param stdClass|null $room null for events without a room
     * @return bool
     */
    public function can_use_room(?stdClass $room): bool {
        if ($this->isadmin) {
            return true;
        }
        if (!$this->can_add()) {
            return false;
        }
        if ($room === null || !empty($room->allgroups)) {
            return true;
        }
        if ($this->roomgroups === null) {
            $this->roomgroups = group_manager::get_room_groupids($this->calendar->id);
        }
        return in_array((int)$this->group->id, $this->roomgroups[(int)$room->id] ?? [], true);
    }

    /**
     * Load the allowed groups of many entries at once, to avoid one query per entry.
     *
     * @param stdClass[] $entries
     */
    public function preload_entries(array $entries): void {
        $missing = [];
        foreach ($entries as $entry) {
            if (!array_key_exists((int)$entry->id, $this->entrygroups)) {
                $missing[] = (int)$entry->id;
            }
        }
        $loaded = group_manager::get_entry_groupids($missing);
        foreach ($missing as $id) {
            $this->entrygroups[$id] = $loaded[$id] ?? [];
        }
    }

    /**
     * May the user change and delete this entry?
     *
     * @param stdClass $entry
     * @return bool
     */
    public function can_edit_entry(stdClass $entry): bool {
        if ($this->isadmin) {
            return true;
        }
        if ($this->rooms === null) {
            $this->rooms = room_manager::get_rooms($this->calendar->id);
        }
        $room = $entry->roomid ? ($this->rooms[(int)$entry->roomid] ?? null) : null;
        if (!$this->can_use_room($room)) {
            return false;
        }
        if (!empty($entry->allgroups)) {
            return true;
        }
        $this->preload_entries([$entry]);
        return in_array((int)$this->group->id, $this->entrygroups[(int)$entry->id], true);
    }
}
