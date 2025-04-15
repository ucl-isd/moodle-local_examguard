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
 * Factory class for exam activities.
 *
 * @package    local_examguard
 * @copyright  2025 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Alex Yeung <k.yeung@ucl.ac.uk>
 */
class examactivityfactory {

    /**
     * Return exam activity object.
     *
     * @param int $cmid The course module id.
     * @param string $modname The module name.
     * @return examactivity
     * @throws \moodle_exception
     */
    public static function get_exam_activity(int $cmid, string $modname): examactivity {
        $fullclassname = __NAMESPACE__ . '\\' . $modname;
        if (!class_exists($fullclassname)) {
            throw new \moodle_exception('error:exam_activity_class_not_found', 'local_examguard', '', $modname);
        }

        return new $fullclassname($cmid);
    }
}
