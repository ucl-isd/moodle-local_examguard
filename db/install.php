<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

use local_examguard\manager;

/**
 * Set up the ExamGuard plugin along with the installation of the plugin.
 *
 * @package    local_examguard
 * @copyright  2024 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Alex Yeung <k.yeung@ucl.ac.uk>
 */

/**
 * Extra steps to complete the installation of the ExamGuard plugin.
 *
 * @return bool
 * @throws moodle_exception
 */
function xmldb_local_examguard_install() {
    global $DB;

    // Check if the role examguard exists.
    $role = $DB->get_record('role', ['shortname' => 'localexamguard']);

    // Create the role if it does not exist.
    if (empty($role)) {
        // Create the role.
        $roleid = create_role('Exam Guard', 'localexamguard', 'Used by the ExamGuard plugin to manage course editing.');

        // Throw an exception if the role was not created.
        if (!$roleid) {
            throw new \moodle_exception('error:create_role', 'local_examguard');
        }

        // Only allow the role to be assigned in the course context.
        set_role_contextlevels($roleid, [CONTEXT_COURSE]);

        // Assign prohibit capabilities to the role.
        foreach (manager::EDIT_PERMISSIONS as $capability) {
            assign_capability($capability, CAP_PROHIBIT, $roleid, \context_system::instance()->id);
        }
    }

    return true;
}
