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

namespace local_examguard;

use cache;
use context_course;
use core\notification;
use local_examguard\examactivity\examactivityfactory;

/**
 * Manager class for local_examguard.
 *
 * @package    local_examguard
 * @copyright  2024 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Alex Yeung <k.yeung@ucl.ac.uk>
 */
class manager {
    /** @var string[] Course activities counted as exam activity */
    const EXAM_ACTIVITIES = ['quiz'];

    /** @var string[] Permissions required for course editing */
    const EDIT_PERMISSIONS = [
        "moodle/course:update",
        "moodle/course:manageactivities",
        "moodle/course:activityvisibility",
        "moodle/course:sectionvisibility",
        "moodle/course:movesections",
        "moodle/course:setcurrentsection",
        "moodle/site:manageblocks",
    ];

    /**
     * Get active exam activities in a course.
     *
     * @param int $courseid The course id.
     * @return array
     * @throws \dml_exception
     */
    public static function get_active_exam_activities(int $courseid): array {
        $activities = [];

        // Get course.
        $course = get_course($courseid);

        // Get all active instances of exam activities in the course.
        foreach (self::EXAM_ACTIVITIES as $activityname) {
            $activityinstances = get_all_instances_in_course($activityname, $course);

            // Skip if no activity instances found for this activity type.
            if (empty($activityinstances)) {
                continue;
            }

            // Put active exam activities into an array.
            foreach ($activityinstances as $activityinstance) {
                $examactivity = examactivityfactory::get_exam_activity($activityinstance->coursemodule, $activityname);
                if ($examactivity->is_active_exam_activity()) {
                    $activities[] = $examactivity;
                }
            }
        }

        if (!empty($activities)) {
            // Sort the activities by end time in descending order.
            usort($activities, function ($a, $b) {
                return $b->get_exam_end_time() <=> $a->get_exam_end_time();
            });
        }

        return $activities;
    }

    /**
     * Check whether course editing should be prevented.
     *
     * @param int $courseid The course id.
     * @return bool
     * @throws \dml_exception
     */
    public static function should_prevent_course_editing(int $courseid): bool {
        return !empty(self::get_active_exam_activities($courseid));
    }

    /**
     * Get exam course record.
     *
     * @param int $courseid The course id.
     * @return mixed
     * @throws \dml_exception
     */
    public static function get_examcourse_record(int $courseid): mixed {
        global $DB;
        return $DB->get_record('local_examguard', ['courseid' => $courseid]);
    }

    /**
     * Get editing roles, e.g. editingteacher, manager.
     *
     * @return array
     * @throws \dml_exception|\coding_exception
     */
    public static function get_editing_roles() {
        global $DB;

        $cache = cache::make('local_examguard', 'editingroles');
        if ($rolesids = $cache->get('editingroles')) {
            return $rolesids;
        }

        list($insql, $params) = $DB->get_in_or_equal(['editingteacher', 'manager']);
        $sql = "SELECT r.* FROM {role} r WHERE r.archetype $insql";

        if ($roles = $DB->get_records_sql($sql, $params)) {
            $rolesids = array_keys($roles);
            // Set cache.
            $cache->set('editingroles', $rolesids);
            return $rolesids;
        }

        return [];
    }

    /**
     * Check if the user has an editing role.
     *
     * @param int $courseid The course id.
     * @param int $userid The user id.
     * @return bool
     * @throws \dml_exception|\coding_exception
     */
    public static function user_has_an_editing_role(int $courseid, int $userid): bool {
        // Get user roles in the course context.
        $userroles = get_users_roles(context_course::instance($courseid), [], false);

        // Get all editing roles in Moodle in ids array format.
        $editingrolesid = self::get_editing_roles();

        // No editing roles found in the system, very unlikely.
        if (empty($editingrolesid)) {
            return false;
        }

        $haseditingrole = false;

        // Check if the user has an editing role.
        if (!empty($userroles[$userid])) {
            foreach ($userroles[$userid] as $roleassignment) {
                if (in_array($roleassignment->roleid, $editingrolesid)) {
                    $haseditingrole = true;
                    break;
                }
            }
        }

        return $haseditingrole;
    }

    /**
     * Depends on course's exam status, add/remove the exam guard role.
     *
     * @param int $courseid
     * @return array
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function check_course_exam_status(int $courseid): array {
        global $DB;

        $editbanned = $DB->get_record('local_examguard', ['courseid' => $courseid]);
        $activeexamactivities = self::get_active_exam_activities($courseid);
        $preventediting = !empty($activeexamactivities);

        // Added exam guard role to all users having editing roles in the course already.
        if ($editbanned && $preventediting) {
            // Do nothing and show the notification message.
            return [true, $activeexamactivities];
        } else if (!$editbanned && !$preventediting) {
            // Do nothing and do not show the notification message.
            return [false, $activeexamactivities];
        }

        return [self::handle_users_roles($courseid, $preventediting), $activeexamactivities];
    }

    /**
     * Show notification banner.
     *
     * @param array $activeexamactivities
     * @return void
     */
    public static function show_notfication_banner(array $activeexamactivities): void {
        global $PAGE;

        if (!empty($activeexamactivities)) {
            // Get the active exam activity with the latest end time.
            $latestexamactivity = reset($activeexamactivities);
            $renderer = $PAGE->get_renderer('local_examguard');

            // Add notification banner.
            notification::add(
                $renderer->render_notification_banner($latestexamactivity->get_exam_end_time()),
                notification::ERROR
            );
        }
    }

    /**
     * Add/remove exam guard role for all editing roles in the course.
     *
     * @param int $courseid The course id.
     * @param bool $preventediting
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function handle_users_roles(int $courseid, bool $preventediting): bool {
        global $DB;

        // Get course context.
        $context = context_course::instance($courseid);

        // Get all users in the course.
        $users = enrol_get_course_users($courseid, true);

        if (empty($users)) {
            return false;
        }

        // Check if the role examguard exists.
        $examguardrole = $DB->get_record('role', ['shortname' => 'localexamguard']);

        // Exam guard role does not exist.
        if (empty($examguardrole)) {
            throw new \moodle_exception('error:role_not_exists', 'local_examguard');
        }

        // Get override record.
        $examcourse = self::get_examcourse_record($courseid);

        // Loop through each user.
        foreach ($users as $user) {
            // Skip the user if the user does not have an editing role.
            if (!self::user_has_an_editing_role($courseid, $user->id)) {
                continue;
            }

            if ($preventediting) {
                // Assign the exam guard role to the user.
                role_assign($examguardrole->id, $user->id, $context->id);
            } else {
                // Unassign the exam guard role from the user.
                role_unassign($examguardrole->id, $user->id, $context->id);
            }
        }

        if ($preventediting && empty($examcourse)) {
            $DB->insert_record('local_examguard', ['courseid' => $courseid, 'timecreated' => time()]);
            return true;
        } else if (!$preventediting && !empty($examcourse)) {
            $DB->delete_records('local_examguard', ['courseid' => $courseid]);
            return false;
        }

        return false;
    }

    /**
     * Check if the module is exam guard supported.
     *
     * @param string $modname
     * @return bool
     */
    public static function is_exam_guard_supported_activity(string $modname): bool {
        return in_array($modname, self::EXAM_ACTIVITIES);
    }

    /**
     * Returns the course module instance and the activity instance.
     *
     * @param int $cmid course module ID.
     * @return array
     */
    public static function get_module_from_cmid(int $cmid): array {
        global $DB;

        // Get course module instance.
        $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);

        // Get activity module instance.
        $module = $DB->get_record($cm->modname, ['id' => $cm->instance], '*', MUST_EXIST);
        $module->cmid = $cm->id;
        return [$cm, $module];
    }
}
