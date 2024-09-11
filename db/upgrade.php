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
 * Keeps track of upgrades to the advwork module
 *
 * @package    mod_advwork
 * @category   upgrade
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Performs upgrade of the database structure and data
 *
 * advwork supports upgrades from version 1.9.0 and higher only. During 1.9 > 2.0 upgrade,
 * there are significant database changes.
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool result
 */
function xmldb_advwork_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2016022200) {
        // Add field submissionfiletypes to the table advwork.
        $table = new xmldb_table('advwork');
        $field = new xmldb_field('submissionfiletypes', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'nattachments');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add field overallfeedbackfiletypes to the table advwork.
        $field = new xmldb_field('overallfeedbackfiletypes',
                XMLDB_TYPE_CHAR, '255', null, null, null, null, 'overallfeedbackfiles');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2016022200, 'advwork');
    }

    if ($oldversion < 2018062400) {

        // Define table advwork_overall_grades to be created.
        $table = new xmldb_table('advwork_overall_grades');

        // Adding fields to table advwork_overall_grades.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('overallsubmissiongrade', XMLDB_TYPE_NUMBER, '10, 5', null, null, null, null);
        $table->add_field('overallassessmentgrade', XMLDB_TYPE_NUMBER, '10, 5', null, null, null, null);
        $table->add_field('continuitysessionlevel', XMLDB_TYPE_NUMBER, '10, 5', null, null, null, null);
        $table->add_field('continuitychunks', XMLDB_TYPE_NUMBER, '10, 5', null, null, null, null);
        $table->add_field('stability', XMLDB_TYPE_NUMBER, '10, 5', null, null, null, null);
        $table->add_field('reliability', XMLDB_TYPE_NUMBER, '10, 5', null, null, null, null);
        $table->add_field('timegraded', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table advwork_overall_grades.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('course_fk', XMLDB_KEY_FOREIGN, array('courseid'), 'course', array('id'));
        $table->add_key('user_fk', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));
        $table->add_key('courseuser', XMLDB_KEY_UNIQUE, array('courseid', 'userid'));

        // Conditionally launch create table for advwork_overall_grades.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // advwork savepoint reached.
        upgrade_mod_savepoint(true, 2018062400, 'advwork');
    }

    if ($oldversion < 2018062400) {

        // Define table advwork_domain_values to be created.
        $table = new xmldb_table('advwork_domain_values');

        // Adding fields to table advwork_domain_values.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('value', XMLDB_TYPE_NUMBER, '10, 5', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table advwork_domain_values.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('course_fk', XMLDB_KEY_FOREIGN, array('courseid'), 'course', array('id'));

        // Conditionally launch create table for advwork_domain_values.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // advwork savepoint reached.
        upgrade_mod_savepoint(true, 2018062400, 'advwork');
    }

    if ($oldversion < 2018062401) {

        // Define table advwork_capabilities to be created.
        $table = new xmldb_table('advwork_capabilities');

        // Adding fields to table advwork_capabilities.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);

        // Adding keys to table advwork_capabilities.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for advwork_capabilities.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // advwork savepoint reached.
        upgrade_mod_savepoint(true, 2018062401, 'advwork');
    }

    if ($oldversion < 2018062402) {

        // Define table advwork_student_models to be created.
        $table = new xmldb_table('advwork_student_models');

        // Adding fields to table advwork_student_models.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('advworkid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('capabilityid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('domainvalueid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('probability', XMLDB_TYPE_NUMBER, '10, 5', null, XMLDB_NOTNULL, null, null);
        $table->add_field('capabilityoverallgrade', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('capabilityoverallvalue', XMLDB_TYPE_NUMBER, '10, 5', null, null, null, null);
        $table->add_field('iscumulated', XMLDB_TYPE_CHAR, null, null, null, null, null);

        // Adding keys to table advwork_student_models.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('course_fk', XMLDB_KEY_FOREIGN, array('courseid'), 'course', array('id'));
        $table->add_key('advwork_fk', XMLDB_KEY_FOREIGN, array('advworkid'), 'advwork', array('id'));
        $table->add_key('user_fk', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));
        $table->add_key('domainvalue_fk', XMLDB_KEY_FOREIGN, array('domainvalueid'), 'advwork_domain_values', array('id'));
        $table->add_key('capability_fk', XMLDB_KEY_FOREIGN, array('capabilityid'), 'advwork_capabilities', array('id'));

        // Conditionally launch create table for advwork_student_models.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // advwork savepoint reached.
        upgrade_mod_savepoint(true, 2018062402, 'advwork');
    }

    if ($oldversion < 2018062403) {

        // Define table advwork_best_peer to be created.
        $table = new xmldb_table('advwork_best_peer');

        // Adding fields to table advwork_best_peer.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('advworkid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('peerid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('grade', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('max', XMLDB_TYPE_NUMBER, '10, 5', null, null, null, null);
        $table->add_field('value', XMLDB_TYPE_NUMBER, '10, 5', null, null, null, null);

        // Adding keys to table advwork_best_peer.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('course_fk', XMLDB_KEY_FOREIGN, array('courseid'), 'course', array('id'));
        $table->add_key('advwork_fk', XMLDB_KEY_FOREIGN, array('advworkid'), 'advwork', array('id'));
        $table->add_key('user_fk', XMLDB_KEY_FOREIGN, array('peerid'), 'user', array('id'));

        // Conditionally launch create table for advwork_best_peer.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // advwork savepoint reached.
        upgrade_mod_savepoint(true, 2018062403, 'advwork');
    }


    // Moodle v3.1.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.2.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.3.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.4.0 release upgrade line.
    // Put any upgrade step following this.

    return true;
}
