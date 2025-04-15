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
 * Plugin strings are defined here.
 *
 * @package     local_examguard
 * @category    string
 * @copyright   2024 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author      Alex Yeung <k.yeung@ucl.ac.uk>
 */

defined('MOODLE_INTERNAL') || die();

$string['cachedef_editingroles'] = 'Stores editing roles in the system.';
$string['cachedef_examguardcourses'] = 'Stores courses that are in exam mode.';
$string['confirm_time_extension'] = 'Are you sure to extend the time by {$a} minutes?';
$string['error:bulk_extension_not_enabled'] = 'Bulk extension is not enabled.';
$string['error:course_editing_banned'] = 'Editing is not allowed during exam.';
$string['error:create_role'] = 'Exam Guard role could not be created.';
$string['error:exam_activity_class_not_found'] = 'Exam activity class not found for module {$a}.';
$string['error:failed_to_create_time_extension_button'] = 'Failed to create time extension button. Error: {$a}.';
$string['error:integer'] = 'Time entered must be an integer.';
$string['error:maxlength'] = 'Time entered must be less than or equal to 3 digits';
$string['error:multiple_exam_guard_groups_found'] = 'Multiple exam guard extension groups found. This should not happen.';
$string['error:not_active_exam_activity'] = 'This is not an active exam activity.';
$string['error:role_not_exists'] = 'Exam Guard role not exists.';
$string['label:extend_time'] = 'Extension time (minutes)';
$string['label:extend_time_button'] = 'Extend time';
$string['label:extend_time_help'] = 'Enter the extension time (in minutes). For students who already have user or group overrides, a new user override will be created based on their existing settings. For students without any overrides, a new exam guard group override will be created to apply the extension.';
$string['notification:update_success'] = 'Update successful';
$string['pluginname'] = 'Exam Guard';
$string['privacy:metadata'] = 'This plugin does not store any personal data.';
$string['settings:bulkextension'] = 'Enable bulk extension';
$string['settings:enable'] = 'Enable Exam Guard';
$string['settings:enable:desc'] = 'Enable Exam Guard to prevent course editing during exams.';
$string['settings:examduration'] = 'Exam duration';
$string['settings:examduration_desc'] = 'The duration of an exam activity will be considered an exam activity (in minutes).';
$string['settings:generalsettingsheader'] = 'General settings';
$string['settings:manage'] = 'Manage Exam Guard';
$string['settings:timebuffer'] = 'Time buffer';
$string['settings:timebuffer_desc'] = 'The time buffer before and after an exam activity (in minutes).';
$string['warningmessage'] = 'Exam in progress! Course editing will be available after {$a}';
