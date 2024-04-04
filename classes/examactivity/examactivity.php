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
    protected $activityinstance;

    /**
     * Constructor.
     *
     * @param \stdClass $activityinstance The activity instance.
     */
    public function __construct(\stdClass $activityinstance) {
        $this->activityinstance = $activityinstance;
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
}
