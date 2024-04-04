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

    try {
        // Check if the current page is a course view page.
        if (str_contains($PAGE->pagetype, 'course-view') && $PAGE->course->id != SITEID) {
            // Check exam guard is enabled.
            if (get_config('local_examguard', 'enabled')) {

                // Do nothing if the current user is not an editing role, e.g. student.
                if (!manager::user_has_an_editing_role($PAGE->course->id, $USER->id)) {
                    return '';
                }

                // Ban course editing if the exam is in progress / release course editing if the exam is finished.
                $editingbanned = manager::check_course_exam_status($PAGE->course->id);

                // Add notification if course editing is banned.
                if ($editingbanned) {
                    notification::add(
                        '<h4>
                            <i class="fa-solid fa-user-clock fa-lg text-danger ml-2 mr-3"></i>
                            ' . get_string('warningmessage', 'local_examguard') . '
                        </h4>',
                        notification::ERROR
                    );
                }
            }
        }
    } catch (Exception $e) {
        notification::add($e->getMessage(), notification::ERROR);
    }

    return '';
}
