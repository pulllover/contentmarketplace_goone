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
 * @author Kirill Astashov <kirill.astashov@androgogic.com>
 * @package contentmarketplace_goone
 */

use contentmarketplace_goone\api;
use contentmarketplace_goone\webhook;

defined('MOODLE_INTERNAL') || die();

global $OUTPUT;

require_capability('totara/contentmarketplace:config', context_system::instance());

$action = optional_param('action', '', PARAM_ALPHA);

$oauth_client_id = get_config('contentmarketplace_goone', 'oauth_client_id');
if (empty($oauth_client_id)) {
    throw new moodle_exception('error:pluginnotconfigured', 'contentmarketplace_goone');
}

$api = new api();

switch ($action) {
    case 'create':
        require_sesskey();
        $webhook = webhook::get_webhook($api);
        if ($webhook === false) {
            webhook::create_webhook($api);
        }
        break;
    case 'update':
        require_sesskey();
        $webhook = webhook::get_webhook($api);
        if ($webhook !== false) {
            webhook::update_webhook($webhook->id, $api);
        }
        break;
    default:
        break;

}

$webhook = webhook::get_webhook($api);
$webhook_exists = ($webhook !== false);

$data = new \stdClass();
$data->sesskey = sesskey();
$data->webhook_exists = $webhook_exists;
if ($webhook_exists) {
    $data->webhook_data = json_encode($webhook, JSON_PRETTY_PRINT);
    $data->webhook_data = preg_replace('/\"secret_key\"\: \"[^"]+\"/', '"secret_key": "***********"', $data->webhook_data);
    $data->update_webhook_url = new moodle_url('/totara/contentmarketplace/contentmarketplaces/goone/config.php');
    $data->webhook_out_of_sync = !webhook::is_synchronised($webhook);
} else {
    $data->create_webhook_url = new moodle_url('/totara/contentmarketplace/contentmarketplaces/goone/config.php');
}

echo $OUTPUT->render_from_template('contentmarketplace_goone/webhook', $data);

