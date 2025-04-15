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

/**
 * Unit tests for quiz exam activity class.
 *
 * @package    local_examguard
 * @copyright  2024 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Alex Yeung <k.yeung@ucl.ac.uk>
 */

namespace local_examguard;

use core\clock;
use core\context\module;
use local_examguard\examactivity\examactivityfactory;
use local_examguard\examactivity\quiz;
use mod_quiz\local\override_manager;
use advanced_testcase;
use stdClass;

/**
 * Unit tests for quiz exam activity class.
 *
 * @package    local_examguard
 * @copyright  2024 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \local_examguard\examactivity\quiz
 */
final class quiz_test extends advanced_testcase {

    /**
     * @var stdClass The course object.
     */
    protected stdClass $course;

    /**
     * @var stdClass The quiz instance.
     */
    protected stdClass $quiz;

    /**
     * @var stdClass The course module.
     */
    protected stdClass $cm;

    /**
     * @var array Array of user objects.
     */
    protected array $students;

    /**
     * @var stdClass The teacher object.
     */
    protected stdClass $teacher;

    /**
     * @var array Array of group objects.
     */
    protected array $groups;

    /**
     * @var quiz The quiz exam activity instance.
     */
    protected quiz $quizactivity;

    /** @var clock $clock */
    protected readonly clock $clock;

    /** @var module $context */
    protected module $context;

    /** @var override_manager $overridemanager */
    protected override_manager $overridemanager;

    /**
     * Setup test data.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        // Mock the clock.
        $this->clock = $this->mock_clock_with_frozen(1744099200); // Current time 2025-04-08 09:00:00.

        // Set buffer config.
        set_config('timebuffer', 10, 'local_examguard'); // 10 minutes.
        set_config('examduration', 300, 'local_examguard'); // 5 hours.

        // Create a course.
        $this->course = $this->getDataGenerator()->create_course();

        // Create students.
        $this->students = [];
        for ($i = 1; $i <= 5; $i++) {
            $this->students[$i] = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($this->students[$i]->id, $this->course->id, 'student');
        }

        // Create a teacher.
        $this->teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->teacher->id, $this->course->id, 'editingteacher');

        // Create groups.
        $this->groups = [];
        for ($i = 1; $i <= 2; $i++) {
            $this->groups[$i] = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        }

        // Group membership:
        // Group 1: students[1], students[2].
        // Group 2: students[3], students[4].
        // No group: students[5].
        groups_add_member($this->groups[1], $this->students[1]);
        groups_add_member($this->groups[1], $this->students[2]);
        groups_add_member($this->groups[2], $this->students[3]);
        groups_add_member($this->groups[2], $this->students[4]);

        // Create a quiz.
        $this->quiz = $this->getDataGenerator()->create_module('quiz',
            [
                'course' => $this->course->id,
                'timeopen' => $this->clock->time(),
                'timeclose' => $this->clock->time() + HOURSECS * 3, // 3 hour duration.
                'timelimit' => HOURSECS * 3,
            ]
        );
        $this->cm = get_coursemodule_from_instance('quiz', $this->quiz->id, $this->course->id);
        $this->quizactivity = examactivityfactory::get_exam_activity($this->cm->id, 'quiz');
        $this->context = \context_module::instance($this->cm->id);
        $this->overridemanager = new override_manager($this->quiz, $this->context);
    }

    /**
     * Get a quiz exam activity instance.
     *
     * @param int $timeopen quiz's time open
     * @param int $timeclose quiz's time close
     * @param int $timelimit quiz's time limit
     *
     * @return quiz quiz exam activity instance
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    private function get_quiz_examactivity(int $timeopen, int $timeclose, int $timelimit): quiz {
        $quiz = $this->getDataGenerator()->create_module('quiz',
            [
                'course' => $this->course->id,
                'timeopen' => $timeopen,
                'timeclose' => $timeclose,
                'timelimit' => $timelimit,
            ]
        );

        // Get the course module.
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $this->course->id);

        // Create the quiz activity instance.
        return examactivityfactory::get_exam_activity($cm->id, 'quiz');
    }

    /**
     * Test is_exam_activity method.
     *
     * @covers ::is_exam_activity
     */
    public function test_is_exam_activity(): void {
        $now = $this->clock->time();
        // Exam duration set is 5 hours.
        // Test case 1: Quiz duration (3 hours) is less than exam duration.
        $quizactivity = $this->get_quiz_examactivity($now, $now + HOURSECS * 3, 0);
        $this->assertTrue($quizactivity->is_exam_activity());

        // Test case 2: Quiz duration (5 hours) is equal to exam duration.
        $quizactivity = $this->get_quiz_examactivity($now, $now + HOURSECS * 5, 0);
        $this->assertTrue($quizactivity->is_exam_activity());

        // Test case 3: Quiz duration (6 hours) is greater than exam duration.
        $quizactivity = $this->get_quiz_examactivity($now, $now + HOURSECS * 6, 0);
        $this->assertFalse($quizactivity->is_exam_activity());

        // Test case 4: Quiz has no timeopen.
        $quizactivity = $this->get_quiz_examactivity(0, $now + HOURSECS * 3, 0);
        $this->assertFalse($quizactivity->is_exam_activity());

        // Test case 5: Quiz has no timeclose.
        $quizactivity = $this->get_quiz_examactivity($now, 0, 0);
        $this->assertFalse($quizactivity->is_exam_activity());
    }

    /**
     * Test is_active_exam_activity method.
     *
     * @covers ::is_active_exam_activity
     */
    public function test_is_active_exam_activity(): void {
        $now = $this->clock->time();

        // Test case 1: Not an exam activity.
        $quizactivity = $this->get_quiz_examactivity(0, 0, 0);
        $this->assertFalse($quizactivity->is_active_exam_activity());

        // Test case 2: Is an exam activity, current time is after buffer start and before end time.
        $quizactivity = $this->get_quiz_examactivity($now, $now + HOURSECS * 3, 0);
        $this->assertTrue($quizactivity->is_active_exam_activity());

        // Test case 3: Is an exam activity, current time is before buffer start.
        $quizactivity = $this->get_quiz_examactivity($now + MINSECS * 20, $now + HOURSECS * 3, 0);
        $this->assertFalse($quizactivity->is_active_exam_activity());

        // Test case 4: Is an exam activity, current time is after end time.
        $quizactivity = $this->get_quiz_examactivity($now - HOURSECS * 4, $now - HOURSECS * 2, 0);
        $this->assertFalse($quizactivity->is_active_exam_activity());
    }

    /**
     * Test get_exam_start_time method.
     *
     * @covers ::get_exam_start_time
     */
    public function test_get_exam_start_time(): void {
        // Test that the method returns the timeopen value.
        $this->assertEquals($this->quiz->timeopen, $this->quizactivity->get_exam_start_time());
    }

    /**
     * Test get_exam_end_time method.
     *
     * @covers ::get_exam_end_time
     */
    public function test_get_exam_end_time(): void {

        // Test case 1: No extension.
        $expected = $this->quiz->timeclose + MINSECS * 10;
        $this->assertEquals($expected, $this->quizactivity->get_exam_end_time());

        // Test case 2: With extension.
        $reflection = new \ReflectionClass($this->quizactivity);
        $method = $reflection->getMethod('record_extension');
        $method->setAccessible(true);
        $method->invokeArgs($this->quizactivity, [30]);
        $expected = $this->quiz->timeclose + (MINSECS * 10) + (MINSECS * 30);
        $this->assertEquals($expected, $this->quizactivity->get_exam_end_time());
    }

    /**
     * Test can_extend_time method.
     *
     * @covers ::can_extend_time
     */
    public function test_can_extend_time(): void {
        // Set the current user to a student (should not have capability).
        $this->setUser($this->students[1]);
        $this->assertFalse($this->quizactivity->can_extend_time());

        // Set the current user to a teacher (should have capability).
        $this->setUser($this->teacher);
        $this->assertTrue($this->quizactivity->can_extend_time());
    }

    /**
     * Test students with existing user override.
     *
     * @covers ::apply_extension
     * @covers ::update_time_field
     * @covers ::get_current_extension
     */
    public function test_students_with_existing_user_override(): void {
        global $DB;
        $overridemanager = new override_manager($this->quiz, $this->context);

        // Create user overrides with different extensions.
        $overrides = [
            [
                'quiz' => $this->quiz->id,
                'userid' => $this->students[1]->id,
                'timeclose' => $this->quiz->timeclose + MINSECS * 30,
            ],
            [
                'quiz' => $this->quiz->id,
                'userid' => $this->students[2]->id,
                'timeclose' => $this->quiz->timeclose + MINSECS * 45,
                'timelimit' => $this->quiz->timelimit + MINSECS * 45,
            ],
        ];

        $overrideids = [];
        foreach ($overrides as $override) {
            $overrideids[] = $overridemanager->save_override($override);
        }

        // Apply extension as teacher.
        $this->setUser($this->teacher);
        $extension = 15;
        $this->quizactivity->apply_extension($extension);

        // Verify extensions for student 1 (no original timelimit).
        $updatedoverride = $DB->get_record('quiz_overrides', ['id' => $overrideids[0]]);
        $this->assertEquals($overrides[0]['timeclose'] + (MINSECS * $extension), $updatedoverride->timeclose);
        $this->assertEquals($this->quiz->timelimit + (MINSECS * 30) + (MINSECS * $extension), $updatedoverride->timelimit);

        // Verify extensions for student 2 (with original timelimit).
        $updatedoverride = $DB->get_record('quiz_overrides', ['id' => $overrideids[1]]);
        $this->assertEquals($overrides[1]['timeclose'] + (MINSECS * $extension), $updatedoverride->timeclose);
        $this->assertEquals($overrides[1]['timelimit'] + (MINSECS * $extension), $updatedoverride->timelimit);
    }

    /**
     * Test students with group overrides, including multiple group membership.
     *
     * @covers ::apply_extension
     * @covers ::get_best_override_settings
     * @covers ::get_current_extension
     */
    public function test_students_with_groups_override(): void {
        global $DB;

        // Add student 1 to group 2 for multiple group testing.
        groups_add_member($this->groups[2], $this->students[1]);

        // Create group overrides with different extensions.
        $overrides = [
            [
                'quiz' => $this->quiz->id,
                'groupid' => $this->groups[1]->id,
                'timeclose' => $this->quiz->timeclose + MINSECS * 30,
                'timelimit' => $this->quiz->timelimit + MINSECS * 30,
            ],
            [
                'quiz' => $this->quiz->id,
                'groupid' => $this->groups[2]->id,
                'timeclose' => $this->quiz->timeclose + MINSECS * 45,
                'timelimit' => $this->quiz->timelimit + MINSECS * 45,
            ],
        ];

        foreach ($overrides as $override) {
            $this->overridemanager->save_override($override);
        }

        // Apply extension as teacher.
        $this->setUser($this->teacher);
        $extension = 15;
        $this->quizactivity->apply_extension($extension);

        // Test cases for different student scenarios.
        $expectedoverrides = [
            // Student 1 (in both groups) should get best override (group 2).
            $this->students[1]->id => [
                'timeclose' => $overrides[1]['timeclose'] + (MINSECS * $extension),
                'timelimit' => $overrides[1]['timelimit'] + (MINSECS * $extension),
            ],
            // Student 2 (group 1 only).
            $this->students[2]->id => [
                'timeclose' => $overrides[0]['timeclose'] + (MINSECS * $extension),
                'timelimit' => $overrides[0]['timelimit'] + (MINSECS * $extension),
            ],
            // Students 3 and 4 (group 2 only).
            $this->students[3]->id => [
                'timeclose' => $overrides[1]['timeclose'] + (MINSECS * $extension),
                'timelimit' => $overrides[1]['timelimit'] + (MINSECS * $extension),
            ],
            $this->students[4]->id => [
                'timeclose' => $overrides[1]['timeclose'] + (MINSECS * $extension),
                'timelimit' => $overrides[1]['timelimit'] + (MINSECS * $extension),
            ],
        ];

        foreach ($expectedoverrides as $userid => $expected) {
            $override = $DB->get_record('quiz_overrides', ['quiz' => $this->quiz->id, 'userid' => $userid]);
            $this->assertEquals($expected['timeclose'], $override->timeclose);
            $this->assertEquals($expected['timelimit'], $override->timelimit);
        }
    }

    /**
     * Test student with both user and group overrides.
     *
     * @covers ::apply_extension
     * @covers ::get_current_extension
     */
    public function test_student_with_user_override_and_group_override(): void {
        global $DB;

        // Create overrides.
        $overrides = [
            'user' => [
                'quiz' => $this->quiz->id,
                'userid' => $this->students[1]->id,
                'timeclose' => $this->quiz->timeclose + MINSECS * 30,
                'timelimit' => $this->quiz->timelimit + MINSECS * 30,
            ],
            'group' => [
                'quiz' => $this->quiz->id,
                'groupid' => $this->groups[1]->id,
                'timeclose' => $this->quiz->timeclose + MINSECS * 45,
                'timelimit' => $this->quiz->timelimit + MINSECS * 45,
            ],
        ];

        foreach ($overrides as $override) {
            $this->overridemanager->save_override($override);
        }

        // Apply extension as teacher.
        $this->setUser($this->teacher);
        $extension = 15;
        $this->quizactivity->apply_extension($extension);

        // Verify user override takes precedence.
        $updatedoverride = $DB->get_record('quiz_overrides', ['quiz' => $this->quiz->id, 'userid' => $this->students[1]->id]);
        $this->assertEquals($overrides['user']['timeclose'] + (MINSECS * $extension), $updatedoverride->timeclose);
        $this->assertEquals($overrides['user']['timelimit'] + (MINSECS * $extension), $updatedoverride->timelimit);
    }

    /**
     * Test student with no existing overrides.
     *
     * @covers ::apply_extension
     * @covers ::create_group_override
     * @covers ::get_current_extension
     */
    public function test_student_with_no_override(): void {
        global $DB;

        // Create some group overrides to ensure they don't affect the test.
        $overrides = [
            [
                'quiz' => $this->quiz->id,
                'groupid' => $this->groups[1]->id,
                'timeclose' => $this->quiz->timeclose + MINSECS * 15,
                'timelimit' => $this->quiz->timelimit + MINSECS * 15,
            ],
            [
                'quiz' => $this->quiz->id,
                'groupid' => $this->groups[2]->id,
                'timeclose' => $this->quiz->timeclose + MINSECS * 30,
                'timelimit' => $this->quiz->timelimit + MINSECS * 30,
            ],
        ];

        foreach ($overrides as $override) {
            $this->overridemanager->save_override($override);
        }

        // Apply extension as teacher.
        $this->setUser($this->teacher);
        $extension = 15;
        $this->quizactivity->apply_extension($extension);

        // Verify exam guard group was created and override applied.
        $examguardgroupid = groups_get_group_by_name(
            $this->course->id,
            "Exam_guard_activity_{$this->cm->id}_extension_{$extension}"
        );
        $this->assertNotEmpty($examguardgroupid);

        $examguardgroupoverride = $DB->get_record('quiz_overrides', ['quiz' => $this->quiz->id, 'groupid' => $examguardgroupid]);
        $this->assertNotEmpty($examguardgroupoverride);
        $this->assertEquals($this->quiz->timeclose + (MINSECS * $extension), $examguardgroupoverride->timeclose);
        $this->assertEquals($this->quiz->timelimit + (MINSECS * $extension), $examguardgroupoverride->timelimit);
    }
}
