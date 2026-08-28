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

use contentmarketplace_goone\helper;
use contentmarketplace_goone\sync_action\sync_learning_objects;
use contentmarketplace_goone\testing\generator;
use core_phpunit\testcase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

defined('MOODLE_INTERNAL') || die();

/**
 * Test the content sync action's success/failure reporting.
 */
#[CoversClass(sync_learning_objects::class)]
#[Group('totara_contentmarketplace')]
class contentmarketplace_goone_sync_learning_objects_test extends testcase {

    protected function setUp(): void {
        parent::setUp();
        helper::$config = null;
    }

    protected function tearDown(): void {
        helper::$config = null;
        parent::tearDown();
    }

    /**
     * @param string $collections Comma separated collection config
     * @return sync_learning_objects Sync action wired to the mock API
     */
    private function make_configured_sync(string $collections): sync_learning_objects {
        set_config('create_courses', 1, 'contentmarketplace_goone');
        set_config('course_category', 1, 'contentmarketplace_goone');
        set_config('course_type', helper::CREATE_COURSE_SINGLE, 'contentmarketplace_goone');
        set_config('sync_collections', $collections, 'contentmarketplace_goone');

        $sync = new sync_learning_objects();
        $reflection = new ReflectionProperty(sync_learning_objects::class, 'api');
        $reflection->setValue($sync, generator::instance()->get_mock_api());
        return $sync;
    }

    public function test_invoke_skipped_when_not_configured(): void {
        $sync = new sync_learning_objects();
        self::assertTrue($sync->is_skipped());

        $sync->invoke();

        self::assertFalse(get_config('contentmarketplace_goone', 'last_synced_new_content'));
    }

    public function test_successful_run_stamps_last_synced(): void {
        // The mocked custom collection is empty, so the run succeeds with nothing to do.
        $sync = $this->make_configured_sync('custom');

        $sync->invoke();

        self::assertNotEmpty(get_config('contentmarketplace_goone', 'last_synced_new_content'));
    }

    public function test_failed_run_throws_and_does_not_stamp_last_synced(): void {
        // The "free" collection has no mocked listing endpoint, so the API layer throws.
        $sync = $this->make_configured_sync('free');

        $thrown = null;
        try {
            $sync->invoke();
        } catch (moodle_exception $e) {
            $thrown = $e;
        }

        self::assertNotNull($thrown, 'A failed sync run must throw so the scheduled task reports the failure');
        self::assertStringContainsString('Go1 content sync failed', $thrown->getMessage());
        self::assertStringContainsString('free', $thrown->getMessage());
        // The last-synced marker must not move on failure.
        self::assertFalse(get_config('contentmarketplace_goone', 'last_synced_new_content'));
    }

    public function test_partial_failure_still_processes_other_collections_then_throws(): void {
        // "custom" succeeds (mocked, empty), "free" fails (unmocked): the run must
        // process everything it can and still end in a visible failure.
        $sync = $this->make_configured_sync('custom,free');

        $thrown = null;
        try {
            $sync->invoke();
        } catch (moodle_exception $e) {
            $thrown = $e;
        }

        self::assertNotNull($thrown);
        self::assertStringContainsString('free', $thrown->getMessage());
        self::assertStringNotContainsString('collection custom', $thrown->getMessage());
        self::assertFalse(get_config('contentmarketplace_goone', 'last_synced_new_content'));
    }
}
