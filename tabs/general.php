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
 * @author Kirill Astashov <kirill.astashov@androgogic.com>
 * @package contentmarketplace_goone
 */

use contentmarketplace_goone\form\general_settings_form;
use contentmarketplace_goone\helper;
use contentmarketplace_goone\config_db_storage as config;

defined('MOODLE_INTERNAL') || die();

$config = new config();
$create_course_manual = $config->get('create_course_manual');
if ($create_course_manual === false || $create_course_manual === null || $create_course_manual === '') {
    $create_course_manual = helper::CREATE_COURSE_MANUAL_DISABLED;
}

$form = new general_settings_form([
    'create_course_manual' => (int) $create_course_manual,
]);

$formdata = $form->get_data();

if ($formdata) {
    $config->set('create_course_manual', (int) $formdata->create_course_manual);

    \core\notification::success(get_string('settings_saved', 'contentmarketplace_goone'));
}

echo $form->render();
