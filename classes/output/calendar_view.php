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

namespace mod_roomcalendar\output;

use core\context;
use core\output\named_templatable;
use core\output\renderable;
use core\output\renderer_base;
use core\url;
use core_date;
use DateTimeImmutable;
use mod_roomcalendar\local\entry_manager;
use mod_roomcalendar\local\permissions;
use mod_roomcalendar\local\room_manager;
use stdClass;

/**
 * Day, week and month view of a room calendar, for all rooms or a single room.
 *
 * @package    mod_roomcalendar
 * @copyright  2026 Michael K.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class calendar_view implements renderable, named_templatable {

    /** @var string[] Supported views. */
    public const VIEWS = ['day', 'week', 'month'];

    /** @var int Number of entries listed per day in the month view before "more" is shown. */
    protected const MONTH_MAX_ENTRIES = 3;

    /** @var int Minutes per clickable slot in the day and week grid. */
    protected const SLOT_MINUTES = 30;

    /** @var \cm_info|stdClass course module */
    protected $cm;

    /** @var stdClass roomcalendar record */
    protected $calendar;

    /** @var context module context */
    protected $context;

    /** @var string day, week or month */
    protected $view;

    /** @var DateTimeImmutable selected day, midnight in the user's timezone */
    protected $date;

    /** @var int selected room, 0 for all rooms */
    protected $roomid;

    /** @var stdClass[] all rooms of the calendar */
    protected $rooms;

    /** @var stdClass[] all event categories of the calendar */
    protected $categories;

    /** @var int[] categories hidden by the category filter, 0 for entries without a category */
    protected $hiddencats = [];

    /** @var bool whether the calendar has entries without a category */
    protected $hasuncategorised = false;

    /** @var permissions of the current user */
    protected $permissions;

    /** @var bool base permission to add events */
    protected $canadd;

    /** @var bool whether chips omit the room name because every column is a room */
    protected $hideroom = false;

    /** @var array entry details for the JavaScript, keyed by entry id */
    protected $details = [];

    /**
     * Constructor.
     *
     * @param \cm_info|stdClass $cm
     * @param stdClass $calendar
     * @param context $context
     * @param string $view
     * @param string $date Y-m-d, empty for today
     * @param int $roomid
     * @param string $hidecats comma separated ids of the categories to hide, 0 for entries without a category
     */
    public function __construct(\cm_info|stdClass $cm, stdClass $calendar, context $context, string $view,
            string $date, int $roomid, string $hidecats = '') {
        global $DB;
        $this->cm = $cm;
        $this->calendar = $calendar;
        $this->context = $context;
        $this->view = in_array($view, self::VIEWS, true) ? $view : $calendar->defaultview;
        $this->rooms = room_manager::get_rooms($calendar->id);
        $this->categories = \mod_roomcalendar\local\category_manager::get_categories($calendar->id);
        $this->roomid = isset($this->rooms[$roomid]) ? $roomid : 0;
        if ($this->categories) {
            $this->hasuncategorised = $DB->record_exists('roomcalendar_entries',
                ['roomcalendarid' => $calendar->id, 'categoryid' => 0]);
            $hidden = array_unique(array_map('intval', array_filter(explode(',', $hidecats), 'is_numeric')));
            $this->hiddencats = array_values(array_filter($hidden,
                fn($c) => isset($this->categories[$c]) || ($c === 0 && $this->hasuncategorised)));
            sort($this->hiddencats);
        }
        $this->permissions = permissions::for_current_user($calendar, $context);
        $this->canadd = $this->permissions->can_add();

        $tz = core_date::get_user_timezone_object();
        $parsed = false;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $tz);
        }
        $this->date = $parsed ?: (new DateTimeImmutable('now', $tz))->setTime(0, 0);
    }

    /**
     * URL of this calendar with modified parameters.
     *
     * @param array $params view, date (DateTimeImmutable), room and/or hidecats (int[])
     * @return url
     */
    protected function url(array $params = []): url {
        $date = $params['date'] ?? $this->date;
        $urlparams = [
            'id' => $this->cm->id,
            'view' => $params['view'] ?? $this->view,
            'date' => $date->format('Y-m-d'),
            'room' => $params['room'] ?? $this->roomid,
        ];
        $hidecats = $params['hidecats'] ?? $this->hiddencats;
        if ($hidecats) {
            $urlparams['hidecats'] = implode(',', $hidecats);
        }
        return new url('/mod/roomcalendar/view.php', $urlparams);
    }

    /**
     * Data of the category filter, null when the calendar has no categories.
     *
     * @return array|null
     */
    protected function export_category_filter(): ?array {
        if (!$this->categories) {
            return null;
        }
        $options = [];
        foreach ($this->categories as $category) {
            $options[] = [
                'id' => (int)$category->id,
                'name' => format_string($category->name, true, ['context' => $this->context]),
                'checked' => !in_array((int)$category->id, $this->hiddencats, true),
            ];
        }
        if ($this->hasuncategorised) {
            $options[] = [
                'id' => 0,
                'name' => get_string('withoutcategory', 'mod_roomcalendar'),
                'checked' => !in_array(0, $this->hiddencats, true),
            ];
        }
        $total = count($options);
        $shown = $total - count($this->hiddencats);
        return [
            'label' => $shown === $total ? get_string('allcategories', 'mod_roomcalendar')
                : get_string('categoriesshown', 'mod_roomcalendar', (object)['shown' => $shown, 'total' => $total]),
            'active' => $shown !== $total,
            'baseurl' => $this->url(['hidecats' => []])->out(false),
            'options' => $options,
        ];
    }

    /**
     * First day of the week containing the given day.
     *
     * @param DateTimeImmutable $day
     * @return DateTimeImmutable
     */
    protected static function week_start(DateTimeImmutable $day): DateTimeImmutable {
        $startwday = \core_calendar\type_factory::get_calendar_instance()->get_starting_weekday();
        $offset = ((int)$day->format('w') - $startwday + 7) % 7;
        return $day->modify("-{$offset} days");
    }

    /**
     * Template used to render this view.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'mod_roomcalendar/calendar';
    }

    /**
     * Export data for the calendar template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $data = [
            'cmid' => $this->cm->id,
            'roomid' => $this->roomid,
            'canadd' => $this->canadd,
            'canaddhere' => $this->slot_roomid($this->selected_room()) >= 0,
            'addroomid' => max(0, $this->slot_roomid($this->selected_room())),
            'todayurl' => $this->url(['date' => new DateTimeImmutable('now', $this->date->getTimezone())])->out(false),
            'views' => [],
            'roomoptions' => [],
            'legend' => [],
            'categoryfilter' => $this->export_category_filter(),
        ];

        foreach (self::VIEWS as $view) {
            $data['views'][] = [
                'label' => get_string('view' . $view, 'mod_roomcalendar'),
                'url' => $this->url(['view' => $view])->out(false),
                'active' => $view === $this->view,
            ];
        }

        $data['roomoptions'][] = [
            'value' => $this->url(['room' => 0])->out(false),
            'label' => get_string('allrooms', 'mod_roomcalendar'),
            'selected' => $this->roomid === 0,
        ];
        foreach ($this->rooms as $room) {
            $name = format_string($room->name, true, ['context' => $this->context]);
            $data['roomoptions'][] = [
                'value' => $this->url(['room' => $room->id])->out(false),
                'label' => $name,
                'selected' => (int)$room->id === $this->roomid,
            ];
            $data['legend'][] = [
                'name' => $name,
                'color' => $room->color,
                'notbookable' => !$room->bookable,
                'url' => $this->url(['room' => $room->id])->out(false),
            ];
        }
        $data['hasrooms'] = !empty($this->rooms);
        if ($this->roomid) {
            $room = $this->rooms[$this->roomid];
            $data['selectedroom'] = [
                'name' => format_string($room->name, true, ['context' => $this->context]),
                'description' => format_text($room->description, $room->descriptionformat, ['context' => $this->context]),
                'color' => $room->color,
                'notbookable' => !$room->bookable,
            ];
        }
        if (has_capability('mod/roomcalendar:managegroups', $this->context)) {
            $data['managegroupsurl'] = (new url('/mod/roomcalendar/groups.php', ['id' => $this->cm->id]))->out(false);
        }
        if ($this->permissions->is_admin()) {
            $data['mygroup'] = get_string('youareadmin', 'mod_roomcalendar');
        } else if ($group = $this->permissions->get_group()) {
            $data['mygroup'] = get_string('yourgroup', 'mod_roomcalendar',
                format_string($group->name, true, ['context' => $this->context]));
        }
        if (has_capability('mod/roomcalendar:managerooms', $this->context)) {
            $data['manageroomsurl'] = (new url('/mod/roomcalendar/rooms.php', ['id' => $this->cm->id]))->out(false);
        }

        switch ($this->view) {
            case 'month':
                $data += $this->export_month();
                break;
            case 'week':
                $data += $this->export_week();
                break;
            default:
                $data += $this->export_day();
        }
        $data['entriesjson'] = json_encode((object)$this->details);
        return $data;
    }

    /**
     * Load entries for a range, honouring the room filter.
     *
     * @param DateTimeImmutable $from
     * @param DateTimeImmutable $to
     * @return stdClass[]
     */
    protected function load_entries(DateTimeImmutable $from, DateTimeImmutable $to): array {
        $entries = entry_manager::get_entries_in_range($this->calendar->id, $from->getTimestamp(), $to->getTimestamp(),
            $this->roomid ?: null);
        if ($this->hiddencats) {
            $entries = array_filter($entries, fn($e) => !in_array((int)$e->categoryid, $this->hiddencats, true));
        }
        $this->add_details($entries);
        return $entries;
    }

    /**
     * Collect the details shown in the entry popup.
     *
     * @param stdClass[] $entries
     */
    protected function add_details(array $entries): void {
        global $DB;
        $userids = array_unique(array_column($entries, 'userid'));
        $users = [];
        if ($userids) {
            $fields = \core_user\fields::for_name()->get_sql('', false, '', '', false)->selects;
            $users = $DB->get_records_list('user', 'id', $userids, '', 'id, ' . $fields);
        }
        $this->permissions->preload_entries($entries);
        foreach ($entries as $entry) {
            $room = $this->rooms[$entry->roomid] ?? null;
            $category = $this->categories[$entry->categoryid] ?? null;
            $this->details[$entry->id] = [
                'category' => $category ? format_string($category->name, true, ['context' => $this->context]) : '',
                'id' => (int)$entry->id,
                'name' => format_string($entry->name, true, ['context' => $this->context]),
                'time' => entry_manager::format_timespan($entry->timestart, $entry->timeend, (bool)$entry->allday),
                'room' => $room ? format_string($room->name, true, ['context' => $this->context]) : '',
                'color' => $room ? $room->color : room_manager::NOROOM_COLOR,
                'description' => format_text($entry->description, $entry->descriptionformat, ['context' => $this->context]),
                'contactname' => $entry->contactname,
                'contactemail' => $entry->contactemail,
                'creator' => isset($users[$entry->userid]) ? fullname($users[$entry->userid]) : '',
                'canedit' => $this->permissions->can_edit_entry($entry),
                'inseries' => !empty($entry->seriesid),
                'dayurl' => $this->url(['view' => 'day', 'date' => $this->local_day($entry->timestart)])->out(false),
            ];
        }
    }

    /**
     * The room selected by the room filter.
     *
     * @return stdClass|null
     */
    protected function selected_room(): ?stdClass {
        return $this->roomid ? $this->rooms[$this->roomid] : null;
    }

    /**
     * Room to preselect when the user clicks a free slot.
     *
     * @param stdClass|null $room the room of the column or filter, null for "no room" or "any room"
     * @return int room id, 0 for no room, -1 when the user may not add events there
     */
    protected function slot_roomid(?stdClass $room): int {
        if (!$this->canadd) {
            return -1;
        }
        if ($room === null) {
            return 0;
        }
        return ($room->bookable && $this->permissions->can_use_room($room)) ? (int)$room->id : -1;
    }

    /**
     * Midnight of the day containing a timestamp, in the user's timezone.
     *
     * @param int $timestamp
     * @return DateTimeImmutable
     */
    protected function local_day(int $timestamp): DateTimeImmutable {
        return (new DateTimeImmutable('@' . $timestamp))->setTimezone($this->date->getTimezone())->setTime(0, 0);
    }

    /**
     * Compact representation of an entry used inside a calendar cell.
     *
     * @param stdClass $entry
     * @param bool $short show only the start time (month view)
     * @return array
     */
    protected function chip(stdClass $entry, bool $short = false): array {
        $room = $this->rooms[$entry->roomid] ?? null;
        $timefmt = get_string('strftimetime', 'langconfig');
        if ($entry->allday) {
            $time = get_string('allday', 'mod_roomcalendar');
        } else if ($short) {
            $time = userdate($entry->timestart, $timefmt);
        } else {
            $time = userdate($entry->timestart, $timefmt) . '–' . userdate($entry->timeend, $timefmt);
        }
        $showroom = $room && !$this->roomid && !$this->hideroom;
        // Show the responsible person below the title, unless the title already is that name.
        $contact = trim((string)($entry->contactname ?? ''));
        if (\core_text::strtolower($contact) === \core_text::strtolower(trim($entry->name))) {
            $contact = '';
        }
        return [
            'id' => (int)$entry->id,
            'name' => format_string($entry->name, true, ['context' => $this->context]),
            'contact' => $contact,
            'time' => $time,
            'room' => $showroom ? format_string($room->name, true, ['context' => $this->context]) : '',
            'color' => $room ? $room->color : room_manager::NOROOM_COLOR,
        ];
    }

    /**
     * Sort callback: all-day entries first, then by start time.
     *
     * @param stdClass $a
     * @param stdClass $b
     * @return int
     */
    protected static function compare_entries(stdClass $a, stdClass $b): int {
        return [$b->allday, $a->timestart, $a->id] <=> [$a->allday, $b->timestart, $b->id];
    }

    /**
     * Data for the month view.
     *
     * @return array
     */
    protected function export_month(): array {
        $first = $this->date->modify('first day of this month');
        $nextmonth = $first->modify('+1 month');
        $gridstart = self::week_start($first);
        $gridend = self::week_start($nextmonth->modify('-1 day'))->modify('+7 days');
        $entries = $this->load_entries($gridstart, $gridend);
        usort($entries, [self::class, 'compare_entries']);
        $today = $this->local_day(time());

        $weekdays = [];
        for ($i = 0, $d = $gridstart; $i < 7; $i++, $d = $d->modify('+1 day')) {
            $weekdays[] = ['name' => userdate($d->getTimestamp(), '%a')];
        }

        $weeks = [];
        $day = $gridstart;
        while ($day < $gridend) {
            $week = ['days' => []];
            for ($i = 0; $i < 7; $i++) {
                $next = $day->modify('+1 day');
                $dayentries = array_values(array_filter($entries, function($e) use ($day, $next) {
                    return $e->timestart < $next->getTimestamp() && $e->timeend > $day->getTimestamp();
                }));
                $shown = array_slice($dayentries, 0, self::MONTH_MAX_ENTRIES);
                $more = count($dayentries) - count($shown);
                $week['days'][] = [
                    'daynum' => (int)$day->format('j'),
                    'label' => userdate($day->getTimestamp(), get_string('strftimedaydate', 'langconfig')),
                    'dayurl' => $this->url(['view' => 'day', 'date' => $day])->out(false),
                    'istoday' => $day == $today,
                    'othermonth' => $day < $first || $day >= $nextmonth,
                    'addstart' => $day->setTime(9, 0)->getTimestamp(),
                    'entries' => array_map(fn($e) => $this->chip($e, true), $shown),
                    'more' => $more > 0 ? get_string('more', 'mod_roomcalendar', $more) : '',
                ];
                $day = $next;
            }
            $weeks[] = $week;
        }

        return [
            'ismonth' => true,
            'title' => userdate($first->getTimestamp(), get_string('strftimemonthyear', 'langconfig')),
            'prevurl' => $this->url(['date' => $first->modify('-1 month')])->out(false),
            'nexturl' => $this->url(['date' => $nextmonth])->out(false),
            'month' => ['weekdays' => $weekdays, 'weeks' => $weeks],
        ];
    }

    /**
     * Data for the week view: one column per day.
     *
     * @return array
     */
    protected function export_week(): array {
        $start = self::week_start($this->date);
        $end = $start->modify('+7 days');
        $entries = $this->load_entries($start, $end);
        $today = $this->local_day(time());

        $columns = [];
        for ($day = $start; $day < $end; $day = $day->modify('+1 day')) {
            $columns[] = $this->grid_column($day, $entries, [
                'title' => userdate($day->getTimestamp(), '%a'),
                'subtitle' => userdate($day->getTimestamp(), get_string('strftimedateshort', 'langconfig')),
                'url' => $this->url(['view' => 'day', 'date' => $day])->out(false),
                'istoday' => $day == $today,
            ], $this->slot_roomid($this->selected_room()));
        }

        $last = $end->modify('-1 day');
        $title = get_string('timespan', 'mod_roomcalendar', (object)[
            'start' => userdate($start->getTimestamp(), get_string('strftimedateshort', 'langconfig')),
            'end' => userdate($last->getTimestamp(), get_string('strftimedate', 'langconfig')),
        ]);
        return [
            'isgrid' => true,
            'title' => $title,
            'prevurl' => $this->url(['date' => $start->modify('-7 days')])->out(false),
            'nexturl' => $this->url(['date' => $end])->out(false),
            'grid' => $this->grid($start, $columns),
        ];
    }

    /**
     * Data for the day view: one column per room, or a single column when a room is selected.
     *
     * @return array
     */
    protected function export_day(): array {
        $day = $this->date;
        $next = $day->modify('+1 day');
        $entries = $this->load_entries($day, $next);

        $columns = [];
        if ($this->roomid || empty($this->rooms)) {
            $columns[] = $this->grid_column($day, $entries, [
                'title' => $this->roomid ? format_string($this->rooms[$this->roomid]->name, true, ['context' => $this->context])
                    : get_string('entries', 'mod_roomcalendar'),
            ], $this->slot_roomid($this->selected_room()));
        } else {
            $this->hideroom = true;
            foreach ($this->rooms as $room) {
                $roomentries = array_filter($entries, fn($e) => (int)$e->roomid === (int)$room->id);
                $columns[] = $this->grid_column($day, $roomentries, [
                    'title' => format_string($room->name, true, ['context' => $this->context]),
                    'url' => $this->url(['room' => $room->id])->out(false),
                    'color' => $room->color,
                ], $this->slot_roomid($room));
            }
            $columns[] = $this->grid_column($day, array_filter($entries, fn($e) => (int)$e->roomid === 0), [
                'title' => get_string('noroom', 'mod_roomcalendar'),
                'color' => room_manager::NOROOM_COLOR,
            ], $this->slot_roomid(null));
        }

        return [
            'isgrid' => true,
            'isday' => true,
            'title' => userdate($day->getTimestamp(), get_string('strftimedaydate', 'langconfig')),
            'prevurl' => $this->url(['date' => $day->modify('-1 day')])->out(false),
            'nexturl' => $this->url(['date' => $next])->out(false),
            'grid' => $this->grid($day, $columns),
        ];
    }

    /**
     * Common data of the day/week time grid.
     *
     * @param DateTimeImmutable $day any day of the grid, used for the hour labels
     * @param array $columns
     * @return array
     */
    protected function grid(DateTimeImmutable $day, array $columns): array {
        $hours = [];
        $timefmt = get_string('strftimetime', 'langconfig');
        for ($h = (int)$this->calendar->firsthour; $h < (int)$this->calendar->lasthour; $h++) {
            $hours[] = ['label' => userdate($day->setTime($h, 0)->getTimestamp(), $timefmt)];
        }
        $hasallday = false;
        foreach ($columns as $column) {
            $hasallday = $hasallday || !empty($column['alldayentries']);
        }
        return [
            'hours' => $hours,
            'hourcount' => count($hours),
            'columns' => $columns,
            'columncount' => count($columns),
            'hasallday' => $hasallday,
        ];
    }

    /**
     * One column of the time grid.
     *
     * @param DateTimeImmutable $day
     * @param stdClass[] $entries candidate entries, filtered to the day here
     * @param array $header title, subtitle, url, istoday, color
     * @param int $slotroomid room preselected when clicking a free slot, -1 to disable adding
     * @return array
     */
    protected function grid_column(DateTimeImmutable $day, array $entries, array $header, int $slotroomid): array {
        $daystart = $day->getTimestamp();
        $dayend = $day->modify('+1 day')->getTimestamp();
        $gridstart = $day->setTime((int)$this->calendar->firsthour, 0)->getTimestamp();
        $lasthour = (int)$this->calendar->lasthour;
        $gridend = $lasthour >= 24 ? $dayend : $day->setTime($lasthour, 0)->getTimestamp();
        $duration = max(1, $gridend - $gridstart);

        $allday = [];
        $timed = [];
        foreach ($entries as $entry) {
            if ($entry->timestart >= $dayend || $entry->timeend <= $daystart) {
                continue;
            }
            if ($entry->allday || $entry->timeend <= $gridstart || $entry->timestart >= $gridend) {
                // All-day entries, and entries outside the visible hours, are listed above the grid.
                $allday[] = $entry;
                continue;
            }
            $timed[] = (object)[
                'entry' => $entry,
                'start' => max($entry->timestart, $gridstart),
                'end' => min($entry->timeend, $gridend),
            ];
        }
        usort($allday, [self::class, 'compare_entries']);
        usort($timed, fn($a, $b) => [$a->start, $b->end] <=> [$b->start, $a->end]);
        self::assign_lanes($timed);

        $items = [];
        foreach ($timed as $item) {
            $chip = $this->chip($item->entry);
            $chip['style'] = sprintf('top:%.3f%%;height:%.3f%%;left:%.3f%%;width:%.3f%%;',
                ($item->start - $gridstart) / $duration * 100,
                ($item->end - $item->start) / $duration * 100,
                $item->lane / $item->lanes * 100,
                100 / $item->lanes);
            $chip['continuesbefore'] = $item->entry->timestart < $item->start;
            $chip['continuesafter'] = $item->entry->timeend > $item->end;
            $items[] = $chip;
        }

        $slots = [];
        if ($slotroomid >= 0) {
            for ($t = $gridstart; $t < $gridend; $t += self::SLOT_MINUTES * MINSECS) {
                $slots[] = ['start' => $t, 'label' => userdate($t, get_string('strftimedaydatetime', 'langconfig'))];
            }
        }

        return $header + [
            'alldayentries' => array_map([$this, 'chip'], $allday),
            'entries' => $items,
            'slots' => $slots,
            'slotroomid' => max(0, $slotroomid),
            'daystart' => $daystart,
            'canaddallday' => $slotroomid >= 0,
        ];
    }

    /**
     * Assign overlapping entries to side by side lanes.
     *
     * Sets ->lane (0-based) and ->lanes (number of lanes in the cluster of overlapping entries) on each item.
     *
     * @param stdClass[] $items sorted by start, with ->start and ->end
     */
    protected static function assign_lanes(array $items): void {
        $cluster = [];
        $laneends = [];
        $clusterend = 0;
        $finish = function() use (&$cluster, &$laneends) {
            foreach ($cluster as $c) {
                $c->lanes = count($laneends);
            }
            $cluster = [];
            $laneends = [];
        };
        foreach ($items as $item) {
            if ($cluster && $item->start >= $clusterend) {
                $finish();
            }
            $lane = null;
            foreach ($laneends as $i => $laneend) {
                if ($laneend <= $item->start) {
                    $lane = $i;
                    break;
                }
            }
            if ($lane === null) {
                $lane = count($laneends);
            }
            $laneends[$lane] = $item->end;
            $item->lane = $lane;
            $cluster[] = $item;
            $clusterend = count($cluster) === 1 ? $item->end : max($clusterend, $item->end);
        }
        if ($cluster) {
            $finish();
        }
    }
}
