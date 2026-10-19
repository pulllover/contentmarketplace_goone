<?php
/**
 * This file is part of Totara Learn
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
 * @package    contentmarketplace_goone
 * @copyright  2026 onwards Androgogic Pty Ltd
 * @author     Kirill Astashov <kirill.astashov@androgogic.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use contentmarketplace_goone\entity\user_map as user_map_entity;
use contentmarketplace_goone\userdata\user_map;
use core_phpunit\testcase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use totara_userdata\userdata\export;
use totara_userdata\userdata\item;
use totara_userdata\userdata\target_user;

defined('MOODLE_INTERNAL') || die();

/**
 * Test the user data item for the Totara user to Go1 user account mapping.
 */
#[CoversClass(user_map::class)]
#[Group('totara_contentmarketplace')]
#[Group('totara_userdata')]
class contentmarketplace_goone_userdata_user_map_test extends testcase {

    /**
     * @param stdClass $user
     * @param int $goone_userid
     * @return void
     */
    private function create_map(stdClass $user, int $goone_userid): void {
        $map = new user_map_entity();
        $map->userid = $user->id;
        $map->goone_userid = $goone_userid;
        $map->save();
    }

    /**
     * @param int $userid
     * @return target_user
     */
    private function load_target_user(int $userid): target_user {
        global $DB;
        return new target_user($DB->get_record('user', ['id' => $userid], '*', MUST_EXIST));
    }

    public function test_item_settings(): void {
        self::assertTrue(user_map::is_countable());
        self::assertTrue(user_map::is_exportable());
        self::assertTrue(user_map::is_purgeable(target_user::STATUS_ACTIVE));
        self::assertTrue(user_map::is_purgeable(target_user::STATUS_SUSPENDED));
        self::assertTrue(user_map::is_purgeable(target_user::STATUS_DELETED));
        self::assertSame([CONTEXT_SYSTEM], user_map::get_compatible_context_levels());
        self::assertSame('totara_contentmarketplace', user_map::get_main_component());
        self::assertSame('Go1 user account mapping', user_map::get_fullname());
    }

    public function test_count(): void {
        $generator = self::getDataGenerator();
        $user1 = $generator->create_user();
        $user2 = $generator->create_user();
        $user3 = $generator->create_user();
        $this->create_map($user1, 501);
        $this->create_map($user2, 502);
        $context = context_system::instance();

        self::assertSame(1, user_map::execute_count(new target_user($user1), $context));
        self::assertSame(1, user_map::execute_count(new target_user($user2), $context));
        self::assertSame(0, user_map::execute_count(new target_user($user3), $context));
    }

    public function test_export(): void {
        $generator = self::getDataGenerator();
        $user1 = $generator->create_user();
        $user2 = $generator->create_user();
        $this->create_map($user1, 501);
        $this->create_map($user2, 502);

        $result = user_map::execute_export(new target_user($user1), context_system::instance());

        self::assertInstanceOf(export::class, $result);
        self::assertSame([], $result->files);
        self::assertArrayHasKey('user_map', $result->data);
        self::assertCount(1, $result->data['user_map']);
        self::assertSame(501, $result->data['user_map'][0]['goone_userid']);
    }

    /**
     * @return array
     */
    public static function user_status_provider(): array {
        return [
            'active' => [target_user::STATUS_ACTIVE],
            'suspended' => [target_user::STATUS_SUSPENDED],
            'deleted' => [target_user::STATUS_DELETED],
        ];
    }

    #[DataProvider('user_status_provider')]
    public function test_purge(int $status): void {
        $generator = self::getDataGenerator();
        $user1 = $generator->create_user(['suspended' => $status === target_user::STATUS_SUSPENDED ? 1 : 0]);
        $user2 = $generator->create_user();
        $this->create_map($user1, 501);
        $this->create_map($user2, 502);
        if ($status === target_user::STATUS_DELETED) {
            delete_user($user1);
        }
        $target = $this->load_target_user($user1->id);
        self::assertSame($status, $target->status);
        $context = context_system::instance();

        self::assertSame(item::RESULT_STATUS_SUCCESS, user_map::execute_purge($target, $context));
        self::assertFalse(user_map_entity::repository()->where('userid', $user1->id)->exists());
        self::assertTrue(user_map_entity::repository()->where('userid', $user2->id)->exists());

        // A second purge finds nothing to delete and still succeeds.
        self::assertSame(item::RESULT_STATUS_SUCCESS, user_map::execute_purge($target, $context));
        self::assertSame(1, user_map_entity::repository()->count());
    }

}
