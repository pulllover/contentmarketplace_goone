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
 * Helper class, Go1 admin GUI embedding to curate and sync content.
 *
 * @author Kirill Astashov <kirill.astashov@androgogic.com>
 * @package contentmarketplace_goone
 */

namespace contentmarketplace_goone;

defined('MOODLE_INTERNAL') || die();

use contentmarketplace_goone\entity\user_map as user_map_entity;
use contentmarketplace_goone\model\user_map as user_map_model;
use contentmarketplace_goone\api;

class embed {

    public static $role = 'Content administrator';

    /**
     * Generate and return OTT (one-time token) for the current Totara user to be used with embedding SDK (content admin Go1 user account).
     * Create Go1 user if not exists.
     * See https://developers.go1.com/docs/guides/content-hub/setup-user-auth/
     * V3: https://developers.go1.com/docs/guides/go1-admin/access-go1-admin/#1.-Create-a-user-account
     *
     * // Obtain Go1 user account id, create if not exists.
     * // See https://developers.go1.com/api/rest/2025-01-01/user-accounts/#create
     *
     * @param $api
     *
     * @return bool|string - One-time token or false on fail
     */
    public static function generate_user_login_ott($api): string|bool {
        global $USER;

        $goone_user = null;
        $goone_userid = null;

        // Search existing Go1 user in the user mapping table.
        if (user_map_entity::repository()->where('userid', $USER->id)->exists()) {
            $user_map = user_map_model::load_by_userid($USER->id);
            $goone_userid = $user_map->goone_userid;
            try {
                $goone_user = $api->get_user($goone_userid);
            } catch (missing_scope_exception | invalid_token_exception $e) {
                // Authentication and scope problems must surface to the caller.
                throw $e;
            } catch (\Exception $e) {
                // The mapped user no longer exists in Go1: drop the stale mapping and
                // fall through to matching by email or creating a new user.
                user_map_entity::repository()
                    ->where('userid', $USER->id)
                    ->delete();
                $goone_user = null;
                $goone_userid = null;
            }
        }

        if (empty($goone_user)) {
            // Search user in Go1 by their real email only.
            // (This should not be a user created by SCORM with fake email address).
            $params = [];
            $params['email'] = $USER->email;
            $params['include'] = ['standard_fields'];
            if (($data = $api->search_user($params)) !== false) {
                $goone_userid = $data->hits[0]->id;
                $goone_user = $api->get_user($goone_userid);
                // Remember the mapping so the search is not repeated on every visit.
                $entity = new user_map_entity();
                $entity->userid = $USER->id;
                $entity->goone_userid = $goone_userid;
                $entity->save();
            }
        }

        if (!empty($goone_user)) {
            // Check the user a role to manage Go1 content.
            $hasrole = false;
            foreach (api::$roles_contentadmin as $role_guid) {
                foreach ($goone_user->roles as $user_role) {
                    if ($role_guid == $user_role->guid) {
                        $hasrole = true;
                        break 2;
                    }
                }
            }
            if (!$hasrole) {
                $updateuser = new \stdClass();
                $roles = [];
                foreach ($goone_user->roles as $user_role) {
                    $roles[] = $user_role->guid;
                }
                $roles[] = api::$roles[self::$role];
                $updateuser->roles = $roles;
                $api->update_user($goone_user->id, $updateuser);
            }
        } else {
            $goone_userid = $api->create_user($USER, [api::$roles[self::$role]]);
            if (!$goone_userid) {
                return false;
            }
            $entity = new user_map_entity();
            $entity->userid = $USER->id;
            $entity->goone_userid = $goone_userid;
            $entity->save();
        }

        // Now that we have GO1 userid, generate and return OTT.
        return $api->get_ott($goone_userid);
    }

}