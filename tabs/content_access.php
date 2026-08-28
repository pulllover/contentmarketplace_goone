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

use contentmarketplace_goone\form\content_access_settings_form;
use contentmarketplace_goone\config_db_storage as config;

defined('MOODLE_INTERNAL') || die();

/** @var totara_contentmarketplace\plugininfo\contentmarketplace $plugin */
$plugin = core_plugin_manager::instance()->get_plugin_info("contentmarketplace_goone");
if (!$plugin->is_enabled()) {
    throw new moodle_exception('error:disabledmarketplace', 'totara_contentmarketplace', '', $plugin->displayname);
}

$config = new config();
$content_settings_creators_config = $config->get('content_settings_creators');
$content_settings_creators = !empty($content_settings_creators_config) ? explode(',', $content_settings_creators_config) : [];

$form = new content_access_settings_form([
    'content_settings_creators' => $content_settings_creators,
]);

$data = $form->get_data();
if ($data) {
    $content_settings_creators = (!empty($data->content_settings_creators)) ? implode(',', $data->content_settings_creators) : '';
    $config->set('content_settings_creators', $content_settings_creators);
    \core\notification::success(get_string('settings_saved', 'contentmarketplace_goone'));
}

echo $form->render();
