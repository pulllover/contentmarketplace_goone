<?php
/*
 * This file is part of Totara Learn
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
 * @package contentmarketplace_goone
 */

use core_phpunit\testcase;
use PHPUnit\Framework\Attributes\Group;

defined('MOODLE_INTERNAL') || die();

/**
 * Test the schema drift alignment upgrade step.
 */
#[Group('totara_contentmarketplace')]
class contentmarketplace_goone_upgrade_test extends testcase {

    public function test_user_map_userid_is_unique(): void {
        global $DB;

        $user = self::getDataGenerator()->create_user();
        $DB->insert_record('marketplace_goone_user_map', (object) ['userid' => $user->id, 'goone_userid' => 1]);

        $this->expectException(dml_write_exception::class);
        $DB->insert_record('marketplace_goone_user_map', (object) ['userid' => $user->id, 'goone_userid' => 2]);
    }

    public function test_fix_schema_drift_converges_drifted_schema(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/totara/contentmarketplace/contentmarketplaces/goone/db/upgradelib.php');

        $dbman = $DB->get_manager();

        // Recreate the drifted schema of old fresh installs: non-unique userid mapping
        // and a nullable webhook log message.
        $maptable = new xmldb_table('marketplace_goone_user_map');
        $dbman->drop_key($maptable, new xmldb_key('userid_fk', XMLDB_KEY_FOREIGN_UNIQUE, ['userid'], 'user', ['id'], 'cascade'));
        $dbman->add_key($maptable, new xmldb_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id'], 'cascade'));

        $logtable = new xmldb_table('marketplace_goone_webhook_logs');
        $message = new xmldb_field('message', XMLDB_TYPE_TEXT, null, null, null, null, null, 'id');
        $dbman->change_field_notnull($logtable, $message);

        // Drifted data: duplicate mappings and a NULL log message.
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $DB->insert_record('marketplace_goone_user_map', (object) ['userid' => $user1->id, 'goone_userid' => 1]);
        $DB->insert_record('marketplace_goone_user_map', (object) ['userid' => $user1->id, 'goone_userid' => 2]);
        $DB->insert_record('marketplace_goone_user_map', (object) ['userid' => $user2->id, 'goone_userid' => 3]);
        $DB->execute("INSERT INTO {marketplace_goone_webhook_logs} (message, timecreated) VALUES (NULL, 123)");

        contentmarketplace_goone_fix_schema_drift();

        // Duplicates removed, earliest mapping kept, other users untouched.
        $maps = $DB->get_records('marketplace_goone_user_map', null, 'userid ASC');
        self::assertCount(2, $maps);
        self::assertEquals([1, 3], array_values(array_map(fn($map) => (int) $map->goone_userid, $maps)));

        // The unique index is enforced again (index_exists ignores uniqueness, so
        // the real index metadata is inspected instead).
        $unique = false;
        foreach ($DB->get_indexes('marketplace_goone_user_map') as $index) {
            if ($index['columns'] === ['userid'] && !empty($index['unique'])) {
                $unique = true;
            }
        }
        self::assertTrue($unique);

        // The NULL message has been replaced and the column is NOT NULL again.
        self::assertSame('', $DB->get_field('marketplace_goone_webhook_logs', 'message', ['timecreated' => 123]));

        // Running the alignment again is a no-op.
        contentmarketplace_goone_fix_schema_drift();
        self::assertCount(2, $DB->get_records('marketplace_goone_user_map'));
    }

    /**
     * Link a scorm course module to a Go1 learning object the way the plugin does on creation.
     */
    private function link_to_marketplace(int $cmid, int $external_id, string $component = 'contentmarketplace_goone'): void {
        global $DB;
        $loid = $DB->insert_record('marketplace_goone_learning_object', (object) ['external_id' => $external_id]);
        $DB->insert_record('totara_contentmarketplace_course_module_source', (object) [
            'cm_id' => $cmid,
            'learning_object_id' => $loid,
            'marketplace_component' => $component,
        ]);
    }

    public function test_fix_single_activity_skipview_changes_only_go1_scorms_in_single_activity_courses(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/totara/contentmarketplace/contentmarketplaces/goone/db/upgradelib.php');
        require_once($CFG->dirroot . '/mod/scorm/locallib.php');
        self::setAdminUser();
        $generator = self::getDataGenerator();

        $single_go1 = $generator->create_course(['format' => 'singleactivity', 'activitytype' => 'scorm']);
        $go1_always = $generator->create_module('scorm', ['course' => $single_go1, 'skipview' => SCORM_SKIPVIEW_ALWAYS]);
        $this->link_to_marketplace($go1_always->cmid, 101);

        $single_go1_first = $generator->create_course(['format' => 'singleactivity', 'activitytype' => 'scorm']);
        $go1_never = $generator->create_module('scorm', ['course' => $single_go1_first, 'skipview' => SCORM_SKIPVIEW_NEVER]);
        $this->link_to_marketplace($go1_never->cmid, 102);

        $single_plain = $generator->create_course(['format' => 'singleactivity', 'activitytype' => 'scorm']);
        $plain_always = $generator->create_module('scorm', ['course' => $single_plain, 'skipview' => SCORM_SKIPVIEW_ALWAYS]);

        $single_other = $generator->create_course(['format' => 'singleactivity', 'activitytype' => 'scorm']);
        $other_always = $generator->create_module('scorm', ['course' => $single_other, 'skipview' => SCORM_SKIPVIEW_ALWAYS]);
        $this->link_to_marketplace($other_always->cmid, 103, 'contentmarketplace_linkedin');

        $topics = $generator->create_course(['format' => 'topics']);
        $go1_topics_always = $generator->create_module('scorm', ['course' => $topics, 'skipview' => SCORM_SKIPVIEW_ALWAYS]);
        $this->link_to_marketplace($go1_topics_always->cmid, 104);

        contentmarketplace_goone_fix_single_activity_skipview();

        self::assertEquals(SCORM_SKIPVIEW_FIRST, $DB->get_field('scorm', 'skipview', ['id' => $go1_always->id]));
        self::assertEquals(SCORM_SKIPVIEW_NEVER, $DB->get_field('scorm', 'skipview', ['id' => $go1_never->id]));
        self::assertEquals(SCORM_SKIPVIEW_ALWAYS, $DB->get_field('scorm', 'skipview', ['id' => $plain_always->id]));
        self::assertEquals(SCORM_SKIPVIEW_ALWAYS, $DB->get_field('scorm', 'skipview', ['id' => $other_always->id]));
        self::assertEquals(SCORM_SKIPVIEW_ALWAYS, $DB->get_field('scorm', 'skipview', ['id' => $go1_topics_always->id]));
    }
}
