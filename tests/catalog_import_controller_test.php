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
 * @package contentmarketplace_goone
 */

use contentmarketplace_goone\controllers\catalog_import;
use core_phpunit\testcase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use totara_contentmarketplace\explorer as explorer_model;
use totara_contentmarketplace\plugininfo\contentmarketplace;

defined('MOODLE_INTERNAL') || die();

/**
 * Test the Go1 explorer controller: section resolution in add-activity mode and
 * the capability behind the "Manage available content" button.
 */
#[CoversClass(catalog_import::class)]
#[Group('totara_contentmarketplace')]
class contentmarketplace_goone_catalog_import_controller_test extends testcase {

    protected function tearDown(): void {
        unset($_GET['marketplace'], $_GET['mode'], $_GET['section'], $_GET['section_id'], $_GET['category']);
        parent::tearDown();
    }

    /**
     * Create a course + section and populate $_GET for add-activity mode.
     *
     * @param string $section_param Name of the request parameter carrying the section id
     * @return stdClass The course record
     */
    private function set_up_add_activity_request(string $section_param): stdClass {
        $plugin = contentmarketplace::plugin('goone');
        if (!$plugin->is_enabled()) {
            $plugin->enable();
        }

        $generator = self::getDataGenerator();
        $course = $generator->create_course(['fullname' => 'Go1 target course']);
        $section = $generator->create_course_section(['course' => $course->id, 'section' => 1]);

        $_GET['marketplace'] = 'goone';
        $_GET['mode'] = explorer_model::MODE_ADD_ACTIVITY;
        $_GET[$section_param] = $section->id;

        return $course;
    }

    public function test_add_activity_mode_with_section_id_parameter(): void {
        self::setAdminUser();
        $course = $this->set_up_add_activity_request('section_id');

        $controller = new catalog_import();

        self::assertEquals($course->id, $controller->get_course_id());
        self::assertEquals(context_course::instance($course->id), $controller->get_context());
        // The explorer heading carries the course name, not a raw {$a} placeholder.
        $heading = $controller->get_explorer()->get_data()->heading;
        self::assertStringContainsString('Go1 target course', $heading);
        self::assertStringNotContainsString('{$a}', $heading);
    }

    public function test_add_activity_mode_with_legacy_section_parameter(): void {
        self::setAdminUser();
        $course = $this->set_up_add_activity_request('section');

        $controller = new catalog_import();

        self::assertEquals($course->id, $controller->get_course_id());
        self::assertEquals(context_course::instance($course->id), $controller->get_context());

        $data = $controller->get_explorer()->get_data();
        // The heading carries the course name, not a raw {$a} placeholder.
        self::assertStringContainsString('Go1 target course', $data->heading);
        self::assertStringNotContainsString('{$a}', $data->heading);
        // The section is passed through to the explorer JS so "Add activity" works.
        self::assertEquals($_GET['section'], $data->section);
    }

    /**
     * Create a user who can open the create-course explorer (totara/contentmarketplace:add) and
     * additionally holds the given capability via a system-level role, then log them in.
     * Populates $_GET for create-course mode in a fresh course category.
     *
     * @param string $capability
     * @return void
     */
    private function set_up_create_course_request_as_user_with(string $capability): void {
        $plugin = contentmarketplace::plugin('goone');
        if (!$plugin->is_enabled()) {
            $plugin->enable();
        }

        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $user = $generator->create_user();
        $syscontext = context_system::instance();
        $roleid = create_role('Explorer tester', 'explorertester', '');
        assign_capability('totara/contentmarketplace:add', CAP_ALLOW, $roleid, $syscontext->id, true);
        assign_capability($capability, CAP_ALLOW, $roleid, $syscontext->id, true);
        role_assign($roleid, $user->id, $syscontext->id);
        self::setUser($user);

        $_GET['marketplace'] = 'goone';
        $_GET['mode'] = explorer_model::MODE_CREATE_COURSE;
        $_GET['category'] = $category->id;
    }

    public function test_manage_content_button_shown_with_curatecontent_capability(): void {
        $this->set_up_create_course_request_as_user_with('contentmarketplace/goone:curatecontent');

        $controller = new catalog_import();

        self::assertTrue($controller->can_manage_marketplace_plugins());
    }

    public function test_manage_content_button_hidden_with_only_config_capability(): void {
        $this->set_up_create_course_request_as_user_with('totara/contentmarketplace:config');

        $controller = new catalog_import();

        self::assertFalse($controller->can_manage_marketplace_plugins());
    }
}
