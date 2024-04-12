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
$string['errorcourseeditingbanned'] = 'Editing is not allowed during exam.';
$string['errorcreaterole'] = 'Exam Guard role could not be created.';
$string['errorrolenotexists'] = 'Exam Guard role not exists.';
$string['pluginname'] = 'Exam Guard';
$string['privacy:metadata'] = 'This plugin does not store any personal data.';
$string['settings:enable'] = 'Enable Exam Guard';
$string['settings:enable:desc'] = 'Enable Exam Guard to prevent course editing during exams.';
$string['settings:examduration'] = 'Exam duration';
$string['settings:examduration_desc'] = 'The duration of an exam activity will be considered an exam activity (in minutes).';
$string['settings:generalsettingsheader'] = 'General settings';
$string['settings:manage'] = 'Manage Exam Guard';
$string['settings:timebuffer'] = 'Time buffer';
$string['settings:timebuffer_desc'] = 'The time buffer before and after an exam activity (in minutes).';
$string['warningmessage'] = 'Exam in progress! Course editing will be available after {$a}';
