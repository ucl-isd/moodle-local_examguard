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
use local_examguard\manager;

/**
 * Class for local_examguard observer.
 *
 * @package    local_examguard
 * @copyright  2024 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Alex Yeung <k.yeung@ucl.ac.uk>
 */
class local_examguard_observer {
    /**
     * Refresh editing roles cache if a new role is created.
     *
     * @param \core\event\role_created|\core\event\role_deleted $event
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function role_created_or_deleted(\core\event\role_created | \core\event\role_deleted $event) {
        // Refresh editing roles cache.
        cache::make('local_examguard', 'editingroles')->purge();
        manager::get_editing_roles();
    }
}
