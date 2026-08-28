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

use \contentmarketplace_goone\contentmarketplace;

use totara_form\form\element\static_html;
use totara_form\form\element\checkboxes;

defined('MOODLE_INTERNAL') || die();

class content_access_settings_form extends \totara_form\form {

    public function get_action_url() {
        return new \moodle_url('/totara/contentmarketplace/contentmarketplaces/goone/config.php', array(
            'tab' => 'content_access',
        ));
    }

    protected function definition() {

        $this->model->add(new static_html(
            'content_access_header',
            '',
            \html_writer::tag('h3', get_string('content_access_settings', 'contentmarketplace_goone')) .
            \html_writer::tag('p', get_string('content_access_settings_description', 'contentmarketplace_goone'))
        ));

        $this->model->add(new checkboxes(
            'content_settings_creators',
            get_string('content_creators', 'contentmarketplace_goone'),
            contentmarketplace::get_collection_options_withtotals(contentmarketplace::CONTENT_AVAILABILITY_ADD)
        ))->add_help_button('content_creators', 'contentmarketplace_goone');

        $this->model->add_action_buttons(false);
    }

}
