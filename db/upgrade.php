<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2018 onwards Totara Learning Solutions LTD
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Simon Coggins <simon.coggins@totaralearning.com>
 * @package contentmarketplace_goone
 */

defined('MOODLE_INTERNAL') || die;

/**
 * GO1 content marketplace plugin upgrade.
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool always true
 */
function xmldb_contentmarketplace_goone_upgrade($oldversion) {
    global $CFG, $DB;
    require_once(__DIR__ . '/upgradelib.php');

    $dbman = $DB->get_manager();

    // Totara 13.0 release line.

    if ($oldversion < 2021092300) {

        // Define table marketplace_goone_learning_object to be created.
        $table = new xmldb_table('marketplace_goone_learning_object');

        // Adding fields to table marketplace_goone_learning_object.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('external_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table marketplace_goone_learning_object.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table marketplace_goone_learning_object.
        $table->add_index('external_id_index', XMLDB_INDEX_UNIQUE, array('external_id'));

        // Conditionally launch create table for marketplace_goone_learning_object.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        contentmarketplace_goone_create_course_module_source_records();

        // Goone savepoint reached.
        upgrade_plugin_savepoint(true, 2021092300, 'contentmarketplace', 'goone');
    }


    if ($oldversion < 2026061700) {
        // Enable GO1 activity creation workflow on upgrade.
        $workflow = contentmarketplace_goone\workflow\mod_contentmarketplace\create_marketplace_activity\goone::instance();
        if (!$workflow->is_enabled()) {
            $workflow->enable();
        }

        // Define table marketplace_goone_user_map to be created.
        $table = new xmldb_table('marketplace_goone_user_map');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('goone_userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        // Add keys and indexes.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('userid_fk', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'), 'cascade');
        // Conditionally create table.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Update collection name setting in config to match naming to that in API V3.
        $setting = get_config('contentmarketplace_goone', 'content_settings_creators');
        $mapping = [
            'all' => 'free',
            'subscribed' => 'subscribe'
        ];
        if (!empty($setting) && array_key_exists($setting, $mapping)) {
            set_config('content_settings_creators', $mapping[$setting], 'contentmarketplace_goone');
        }

        // Add fields to table {marketplace_goone_learning_object} to track expired content.
        $table = new xmldb_table('marketplace_goone_learning_object');
        $fields = [];
        $fields[] = new xmldb_field('retired_time', XMLDB_TYPE_INTEGER, '10');
        $fields[] = new xmldb_field('retired_actioned', XMLDB_TYPE_CHAR, '255');
        $fields[] = new xmldb_field('removed_time', XMLDB_TYPE_INTEGER, '10');
        $fields[] = new xmldb_field('removed_actioned', XMLDB_TYPE_CHAR, '255');
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Define table marketplace_goone_user_map to be created.
        $table = new xmldb_table('marketplace_goone_webhook_logs');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('message', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        // Add keys and indexes.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        // Conditionally create table.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Goone savepoint reached.
        upgrade_plugin_savepoint(true, 2026061700, 'contentmarketplace', 'goone');
    }


    if ($oldversion < 2026082702) {
        // Align the schema between fresh installs and upgraded sites: NOT NULL webhook
        // log messages and a unique userid in the Go1 user mapping table.
        contentmarketplace_goone_fix_schema_drift();

        // Goone savepoint reached.
        upgrade_plugin_savepoint(true, 2026082702, 'contentmarketplace', 'goone');
    }


    if ($oldversion < 2026082705) {
        // Manual course creation is off by default, the course creation flow redirects to content curation.
        set_config(
            'create_course_manual',
            \contentmarketplace_goone\helper::CREATE_COURSE_MANUAL_DISABLED,
            'contentmarketplace_goone'
        );

        // Goone savepoint reached.
        upgrade_plugin_savepoint(true, 2026082705, 'contentmarketplace', 'goone');
    }


    if ($oldversion < 2026090400) {
        // Go1 SCORMs in single activity courses skip the view page on first access only.
        contentmarketplace_goone_fix_single_activity_skipview();

        // Goone savepoint reached.
        upgrade_plugin_savepoint(true, 2026090400, 'contentmarketplace', 'goone');
    }

    return true;
}
