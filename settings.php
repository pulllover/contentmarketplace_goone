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
 * @author Sergey Vidusov <sergey.vidusov@androgogic.com>
 * @author Kirill Astashov <kirill.astashov@androgogic.com
 * @package contentmarketplace_goone
 */

defined('MOODLE_INTERNAL') || die;

// Hide the menu item that Moodle automatically creates when it sees file "setting.php", we'll add our own one with a custom URL
$settings_page->hidden = true;

$ADMIN->add('contentmarketplace',
            new core\setting\page\externalpage('contentmarketplace_goone_page',
                                    new lang_string('goonesettings', 'contentmarketplace_goone'),
                                    new moodle_url("/totara/contentmarketplace/contentmarketplaces/goone/config.php"),
                                    'totara/contentmarketplace:config'));