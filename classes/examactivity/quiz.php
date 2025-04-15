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

use mod_quiz\local\override_manager;

/**
 * Exam activity class for quiz.
 *
 * @package    local_examguard
 * @copyright  2024 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Alex Yeung <k.yeung@ucl.ac.uk>
 */
class quiz extends examactivity {
    /** @var override_manager $overridemanager */
    protected override_manager $overridemanager;

    /**
     * Constructor.
     *
     * @param int $cmid
     */
    public function __construct(int $cmid) {
        parent::__construct($cmid);

        // Create the override manager.
        $this->overridemanager = new override_manager($this->activityinstance, $this->context);
    }

    /**
     * Apply time extension to the quiz for all applicable students.
     *
     * @param int $extensionminutes Number of minutes to extend
     * @return void
     *
     * @throws \required_capability_exception
     */
    public function apply_extension(int $extensionminutes): void {
        global $DB;

        // Check capability.
        if (!$this->can_extend_time()) {
            throw new \required_capability_exception($this->context, 'mod/quiz:manageoverrides', 'nopermissions', '');
        }

        // Start a transaction.
        $transaction = $DB->start_delegated_transaction();

        try {
            // Convert minutes to seconds.
            $extensionseconds = $extensionminutes * MINSECS;
            $currentextensionseconds = $this->get_current_extension() * MINSECS;

            // Get and organize existing overrides.
            $overrides = $this->get_organized_overrides();

            // Get all students who can attempt the quiz.
            $enrolledusers = $this->get_gradeable_enrolled_users_with_capability('mod/quiz:attempt');

            // Find existing examguard group for this quiz.
            $examguardgroup = $this->find_examguard_group();

            // Process extensions for all users.
            $this->process_user_extensions(
                $enrolledusers,
                $overrides,
                $examguardgroup,
                $extensionseconds,
                $currentextensionseconds,
                $extensionminutes
            );

            // Record the extension at the quiz level.
            $this->record_extension($extensionminutes);

            // If we got here without exceptions, commit the transaction.
            $transaction->allow_commit();
        } catch (\Exception $e) {
            // An error occurred, roll back the transaction.
            $transaction->rollback($e);
            throw $e; // Re-throw the exception after rollback.
        }
    }

    /**
     * Check if the current user has the capability to extend time.
     *
     * @return bool
     */
    public function can_extend_time(): bool {
        return has_capability('mod/quiz:manageoverrides', $this->context);
    }

    /**
     * Get the context.
     *
     * @return \context_module
     */
    public function get_context(): \context_module {
        return $this->context;
    }

    /**
     * Get the exam end time including buffer time (in unix timestamp).
     *
     * @return int
     */
    public function get_exam_end_time(): int {
        $currentextension = $this->get_current_extension();
        $extensionseconds = ($currentextension > 0) ? ($currentextension * MINSECS) : 0;

        return $this->activityinstance->timeclose + $extensionseconds + $this->timebuffer;
    }

    /**
     * Get the exam start time (in unix timestamp).
     *
     * @return int
     */
    public function get_exam_start_time(): int {
        return $this->activityinstance->timeopen;
    }

    /**
     * Check if the quiz is an active exam activity.
     *
     * @return bool
     */
    public function is_active_exam_activity(): bool {
        if (!$this->is_exam_activity()) {
            return false;
        }

        $currenttime = $this->clock->time();
        $bufferstarttime = $this->activityinstance->timeopen - $this->timebuffer;

        return $bufferstarttime < $currenttime && $this->get_exam_end_time() > $currenttime;
    }

    /**
     * Check if the quiz is an exam activity.
     *
     * @return bool
     */
    public function is_exam_activity(): bool {
        // Quiz is considered an exam activity if:
        // 1. Both timeopen and timeclose are set.
        // 2. The duration is less than or equal to the configured exam duration.
        return $this->activityinstance->timeopen != 0 &&
               $this->activityinstance->timeclose != 0 &&
               ($this->activityinstance->timeclose - $this->activityinstance->timeopen) <= $this->examduration;
    }

    /**
     * Create a group override with extended time.
     *
     * @param int $groupid Group ID
     * @param int $extensionseconds Extension in seconds
     * @return int|bool The ID of the created override
     */
    protected function create_group_override(int $groupid, int $extensionseconds): int|bool {
        $data = [
            'quiz' => $this->activityinstance->id,
            'groupid' => $groupid,
        ];

        // Add extended time fields.
        foreach (['timelimit', 'timeclose'] as $field) {
            if ($this->activityinstance->$field > 0) {
                $data[$field] = $this->activityinstance->$field + $extensionseconds;
            }
        }

        return $this->overridemanager->save_override($data);
    }

    /**
     * Get the best settings from multiple group overrides.
     *
     * @param array $groupoverrides Array of group override objects
     * @return array Best settings
     */
    private function get_best_override_settings(array $groupoverrides): array {
        $best = [
            'timelimit' => 0,
            'timeclose' => 0,
        ];

        foreach ($groupoverrides as $override) {
            // For timelimit and attempts, use the highest value.
            foreach (['timelimit', 'attempts'] as $field) {
                if (isset($override->$field) && $override->$field > $best[$field]) {
                    $best[$field] = $override->$field;
                }
            }

            // For timeclose, use the latest time.
            if (isset($override->timeclose) && $override->timeclose > $best['timeclose']) {
                $best['timeclose'] = $override->timeclose;
            }
        }

        return $best;
    }

    /**
     * Process extensions for all users.
     *
     * @param array $enrolledusers Array of enrolled users
     * @param array $overrides Organized overrides
     * @param object|bool $examguardgroup Existing examguard group if any
     * @param int $extensionseconds Extension in seconds
     * @param int $currentextensionseconds Current extension in seconds
     * @param int $minutes Extension in minutes
     * @return void
     */
    private function process_user_extensions(
        array       $enrolledusers,
        array       $overrides,
        object|bool $examguardgroup,
        int         $extensionseconds,
        int         $currentextensionseconds,
        int         $minutes
    ): void {
        // Collect users without user overrides or group overrides.
        $userswithoutuseroverride = [];

        // Process each enrolled user.
        foreach ($enrolledusers as $user) {
            // Find user's group overrides excluding exam guard group.
            $usergroupoverrides = $this->get_user_group_overrides($user->id, $overrides['group']);

            // Filter out examguard group override if it exists.
            if ($examguardgroup) {
                $usergroupoverrides = $this->filter_out_examguard_group($usergroupoverrides, $examguardgroup->id);
            }

            if (isset($overrides['user'][$user->id])) {
                // Case 1: User already has a user override - extend it.
                $this->update_user_override_extension(
                    $overrides['user'][$user->id],
                    $extensionseconds,
                    $currentextensionseconds,
                    $usergroupoverrides
                );
            } else if (!empty($usergroupoverrides)) {
                // Case 2: User is in non-examguard groups with overrides - create override based on best settings.
                $this->create_user_extension_from_groups($user->id, $usergroupoverrides, $extensionseconds);
            } else {
                // Case 3: User has no overrides or only exam guard group override - add to exam guard group.
                $userswithoutuseroverride[] = $user;
            }
        }

        // Create or update exam guard group override for collected users.
        if (!empty($userswithoutuseroverride)) {
            $this->create_examguard_group_override(
                $userswithoutuseroverride,
                $minutes,
                $extensionseconds,
                $examguardgroup
            );
        }
    }

    /**
     * Update a time field (timelimit or timeclose) in an override.
     *
     * @param array $data Override data to update
     * @param \stdClass $override Original override object
     * @param string $field Field name to update
     * @param int $extensionseconds Extension in seconds
     * @param int $currentextensionseconds Current extension in seconds
     *
     * @return bool Whether the field was changed
     */
    private function update_time_field(
        array &$data,
        \stdClass $override,
        string $field,
        int $extensionseconds,
        int $currentextensionseconds
    ): bool {
        // If field is set in the override.
        if (!empty($override->$field)) {
            if ($currentextensionseconds > 0) {
                // Remove the current extension and add the new one.
                $data[$field] = $override->$field - $currentextensionseconds + $extensionseconds;
            } else {
                // Just add the new extension.
                $data[$field] = $override->$field + $extensionseconds;
            }
        } else if (!empty($this->activityinstance->$field)) {
            // If not set in override but set in quiz.
            $data[$field] = $this->activityinstance->$field + $extensionseconds;

            // At this point, there is no override setting for this field in user and group override.
            if ($field === 'timelimit' && isset($data['timeclose'])) {
                // Make sure the time limit is matching the new duration.
                $data['timelimit'] = $data['timeclose'] - $this->get_exam_start_time();
            }
        }

        return isset($data[$field]) && (!isset($override->$field) || $data[$field] != $override->$field);
    }

    /**
     * Update a user override with a new extension time.
     *
     * @param \stdClass $override The existing override object
     * @param int $extensionseconds Number of seconds to extend by
     * @param int $currentextensionseconds Current extension in seconds (if any)
     * @param array $groupoverrides Group overrides applicable to the user
     * @return int|bool The ID of the updated override
     * @throws \invalid_parameter_exception
     */
    private function update_user_override_extension(
        \stdClass $override,
        int $extensionseconds,
        int $currentextensionseconds = 0,
        array $groupoverrides = []
    ): int|bool {
        $data = (array)$override;
        $changed = false;

        // Get the best settings from group overrides if available.
        $best = !empty($groupoverrides) ? $this->get_best_override_settings($groupoverrides) : [];

        // Process time fields.
        foreach (['timeclose', 'timelimit'] as $field) {
            if (empty($override->$field) && !empty($best[$field])) {
                // If not set in user override but available in group overrides, use group value.
                $data[$field] = $best[$field] + $extensionseconds;
                $changed = true;
            } else {
                // Otherwise update normally if needed.
                $changed |= $this->update_time_field($data, $override, $field, $extensionseconds, $currentextensionseconds);
            }
        }

        // If the time close of the override is before the quiz's time close, skip update the user override.
        if (isset($data['timeclose']) && $data['timeclose'] < $this->activityinstance->timeclose) {
            return false;
        }

        // If nothing changed, return false.
        if (!$changed) {
            return false;
        }

        // Save the updated override.
        return $this->overridemanager->save_override($data);
    }

    /**
     * Create a new user override with extended time based on multiple group overrides.
     * Uses the most favorable settings from all applicable group overrides.
     *
     * @param int $userid The user ID to create an override for
     * @param array $groupoverrides Array of group override objects
     * @param int $extensionseconds Number of seconds to extend by
     * @return int|bool The ID of the created override
     * @throws \invalid_parameter_exception
     */
    protected function create_user_extension_from_groups(int $userid, array $groupoverrides, int $extensionseconds): int|bool {
        $data = [
            'quiz' => $this->activityinstance->id,
            'userid' => $userid,
        ];

        // Get the best settings from group overrides.
        $best = $this->get_best_override_settings($groupoverrides);

        // Apply time extensions to time fields.
        foreach (['timeclose', 'timelimit'] as $field) {
            if ($best[$field] > 0) {
                $data[$field] = $best[$field] + $extensionseconds;
            } else if ($this->activityinstance->$field > 0) {
                $data[$field] = $this->activityinstance->$field + $extensionseconds;

                // At this point, there is no override setting for time limit in group override.
                if ($field === 'timelimit' && isset($data['timeclose'])) {
                    // Make sure the time limit is matching the new duration.
                    $data['timelimit'] = $data['timeclose'] - $this->get_exam_start_time();
                }
            }
        }

        // If the time close of the override is before the quiz's time close, skip update the user override.
        if (isset($data['timeclose']) && $data['timeclose'] < $this->activityinstance->timeclose) {
            return false;
        }

        // Save the new override.
        return $this->overridemanager->save_override($data);
    }
}
