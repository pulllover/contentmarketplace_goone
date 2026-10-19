<?php
/**
 * This file is part of Totara Learn
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
 * @package    contentmarketplace_goone
 * @copyright  2026 onwards Androgogic Pty Ltd
 * @author     Kirill Astashov <kirill.astashov@androgogic.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
    'debug_requests' => (int) $config->get('debug_requests'),
]);

$formdata = $form->get_data();

if ($formdata) {
    $config->set('create_course_manual', (int) $formdata->create_course_manual);
    $config->set('debug_requests', (int) $formdata->debug_requests);

    \core\notification::success(get_string('settings_saved', 'contentmarketplace_goone'));
}

$allow_accounts_same_email_warning = helper::get_allow_accounts_same_email_warning();
if ($allow_accounts_same_email_warning !== null) {
    echo $allow_accounts_same_email_warning;
}

echo $form->render();
