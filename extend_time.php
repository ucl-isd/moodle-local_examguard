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

/**
 * Page to extend the time for an exam activity.
 *
 * @package    local_examguard
 * @copyright  2024 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Alex Yeung <k.yeung@ucl.ac.uk>
 */

use local_examguard\examactivity\examactivityfactory;

require_once('../../config.php');
require_once('classes/form/extend_time_form.php');

// Get required and optional parameters.
$courseid = required_param('courseid', PARAM_INT);
$cmid = required_param('cmid', PARAM_INT);

// Fetch course and course module.
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$cm = get_coursemodule_from_id('', $cmid, $courseid)
    ?? throw new moodle_exception('invalidcoursemodule', 'error');
$context = context_module::instance($cm->id);

// Require login and check capability.
require_login($course, false, $cm);
require_capability('mod/' . $cm->modname . ':manageoverrides', $context);

// Setup page.
$PAGE->set_url(new moodle_url('/local/examguard/extend_time.php', ['courseid' => $courseid, 'cmid' => $cmid]));
$PAGE->set_context($context);
$PAGE->set_title($PAGE->set_heading(get_string('label:extend_time_button', 'local_examguard')));
$PAGE->activityheader->disable();

// Define return URL and create form.
$returnurl = new moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cmid]);
$mform = new \local_examguard\extend_time_form($PAGE->url);

echo $OUTPUT->header();

// Get the exam activity.
$examactivity = examactivityfactory::get_exam_activity($cm->id, $cm->modname);

if (get_config('local_examguard', 'bulkextension') !== '1') {
    \core\notification::error(get_string('error:bulk_extension_not_enabled', 'local_examguard'));
} else if (!$examactivity->is_active_exam_activity()) {
    // Do not allow extends time for non-active exam activities.
    \core\notification::error(get_string('error:not_active_exam_activity', 'local_examguard'));
} else {
    $currentextension = $examactivity->get_latest_extension();

    // A time extension is set, load it into the form field.
    if ($currentextension > 0) {
        $mform->set_data(['extendtime' => $currentextension]);
    }

    // Handle form submission.
    if ($mform->is_cancelled()) {
        redirect($returnurl);
    } else if ($fromform = $mform->get_data()) {
        // Verify sesskey for security.
        require_sesskey();
        $examactivity->apply_extension($fromform->extendtime);
        $mform->set_data(['extendtime' => $examactivity->get_latest_extension()]);
        \core\notification::success(get_string('notification:update_success', 'local_examguard'));
    }
    $mform->display();
}
echo $OUTPUT->footer();
