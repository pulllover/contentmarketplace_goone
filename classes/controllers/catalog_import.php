<?php
/**
 * This file is part of Totara Learn
 *
 * Copyright (C) 2021 onwards Totara Learning Solutions LTD
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
 * @author Qingyang Liu <qingyang.liu@totaralearning.com>
 * @package contentmarketplace_goone
 */

namespace contentmarketplace_goone\controllers;

defined('MOODLE_INTERNAL') || die();

use totara_contentmarketplace\controllers\catalog_import as base_catalog_import;
use totara_contentmarketplace\views\override_catalog_import_nav_breadcrumbs;
use totara_mvc\view;

final class catalog_import extends base_catalog_import {
    /**
     * @inheritDoc
     */
    public function action(): view {
        $explorer = $this->get_explorer();
        $data = (array)$explorer->get_data();
        return $this->create_view('totara_contentmarketplace/explorer', (array)$explorer->get_data())
            ->set_title($explorer->get_heading())
            ->add_override(new override_catalog_import_nav_breadcrumbs($this));
    }

    /**
     * The base controller only recognises the 'section_id' parameter, but the Go1
     * explorer pages and JS pass the course section under 'section', so it is
     * accepted here as a fallback. Without it the explorer never resolves the
     * course in add-activity mode (blank course name in the heading, no section
     * passed on to the activity creation page).
     *
     * @return int|null
     */
    public function get_section_id(): ?int {
        $section_id = parent::get_section_id();
        if (is_null($section_id)) {
            $this->section_id = $this->get_optional_param('section', null, PARAM_INT);
            $section_id = $this->section_id;
        }
        return $section_id;
    }

}