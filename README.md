# Room calendar (mod_roomcalendar)

An activity module for a group of Moodle users who share a calendar to coordinate their schedules and to book rooms.

## Features

* **Day, week and month views**, each either for **all rooms** (global view) or for a **single room**.
  * The global day view shows one column per room (plus "No room"), like a resource planner.
  * Overlapping events are shown side by side; each room has its own colour.
  * All-day events, and events outside the configured visible hours, are listed above the time grid.
* **Events with optional room booking.** Events without a room are plain schedule entries.
* **Responsible person per event.** Every event requires the name and email address of the person responsible
  for it. This can differ from the Moodle user who enters it, so one user can book on behalf of several people.
  Both are shown to everyone who can view the calendar (with a mailto link); the creating Moodle user is still
  recorded and shown separately.
* **No double booking.** Overlapping bookings of the same room are rejected, including for every occurrence of a
  recurring series. A database lock prevents two concurrent requests from booking the same slot.
* **Recurring events**: daily or weekly until a given date (max. 400 occurrences). Occurrences can be edited
  individually; deleting offers "this event" or "whole series".
* **Event categories** (e.g. lessons, rehearsals), managed by calendar administrators under *Categories* in the
  activity navigation. Each event belongs to at most one category; a **default category** is preselected for new
  events. Deleting a category keeps its events, which then have no category. The category is shown in the event
  details.
* **Category filter** in the calendar toolbar: tick one or more categories and apply. All categories are shown by
  default; the filter is kept in the URL while switching views, dates and rooms.
* **Rooms are managed inside the activity** ("Manage rooms" in the activity navigation): name, description,
  colour, order, and a "bookable" switch (a non-bookable room keeps its existing bookings).
* English and German language strings.

## Permissions

Access is controlled by **permission groups** that are managed inside each calendar activity
(*Permission groups* in the activity navigation):

1. **Groups and members.** The course administrator creates groups (e.g. students, teachers, guests) and sets two
   permissions per group: *View events* and *Add, change and delete events* (which includes viewing). Course
   participants are added individually or all members of a Moodle course group at once. Each user is in at most one
   group; adding a user to another group moves them. A **default group** can be chosen for participants without a
   membership (or "None" to deny them access).
2. **Rooms.** Each room either allows *all groups* or a selection of groups to add and delete events in it.
3. **Events.** When adding an event, the user chooses which groups may change or delete it later (preselected: all
   groups that may edit, or *all groups*, which also covers groups created later).

To change or delete an event, the user's group needs the *add/change/delete* permission **and** must be allowed by
the event's room **and** by the event itself. Viewing is global: a group that may view sees all events in all rooms.

Moodle capabilities only decide who administers the calendar:

| Capability                        | Meaning                                                        | Default roles           |
|-----------------------------------|----------------------------------------------------------------|-------------------------|
| `mod/roomcalendar:view`           | Open the activity (what is visible inside depends on the groups) | Guest, Student, Teacher, Editing teacher, Manager |
| `mod/roomcalendar:managegroups`   | Manage permission groups and event categories; **full access** to all events and rooms | Editing teacher, Manager |
| `mod/roomcalendar:managerooms`    | Add, edit, reorder and delete rooms                            | Editing teacher, Manager |
| `mod/roomcalendar:addinstance`    | Add the activity to a course                                   | Editing teacher, Manager |

When upgrading from version 0.2, existing rooms and events are open to *all groups*. As no groups exist yet,
only administrators can use an existing calendar until groups are created and participants added.

## Installation

1. Copy this folder to `mod/roomcalendar` in the Moodle code base (or install the ZIP via
   *Site administration → Plugins → Install plugins*).
2. Visit *Site administration → Notifications* to complete the installation.

The plugin adds eight database tables of its own and makes no changes to core, other plugins, or site settings.
It has no site-level settings and does not contact external services.

## Compatibility

* Requires Moodle 4.5 (2024100700) or later, PHP 8.1+. Written to remain compatible with Moodle 5.0 and 5.1:
  * Only current, namespaced APIs are used (`\core\url`, `\core\context\*`, `\core\output\*`,
    `\core_external\*`, `\core_form\dynamic_form`, `core/modal`), with no deprecated functions.
  * Layout is done with the plugin's own CSS classes, so it does not depend on Bootstrap 4 vs. 5 utilities
    and does not use Bootstrap's `data-toggle` JavaScript attributes.
* Supports backup/restore (rooms always; events only with user data), course reset, and the Privacy API.
* Events are not mirrored into Moodle's core calendar.

## Development

* `amd/src/calendar.js` is written as a plain AMD module; `amd/build/calendar.min.js` is a copy of it. Running
  `npx grunt amd` in the Moodle root regenerates a minified build.
* PHPUnit tests: `vendor/bin/phpunit --testsuite mod_roomcalendar_testsuite` (or `--filter mod_roomcalendar`).

## License

Copyright © 2026 Michael K.

This plugin is free software: you can redistribute it and/or modify it under the terms of the
GNU General Public License as published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

It is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the
implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public
License for more details.

The full license text is in [COPYING.txt](COPYING.txt); see also <https://www.gnu.org/licenses/>.
This is the same license as Moodle itself (SPDX: `GPL-3.0-or-later`).
