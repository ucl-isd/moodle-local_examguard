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

    /** @var int Time buffer before/after exam period */
    public int $timebuffer;

    /** @var int Exam duration */
    public int $examduration;

    /**
     * Constructor.
     *
     * @param \stdClass $activityinstance The activity instance.
     * @throws \dml_exception
     */
    public function __construct(\stdClass $activityinstance) {
        // Set activity instance.
        $this->activityinstance = $activityinstance;

        // Get time buffer and exam duration settings in seconds.
        $this->timebuffer = get_config('local_examguard', 'timebuffer') * 60;
        $this->examduration = get_config('local_examguard', 'examduration') * 60;
    }

    /**
     * Check if the activity is an exam activity.
     *
     * @return bool
     */
    abstract public function is_exam_activity(): bool;

    /**
     * Check if the activity is an active exam activity.
     *
     * @return bool
     */
    abstract public function is_active_exam_activity(): bool;

    /**
     * Get the exam end time including buffer time.
     *
     * @return int
     */
    abstract public function get_exam_end_time(): int;
}
