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

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/config.php');

use contentmarketplace_goone\webhook;

// Check Go1 marketplace plugin is enabled.
/** @var \totara_contentmarketplace\plugininfo\contentmarketplace $plugin */
$plugin = \core_plugin_manager::instance()->get_plugin_info("contentmarketplace_goone");
if ($plugin === null) {
    throw new coding_exception('The contentmarketplace_goone plugin is not yet installed.');
}
if (!$plugin->is_enabled()) {
    throw new \moodle_exception('error:disabledmarketplace', 'totara_contentmarketplace', '', $plugin->displayname);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    webhook::process_request();
}