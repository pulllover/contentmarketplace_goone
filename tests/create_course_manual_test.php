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

use contentmarketplace_goone\form\create_course_controller;
use contentmarketplace_goone\form\create_course_form;
use contentmarketplace_goone\form\general_settings_form;
use contentmarketplace_goone\helper;
use contentmarketplace_goone\workflow\core_course\coursecreate\contentmarketplace;
use core_phpunit\testcase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

defined('MOODLE_INTERNAL') || die();

/**
 * Test the manual course creation setting and the course creation page it unlocks.
 */
#[CoversClass(general_settings_form::class)]
#[CoversClass(contentmarketplace::class)]
#[Group('totara_contentmarketplace')]
class contentmarketplace_goone_create_course_manual_test extends testcase {

    /** @var string */
    private const SESSKEY = 'goonetestsesskey';

    /** @var string[] Learning object ids with fixtures under tests/behat/fixtures. */
    private const SELECTION = ['1873868', '29271'];

    protected function setUp(): void {
        parent::setUp();
        global $PAGE;

        // The rest client falls back to the mocked cURL client when the behat constant is set,
        // which keeps these tests off the live Go1 API.
        if (!defined('BEHAT_SITE_RUNNING')) {
            define('BEHAT_SITE_RUNNING', true);
        }
        set_config('oauth_access_token', '--ACCESS-TOKEN--', 'contentmarketplace_goone');

        $PAGE->set_context(context_system::instance());
        $PAGE->set_url(new moodle_url('/totara/contentmarketplace/contentmarketplaces/goone/coursecreate.php'));
    }

    protected function tearDown(): void {
        $_POST = [];
        parent::tearDown();
    }

    public function test_manual_course_creation_is_off_by_default(): void {
        unset_config('create_course_manual', 'contentmarketplace_goone');
        self::assertFalse(helper::is_manual_course_creation_enabled());

        set_config('create_course_manual', helper::CREATE_COURSE_MANUAL_DISABLED, 'contentmarketplace_goone');
        self::assertFalse(helper::is_manual_course_creation_enabled());

        set_config('create_course_manual', helper::CREATE_COURSE_MANUAL_ENABLED, 'contentmarketplace_goone');
        self::assertTrue(helper::is_manual_course_creation_enabled());
    }

    public function test_workflow_url_follows_the_setting(): void {
        $this->setAdminUser();

        $manager = new \core_course\workflow_manager\coursecreate();
        $workflow = $manager->get_workflow('\\' . contentmarketplace::class);

        set_config('create_course_manual', helper::CREATE_COURSE_MANUAL_DISABLED, 'contentmarketplace_goone');
        $url = $workflow->get_url()->out(false);
        self::assertStringContainsString('/contentmarketplaces/goone/curate.php', $url);

        set_config('create_course_manual', helper::CREATE_COURSE_MANUAL_ENABLED, 'contentmarketplace_goone');
        $url = $workflow->get_url()->out(false);
        self::assertStringContainsString('/totara/contentmarketplace/explorer.php', $url);
        self::assertStringContainsString('marketplace=goone', $url);
        self::assertStringContainsString('mode=create-course', $url);
    }

    public function test_general_settings_form_saves_a_valid_value(): void {
        $this->setAdminUser();

        set_config('create_course_manual', helper::CREATE_COURSE_MANUAL_DISABLED, 'contentmarketplace_goone');

        $this->post(general_settings_form::class, ['create_course_manual' => '1']);
        $form = new general_settings_form(['create_course_manual' => helper::CREATE_COURSE_MANUAL_DISABLED]);
        $data = $form->get_data();

        self::assertNotNull($data);
        self::assertEquals(helper::CREATE_COURSE_MANUAL_ENABLED, (int) $data->create_course_manual);

        $this->post(general_settings_form::class, ['create_course_manual' => '0']);
        $form = new general_settings_form(['create_course_manual' => helper::CREATE_COURSE_MANUAL_ENABLED]);
        $data = $form->get_data();

        self::assertNotNull($data);
        self::assertEquals(helper::CREATE_COURSE_MANUAL_DISABLED, (int) $data->create_course_manual);
    }

    public function test_general_settings_form_rejects_a_value_that_is_not_zero_or_one(): void {
        $this->setAdminUser();

        $this->post(general_settings_form::class, ['create_course_manual' => '7']);
        $form = new general_settings_form(['create_course_manual' => helper::CREATE_COURSE_MANUAL_DISABLED]);

        self::assertNull($form->get_data());
        self::assertStringContainsString(
            get_string('create_course_manual_invalid', 'contentmarketplace_goone'),
            $form->render()
        );
    }

    public function test_create_course_form_field_names_match_the_page(): void {
        $this->setAdminUser();

        $category = $this->getDataGenerator()->create_category();

        // Several items in one multi-activity course: the page reads the unsuffixed fields.
        $this->post(create_course_form::class, [
            'selection' => self::SELECTION,
            'create' => (string) create_course_form::CREATE_COURSE_MULTI_ACTIVITY,
            'mode' => 'create-course',
            'fullname' => 'Combined course',
            'shortname' => 'combined-course',
            'category' => (string) $category->id,
        ]);
        $data = $this->submitted_create_course_data(
            self::SELECTION,
            create_course_form::CREATE_COURSE_MULTI_ACTIVITY,
            $category->id
        );

        self::assertNotNull($data);
        self::assertEquals([1873868, 29271], $data->selection);
        self::assertEquals(create_course_form::CREATE_COURSE_MULTI_ACTIVITY, $data->create);
        self::assertEquals('Combined course', $data->fullname);
        self::assertEquals('combined-course', $data->shortname);
        self::assertEquals($category->id, $data->category);

        // A single item: the page reads the fields suffixed with the learning object id.
        $this->post(create_course_form::class, [
            'selection' => ['1873868'],
            'create' => (string) create_course_form::CREATE_COURSE_MULTI_ACTIVITY,
            'mode' => 'create-course',
            'fullname_1873868' => 'Single course',
            'shortname_1873868' => 'single-course',
            'category_1873868' => (string) $category->id,
        ]);
        $data = $this->submitted_create_course_data(
            ['1873868'],
            create_course_form::CREATE_COURSE_MULTI_ACTIVITY,
            $category->id
        );

        self::assertNotNull($data);
        self::assertEquals([1873868], $data->selection);
        self::assertEquals('Single course', $data->fullname_1873868);
        self::assertEquals('single-course', $data->shortname_1873868);
        self::assertEquals($category->id, $data->category_1873868);

        // A course per item: the page reads a set of fields per learning object.
        $this->post(create_course_form::class, [
            'selection' => self::SELECTION,
            'create' => (string) create_course_form::CREATE_COURSE_SINGLE_ACTIVITY,
            'mode' => 'create-course',
            'fullname_1873868' => 'First course',
            'shortname_1873868' => 'first-course',
            'category_1873868' => (string) $category->id,
            'fullname_29271' => 'Second course',
            'shortname_29271' => 'second-course',
            'category_29271' => (string) $category->id,
        ]);
        $data = $this->submitted_create_course_data(
            self::SELECTION,
            create_course_form::CREATE_COURSE_SINGLE_ACTIVITY,
            $category->id
        );

        self::assertNotNull($data);
        foreach ($data->selection as $id) {
            self::assertNotEmpty($data->{'fullname_' . $id});
            self::assertNotEmpty($data->{'shortname_' . $id});
            self::assertEquals($category->id, $data->{'category_' . $id});
        }
    }

    public function test_create_course_form_rejects_a_shortname_that_is_taken(): void {
        $this->setAdminUser();

        $category = $this->getDataGenerator()->create_category();
        $this->getDataGenerator()->create_course(['shortname' => 'taken-shortname']);

        $this->post(create_course_form::class, [
            'selection' => ['1873868'],
            'create' => (string) create_course_form::CREATE_COURSE_MULTI_ACTIVITY,
            'mode' => 'create-course',
            'fullname_1873868' => 'Single course',
            'shortname_1873868' => 'taken-shortname',
            'category_1873868' => (string) $category->id,
        ]);

        self::assertNull($this->submitted_create_course_data(
            ['1873868'],
            create_course_form::CREATE_COURSE_MULTI_ACTIVITY,
            $category->id
        ));
    }

    /**
     * Populate $_POST with the fields totara_form needs to treat the request as a submission of $form_class.
     *
     * @param string $form_class
     * @param array $fields
     *
     * @return void
     */
    private function post(string $form_class, array $fields): void {
        global $USER;

        // Set after the test has logged a user in, setUser() replaces the $USER record.
        $USER->sesskey = self::SESSKEY;
        $_POST = array_merge([
            'sesskey' => self::SESSKEY,
            '___tf_formclass' => $form_class,
            '___tf_idsuffix' => str_replace('\\', '_', $form_class),
        ], $fields);
    }

    /**
     * Build the course creation form the way coursecreate.php does and return its submitted data.
     *
     * @param array $selection
     * @param int $create
     * @param int $category
     *
     * @return stdClass|null
     */
    private function submitted_create_course_data(array $selection, int $create, int $category): ?stdClass {
        [$currentdata, $params] = create_course_controller::get_current_data_and_params(
            $selection,
            $create,
            $category,
            'create-course'
        );
        $form = new create_course_form($currentdata, $params);
        return $form->get_data();
    }

}
