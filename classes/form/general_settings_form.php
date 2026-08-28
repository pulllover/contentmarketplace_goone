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

namespace contentmarketplace_goone\form;

use contentmarketplace_goone\helper;

use totara_form\form\element\radios;
use totara_form\form\element\static_html;
use totara_form\form\element\text;

defined('MOODLE_INTERNAL') || die();

class general_settings_form extends \totara_form\form {

    public function get_action_url() {
        return new \moodle_url('/totara/contentmarketplace/contentmarketplaces/goone/config.php', array(
            'tab' => 'general',
        ));
    }

    protected function definition() {

        $this->model->add(new static_html(
            'general_settings_header',
            '',
            \html_writer::tag('h3', get_string('general_settings', 'contentmarketplace_goone')) .
            \html_writer::tag('p', get_string('general_settings_description', 'contentmarketplace_goone'))
        ));

        $clientid = $this->model->add(new static_html(
            'clientid',
            get_string('clientid', 'contentmarketplace_goone'),
            "<code>" . (string) get_config('contentmarketplace_goone', 'oauth_client_id') . "</code>"
        ));
        $clientid->add_help_button('clientid', 'contentmarketplace_goone');

        $options = [
            helper::CREATE_COURSE_MANUAL_ENABLED => get_string('create_course_manual_enabled', 'contentmarketplace_goone'),
            helper::CREATE_COURSE_MANUAL_DISABLED => get_string('create_course_manual_disabled', 'contentmarketplace_goone'),
        ];
        $create_course_manual = $this->model->add(new radios(
            'create_course_manual',
            get_string('create_course_manual', 'contentmarketplace_goone'),
            $options
        ));
        $create_course_manual->set_attribute('required', true);
        $create_course_manual->add_help_button('create_course_manual', 'contentmarketplace_goone');

        $this->model->add_action_buttons(false);
    }

    protected function validation(array $data, array $files) {
        $errors = parent::validation($data, $files);

        $valid = [
            (string) helper::CREATE_COURSE_MANUAL_ENABLED,
            (string) helper::CREATE_COURSE_MANUAL_DISABLED,
        ];
        if (!isset($data['create_course_manual']) || !in_array((string) $data['create_course_manual'], $valid, true)) {
            $errors['create_course_manual'] = get_string('create_course_manual_invalid', 'contentmarketplace_goone');
        }

        return $errors;
    }

}
