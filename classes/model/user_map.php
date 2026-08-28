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
 * @author Kirill Astashov <kirill.astashov@androgogic.com>
 * @package contentmarketplace_goone
 */

namespace contentmarketplace_goone\model;

defined('MOODLE_INTERNAL') || die();

use contentmarketplace_goone\entity\user_map as user_map_entity;
use core\orm\entity\entity;
use core\orm\entity\model;

/**
 *
 * Entity properties:
 * @property-read int $id
 * @property-read int $userid
 * @property-read int $goone_userid
 *
 * @package contentmarketplace_goone\model
 */
class user_map extends model {

    /**
     * @inheritDoc
     */
    protected static function get_entity_class(): string {
        return user_map_entity::class;
    }

    /**
     * @param entity $entity
     */
    public function __construct(entity $entity) {
        parent::__construct($entity);
    }

    /**
     * @param int $userid
     *
     * @return static
     */
    public static function load_by_userid(int $userid): self {
        $entity = user_map_entity::repository()
            ->where('userid', $userid)
            ->one();

        return static::load_by_entity($entity);
    }

}
