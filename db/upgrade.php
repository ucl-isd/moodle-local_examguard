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

/**
 * Plugin upgrade steps are defined here.
 *
 * @package     local_examguard
 * @category    upgrade
 * @copyright   2024 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author      Alex Yeung <k.yeung@ucl.ac.uk>
 */

/**
 * Execute local_examguard upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_examguard_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2025040900) {
        // Define table local_examguard_extension_history to be created.
        $table = new xmldb_table('local_examguard_extension_history');

        // Adding fields to table local_examguard_extension_history.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('extensionminutes', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table local_examguard_extension_history.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table local_examguard_extension_history.
        $table->add_index('idx_cmid', XMLDB_INDEX_NOTUNIQUE, ['cmid']);
        $table->add_index('idx_usermodified', XMLDB_INDEX_NOTUNIQUE, ['usermodified']);
        $table->add_index('idx_timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);

        // Conditionally launch create table for local_examguard_extension_history.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Examguard savepoint reached.
        upgrade_plugin_savepoint(true, 2025040900, 'local', 'examguard');
    }

    if ($oldversion < 2025042500) {

        // Define table local_examguard_overrides to be created.
        $table = new xmldb_table('local_examguard_overrides');

        // Adding fields to table local_examguard_overrides.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('overrideid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('extensionminutes', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, null);
        $table->add_field('ori_override_data', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table local_examguard_overrides.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table local_examguard_overrides.
        $table->add_index('idx_cmid', XMLDB_INDEX_NOTUNIQUE, ['cmid']);
        $table->add_index('idx_overrideid', XMLDB_INDEX_NOTUNIQUE, ['overrideid']);
        $table->add_index('idx_usermodified', XMLDB_INDEX_NOTUNIQUE, ['usermodified']);
        $table->add_index('idx_cmid_overrideid', XMLDB_INDEX_NOTUNIQUE, ['cmid', 'overrideid']);
        $table->add_index('idx_timemodified', XMLDB_INDEX_NOTUNIQUE, ['timemodified']);

        // Conditionally launch create table for local_examguard_overrides.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Examguard savepoint reached.
        upgrade_plugin_savepoint(true, 2025042500, 'local', 'examguard');
    }

    return true;
}
