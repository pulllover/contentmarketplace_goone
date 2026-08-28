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

defined('MOODLE_INTERNAL') || die();

class content_sync_settings_form extends \totara_form\form {

    public function get_action_url() {
        return new \moodle_url('/totara/contentmarketplace/contentmarketplaces/goone/config.php', array(
            'tab' => 'content_sync',
        ));
    }

    protected function definition() {

        $this->model->add(new static_html(
            'content_sync_settings_header1',
            '',
            \html_writer::tag('h3', get_string('content_sync_settings', 'contentmarketplace_goone')) .
            \html_writer::tag('p', get_string('content_sync_settings_description', 'contentmarketplace_goone'))
        ));

        $create_courses = $this->model->add(new yesno(
            'create_courses',
            get_string('create_courses', 'contentmarketplace_goone')
        ));
        $create_courses->set_attribute('required', true);
        $create_courses->add_help_button('create_courses', 'contentmarketplace_goone');

        $catlist = ['' => get_string('choosedots')];
        $catlist += \coursecat::make_categories_list();
        $this->model->add(new select(
                'course_category',
                get_string('course_category', 'contentmarketplace_goone'),
                $catlist
        ));

        $coursetypes = [
            '' => get_string('choosedots'),
            helper::CREATE_COURSE_SINGLE => get_string('course_type_single', 'contentmarketplace_goone'),
            helper::CREATE_COURSE_MULTI => get_string('course_type_multi', 'contentmarketplace_goone'),
        ];
        $this->model->add(new select(
            'course_type',
            get_string('course_type', 'contentmarketplace_goone'),
            $coursetypes
        ));

        $options = [
            'fullname' => get_string('course_shortname_fullname', 'contentmarketplace_goone'),
            'withloid' => get_string('course_shortname_withloid', 'contentmarketplace_goone')
        ];
        $this->model->add(new radios(
            'course_shortname',
            get_string('course_shortname', 'contentmarketplace_goone'),
            $options
        ));

        $this->model->add(new checkboxes(
            'sync_collections',
            get_string('collections_sync', 'contentmarketplace_goone'),
            contentmarketplace::get_collection_options_withtotals(contentmarketplace::CONTENT_AVAILABILITY_SYNC)
        ))->add_help_button('collections', 'contentmarketplace_goone');

        $this->model->add(new checkboxes(
            'content_regions',
            get_string('content_regions', 'contentmarketplace_goone'),
            contentmarketplace::get_region_options()
        ))->add_help_button('content_regions', 'contentmarketplace_goone');

        $this->model->add_action_buttons(false);
    }

    protected function validation(array $data, array $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['create_courses']) && empty($data['course_category'])) {
            $errors['course_category'] = get_string('course_category_notselected', 'contentmarketplace_goone');
        }

        if (!empty($data['create_courses']) && empty($data['course_type'])) {
            $errors['course_type'] = get_string('course_type_notselected', 'contentmarketplace_goone');
        }

        if (!empty($data['create_courses']) && empty($data['course_shortname'])) {
            $errors['course_shortname'] = get_string('required');
        }

        return $errors;
    }

}
