<?php
/**
 * This file is part of Totara Learn
 *
 * Copyright (C) 2021 onwards Totara Learning Solutions LTD
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
 * @author Mark Metcalfe <mark.metcalfe@totaralearning.com>
 * @package contentmarketplace_goone
 */

/**
 * Align the schema between fresh installs and upgraded sites.
 *
 * Historically install.xml allowed NULL webhook log messages and a non-unique userid
 * in the Go1 user mapping table, while upgraded sites got a NOT NULL message column.
 * This converges both cohorts: NOT NULL message, unique userid mapping (duplicate
 * mappings are removed first, keeping the earliest one per user).
 */
function contentmarketplace_goone_fix_schema_drift(): void {
    global $DB;

    $dbman = $DB->get_manager();

    // The webhook log message is always written, enforce NOT NULL everywhere.
    $table = new xmldb_table('marketplace_goone_webhook_logs');
    $field = new xmldb_field('message', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null, 'id');
    if ($dbman->field_exists($table, $field)) {
        $DB->execute("UPDATE {marketplace_goone_webhook_logs} SET message = '' WHERE message IS NULL");
        $dbman->change_field_notnull($table, $field);
    }

    // A Totara user must only ever map to one Go1 user: make the userid key unique.
    // Note: index_exists()/find_index_name() match on columns only and ignore uniqueness,
    // so the actual index metadata has to be inspected here.
    $table = new xmldb_table('marketplace_goone_user_map');
    $has_unique_userid_index = false;
    foreach ($DB->get_indexes('marketplace_goone_user_map') as $index) {
        if ($index['columns'] === ['userid'] && !empty($index['unique'])) {
            $has_unique_userid_index = true;
            break;
        }
    }
    if (!$has_unique_userid_index) {
        // Remove duplicate mappings first, keeping the earliest one per user.
        $duplicates = $DB->get_records_sql("
            SELECT id
              FROM {marketplace_goone_user_map}
             WHERE id NOT IN (
                       SELECT MIN(id)
                         FROM {marketplace_goone_user_map}
                     GROUP BY userid
                   )
        ");
        if ($duplicates) {
            $DB->delete_records_list('marketplace_goone_user_map', 'id', array_keys($duplicates));
        }

        $oldkey = new xmldb_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id'], 'cascade');
        $dbman->drop_key($table, $oldkey);
        $newkey = new xmldb_key('userid_fk', XMLDB_KEY_FOREIGN_UNIQUE, ['userid'], 'user', ['id'], 'cascade');
        $dbman->add_key($table, $newkey);
    }
}

function contentmarketplace_goone_create_course_module_source_records(): void {
    global $DB;

    $transaction = $DB->start_delegated_transaction();

    $source_prefix = 'content-marketplace://goone/';
    $learning_object_field = $DB->sql_concat("'{$source_prefix}'", 'learning_object.external_id');

    // Stupid extra stuff needed for Mssql for some reason
    $mssql_extra = $DB instanceof sqlsrv_native_moodle_database ? 'OFFSET 0 ROWS' : '';
    $source_substring = $DB->sql_cast_char2int(
        $DB->sql_substr('source', strlen($source_prefix) + 1)
    );
    $DB->execute("
        INSERT INTO {marketplace_goone_learning_object} (external_id)
        SELECT {$source_substring}
        FROM (
            SELECT DISTINCT source
            FROM {files} files
            LEFT JOIN {marketplace_goone_learning_object} learning_object ON source = {$learning_object_field}
            WHERE component = 'mod_scorm'
              AND filearea = 'package'
              AND source LIKE '{$source_prefix}%'
              AND learning_object.id IS NULL
            ORDER BY source
            {$mssql_extra}
        ) AS source        
    ");

    $DB->execute("
        INSERT INTO {totara_contentmarketplace_course_module_source} (cm_id, learning_object_id, marketplace_component)
        SELECT context.instanceid, learning_object.id, 'contentmarketplace_goone'
        FROM {marketplace_goone_learning_object} learning_object
        INNER JOIN {files} files ON files.source = {$learning_object_field}
        INNER JOIN {context} context ON context.id = files.contextid
        LEFT JOIN {totara_contentmarketplace_course_module_source} cm_source
           ON cm_source.learning_object_id = learning_object.id
          AND cm_source.marketplace_component = 'contentmarketplace_goone'
          AND cm_source.cm_id = context.instanceid
        WHERE cm_source.id IS NULL
        AND context.contextlevel = " . CONTEXT_MODULE . "
        ORDER BY context.instanceid
    ");

    $transaction->allow_commit();
}

/**
 * Change Go1 SCORM activities in single activity courses from skipping the view page always to
 * skipping it on first access only.
 *
 * Single activity courses have no course page to return to when the SCORM pop-up closes, so a
 * SCORM that always skips its view page relaunches the pop-up as soon as it is closed.
 */
function contentmarketplace_goone_fix_single_activity_skipview(): void {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/scorm/locallib.php');

    $DB->execute("
        UPDATE {scorm}
           SET skipview = :first
         WHERE skipview = :always
           AND id IN (
               SELECT cm.instance
                 FROM {course_modules} cm
                 JOIN {modules} m ON m.id = cm.module AND m.name = 'scorm'
                 JOIN {course} c ON c.id = cm.course AND c.format = 'singleactivity'
                 JOIN {totara_contentmarketplace_course_module_source} src
                   ON src.cm_id = cm.id AND src.marketplace_component = 'contentmarketplace_goone'
           )
    ", ['first' => SCORM_SKIPVIEW_FIRST, 'always' => SCORM_SKIPVIEW_ALWAYS]);
}
