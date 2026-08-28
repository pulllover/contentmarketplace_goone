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

use contentmarketplace_goone\contentmarketplace;
use contentmarketplace_goone\form\content_sync_settings_form;
use contentmarketplace_goone\config_db_storage as config;

defined('MOODLE_INTERNAL') || die();

/** @var totara_contentmarketplace\plugininfo\contentmarketplace $plugin */
$plugin = core_plugin_manager::instance()->get_plugin_info("contentmarketplace_goone");
if (!$plugin->is_enabled()) {
    throw new moodle_exception('error:disabledmarketplace', 'totara_contentmarketplace', '', $plugin->displayname);
}

$config = new config();
$sync_collections_config = $config->get('sync_collections');
$sync_collections = !empty($sync_collections_config) ? explode(',', $sync_collections_config) : contentmarketplace::$default_collections;
$content_regions_config = $config->get('content_regions');
$content_regions = !empty($content_regions_config) ? explode(',', $content_regions_config) : [];

$form = new content_sync_settings_form([
    'create_courses' => (int) $config->get('create_courses'),
    'course_category' => $config->get('course_category') ? (int) $config->get('course_category') : '',
    'course_type' => (string) $config->get('course_type'),
    'course_shortname' => !empty($config->get('course_shortname')) ? $config->get('course_shortname') : 'withloid',
    'sync_collections' => $sync_collections,
    'content_regions' => $content_regions
]);

$data = $form->get_data();

if ($data) {
    $create_courses = isset($data->create_courses) ? (int)$data->create_courses : '';
    $config->set('create_courses', $create_courses);

    $course_category = isset($data->course_category) ? (int)$data->course_category : '';
    $config->set('course_category', $course_category);

    $course_type = isset($data->course_type) ? (string)$data->course_type : '';
    $config->set('course_type', $course_type);

    $course_shortname = isset($data->course_shortname) ? (string)$data->course_shortname : '';
    $config->set('course_shortname', $course_shortname);

    $sync_collections = !empty($data->sync_collections) ? implode(',', $data->sync_collections) : '';
    $config->set('sync_collections', $sync_collections);

    $content_regions = !empty($data->content_regions) ? implode(',', $data->content_regions) : '';
    $config->set('content_regions', $content_regions);

    \core\notification::success(get_string('settings_saved', 'contentmarketplace_goone'));
}

echo $form->render();
