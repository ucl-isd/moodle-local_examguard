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
 * Exam activity class for quiz.
 *
 * @package    local_examguard
 * @copyright  2024 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Alex Yeung <k.yeung@ucl.ac.uk>
 */
class quiz extends examactivity {
    /**
     * Check if the quiz is an exam activity.
     *
     * @return bool
     */
    public function is_exam_activity(): bool {
        // Timeopen and timeclose are set.
        if ($this->activityinstance->timeopen != 0 && $this->activityinstance->timeclose != 0) {
            // Quiz is considered an exam activity if the time between open and close is less than configured exam duration.
            return ($this->activityinstance->timeclose - $this->activityinstance->timeopen) < $this->examduration;
        }

        return false;
    }

    /**
     * Check if the quiz is an active exam activity.
     *
     * @return bool
     */
    public function is_active_exam_activity(): bool {
        if ($this->is_exam_activity()) {
            $now = time();
            return ($this->activityinstance->timeopen - $this->timebuffer < $now &&
                $this->activityinstance->timeclose + $this->timebuffer > $now);
        }

        return false;
    }

    /**
     * Get the exam end time including buffer time (in unix timestamp).
     *
     * @return int
     */
    public function get_exam_end_time(): int {
        return $this->activityinstance->timeclose + $this->timebuffer;
    }
}
