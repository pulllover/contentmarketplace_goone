<?php
/**
* This file is part of Totara Learn
*
* Copyright (C) 2022 onwards Totara Learning Solutions LTD
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

namespace contentmarketplace_goone\workflow\mod_contentmarketplace\create_marketplace_activity;

defined('MOODLE_INTERNAL') || die();

use core\orm\query\builder;
use moodle_url;
use totara_contentmarketplace\explorer;
use totara_contentmarketplace\workflow\marketplace_workflow;
use contentmarketplace_goone\config_db_storage as config;

class goone extends marketplace_workflow {

    /**
     * @inheritDoc
     */
    public function get_name(): string {
        return get_string('workflow:name', 'contentmarketplace_goone');
    }

    /**
     * @inheritDoc
     */
    public function get_description(): string {
        return get_string('workflow:description', 'contentmarketplace_goone');
    }

    /**
     * @inheritDoc
     */
    public function can_access(): bool {
        $config = new config();
        if (!$config->get('oauth_client_id') || !$config->get('oauth_client_secret')) {
            return false;
        }
        return parent::can_access();
    }

    /**
     * @inheritDoc
     */
    protected function get_workflow_url(): moodle_url {
        $url = parent::get_workflow_url();
        $url->params(array_merge(
            $this->manager->get_params(),
            [
                'mode' => explorer::MODE_ADD_ACTIVITY,
            ]
        ));
        return $url;
    }

}