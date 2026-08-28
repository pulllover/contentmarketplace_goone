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
namespace contentmarketplace_goone;

defined('MOODLE_INTERNAL') || die();

use moodle_exception;
use stdClass;

class missing_scope_exception extends moodle_exception {
    /**
     * invalid_token_exception constructor.
     * @param $url
     */
    public function __construct($url, $response = '') {
        $errorcode = "error:missing_scope";
        $module = "contentmarketplace_goone";
        $link = null;
        $a = null;
        $data = json_decode($response);
        $scopes = "";
        if (json_last_error() == 0 && isset($data->message)) {
            $scopes = implode(', ',$data->scopes);
        }
        $debuginfo = "Missing scope(s): ".var_export($scopes, true);
        parent::__construct($errorcode, $module, $link, $a, $debuginfo);
    }
}