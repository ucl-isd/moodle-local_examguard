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
use core_course_category;
use core\hook\output\before_standard_top_of_body_html_generation;
use core\notification;
use local_examguard\local\hook_callbacks;
use moodle_page;
use stdClass;

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
    /** @var core_course_category Course category. */
    private core_course_category $category;

    /** @var stdClass Course in the category with an exam quiz and an enrolled student. */
    private stdClass $course;

    /** @var stdClass Exam quiz open from 09:00 to 10:00. */
    private stdClass $quiz;

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

        $this->category = $this->getDataGenerator()->create_category();
        $this->course = $this->getDataGenerator()->create_course(['category' => $this->category->id]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');

        // Create the quiz as admin, as the exam check on creation only counts activities visible to the current user.
        $this->setAdminUser();
        $this->quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $this->course->id,
            'timeopen' => strtotime('2025-04-08 09:00:00'),
            'timeclose' => strtotime('2025-04-08 10:00:00'),
        ]);
        $this->setUser();
    }

    /**
     * Test the quiz creation starts the ban when the course has students.
     */
    public function test_quiz_creation_starts_ban(): void {
        global $DB;

        $this->assertTrue($DB->record_exists('local_examguard', ['courseid' => $this->course->id]));
    }

    /**
     * Test an editing role inherited from the course category bans course editing during an exam.
     */
    public function test_category_editing_role_is_banned_during_exam(): void {
        global $DB;

        // Teacher is enrolled without a course role and gets editing rights from the category.
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->course->id);
        $editingteacherid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        role_assign($editingteacherid, $teacher->id, context_coursecat::instance($this->category->id)->id);

        $coursecontext = context_course::instance($this->course->id);
        $this->assertTrue(manager::user_has_an_editing_role($this->course->id, $teacher->id));
        $this->assertTrue(has_capability('moodle/course:update', $coursecontext, $teacher));

        // Run the ban assignment for the course's enrolled users.
        manager::handle_users_roles($this->course->id, true);

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
        global $PAGE;

        // The ban started on quiz creation, before the teacher joins.
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->course->id, 'editingteacher');

        $coursecontext = context_course::instance($this->course->id);
        $this->assertTrue(has_capability('moodle/course:update', $coursecontext, $teacher));

        // Teacher views the page.
        $this->setUser($teacher);
        if ($isactivitypage) {
            $PAGE->set_cm(get_coursemodule_from_instance('quiz', $this->quiz->id), $this->course);
        } else {
            $PAGE->set_course($this->course);
        }
        $PAGE->set_pagetype($pagetype);
        hook_callbacks::before_standard_top_of_body_html_generation(
            new before_standard_top_of_body_html_generation($PAGE->get_renderer('core'), '')
        );

        $this->assertFalse(has_capability('moodle/course:update', $coursecontext, $teacher));
    }

    /**
     * Test a category editing user who is not enrolled in the course is released when the exam ends.
     *
     * @covers \local_examguard\local\hook_callbacks::before_standard_top_of_body_html_generation
     */
    public function test_unenrolled_category_editing_user_is_released_after_exam(): void {
        global $DB, $PAGE;

        // Teacher gets editing rights from the category only, with no course enrolment.
        $teacher = $this->getDataGenerator()->create_user();
        $editingteacherid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        role_assign($editingteacherid, $teacher->id, context_coursecat::instance($this->category->id)->id);
        $coursecontext = context_course::instance($this->course->id);
        $this->setUser($teacher);

        // Teacher views the course page during the exam.
        $this->view_course_page($this->course);
        $this->assertFalse(has_capability('moodle/course:update', $coursecontext, $teacher));

        // Teacher views the course page after the exam and buffer have ended.
        $this->mock_clock_with_frozen(strtotime('2025-04-08 10:30:00'));
        $PAGE = new moodle_page();
        $this->view_course_page($this->course);

        $this->assertFalse($DB->record_exists('local_examguard', ['courseid' => $this->course->id]));
        $this->assertTrue(has_capability('moodle/course:update', $coursecontext, $teacher));
    }

    /**
     * Test a site admin sees the banner during an exam but is not given the exam guard role.
     *
     * @covers \local_examguard\local\hook_callbacks::before_standard_top_of_body_html_generation
     */
    public function test_site_admin_sees_banner_and_can_edit_during_exam(): void {
        global $DB, $USER;

        $this->setAdminUser();
        $this->view_course_page($this->course);

        $coursecontext = context_course::instance($this->course->id);
        $this->assertNotEmpty(notification::fetch());
        $this->assertFalse(user_has_role_assignment(
            $USER->id,
            $DB->get_field('role', 'id', ['shortname' => 'localexamguard']),
            $coursecontext->id
        ));
        $this->assertTrue(has_capability('moodle/course:update', $coursecontext));
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

    /**
     * Run the page hook as if the current user viewed the course page.
     *
     * @param stdClass $course The course.
     */
    private function view_course_page(stdClass $course): void {
        global $PAGE;

        $PAGE->set_course($course);
        $PAGE->set_pagetype('course-view-topics');
        hook_callbacks::before_standard_top_of_body_html_generation(
            new before_standard_top_of_body_html_generation($PAGE->get_renderer('core'), '')
        );
    }
}
