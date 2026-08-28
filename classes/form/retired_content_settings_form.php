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

use contentmarketplace_goone\contentmarketplace;
use contentmarketplace_goone\helper;

use totara_form\form\element\static_html;
use totara_form\form\element\checkboxes;
use totara_form\form\element\yesno;
use totara_form\form\element\select;
use totara_form\form\element\radios;
use totara_form\form\element\text;

defined('MOODLE_INTERNAL') || die();

class retired_content_settings_form extends \totara_form\form {

    public function get_action_url() {
        return new \moodle_url('/totara/contentmarketplace/contentmarketplaces/goone/config.php', array(
            'tab' => 'retired_content',
        ));
    }

    protected function definition() {

        $this->model->add(new static_html(
            'retired_content_header1',
            '',
            \html_writer::tag('h3', get_string('retired_content_settings', 'contentmarketplace_goone')) .
            \html_writer::tag('p', get_string('retired_content_settings_description', 'contentmarketplace_goone'))
        ));

        $this->model->add(new yesno(
            'retired_content_process',
            get_string('retired_content_process', 'contentmarketplace_goone')
        ))->add_help_button('retired_content_process', 'contentmarketplace_goone');

        $this->model->add(new radios(
            'retired_content_location',
            get_string('retired_content_location', 'contentmarketplace_goone'),
            [
                'synccatonly' => get_string('retired_content_location_synccatonly', 'contentmarketplace_goone'),
                'alltotara' => get_string('retired_content_location_alltotara', 'contentmarketplace_goone'),
            ]
        ))->add_help_button('retired_content_location', 'contentmarketplace_goone');

        $this->model->add(new checkboxes(
            'retired_content_retired_actions',
            get_string('retired_content_retired_actions', 'contentmarketplace_goone'),
            [
                'courseimage' => get_string('retired_content_action_courseimage', 'contentmarketplace_goone'),
                'description' => get_string('retired_content_action_description', 'contentmarketplace_goone'),
                'move' => get_string('retired_content_action_move', 'contentmarketplace_goone'),
                'hide' => get_string('retired_content_action_hide', 'contentmarketplace_goone')
            ]
        ));

        $this->model->add(new checkboxes(
            'retired_content_removed_actions',
            get_string('retired_content_removed_actions', 'contentmarketplace_goone'),
            [
                'move' => get_string('retired_content_action_move', 'contentmarketplace_goone'),
                'hide' => get_string('retired_content_action_hide', 'contentmarketplace_goone')
            ]
        ));

        $catlist = ['' => get_string('choosedots')];
        $catlist += \coursecat::make_categories_list();
        $this->model->add(new select(
                'retired_content_actions_move_category',
                get_string('retired_content_action_move_category', 'contentmarketplace_goone'),
                $catlist
        ))->add_help_button('retired_content_action_move_category', 'contentmarketplace_goone');

        $this->model->add(new text(
            'retired_content_dateformat',
            get_string('retired_content_dateformat', 'contentmarketplace_goone'),
            PARAM_TEXT
        ));

        $langparams = new \stdClass();
        $langparams->dateformat_default = $this->parameters['dateformat_default'];
        $langparams->example_default = date($this->parameters['dateformat_default']);
        $langparams->dateformat = $this->parameters['dateformat'];
        $langparams->example = date($this->parameters['dateformat']);
        $this->model->add(new static_html(
            'current_cormat',
            '&nbsp;',
            get_string('retired_content_dateformat_help', 'contentmarketplace_goone', $langparams)
        ));

        $this->model->add_action_buttons(false);
    }

    protected function validation(array $data, array $files) {
        $errors = parent::validation($data, $files);

        if ((in_array('move', $data['retired_content_retired_actions'])
                || in_array('move', $data['retired_content_removed_actions'])
            )
            && empty($data['retired_content_actions_move_category'])
            ) {
                $errors['retired_content_actions_move_category'] = get_string('retired_content_category_notselected', 'contentmarketplace_goone');
        }

        return $errors;
    }

}
