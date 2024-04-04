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
    /** @var int Time buffer (20 minutes) before/after exam period. */
    const TIME_BUFFER = 60 * 20;

    /** @var int Exam duration less than this value (5 hours) will be counted as exam */
    const EXAM_DURATION = 3600 * 5;

    /**
     * Check if the quiz is an exam activity.
     *
     * @return bool
     */
    public function is_exam_activity(): bool {
        // Timeopen and timeclose are set.
        if ($this->activityinstance->timeopen != 0 && $this->activityinstance->timeclose != 0) {
            // Quiz is considered an exam activity if the time between open and close is less than 5 hours.
            return ($this->activityinstance->timeclose - $this->activityinstance->timeopen) < self::EXAM_DURATION;
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
            return ($this->activityinstance->timeopen - self::TIME_BUFFER < $now &&
                $this->activityinstance->timeclose + self::TIME_BUFFER > $now);
        }

        return false;
    }
}
