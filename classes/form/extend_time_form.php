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
defined('MOODLE_INTERNAL') || die();

use moodleform;
require_once($CFG->libdir . '/formslib.php');

/**
 * Form to extend the time for an exam activity.
 *
 * @package    local_examguard
 * @copyright  2025 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Alex Yeung <k.yeung@ucl.ac.uk>
 */
class extend_time_form extends moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;

        // Add the time field.
        $mform->addElement('text', 'extendtime', get_string('label:extend_time', 'local_examguard'));
        $mform->setType('extendtime', PARAM_INT);
        $mform->addRule('extendtime', get_string('required'), 'required', null, 'client');
        $mform->addRule('extendtime', get_string('error:integer', 'local_examguard'), 'regex', '/^[0-9]+$/', 'client');
        $mform->addRule('extendtime', get_string('error:maxlength', 'local_examguard'), 'maxlength', 3, 'client');
        $mform->addHelpButton('extendtime', 'label:extend_time', 'local_examguard');

        // Add the submit button.
        $this->add_action_buttons(false, get_string('submit'));
    }
}
