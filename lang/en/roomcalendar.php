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
 * English strings for mod_roomcalendar.
 *
 * @package    mod_roomcalendar
 * @copyright  2026 Michael K.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addalldayentry'] = 'Add an all-day event';
$string['addcategory'] = 'Add category';
$string['addcoursegroup'] = 'Add all members of a course group';
$string['addcoursegroup_help'] = 'Adds every member of the selected Moodle course group to this permission group. Users who are in another permission group are moved here.';
$string['addentry'] = 'New event';
$string['addentryon'] = 'Add an event on {$a}';
$string['addgroup'] = 'Add permission group';
$string['addmembers'] = 'Add to group';
$string['addroom'] = 'Add room';
$string['addusers'] = 'Add participants';
$string['addusers_help'] = 'Search for course participants by name. Users who already belong to another permission group of this calendar are moved to this group, because each user can only be in one group.';
$string['allcategories'] = 'All categories';
$string['allday'] = 'All day';
$string['allgroups'] = 'All groups';
$string['allrooms'] = 'All rooms';
$string['applyfilter'] = 'Apply';
$string['backtocalendar'] = 'Back to calendar';
$string['backtogroups'] = 'Back to permission groups';
$string['bookings'] = 'Bookings';
$string['categoriesshown'] = 'Categories: {$a->shown} of {$a->total}';
$string['category'] = 'Category';
$string['categorydeleted'] = 'The category has been deleted.';
$string['categoryfilter'] = 'Filter by category';
$string['categoryname'] = 'Category name';
$string['categorysaved'] = 'The category has been saved.';
$string['color_blue'] = 'Blue';
$string['color_gray'] = 'Grey';
$string['color_green'] = 'Green';
$string['color_orange'] = 'Orange';
$string['color_pink'] = 'Pink';
$string['color_purple'] = 'Purple';
$string['color_red'] = 'Red';
$string['color_teal'] = 'Teal';
$string['color_yellow'] = 'Yellow';
$string['conflictitem'] = '"{$a->name}" ({$a->time})';
$string['conflictmore'] = 'and {$a} more';
$string['contactemail'] = 'Email of responsible person';
$string['contactname'] = 'Responsible person';
$string['contactname_help'] = 'The person responsible for this event, who is not necessarily the person entering it. Their name and email address are shown to everyone who can view the calendar.';
$string['contactperson'] = 'Responsible';
$string['createdby'] = 'Created by';
$string['currentlyin'] = 'currently in "{$a}"';
$string['defaultcategory'] = 'Default category';
$string['defaultcategory_help'] = 'This category is preselected when someone adds a new event. It can be changed in the event form.';
$string['defaultcategorysaved'] = 'The default category has been saved.';
$string['defaultgroup'] = 'Default group';
$string['defaultgroup_help'] = 'Course participants who are not a member of any permission group get the permissions of this group. Choose "None" to deny them access.';
$string['defaultgroupnone'] = 'None (no access)';
$string['defaultgroupsaved'] = 'The default group has been saved.';
$string['defaultview'] = 'Default view';
$string['deletecategory'] = 'Delete category';
$string['deletecategoryconfirm'] = 'Do you really want to delete the category "{$a->name}"? Its {$a->count} event(s) are kept, but no longer have a category.';
$string['deleteentry'] = 'Delete event';
$string['deleteentryconfirm'] = 'Do you really want to delete the event "{$a}"?';
$string['deletegroup'] = 'Delete permission group';
$string['deletegroupconfirm'] = 'Do you really want to delete the permission group "{$a->name}"? Its {$a->count} member(s) will lose the group\'s permissions, and the group is removed from all rooms and events.';
$string['deleteroom'] = 'Delete room';
$string['deleteroomconfirm'] = 'Do you really want to delete the room "{$a->name}"? Its {$a->count} booking(s) will be deleted as well. To keep past bookings, edit the room and make it not bookable instead.';
$string['deleteseries'] = 'Delete series';
$string['deleteseriesconfirm'] = 'Do you really want to delete all events of the series "{$a}" that you are allowed to delete?';
$string['displaysettings'] = 'Calendar display';
$string['editcategory'] = 'Edit category';
$string['editentry'] = 'Edit event';
$string['editgroup'] = 'Edit permission group';
$string['editroom'] = 'Edit room';
$string['editseriesnote'] = 'This event is part of a recurring series. Changes only apply to this single event.';
$string['enddate'] = 'Last day';
$string['entries'] = 'Events';
$string['entryallgroups'] = 'All groups may change and delete this event';
$string['entryallgroups_help'] = 'If checked, every permission group that is allowed to change events (and allowed in the event\'s room) may change and delete this event, including groups created later. Otherwise only the selected groups may.';
$string['entrygroups'] = 'Groups that may change and delete this event';
$string['entryname'] = 'Title';
$string['entrypermissions'] = 'Permissions';
$string['errorconflict'] = 'The room is already booked at this time:';
$string['errorconflictdetails'] = '{$a}';
$string['errorendbeforestart'] = 'The end must be after the start.';
$string['errorlasthour'] = 'The last hour must be later than the first hour.';
$string['errorlocktimeout'] = 'The room is being booked by someone else right now. Please try again.';
$string['errornoaccess'] = 'You do not have access to this calendar. Please ask the course administrator to add you to a permission group.';
$string['errornogroups'] = 'Select at least one group, or allow all groups.';
$string['errornopermission'] = 'You are not allowed to do this.';
$string['errorrepeatuntil'] = 'The repetition must not end before the event starts.';
$string['errorroomnotallowed'] = 'Your permission group may not add or delete events in this room.';
$string['errorroomnotbookable'] = 'This room cannot be booked.';
$string['errortoomanyoccurrences'] = 'A series can contain at most {$a} events. Please choose an earlier end date.';
$string['evententrycreated'] = 'Calendar event created';
$string['evententrydeleted'] = 'Calendar event deleted';
$string['evententryupdated'] = 'Calendar event updated';
$string['firsthour'] = 'First hour shown';
$string['firsthour_help'] = 'The day and week views show the hours between the first and the last hour. Events outside these hours are listed in the row above the time grid.';
$string['groupcanedit'] = 'Add, change and delete events';
$string['groupcanedit_help'] = 'Members may add events and change or delete events. Whether they may do so for a particular room or event additionally depends on the permissions of that room and event. This permission includes viewing.';
$string['groupcannotedit'] = '(cannot add/change events)';
$string['groupcanview'] = 'View events';
$string['groupdeleted'] = 'The permission group has been deleted.';
$string['groupname'] = 'Group name';
$string['grouppermissions'] = 'Permissions of this group: {$a}';
$string['groupsaved'] = 'The permission group has been saved.';
$string['isdefaultcategory'] = 'Default category';
$string['isdefaultgroup'] = 'Default group';
$string['lasthour'] = 'Last hour shown';
$string['managecategories'] = 'Categories';
$string['managecategoriesintro'] = 'Categories group events by type, for example lessons or rehearsals. Each event belongs to at most one category.';
$string['managegroups'] = 'Permission groups';
$string['managegroupsintro'] = 'Permission groups decide who may view the calendar and who may add, change and delete events. Each participant can be in one group. Users with the capability to manage permission groups (by default teachers) always have full access.';
$string['managerooms'] = 'Manage rooms';
$string['memberremoved'] = 'The user has been removed from the group.';
$string['members'] = 'Members';
$string['membersadded'] = '{$a->added} user(s) added, {$a->moved} user(s) moved from another group.';
$string['membersof'] = 'Members of "{$a}"';
$string['modulename'] = 'Room calendar';
$string['modulename_help'] = 'A room calendar lets a group share a calendar to coordinate their schedules and book rooms.

* Course administrators create permission groups (for example students, teachers, guests), decide for each group whether it may view events and whether it may add, change and delete events, and add participants to the groups.
* For each room they decide which groups may add and delete events in it.
* When adding an event, the user chooses which groups may change or delete it later.
* A room can only be booked once at any time; overlapping bookings are rejected.';
$string['modulename_link'] = 'mod/roomcalendar/view';
$string['modulenameplural'] = 'Room calendars';
$string['more'] = '{$a} more…';
$string['next'] = 'Next';
$string['nocategoriesyet'] = 'No categories have been created yet.';
$string['nocategory'] = 'No category';
$string['noeditinggroups'] = 'There are no permission groups that may change events yet. Only administrators can change this event until such a group exists.';
$string['nogroups'] = 'No groups';
$string['nogroupsyet'] = 'No permission groups have been created yet. Until then, only administrators can use the calendar.';
$string['nomembers'] = 'This group has no members yet.';
$string['noroom'] = 'No room';
$string['norooms'] = 'No rooms have been added yet.';
$string['notbookable'] = 'Not bookable';
$string['notenrolled'] = 'Not enrolled';
$string['nousersselected'] = 'No users selected';
$string['openday'] = 'Show day';
$string['partofseries'] = 'Part of a recurring series';
$string['pluginadministration'] = 'Room calendar administration';
$string['pluginname'] = 'Room calendar';
$string['previous'] = 'Previous';
$string['privacy:membership'] = 'Permission group';
$string['privacy:metadata:entries'] = 'Calendar events and room bookings.';
$string['privacy:metadata:entries:contactemail'] = 'The email address of the person responsible for the event.';
$string['privacy:metadata:entries:contactname'] = 'The name of the person responsible for the event.';
$string['privacy:metadata:entries:description'] = 'The description of the event.';
$string['privacy:metadata:entries:name'] = 'The title of the event.';
$string['privacy:metadata:entries:roomid'] = 'The booked room.';
$string['privacy:metadata:entries:timecreated'] = 'The time the event was created.';
$string['privacy:metadata:entries:timeend'] = 'The end of the event.';
$string['privacy:metadata:entries:timemodified'] = 'The time the event was last modified.';
$string['privacy:metadata:entries:timestart'] = 'The start of the event.';
$string['privacy:metadata:entries:userid'] = 'The user who created the event.';
$string['privacy:metadata:entries:usermodified'] = 'The user who last modified the event.';
$string['privacy:metadata:members'] = 'Membership of users in the permission groups of a calendar.';
$string['privacy:metadata:members:groupid'] = 'The permission group.';
$string['privacy:metadata:members:timecreated'] = 'The time the user was added to the group.';
$string['privacy:metadata:members:userid'] = 'The user who is a member of the group.';
$string['privacy:metadata:series'] = 'Groups of recurring events.';
$string['privacy:metadata:series:timecreated'] = 'The time the series was created.';
$string['privacy:metadata:series:userid'] = 'The user who created the series.';
$string['removemember'] = 'Remove from group';
$string['repeat'] = 'Repeat';
$string['repeatdaily'] = 'Every day';
$string['repeatnone'] = 'Does not repeat';
$string['repeatuntil'] = 'Repeat until';
$string['repeatweekly'] = 'Every week';
$string['resetentries'] = 'Delete all events and bookings';
$string['resetmembers'] = 'Remove all permission group members';
$string['room'] = 'Room';
$string['roomallgroups'] = 'All groups may add and delete events in this room';
$string['roomallgroups_help'] = 'If checked, every permission group that may add events can use this room, including groups created later. Otherwise only the selected groups may add, change or delete events in this room.';
$string['roombookable'] = 'Bookable';
$string['roombookable_help'] = 'If disabled, the room is still shown with its existing bookings, but no new bookings can be made.';
$string['roomcalendar:addinstance'] = 'Add a new room calendar';
$string['roomcalendar:managegroups'] = 'Manage permission groups and event categories (full access to all events)';
$string['roomcalendar:managerooms'] = 'Manage rooms';
$string['roomcalendar:view'] = 'View room calendar';
$string['roomcolor'] = 'Colour';
$string['roomdeleted'] = 'The room has been deleted.';
$string['roomgroups'] = 'Groups allowed in this room';
$string['roomname'] = 'Room name';
$string['roompermissions'] = 'Permissions';
$string['rooms'] = 'Rooms';
$string['roomsaved'] = 'The room has been saved.';
$string['selectallcategories'] = 'All';
$string['selectnocategories'] = 'None';
$string['startdate'] = 'First day';
$string['time'] = 'Time';
$string['timeend'] = 'End';
$string['timespan'] = '{$a->start} – {$a->end}';
$string['timestart'] = 'Start';
$string['today'] = 'Today';
$string['view'] = 'View';
$string['viewday'] = 'Day';
$string['viewmonth'] = 'Month';
$string['viewweek'] = 'Week';
$string['withoutcategory'] = 'Without category';
$string['youareadmin'] = 'You manage this calendar and have full access.';
$string['yourgroup'] = 'Your permission group: {$a}';
