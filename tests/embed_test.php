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

use contentmarketplace_goone\embed;
use contentmarketplace_goone\entity\user_map;
use contentmarketplace_goone\testing\generator;
use core_phpunit\testcase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

defined('MOODLE_INTERNAL') || die();

/**
 * Test the one-time-token generation for the embedded Go1 content hub.
 *
 * Uses the mocked Go1 accounts: 503 (contentadmin503@example.com, has the content
 * administrator role) and 501 (learner501@example.com, learner role only).
 */
#[CoversClass(embed::class)]
#[Group('totara_contentmarketplace')]
class contentmarketplace_goone_embed_test extends testcase {

    /**
     * @param string $email
     * @return stdClass The Totara user, set as the current user
     */
    private function set_up_current_user(string $email): stdClass {
        $user = self::getDataGenerator()->create_user(['email' => $email]);
        self::setUser($user);
        return $user;
    }

    public function test_valid_mapping_returns_ott(): void {
        $user = $this->set_up_current_user('contentadmin503@example.com');
        $map = new user_map();
        $map->userid = $user->id;
        $map->goone_userid = 503;
        $map->save();

        $ott = embed::generate_user_login_ott(generator::instance()->get_mock_api());

        self::assertSame('--OTT-503--', $ott);
        self::assertSame(1, user_map::repository()->count());
    }

    public function test_stale_mapping_is_replaced_via_email_search(): void {
        $user = $this->set_up_current_user('contentadmin503@example.com');
        // Mapping points at a Go1 user that no longer exists (the API responds with an error).
        $map = new user_map();
        $map->userid = $user->id;
        $map->goone_userid = 999999;
        $map->save();

        $ott = embed::generate_user_login_ott(generator::instance()->get_mock_api());

        self::assertSame('--OTT-503--', $ott);
        // The stale mapping has been replaced with the account found by email.
        $maps = user_map::repository()->get();
        self::assertCount(1, $maps);
        self::assertEquals(503, $maps->first()->goone_userid);
    }

    public function test_user_found_by_email_gets_a_mapping(): void {
        $user = $this->set_up_current_user('contentadmin503@example.com');
        self::assertSame(0, user_map::repository()->count());

        $ott = embed::generate_user_login_ott(generator::instance()->get_mock_api());

        self::assertSame('--OTT-503--', $ott);
        $map = user_map::repository()->one();
        self::assertNotNull($map);
        self::assertEquals($user->id, $map->userid);
        self::assertEquals(503, $map->goone_userid);
    }

    public function test_missing_content_admin_role_is_added(): void {
        // Go1 account 501 exists but only holds the learner role, so it gets updated.
        $this->set_up_current_user('learner501@example.com');

        $ott = embed::generate_user_login_ott(generator::instance()->get_mock_api());

        self::assertSame('--OTT-501--', $ott);
        self::assertEquals(501, user_map::repository()->one()->goone_userid);
    }

    public function test_user_without_go1_account_is_created(): void {
        $user = $this->set_up_current_user('nogo1user@example.com');

        $ott = embed::generate_user_login_ott(generator::instance()->get_mock_api());

        self::assertSame('--OTT-777--', $ott);
        $map = user_map::repository()->one();
        self::assertEquals($user->id, $map->userid);
        self::assertEquals(777, $map->goone_userid);
    }
}
