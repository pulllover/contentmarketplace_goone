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

use contentmarketplace_goone\form\retired_content_settings_form;
use contentmarketplace_goone\config_db_storage as config;

defined('MOODLE_INTERNAL') || die();

/** @var totara_contentmarketplace\plugininfo\contentmarketplace $plugin */
$plugin = core_plugin_manager::instance()->get_plugin_info("contentmarketplace_goone");
if (!$plugin->is_enabled()) {
    throw new moodle_exception('error:disabledmarketplace', 'totara_contentmarketplace', '', $plugin->displayname);
}

$config = new config();
$retired_content_retired_actions_config = $config->get('retired_content_retired_actions');
$retired_content_retired_actions = !empty($retired_content_retired_actions_config) ? explode(',', $retired_content_retired_actions_config) : [];
$retired_content_removed_actions_config = $config->get('retired_content_removed_actions');
$retired_content_removed_actions = !empty($retired_content_removed_actions_config) ? explode(',', $retired_content_removed_actions_config) : [];

$dateformat = $config->get('retired_content_dateformat');
$dateformat_default = 'j F Y';
if (empty($dateformat)) {
    $dateformat = $dateformat_default;
}

$form = new retired_content_settings_form([
    'retired_content_dateformat' => $dateformat,
    'retired_content_process' => (int) $config->get('retired_content_process'),
    'retired_content_location' => !empty($config->get('retired_content_location')) ? (string) $config->get('retired_content_location') : 'synccatonly',
    'retired_content_retired_actions' => (array) $retired_content_retired_actions,
    'retired_content_removed_actions' => (array) $retired_content_removed_actions,
    'retired_content_actions_move_category' => !empty($config->get('retired_content_actions_move_category')) ? $config->get('retired_content_actions_move_category') : ''
], [
    'dateformat_default' => $dateformat_default,
    'dateformat' => $dateformat
]);

$data = $form->get_data();
if ($data) {
    $retired_content_process = isset($data->retired_content_process) ? (int)$data->retired_content_process : '0';
    $config->set('retired_content_process', $retired_content_process);

    $retired_content_location = isset($data->retired_content_location) ? $data->retired_content_location : '';
    $config->set('retired_content_location', $retired_content_location);

    $retired_content_retired_actions = !empty($data->retired_content_retired_actions) ? implode(',', $data->retired_content_retired_actions) : '';
    $config->set('retired_content_retired_actions', $retired_content_retired_actions);

    $retired_content_removed_actions = !empty($data->retired_content_removed_actions) ? implode(',', $data->retired_content_removed_actions) : '';
    $config->set('retired_content_removed_actions', $retired_content_removed_actions);

    $retired_content_actions_move_category = isset($data->retired_content_actions_move_category) ? (int)$data->retired_content_actions_move_category : '0';
    $config->set('retired_content_actions_move_category', $retired_content_actions_move_category);

    $dateformat = isset($data->retired_content_dateformat) ? trim($data->retired_content_dateformat) : $dateformat_default;
    $config->set('retired_content_dateformat', $dateformat);

    \core\notification::success(get_string('settings_saved', 'contentmarketplace_goone'));
}

echo $form->render();
