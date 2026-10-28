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
use core_date;
use DateInterval;
use DateTimeImmutable;
use moodle_exception;
use stdClass;

/**
 * Creating, updating, deleting and querying calendar entries, including
 * conflict detection for room bookings and generation of recurring series.
 *
 * @package    mod_roomcalendar
 * @copyright  2026 Michael K.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class entry_manager {

    /** @var string No repetition. */
    public const REPEAT_NONE = '';

    /** @var string Repeat every day. */
    public const REPEAT_DAILY = 'daily';

    /** @var string Repeat every week. */
    public const REPEAT_WEEKLY = 'weekly';

    /** @var int Upper limit of entries created by one series. */
    public const MAX_OCCURRENCES = 400;

    /** @var int Seconds to wait for the booking lock of a room. */
    protected const LOCK_TIMEOUT = 10;

    /**
     * Fetch an entry, ensuring it belongs to the given calendar.
     *
     * @param int $roomcalendarid
     * @param int $entryid
     * @return stdClass
     */
    public static function get_entry(int $roomcalendarid, int $entryid): stdClass {
        global $DB;
        return $DB->get_record('roomcalendar_entries', ['id' => $entryid, 'roomcalendarid' => $roomcalendarid], '*', MUST_EXIST);
    }

    /**
     * All entries overlapping the interval [$from, $to).
     *
     * @param int $roomcalendarid
     * @param int $from
     * @param int $to
     * @param int|null $roomid restrict to a room, null for all entries
     * @return stdClass[]
     */
    public static function get_entries_in_range(int $roomcalendarid, int $from, int $to, ?int $roomid = null): array {
        global $DB;
        $sql = "roomcalendarid = :roomcalendarid AND timestart < :rangeend AND timeend > :rangestart";
        $params = ['roomcalendarid' => $roomcalendarid, 'rangestart' => $from, 'rangeend' => $to];
        if ($roomid !== null) {
            $sql .= " AND roomid = :roomid";
            $params['roomid'] = $roomid;
        }
        return $DB->get_records_select('roomcalendar_entries', $sql, $params, 'timestart ASC, timeend DESC, id ASC');
    }

    /**
     * Expand a (possibly recurring) entry into its occurrences.
     *
     * Repetition happens in the user's timezone, so that a weekly 10:00 meeting
     * stays at 10:00 across daylight saving time changes.
     *
     * @param int $timestart start of the first occurrence
     * @param int $timeend end of the first occurrence
     * @param string $frequency one of the REPEAT_* constants
     * @param int $repeatuntil timestamp of the last day (inclusive) on which an occurrence may start
     * @return array[] list of [timestart, timeend]
     */
    public static function compute_occurrences(int $timestart, int $timeend, string $frequency, int $repeatuntil): array {
        if ($frequency !== self::REPEAT_DAILY && $frequency !== self::REPEAT_WEEKLY) {
            return [[$timestart, $timeend]];
        }
        $tz = core_date::get_user_timezone_object();
        $start = (new DateTimeImmutable('@' . $timestart))->setTimezone($tz);
        $end = (new DateTimeImmutable('@' . $timeend))->setTimezone($tz);
        $limit = (new DateTimeImmutable('@' . $repeatuntil))->setTimezone($tz)->setTime(0, 0)->modify('+1 day');
        $step = new DateInterval($frequency === self::REPEAT_DAILY ? 'P1D' : 'P1W');

        $occurrences = [];
        while ($start < $limit && count($occurrences) <= self::MAX_OCCURRENCES) {
            $occurrences[] = [$start->getTimestamp(), $end->getTimestamp()];
            $start = $start->add($step);
            $end = $end->add($step);
        }
        return $occurrences;
    }

    /**
     * Find bookings of a room that overlap any of the given intervals.
     *
     * @param int $roomcalendarid
     * @param int $roomid
     * @param array[] $intervals list of [timestart, timeend]
     * @param int[] $excludeids entry ids to ignore (the entry being edited)
     * @return array[] list of ['timestart' => int, 'timeend' => int, 'entry' => stdClass] per clashing pair
     */
    public static function find_conflicts(int $roomcalendarid, int $roomid, array $intervals, array $excludeids = []): array {
        if ($roomid <= 0 || empty($intervals)) {
            return [];
        }
        $min = min(array_column($intervals, 0));
        $max = max(array_column($intervals, 1));
        $existing = self::get_entries_in_range($roomcalendarid, $min, $max, $roomid);
        foreach ($excludeids as $id) {
            unset($existing[$id]);
        }
        $conflicts = [];
        foreach ($intervals as [$start, $end]) {
            foreach ($existing as $entry) {
                if ($entry->timestart < $end && $entry->timeend > $start) {
                    $conflicts[] = ['timestart' => $start, 'timeend' => $end, 'entry' => $entry];
                }
            }
        }
        return $conflicts;
    }

    /**
     * Human readable description of booking conflicts.
     *
     * @param array[] $conflicts as returned by find_conflicts()
     * @param context $context
     * @param int $max maximum number of conflicts to list
     * @return string
     */
    public static function describe_conflicts(array $conflicts, context $context, int $max = 3): string {
        $lines = [];
        foreach (array_slice($conflicts, 0, $max) as $conflict) {
            $entry = $conflict['entry'];
            $lines[] = get_string('conflictitem', 'mod_roomcalendar', (object)[
                'name' => format_string($entry->name, true, ['context' => $context]),
                'time' => self::format_timespan($entry->timestart, $entry->timeend, (bool)$entry->allday),
            ]);
        }
        if (count($conflicts) > $max) {
            $lines[] = get_string('conflictmore', 'mod_roomcalendar', count($conflicts) - $max);
        }
        return get_string('errorconflict', 'mod_roomcalendar') . ' ' . implode('; ', $lines);
    }

    /**
     * Create an entry, or a series of entries if a repetition is requested.
     *
     * @param stdClass $calendar roomcalendar record
     * @param stdClass $data name, contactname, contactemail, description, descriptionformat, roomid, timestart,
     *                       timeend, allday, allgroups, groupids (int[], used unless allgroups),
     *                       optional repeat (REPEAT_*) and repeatuntil
     * @param context $context module context
     * @return int[] ids of the created entries
     */
    public static function create_entries(stdClass $calendar, stdClass $data, context $context): array {
        global $DB, $USER;

        $frequency = $data->repeat ?? self::REPEAT_NONE;
        $occurrences = self::compute_occurrences($data->timestart, $data->timeend, $frequency, (int)($data->repeatuntil ?? 0));
        if (count($occurrences) > self::MAX_OCCURRENCES) {
            throw new moodle_exception('errortoomanyoccurrences', 'mod_roomcalendar', '', self::MAX_OCCURRENCES);
        }
        $roomid = (int)($data->roomid ?? 0);
        $categoryid = category_manager::clean_categoryid($calendar->id, (int)($data->categoryid ?? 0));

        $lock = self::get_room_lock($roomid);
        try {
            $conflicts = self::find_conflicts($calendar->id, $roomid, $occurrences);
            if ($conflicts) {
                throw new moodle_exception('errorconflictdetails', 'mod_roomcalendar', '',
                    self::describe_conflicts($conflicts, $context));
            }

            $transaction = $DB->start_delegated_transaction();
            $now = time();
            $seriesid = 0;
            if (count($occurrences) > 1) {
                $seriesid = $DB->insert_record('roomcalendar_series', (object)[
                    'roomcalendarid' => $calendar->id,
                    'frequency' => $frequency,
                    'repeatuntil' => (int)$data->repeatuntil,
                    'userid' => $USER->id,
                    'timecreated' => $now,
                ]);
            }
            $ids = [];
            foreach ($occurrences as [$start, $end]) {
                $entry = (object)[
                    'roomcalendarid' => $calendar->id,
                    'roomid' => $roomid,
                    'seriesid' => $seriesid,
                    'name' => $data->name,
                    'contactname' => $data->contactname,
                    'contactemail' => $data->contactemail,
                    'description' => $data->description ?? '',
                    'descriptionformat' => $data->descriptionformat ?? FORMAT_HTML,
                    'timestart' => $start,
                    'timeend' => $end,
                    'allday' => empty($data->allday) ? 0 : 1,
                    'allgroups' => empty($data->allgroups) ? 0 : 1,
                    'categoryid' => $categoryid,
                    'userid' => $USER->id,
                    'usermodified' => $USER->id,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ];
                $entry->id = $DB->insert_record('roomcalendar_entries', $entry);
                group_manager::set_entry_groups($entry->id, $entry->allgroups ? [] : ($data->groupids ?? []));
                $ids[] = $entry->id;
                \mod_roomcalendar\event\entry_created::create_from_entry($entry, $context)->trigger();
            }
            $transaction->allow_commit();
        } finally {
            if ($lock) {
                $lock->release();
            }
        }
        return $ids;
    }

    /**
     * Update a single entry. Other entries of its series are not changed.
     *
     * @param stdClass $entry existing record
     * @param stdClass $data name, contactname, contactemail, description, descriptionformat, roomid, timestart,
     *                       timeend, allday, allgroups, groupids (int[], used unless allgroups)
     * @param context $context module context
     */
    public static function update_entry(stdClass $entry, stdClass $data, context $context): void {
        global $DB, $USER;
        $roomid = (int)($data->roomid ?? 0);

        $lock = self::get_room_lock($roomid);
        try {
            $conflicts = self::find_conflicts($entry->roomcalendarid, $roomid, [[$data->timestart, $data->timeend]],
                [$entry->id]);
            if ($conflicts) {
                throw new moodle_exception('errorconflictdetails', 'mod_roomcalendar', '',
                    self::describe_conflicts($conflicts, $context));
            }
            $entry->roomid = $roomid;
            $entry->name = $data->name;
            $entry->contactname = $data->contactname;
            $entry->contactemail = $data->contactemail;
            $entry->description = $data->description ?? '';
            $entry->descriptionformat = $data->descriptionformat ?? FORMAT_HTML;
            $entry->timestart = $data->timestart;
            $entry->timeend = $data->timeend;
            $entry->allday = empty($data->allday) ? 0 : 1;
            $entry->allgroups = empty($data->allgroups) ? 0 : 1;
            $entry->categoryid = category_manager::clean_categoryid($entry->roomcalendarid,
                (int)($data->categoryid ?? 0));
            $entry->usermodified = $USER->id;
            $entry->timemodified = time();
            $transaction = $DB->start_delegated_transaction();
            $DB->update_record('roomcalendar_entries', $entry);
            group_manager::set_entry_groups($entry->id, $entry->allgroups ? [] : ($data->groupids ?? []));
            $transaction->allow_commit();
            \mod_roomcalendar\event\entry_updated::create_from_entry($entry, $context)->trigger();
        } finally {
            if ($lock) {
                $lock->release();
            }
        }
    }

    /**
     * Delete an entry, or every entry of its series the current user may edit.
     *
     * @param stdClass $entry
     * @param bool $series delete the whole series
     * @param context $context module context
     * @param permissions $permissions of the current user
     * @return int number of deleted entries
     */
    public static function delete_entry(stdClass $entry, bool $series, context $context, permissions $permissions): int {
        global $DB;
        if ($series && $entry->seriesid) {
            $entries = $DB->get_records('roomcalendar_entries',
                ['roomcalendarid' => $entry->roomcalendarid, 'seriesid' => $entry->seriesid]);
        } else {
            $entries = [$entry->id => $entry];
        }

        $permissions->preload_entries($entries);
        $transaction = $DB->start_delegated_transaction();
        $deleted = 0;
        foreach ($entries as $e) {
            if (!$permissions->can_edit_entry($e)) {
                continue;
            }
            $DB->delete_records('roomcalendar_entrygroups', ['entryid' => $e->id]);
            $DB->delete_records('roomcalendar_entries', ['id' => $e->id]);
            \mod_roomcalendar\event\entry_deleted::create_from_entry($e, $context)->trigger();
            $deleted++;
        }
        if ($entry->seriesid && !$DB->record_exists('roomcalendar_entries', ['seriesid' => $entry->seriesid])) {
            $DB->delete_records('roomcalendar_series', ['id' => $entry->seriesid]);
        }
        $transaction->allow_commit();
        return $deleted;
    }

    /**
     * Format the time span of an entry for display.
     *
     * @param int $timestart
     * @param int $timeend
     * @param bool $allday
     * @return string
     */
    public static function format_timespan(int $timestart, int $timeend, bool $allday): string {
        $datefmt = get_string('strftimedaydate', 'langconfig');
        $timefmt = get_string('strftimetime', 'langconfig');
        if ($allday) {
            // All-day entries end at midnight of the following day.
            $lastday = $timeend - 1;
            $first = userdate($timestart, $datefmt);
            $last = userdate($lastday, $datefmt);
            return $first === $last ? $first : get_string('timespan', 'mod_roomcalendar',
                (object)['start' => $first, 'end' => $last]);
        }
        if (userdate($timestart, '%Y%m%d') === userdate($timeend, '%Y%m%d')) {
            return userdate($timestart, $datefmt) . ', ' . get_string('timespan', 'mod_roomcalendar',
                (object)['start' => userdate($timestart, $timefmt), 'end' => userdate($timeend, $timefmt)]);
        }
        return get_string('timespan', 'mod_roomcalendar', (object)[
            'start' => userdate($timestart, $datefmt) . ', ' . userdate($timestart, $timefmt),
            'end' => userdate($timeend, $datefmt) . ', ' . userdate($timeend, $timefmt),
        ]);
    }

    /**
     * Acquire the booking lock of a room, so that concurrent bookings cannot both pass the conflict check.
     *
     * @param int $roomid
     * @return \core\lock\lock|null null when no room is booked
     */
    protected static function get_room_lock(int $roomid): ?\core\lock\lock {
        if ($roomid <= 0) {
            return null;
        }
        $factory = \core\lock\lock_config::get_lock_factory('mod_roomcalendar_booking');
        $lock = $factory->get_lock('room' . $roomid, self::LOCK_TIMEOUT);
        if (!$lock) {
            throw new moodle_exception('errorlocktimeout', 'mod_roomcalendar');
        }
        return $lock;
    }
}
