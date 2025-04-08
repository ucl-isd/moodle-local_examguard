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
 * Configuration settings for local_examguard.
 *
 * @package    local_examguard
 * @copyright  2024 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Alex Yeung <k.yeung@ucl.ac.uk>
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_category('local_examguard_settings', new lang_string('pluginname', 'local_examguard')));
    $settingspage = new admin_settingpage('managelocalexamguard', new lang_string('settings:manage', 'local_examguard'));

    if ($ADMIN->fulltree) {
        // General settings.
        $settingspage->add(new admin_setting_heading('local_examguard_general_settings',
            get_string('settings:generalsettingsheader', 'local_examguard'),
            ''
        ));
        // Setting to enable/disable the plugin.
        $settingspage->add(new admin_setting_configcheckbox(
            'local_examguard/enabled',
            get_string('settings:enable', 'local_examguard'),
            get_string('settings:enable:desc', 'local_examguard'),
            '1'
        ));

        // Exam duration setting, default to 300 minutes, i.e. 5 hours.
        $settingspage->add(new admin_setting_configtext(
            'local_examguard/examduration',
            get_string('settings:examduration', 'local_examguard'),
            get_string('settings:examduration_desc', 'local_examguard'),
            300,
            PARAM_INT
        ));

        // Time buffer setting, default to 10 minutes.
        $settingspage->add(new admin_setting_configtext(
            'local_examguard/timebuffer',
            get_string('settings:timebuffer', 'local_examguard'),
            get_string('settings:timebuffer_desc', 'local_examguard'),
            10,
            PARAM_INT
        ));

        // Setting to enable/disable the plugin.
        $settingspage->add(new admin_setting_configcheckbox(
            'local_examguard/bulkextension',
            get_string('settings:bulkextension', 'local_examguard'),
            '',
            '1'
        ));
    }

    $ADMIN->add('localplugins', $settingspage);
}
