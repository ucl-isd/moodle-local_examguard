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

use advanced_testcase;
use context_course;
use context_coursecat;
use core\hook\output\before_standard_top_of_body_html_generation;
use local_examguard\local\hook_callbacks;

/**
 * Unit tests for the manager class.
 *
 * @package    local_examguard
 * @copyright  2026 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Alex Yeung <k.yeung@ucl.ac.uk>
 * @covers     \local_examguard\manager
 */
final class manager_test extends advanced_testcase {
    /**
     * Setup test data.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        // Freeze the clock inside the exam window.
        $this->mock_clock_with_frozen(strtotime('2025-04-08 09:05:00'));
        set_config('enabled', 1, 'local_examguard');
        set_config('timebuffer', 10, 'local_examguard');
        set_config('examduration', 300, 'local_examguard');
    }

    /**
     * Test an editing role inherited from the course category bans course editing during an exam.
     */
    public function test_category_editing_role_is_banned_during_exam(): void {
        global $DB;

        $category = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'timeopen' => strtotime('2025-04-08 09:00:00'),
            'timeclose' => strtotime('2025-04-08 10:00:00'),
        ]);

        // Teacher is enrolled without a course role and gets editing rights from the category.
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id);
        $editingteacherid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        role_assign($editingteacherid, $teacher->id, context_coursecat::instance($category->id)->id);

        $coursecontext = context_course::instance($course->id);
        $this->assertTrue(manager::user_has_an_editing_role($course->id, $teacher->id));
        $this->assertTrue(has_capability('moodle/course:update', $coursecontext, $teacher));

        // Run as the teacher, the exam activity is not visible to a not-logged-in user on Moodle 5.x.
        $this->setUser($teacher);
        [$editingbanned] = manager::check_course_exam_status($course->id);

        $this->assertTrue($editingbanned);
        $this->assertTrue(user_has_role_assignment(
            $teacher->id,
            $DB->get_field('role', 'id', ['shortname' => 'localexamguard']),
            $coursecontext->id
        ));
        $this->assertFalse(has_capability('moodle/course:update', $coursecontext, $teacher));
    }

    /**
     * Test an editing user who joins after the ban started is banned when viewing a course or activity page.
     *
     * @covers \local_examguard\local\hook_callbacks::before_standard_top_of_body_html_generation
     * @dataProvider page_provider
     * @param string $pagetype The page type viewed.
     * @param bool $isactivitypage Whether the page is the exam activity page.
     */
    public function test_editing_user_joining_mid_exam_is_banned_on_page_view(string $pagetype, bool $isactivitypage): void {
        global $DB, $PAGE;

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'timeopen' => strtotime('2025-04-08 09:00:00'),
            'timeclose' => strtotime('2025-04-08 10:00:00'),
        ]);

        // The ban starts before the teacher joins.
        $DB->insert_record('local_examguard', ['courseid' => $course->id, 'timecreated' => time()]);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        $coursecontext = context_course::instance($course->id);
        $this->assertTrue(has_capability('moodle/course:update', $coursecontext, $teacher));

        // Teacher views the page.
        $this->setUser($teacher);
        if ($isactivitypage) {
            $PAGE->set_cm(get_coursemodule_from_instance('quiz', $quiz->id), $course);
        } else {
            $PAGE->set_course($course);
        }
        $PAGE->set_pagetype($pagetype);
        hook_callbacks::before_standard_top_of_body_html_generation(
            new before_standard_top_of_body_html_generation($PAGE->get_renderer('core'), '')
        );

        $this->assertFalse(has_capability('moodle/course:update', $coursecontext, $teacher));
    }

    /**
     * Data provider for test_editing_user_joining_mid_exam_is_banned_on_page_view.
     *
     * @return array
     */
    public static function page_provider(): array {
        return [
            'course page' => ['course-view-topics', false],
            'activity page' => ['mod-quiz-view', true],
        ];
    }
}
