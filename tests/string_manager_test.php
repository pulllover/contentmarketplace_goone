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
 * @author Michael Dunstan <michael.dunstan@androgogic.com>
 * @package contentmarketplace_goone
 */

use contentmarketplace_goone\string_manager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

defined('MOODLE_INTERNAL') || die();

/**
 * Test string_manager class
 */
#[Group('totara_contentmarketplace')]
class contentmarketplace_goone_string_manager_test extends \core_phpunit\testcase {

    #[DataProvider('language_provider')]
    public function test_get_language($lang, $expected) {
        $manager = new string_manager();
        $string = $manager->get_language($lang);
        $this->assertSame($expected, $string);
    }

    public static function language_provider() {
        return [
            ['en', 'English'],
            ['en-gb', 'English (United Kingdom)'],
            ['xx', 'xx'],
            ['en-xx', 'en-xx'],
            ['', 'Unknown'],
        ];
    }

    #[DataProvider('region_provider')]
    public function test_get_region($region, $expected) {
        $manager = new string_manager();
        $string = $manager->get_region($region);
        $this->assertSame($expected, $string);
    }

    public static function region_provider() {
        return [
            ['AU', 'Australia'],
            ['OTHER', 'Rest of the world'],
            ['XX', 'XX'],
            ['', ''],
        ];
    }
}
