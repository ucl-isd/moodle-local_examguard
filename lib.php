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
 * Function to add notification to the top of the page if the exam is in progress.
 *
 * @return string
 */
function local_examguard_before_standard_top_of_body_html(): string {
    global $PAGE, $USER;

    // Check if the current page is a course view page or a module view page.
    if ((str_contains($PAGE->pagetype, 'course-view') || preg_match('/mod-(.+?)-view/', $PAGE->pagetype))
        && $PAGE->course->id != SITEID) {
        try {
            // Exam guard is enabled.
            if (get_config('local_examguard', 'enabled')) {

                // Do nothing if the current user is not an editing role, e.g. student.
                if (!manager::user_has_an_editing_role($PAGE->course->id, $USER->id)) {
                    return '';
                }

                // Ban course editing if the exam is in progress / release course editing if the exam is finished.
                list($editingbanned, $activeexamactivities) = manager::check_course_exam_status($PAGE->course->id);

                // Add notification if course editing is banned.
                if ($editingbanned) {
                    manager::show_notfication_banner($activeexamactivities);
                }
            }
        } catch (Exception $e) {
            notification::add($e->getMessage(), notification::ERROR);
        }
    }

    return '';
}

/**
 * Validate the data in the new field when the form is submitted
 *
 * @param moodleform_mod $fromform
 * @param array $fields
 * @return string[]|void
 */
function local_examguard_coursemodule_validation($fromform, $fields) {
    try {
        // Exam Guard is enabled.
        if (get_config('local_examguard', 'enabled')) {
            // Prevent change to activity if the course is in exam mode and the user is not a site admin.
            if (!has_capability('moodle/site:config', context_system::instance()) &&
                manager::should_prevent_course_editing($fields['course'])) {
                // Return an error message to prevent saving changes, but the user will not see it.
                return ['examguard' => get_string('errorcourseeditingbanned', 'local_examguard')];
            }
        }
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
    try {
        // Exam Guard is enabled.
        if (get_config('local_examguard', 'enabled')) {
            // Execute Exam Guard actions if the module is an exam activity.
            if (in_array($data->modulename, manager::EXAM_ACTIVITIES)) {
                manager::check_course_exam_status($course->id);
            }
        }
    } catch (Exception $e) {
        // Show error message when exception is caught.
        notification::add($e->getMessage(), notification::ERROR);
    }

    return $data;
}
