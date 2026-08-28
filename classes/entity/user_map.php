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

namespace contentmarketplace_goone\entity;

defined('MOODLE_INTERNAL') || die();

use core\orm\entity\entity;

/**
 * A mapping between Totara userid and Go1 user id.
 *
 * @property-read int $id
 * @property int $userid
 * @property int $goone_userid
 *
 * @package contentmarketplace_goone\entity
 */
class user_map extends entity {

    /**
     * @var string
     */
    public const TABLE = 'marketplace_goone_user_map';

}
