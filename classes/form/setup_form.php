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
 * @package contentmarketplace_goone
 */

namespace contentmarketplace_goone\form;

use totara_form\form\element\checkboxes;
use totara_form\form\element\hidden;
use totara_form\form\element\static_html;

defined('MOODLE_INTERNAL') || die();

final class setup_form extends \totara_form\form {

    public static function get_form_controller() {
        return new setup_wizard_default_controller();
    }

    protected function definition() {
        global $OUTPUT;

        $wizard = $this->model->add(new setup_wizard('setup'));

        $s2 = $wizard->add_stage(new setup_wizard_stage('stage_two', get_string('content_access_settings', 'contentmarketplace_goone')));
        $s2->add(new static_html(
            'creatorsdesc',
            '',
            get_string('content_access_settings_description', 'contentmarketplace_goone')
        ));
        $s2->add(new checkboxes(
            'creators',
            get_string('content_creators', 'contentmarketplace_goone'),
            array(
                'free' => get_string('collection:free', 'contentmarketplace_goone', $this->parameters['courses_free'] ?? null),
                'subscribe' => get_string('collection:subscribe', 'contentmarketplace_goone', $this->parameters['courses_subscribe'] ?? null),
                'custom' => get_string('collection:custom', 'contentmarketplace_goone', $this->parameters['courses_custom'] ?? null),
            )
        ))->add_help_button('content_creators', 'contentmarketplace_goone');

        $payperseat = new hidden('pay_per_seat', PARAM_INT);
        $s2->add($payperseat);

        $wizard->set_submit_label(get_string('saveandexplorego1', 'contentmarketplace_goone'));
        $wizard->finalise();
    }
}
