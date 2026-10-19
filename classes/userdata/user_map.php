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

namespace contentmarketplace_goone\userdata;

use contentmarketplace_goone\entity\user_map as user_map_entity;
use context;
use core\orm\entity\repository;
use totara_userdata\userdata\export;
use totara_userdata\userdata\item;
use totara_userdata\userdata\target_user;

/**
 * The link between a Totara user and their Go1 user account (marketplace_goone_user_map).
 *
 * The curation page writes the link when the user opens the embedded Go1 content hub.
 * Purge removes only the local link. The Go1 user account is not changed. If an active
 * user opens the curation page again, the link is created again.
 */
class user_map extends item {

    public static function get_main_component(): string {
        return 'totara_contentmarketplace';
    }

    /**
     * Can user data of this item be somehow counted?
     *
     * @return bool
     */
    public static function is_countable(): bool {
        return true;
    }

    /**
     * Count user data for this item.
     *
     * @param target_user $user
     * @param context $context Not used, as the mapping is independent of courses
     * @return int amount of data or negative integer status code (self::RESULT_STATUS_ERROR or self::RESULT_STATUS_SKIPPED)
     */
    protected static function count(target_user $user, context $context): int {
        return static::query($user->id)->count();
    }

    /**
     * Can user data of this item data be exported from the system?
     *
     * @return bool
     */
    public static function is_exportable(): bool {
        return true;
    }

    /**
     * Export user data from this item.
     *
     * @param target_user $user
     * @param context $context Not used, as the mapping is independent of courses
     * @return export result object
     */
    protected static function export(target_user $user, context $context) {
        $data = static::query($user->id)
            ->order_by('id')
            ->get()
            ->map(static function (user_map_entity $map) {
                return [
                    'id' => (int) $map->id,
                    'goone_userid' => (int) $map->goone_userid,
                ];
            })
            ->all();

        $export = new export();
        $export->data = [static::get_name() => $data];
        return $export;
    }

    /**
     * Can user data of this item data be purged from system?
     *
     * @param int $userstatus target_user::STATUS_ACTIVE, target_user::STATUS_DELETED or target_user::STATUS_SUSPENDED
     * @return bool
     */
    public static function is_purgeable(int $userstatus) {
        return true;
    }

    /**
     * Purge user data for this item.
     *
     * @param target_user $user
     * @param context $context Not used, as the mapping is independent of courses
     * @return int result self::RESULT_STATUS_SUCCESS, self::RESULT_STATUS_ERROR or self::RESULT_STATUS_SKIPPED
     */
    protected static function purge(target_user $user, context $context) {
        static::query($user->id)->delete();

        return static::RESULT_STATUS_SUCCESS;
    }

    /**
     * Repository query for the user's Go1 account mapping.
     *
     * @param int $userid
     * @return repository
     */
    protected static function query(int $userid): repository {
        return user_map_entity::repository()
            ->where('userid', $userid);
    }

}
