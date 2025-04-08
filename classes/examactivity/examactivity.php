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

namespace local_examguard\examactivity;

use core\clock;
use core\context\module;
use core\di;
use local_examguard\manager;

/**
 * Abstract class for exam activities.
 *
 * @package    local_examguard
 * @copyright  2024 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Alex Yeung <k.yeung@ucl.ac.uk>
 */
abstract class examactivity {
    /** @var \stdClass Activity instance */
    public \stdClass $activityinstance;

    /** @var \stdClass Course module */
    public \stdClass $coursemodule;

    /** @var int Time buffer before/after exam period */
    public int $timebuffer;

    /** @var int Exam duration */
    public int $examduration;

    /** @var \context_module Context module */
    public module $context;

    /** @var clock Clock instance. */
    protected readonly clock $clock;

    /** @var string Extension history table name */
    const EXTENSION_HISTORY_TABLE = 'local_examguard_extension_history';

    /**
     * Get the exam end time including buffer time.
     *
     * @return int
     */
    abstract public function get_exam_end_time(): int;

    /**
     * Check if the activity is an active exam activity.
     *
     * @return bool
     */
    abstract public function is_active_exam_activity(): bool;

    /**
     * Check if the activity is an exam activity.
     *
     * @return bool
     */
    abstract public function is_exam_activity(): bool;

    /**
     * Apply time extension.
     *
     * @param int $extensionminutes Number of minutes to extend
     * @return void
     */
    abstract public function apply_extension(int $extensionminutes): void;

    /**
     * Create a group override with extended time.
     *
     * @param int $groupid
     * @param int $extensionseconds
     * @return int|bool
     */
    abstract protected function create_group_override(int $groupid, int $extensionseconds): int|bool;

    /**
     * Constructor.
     *
     * @param int $cmid The course module id.
     */
    public function __construct(int $cmid) {
        [$cm, $module] = manager::get_module_from_cmid($cmid);

        // Set activity instance.
        $this->activityinstance = $module;

        // Set course module.
        $this->coursemodule = $cm;

        // Get the context.
        $this->context = \context_module::instance($cm->id);

        // Get the clock instance.
        $this->clock = di::get(clock::class);

        // Get time buffer and exam duration settings in seconds.
        $this->timebuffer = get_config('local_examguard', 'timebuffer') * MINSECS;
        $this->examduration = get_config('local_examguard', 'examduration') * MINSECS;
    }

    /**
     * Get the current extension.
     *
     * @return int The current extension in minutes, or 0 if none
     */
    public function get_current_extension(): int {
        global $DB;

        $records = $DB->get_records(self::EXTENSION_HISTORY_TABLE,
            ['cmid' => $this->coursemodule->id],
            'timecreated DESC',
            '*',
            0, 1);

        if (!empty($records)) {
            $record = reset($records);
            return $record->extensionminutes;
        }

        return 0;
    }

    /**
     * Create a new examguard group in course.
     *
     * @param int $minutes Number of minutes to extend
     * @return int|bool The ID of the created group
     */
    protected function create_examguard_group(int $minutes): int|bool {
        $groupdata = new \stdClass();
        $groupdata->courseid = $this->activityinstance->course;
        $groupdata->name = "Exam_guard_activity_{$this->coursemodule->id}_extension_{$minutes}";
        $groupdata->visibility = GROUPS_VISIBILITY_OWN;
        $groupdata->timecreated = $this->clock->time();
        $groupdata->timemodified = $groupdata->timecreated;

        return groups_create_group($groupdata);
    }

    /**
     * Create a new examguard group override, deleting any existing one.
     *
     * @param array $users Array of user objects
     * @param int $minutes Number of minutes to extend
     * @param int $extensionseconds Number of seconds to extend by
     * @param object|bool $existinggroup Existing examguard group if any
     * @return int|bool The ID of the created group override
     */
    protected function create_examguard_group_override(
        array $users,
        int $minutes,
        int $extensionseconds,
        object|bool $existinggroup): int|bool {
        if (empty($users)) {
            return false;
        }

        // Delete existing examguard group if it exists.
        if ($existinggroup) {
            $this->delete_existing_examguard_group($existinggroup);
        }

        // Do not create new exam guard group if the extension is 0.
        if ($extensionseconds == 0) {
            return false;
        }

        // Create a new group.
        $groupid = $this->create_examguard_group($minutes);
        if (!$groupid) {
            return false;
        }

        // Add users to the group.
        foreach ($users as $user) {
            groups_add_member($groupid, $user->id);
        }

        // Create and save the group override.
        return $this->create_group_override($groupid, $extensionseconds);
    }

    /**
     * Delete existing examguard group and its overrides.
     *
     * @param object $existinggroup Existing examguard group
     * @return void
     */
    protected function delete_existing_examguard_group(object $existinggroup): void {
        // Delete any overrides for this group.
        foreach ($this->get_organized_overrides()['group'] as $override) {
            if ($override->groupid == $existinggroup->id) {
                $this->overridemanager->delete_overrides([$override]);
                break;
            }
        }

        // Delete the group.
        groups_delete_group($existinggroup->id);
    }

    /**
     * Filter out examguard group from user group overrides.
     *
     * @param array $usergroupoverrides Array of group overrides
     * @param int $examguardgroupid Examguard group ID
     * @return array Filtered group overrides
     */
    protected function filter_out_examguard_group(array $usergroupoverrides, int $examguardgroupid): array {
        return array_filter($usergroupoverrides, function($override) use ($examguardgroupid) {
            return $override->groupid != $examguardgroupid;
        });
    }

    /**
     * Find exam guard group for this specific activity.
     *
     * @return object|false Group object or false if not found
     */
    protected function find_examguard_group() {
        global $DB;

        $courseid = $this->activityinstance->course;
        $cmid = $this->coursemodule->id;
        $sql = "SELECT g.id
                  FROM {groups} g
                 WHERE g.courseid = :courseid
                   AND " . $DB->sql_like('g.name', ':prefix') . "
              ORDER BY g.id ASC";

        $params = [
            'courseid' => $courseid,
            'prefix' => "Exam_guard_activity_{$cmid}_extension_%",
        ];

        $groups = $DB->get_records_sql($sql, $params);

        if (count($groups) > 1) {
            throw new \moodle_exception('error:multiple_exam_guard_groups_found', 'local_examguard');
        }

        return reset($groups) ?: false;
    }

    /**
     * Get the details of gradeable (i.e. students not teachers) enrolled users in this context with specified capability.
     * Can be used to get list of participants where activity has no 'student only' capability like 'mod/xxx:submit'.
     * @param string $capability the capability string e.g. 'mod/lti:view'.
     * @return array user details
     */
    protected function get_gradeable_enrolled_users_with_capability(string $capability): array {
        $enrolledusers = get_enrolled_users($this->context, $capability);
        // Filter out non-gradeable users e.g. teachers.
        $gradeableids = self::get_gradeable_user_ids();
        return array_filter($enrolledusers, function($u) use ($gradeableids) {
            return in_array($u->id, $gradeableids);
        });
    }

    /**
     * Get the user IDs of gradeable users in this context i.e. students not teachers.
     * @return int[] user IDs
     */
    protected function get_gradeable_user_ids(): array {
        global $DB, $CFG;

        // Code adapted from grade/report/lib.php to limit to users with a gradeable role, i.e. students.
        // The $CFG->gradebookroles setting is exposed on /admin/search.php?query=gradebookroles admin page.
        $gradebookroles = explode(',', $CFG->gradebookroles);
        if (empty($gradebookroles)) {
            return[];
        }
        list($gradebookrolessql, $gradebookrolesparams) =
            $DB->get_in_or_equal($gradebookroles, SQL_PARAMS_NAMED, 'gradebookroles');

        // We want to query both the current context and parent contexts.
        list($relatedctxsql, $relatedctxparams) = $DB->get_in_or_equal(
            $this->context->get_parent_context_ids(true), SQL_PARAMS_NAMED, 'relatedctx'
        );
        $sql = "SELECT DISTINCT userid FROM {role_assignments} WHERE roleid $gradebookrolessql AND contextid $relatedctxsql";
        return  $DB->get_fieldset_sql($sql, array_merge($gradebookrolesparams, $relatedctxparams));
    }

    /**
     * Get organized overrides by user and group.
     *
     * @return array Associative array with user and group overrides
     */
    protected function get_organized_overrides(): array {
        $existingoverrides = $this->overridemanager->get_all_overrides();
        $organized = [
            'user' => [],
            'group' => [],
        ];

        foreach ($existingoverrides as $override) {
            if (!empty($override->userid)) {
                $organized['user'][$override->userid] = $override;
            } else if (!empty($override->groupid)) {
                $organized['group'][$override->groupid] = $override;
            }
        }

        return $organized;
    }

    /**
     * Get all group overrides applicable to a user.
     *
     * @param int $userid User ID
     * @param array $groupoverrides Group overrides
     * @return array Applicable group overrides
     */
    protected function get_user_group_overrides(int $userid, array $groupoverrides): array {
        $usergroups = groups_get_user_groups($this->activityinstance->course, $userid);
        $usergroups = $usergroups[0]; // First element contains the group IDs.

        $usergroupoverrides = [];
        foreach ($usergroups as $groupid) {
            if (isset($groupoverrides[$groupid])) {
                $usergroupoverrides[] = $groupoverrides[$groupid];
            }
        }

        return $usergroupoverrides;
    }

    /**
     * Record an extension in the history table
     *
     * @param int $extensionminutes The extension in minutes
     */
    protected function record_extension(int $extensionminutes): void {
        global $DB, $USER;

        $record = new \stdClass();
        $record->cmid = $this->coursemodule->id;
        $record->extensionminutes = $extensionminutes;
        $record->timecreated = di::get(clock::class)->time();
        $record->usermodified = $USER->id;

        $DB->insert_record(self::EXTENSION_HISTORY_TABLE, $record);
    }
}
