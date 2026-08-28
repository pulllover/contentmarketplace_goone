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

use contentmarketplace_goone\contentmarketplace;
use core_phpunit\testcase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

defined('MOODLE_INTERNAL') || die();

/**
 * Test the marketplace class (capability driven collection access and settings persistence).
 */
#[CoversClass(contentmarketplace::class)]
#[Group('totara_contentmarketplace')]
class contentmarketplace_goone_contentmarketplace_test extends testcase {

    public function test_content_availability_options_for_site_administrator(): void {
        self::setAdminUser();
        $context = context_system::instance();

        self::assertSame(['free', 'subscribe', 'custom'], contentmarketplace::content_availability_options($context));
    }

    public function test_content_availability_options_for_content_creator(): void {
        global $DB;

        set_config('content_settings_creators', 'free,custom', 'contentmarketplace_goone');

        $user = self::getDataGenerator()->create_user();
        $context = context_system::instance();
        $role_id = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($role_id, $user->id, $context);
        assign_capability('totara/contentmarketplace:add', CAP_ALLOW, $role_id, $context);
        unassign_capability('totara/contentmarketplace:config', $role_id, $context->id);
        assign_capability('totara/contentmarketplace:config', CAP_PROHIBIT, $role_id, $context);

        self::setUser($user);
        self::assertSame(['free', 'custom'], contentmarketplace::content_availability_options($context));

        // An empty allowance means no collections at all, not [''].
        set_config('content_settings_creators', '', 'contentmarketplace_goone');
        self::assertSame([], contentmarketplace::content_availability_options($context));
    }

    public function test_content_availability_options_without_capabilities(): void {
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);

        self::assertSame([], contentmarketplace::content_availability_options(context_system::instance()));
    }

    public function test_save_content_settings_data(): void {
        $data = (object) [
            'creators' => ['free', 'custom'],
            'pay_per_seat' => 1,
        ];
        contentmarketplace::save_content_settings_data($data);

        self::assertSame('free,custom', get_config('contentmarketplace_goone', 'content_settings_creators'));
        self::assertEquals(1, get_config('contentmarketplace_goone', 'pay_per_seat'));

        // Empty creator selection is stored as an empty string.
        contentmarketplace::save_content_settings_data((object) ['creators' => [], 'pay_per_seat' => 0]);
        self::assertSame('', get_config('contentmarketplace_goone', 'content_settings_creators'));
    }

    public function test_settings_url(): void {
        $marketplace = new contentmarketplace();

        $url = $marketplace->settings_url();
        self::assertStringContainsString('/totara/contentmarketplace/contentmarketplaces/goone/config.php', $url->out(false));

        $url = $marketplace->settings_url('content_sync');
        self::assertSame('content_sync', $url->param('tab'));
    }

    public function test_get_region_options(): void {
        $options = contentmarketplace::get_region_options();

        self::assertArrayHasKey('GLOBAL', $options);
        self::assertArrayHasKey('AU', $options);
        self::assertSame(array_keys($options), array_values($options));
    }
}
