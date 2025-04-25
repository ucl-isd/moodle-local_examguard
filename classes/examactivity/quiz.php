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

use core\exception\moodle_exception;
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
            // Get and organize existing overrides.
            $overrides = $this->get_organized_overrides();

            // Get all students who can attempt the quiz.
            $enrolledusers = $this->get_gradeable_enrolled_users_with_capability('mod/quiz:attempt');

            // Find existing examguard groups for this quiz.
            $examguardgroups = $this->find_examguard_groups();

            // Process extensions for all users.
            $this->process_user_extensions(
                $enrolledusers,
                $overrides,
                $examguardgroups,
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
        $currentextension = $this->get_latest_extension();
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
     * Get the effective settings for the quiz with the given user override and group overrides.
     *
     * @param \stdClass|null $useroverride User override
     * @param array $groupoverrides Group overrides
     *
     * @return array
     */
    public function get_effective_settings(?\stdClass $useroverride, array $groupoverrides): array {
        $data = [
            'timeopen' => $this->activityinstance->timeopen,
            'timeclose' => $this->activityinstance->timeclose,
            'timelimit' => $this->activityinstance->timelimit,
        ];

        // Find the best settings from user override and group overrides, falling back to the quiz settings if not set.
        foreach (['timeopen', 'timeclose', 'timelimit'] as $field) {
            // Field is set in user override, use it.
            if (!empty($useroverride->$field)) {
                $data[$field] = $useroverride->$field;
                continue;
            }

            // Field is not set in user override, use the best group override settings if any.
            if (!empty($groupoverrides)) {
                $best = $this->get_best_override_settings($groupoverrides);
                if (!empty($best[$field])) {
                    $data[$field] = $best[$field];
                }
            }
        }

        return $data;
    }

    /**
     * Create a group override with extended time.
     *
     * @param int $groupid Group ID
     * @param array $overridedata Override data
     * @param int $extensionseconds Extension in seconds
     * @return int|bool The ID of the created override
     */
    protected function create_group_override(int $groupid, array $overridedata, int $extensionseconds): int|bool {
        $data = [
            'quiz' => $this->activityinstance->id,
            'groupid' => $groupid,
        ];

        // Add extension to time fields.
        foreach (['timelimit', 'timeclose'] as $field) {
            if (empty($overridedata[$field])) {
                continue;
            }
            // As we are removing the existing exam guard group and creating a new one,
            // so no need to check the current extension time.
            $data[$field] = $overridedata[$field] + $extensionseconds;
        }

        // Match time limit to duration if it is not set or greater than the override group's quiz duration.
        $duration = $data['timeclose'] - $overridedata['timeopen'];
        if (empty($data['timelimit']) || $data['timelimit'] > $duration) {
            $data['timelimit'] = $duration;
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
        $best = ['timeopen' => 0, 'timeclose' => 0, 'timelimit' => 0];

        foreach ($groupoverrides as $override) {
            // For timelimit and timeclose, use highest value.
            $best['timelimit'] = max($best['timelimit'], $override->timelimit ?? 0);
            $best['timeclose'] = max($best['timeclose'], $override->timeclose ?? 0);

            // For timeopen, use lowest non-zero value.
            if (!empty($override->timeopen)) {
                $best['timeopen'] = $best['timeopen'] ? min($best['timeopen'], $override->timeopen) : $override->timeopen;
            }
        }

        return $best;
    }

    /**
     * Process extensions for all users.
     *
     * @param array $enrolledusers Array of enrolled users
     * @param array $overrides Organized overrides
     * @param array $existingexamguardgroups Existing exam guard groups if any
     * @param int $extensionminutes Extension in minutes
     * @return void
     */
    private function process_user_extensions(
        array $enrolledusers,
        array $overrides,
        array $existingexamguardgroups,
        int $extensionminutes
    ): void {
        // Collect users for new exam guard groups.
        $newexamguardgroups = [];

        // Process each enrolled user.
        foreach ($enrolledusers as $user) {
            // Get user's override groups.
            $usergroupoverrides = $this->get_user_group_overrides($user->id, $overrides['group']);

            // Filter out all exam guard group overrides if existing.
            if (!empty($existingexamguardgroups)) {
                $usergroupoverrides = $this->filter_out_examguard_group($usergroupoverrides, $existingexamguardgroups);
            }

            if (isset($overrides['user'][$user->id])) {
                // User has a user override - extend it.
                // Get current extension for this user override if any.
                $currentextension = $this->get_current_extension($this->coursemodule->id, $overrides['user'][$user->id]);

                // Update user override.
                $this->update_user_override_extension(
                    $overrides['user'][$user->id],
                    $extensionminutes,
                    $currentextension,
                    $usergroupoverrides
                );
            } else {
                // Handle user who has pre-existing override groups or without any override.
                // Get the effective settings for this user (time open, time close and time limit).
                $effectivesettings = $this->get_effective_settings(null, $usergroupoverrides);

                // Put the user into a new exam guard group if the current time is within the effective time open and time close.
                if ($effectivesettings['timeopen'] - $this->timebuffer <= $this->clock->time() &&
                    $this->clock->time() <= $effectivesettings['timeclose']) {
                    // Put the effective settings into a key, so all users with same effective settings will be grouped together.
                    $key = $effectivesettings['timeopen'] . '_' .
                        $effectivesettings['timeclose'] . '_' .
                        $effectivesettings['timelimit'];
                    $newexamguardgroups[$key][] = $user->id;
                }
            }
        }

        // Create new exam guard group overrides.
        $this->create_examguard_group_overrides($newexamguardgroups, $existingexamguardgroups, $extensionminutes);
    }

    /**
     * Update a user override with a new extension time.
     *
     * @param \stdClass $override The existing override object
     * @param int $extensionminutes Extension in minutes
     * @param bool|\stdClass $currentextension
     * @param array $groupoverrides Group overrides applicable to the user
     * @return bool False if the override was not updated
     * @throws \invalid_parameter_exception
     * @throws moodle_exception
     */
    private function update_user_override_extension(
        \stdClass $override,
        int $extensionminutes,
        bool|\stdClass $currentextension,
        array $groupoverrides = []
    ): bool {
        if (!empty($currentextension)) {
            if (empty($currentextension->ori_override_data)) {
                throw new moodle_exception('error:original_user_override_data_empty', 'local_examguard');
            }
            $currentextensionseconds = $currentextension->extensionminutes * MINSECS;
            $originaloverridedata = $currentextension->ori_override_data;
        } else {
            $currentextensionseconds = 0;
            $originaloverridedata = json_encode($override);
        }

        if ($extensionminutes == 0) {
            if (!empty($originaloverridedata)) {
                $this->restore_user_override(json_decode($originaloverridedata));
            }
            return false;
        }

        // Convert minutes to seconds.
        $extensionseconds = $extensionminutes * MINSECS;

        // Find the user current settings.
        $effectivesettings = $this->get_effective_settings($override, $groupoverrides);

        // Skip if the current time is not within the student's quiz time window.
        if (!($effectivesettings['timeopen'] - $this->timebuffer <= $this->clock->time() &&
            $this->clock->time() <= $effectivesettings['timeclose'])) {
            return false;
        }

        // Update time fields.
        $changed = false;
        $data = (array)$override;
        foreach (['timeclose', 'timelimit'] as $field) {
            // Skip if the field is not set in anywhere.
            if (empty($effectivesettings[$field])) {
                continue;
            }

            // Calculate the new value.
            $data[$field] = $effectivesettings[$field] - $currentextensionseconds + $extensionseconds;

            // Check if the field has changed.
            if ($override->$field != $data[$field]) {
                $changed = true;
            }
        }

        // Match the time limit to the duration if it is not set or greater than user's quiz duration.
        $duration = $data['timeclose'] - $effectivesettings['timeopen'];
        if (empty($data['timelimit']) || $data['timelimit'] > $duration) {
            $data['timelimit'] = $duration;
        }

        if (!$changed) {
            return false;
        }

        // Save the updated override.
        if (!($overrideid = $this->overridemanager->save_override($data))) {
            throw new moodle_exception('error:failed_to_save_override', 'local_examguard');
        }

        // Record the override in exam guard.
        $this->record_exam_guard_override($this->coursemodule->id, $overrideid, $extensionminutes, $originaloverridedata);

        return true;
    }

    /**
     * Create new exam guard group overrides.
     *
     * @param array $newexamguardgroups Exam guard groups to be created
     * @param array $existingexamguardgroups Existing exam guard groups
     * @param int $extensionminutes Extension in minutes
     * @return bool
     * @throws moodle_exception
     */
    protected function create_examguard_group_overrides(
        array $newexamguardgroups,
        array $existingexamguardgroups,
        int $extensionminutes
    ): bool {
        // Delete active exam guard groups.
        $this->delete_active_existing_examguard_groups($existingexamguardgroups);

        // Skip if no extension needed.
        if ($extensionminutes === 0) {
            return false;
        }

        // Calculate starting group number.
        $nextgroupnum = empty($this->find_examguard_groups())
            ? 1
            : $this->get_max_group_num($existingexamguardgroups) + 1;

        // Create new exam guard groups.
        foreach ($newexamguardgroups as $settingskey => $users) {
            $timesettings = array_combine(
                ['timeopen', 'timeclose', 'timelimit'],
                array_map('intval', explode('_', $settingskey))
            );

            $overrideid = $this->create_examguard_group_override(
                $users,
                $timesettings,
                $extensionminutes,
                $nextgroupnum
            );

            if (!$overrideid) {
                throw new moodle_exception('error:failed_to_save_override', 'local_examguard');
            }

            // Record the extension.
            $this->record_exam_guard_override($this->coursemodule->id, $overrideid, $extensionminutes);
            $nextgroupnum++;
        }

        return true;
    }

    /**
     * Delete existing exam guard group and its overrides.
     *
     * @param array $existingexamguardgroups Existing examguard groups
     * @return void
     */
    protected function delete_active_existing_examguard_groups(array $existingexamguardgroups): void {
        if (empty($existingexamguardgroups)) {
            return;
        }

        // Extract group ids.
        $groupids = array_column($existingexamguardgroups, 'id');

        // Get active overrides for exam guard groups.
        $activeoverrides = array_filter(
            $this->get_organized_overrides()['group'] ?? [],
            fn($override): bool =>
                in_array($override->groupid, $groupids) &&
                $override->timeclose >= $this->clock->time()
        );

        // Delete active overrides and their groups.
        foreach ($activeoverrides as $override) {
            // Delete the override in quiz.
            $this->overridemanager->delete_overrides([$override]);

            // Delete the group in course.
            groups_delete_group($override->groupid);

            // Delete the override in exam guard.
            $this->delete_examguard_overrides($override);
        }
    }

    /**
     * Restore user override if extension is zero.
     *
     * @param \stdClass $override
     * @return void
     * @throws \invalid_parameter_exception
     */
    protected function restore_user_override(\stdClass $override): void {
        if ($this->overridemanager->save_override((array)$override)) {
            $this->delete_examguard_overrides($override);
        }
    }
}
