<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

use core\notification;
use local_examguard\manager;

/**
 * Plugin functions for the local_examguard plugin
 *
 * @package    local_examguard
 * @copyright  2024 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Alex Yeung <k.yeung@ucl.ac.uk>
 */

/**
 * Validate the data in the new field when the form is submitted
 *
 * @param moodleform_mod $fromform
 * @param array $fields
 * @return string[]|void
 */
function local_examguard_coursemodule_validation($fromform, $fields) {
    // Skip if exam guard is not enabled.
    if (!get_config('local_examguard', 'enabled')) {
        return;
    }

    try {
        // Prevent change to activity if the course is in exam mode and the user is not a site admin.
        if (
            !has_capability('moodle/site:config', context_system::instance()) &&
            manager::should_prevent_course_editing($fields['course'])
        ) {
            return ['examguard' => get_string('error:course_editing_banned', 'local_examguard')];
        }
    } catch (Exception $e) {
        // Show error message when exception is caught.
        notification::add($e->getMessage(), notification::ERROR);
    }
}

/**
 * Add AMD module to the module edit form for real-time warnings.
 *
 * @param moodleform_mod $formwrapper
 * @param MoodleQuickForm $mform
 */
function local_examguard_coursemodule_standard_elements($formwrapper, $mform) {
    global $PAGE;

    // Skip if exam guard is not enabled.
    if (!get_config('local_examguard', 'enabled')) {
        return;
    }

    try {
        // Only apply to exam guard supported activities.
        $modulename = $formwrapper->get_current()->modulename ?? '';
        if (!manager::is_exam_guard_supported_activity($modulename)) {
            return;
        }

        $classname = 'local_examguard\\examactivity\\' . $modulename;
        $examduration = (int) get_config('local_examguard', 'examduration') * MINSECS;

        $PAGE->requires->js_call_amd('local_examguard/mod_form_warnings', 'init', [[
            'startfieldname' => $classname::get_start_time_field_name(),
            'endfieldname' => $classname::get_end_time_field_name(),
            'examduration' => $examduration,
        ]]);
    } catch (Exception $e) {
        // Show error message when exception is caught.
        notification::add($e->getMessage(), notification::ERROR);
    }
}

/**
 * Process data from submitted form.
 *
 * @param stdClass $data
 * @param stdClass $course
 */
function local_examguard_coursemodule_edit_post_actions($data, $course) {
    // Skip if exam guard is not enabled.
    if (!get_config('local_examguard', 'enabled')) {
        return $data;
    }

    try {
        // Execute Exam Guard actions if the module is an exam activity.
        if (manager::is_exam_guard_supported_activity($data->modulename)) {
            manager::check_course_exam_status($course->id);
        }
    } catch (Exception $e) {
        // Show error message when exception is caught.
        notification::add($e->getMessage(), notification::ERROR);
    }

    return $data;
}
