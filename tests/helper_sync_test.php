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

use contentmarketplace_goone\helper;
use contentmarketplace_goone\model\learning_object;
use contentmarketplace_goone\testing\generator;
use core\orm\query\builder;
use core_phpunit\testcase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

defined('MOODLE_INTERNAL') || die();

/**
 * Test creation of courses from Go1 learning objects during content sync.
 */
#[CoversClass(helper::class)]
#[Group('totara_contentmarketplace')]
class contentmarketplace_goone_helper_sync_test extends testcase {

    protected function setUp(): void {
        parent::setUp();
        helper::$config = null;
        self::setAdminUser();
    }

    protected function tearDown(): void {
        helper::$config = null;
        // The learning_object model caches an API instance in a static, drop it so
        // other tests are not affected by the mock used here.
        $reflection = new ReflectionClass(learning_object::class);
        $reflection->setStaticPropertyValue('api', null);
        parent::tearDown();
    }

    /**
     * Configure course creation sync.
     *
     * @param int $category_id
     * @param string $course_type
     * @param string $course_shortname
     */
    private function configure_sync(int $category_id, string $course_type, string $course_shortname = 'withloid'): void {
        set_config('create_courses', 1, 'contentmarketplace_goone');
        set_config('course_category', $category_id, 'contentmarketplace_goone');
        set_config('course_type', $course_type, 'contentmarketplace_goone');
        set_config('course_shortname', $course_shortname, 'contentmarketplace_goone');
        set_config('sync_collections', 'custom', 'contentmarketplace_goone');
    }

    /**
     * Build a v3-shaped Go1 listing hit for the mocked learning object 29271.
     *
     * @return stdClass
     */
    private function make_hit(): stdClass {
        return (object) [
            'lo_id' => 29271,
            'core' => (object) [
                'title' => 'Responding to Seizures',
                'description' => '<p>Learn how to respond to seizures.</p>',
                'type' => 'course',
                'image' => (object) ['value' => ''],
            ],
            'provider' => (object) ['name' => 'Test Provider'],
            'relevance' => (object) ['duration' => 45],
            'playback_behavior' => (object) ['assessable' => true],
        ];
    }

    public function test_process_sync_learning_object_not_configured(): void {
        $result = helper::process_sync_learning_object($this->make_hit(), generator::instance()->get_mock_api());

        self::assertFalse($result['success']);
        self::assertFalse($result['created']);
        self::assertSame('Plugin sync not configured properly', $result['message']);
    }

    public function test_create_course_from_learning_object_single_activity(): void {
        $category = self::getDataGenerator()->create_category();
        $this->configure_sync($category->id, helper::CREATE_COURSE_SINGLE);
        $api = generator::instance()->get_mock_api();

        self::assertTrue(helper::create_course_from_learning_object($this->make_hit(), $api));

        $course = builder::table('course')->where('shortname', 'goone_29271')->one();
        self::assertNotNull($course);
        self::assertEquals($category->id, $course->category);
        self::assertSame('singleactivity', $course->format);
        self::assertEquals(1, $course->enablecompletion);
        self::assertEquals(1, $course->completionstartonenrol);
        self::assertEquals(1, $course->completionprogressonview);
        self::assertSame('Responding to Seizures', $course->fullname);
        self::assertStringContainsString('Test Provider', $course->summary);

        // A scorm module was created and linked back to the learning object.
        $scorm = builder::table('scorm')->where('course', $course->id)->one();
        self::assertNotNull($scorm);

        $source = builder::table('totara_contentmarketplace_course_module_source')
            ->join(['marketplace_goone_learning_object', 'lo'], 'learning_object_id', 'id')
            ->where('marketplace_component', 'contentmarketplace_goone')
            ->where('lo.external_id', 29271)
            ->one();
        self::assertNotNull($source);

        // The scorm activity is the course completion criterion.
        $criteria = builder::table('course_completion_criteria')->where('course', $course->id)->get()->all();
        self::assertCount(1, $criteria);
        self::assertEquals(COMPLETION_CRITERIA_TYPE_ACTIVITY, reset($criteria)->criteriatype);

        $aggregations = builder::table('course_completion_aggr_methd')->where('course', $course->id)->count();
        self::assertGreaterThanOrEqual(2, $aggregations);
    }

    public function test_create_course_from_learning_object_multi_activity(): void {
        $category = self::getDataGenerator()->create_category();
        $this->configure_sync($category->id, helper::CREATE_COURSE_MULTI, 'fullname');
        $api = generator::instance()->get_mock_api();

        self::assertTrue(helper::create_course_from_learning_object($this->make_hit(), $api));

        $course = builder::table('course')->where('fullname', 'Responding to Seizures')->one();
        self::assertNotNull($course);
        self::assertNotEquals('singleactivity', $course->format);
        // Shortname derives from the title rather than the learning object id.
        self::assertSame('Responding to Seizures', $course->shortname);

        // No single-activity completion criteria for multi-activity courses.
        self::assertSame(0, builder::table('course_completion_criteria')->where('course', $course->id)->count());
    }

    public function test_process_sync_learning_object_creates_then_skips(): void {
        $category = self::getDataGenerator()->create_category();
        $this->configure_sync($category->id, helper::CREATE_COURSE_SINGLE);
        $api = generator::instance()->get_mock_api();

        $first = helper::process_sync_learning_object($this->make_hit(), $api);
        self::assertTrue($first['success']);
        self::assertTrue($first['created']);

        // The same learning object is not turned into a second course in the sync category.
        $second = helper::process_sync_learning_object($this->make_hit(), $api);
        self::assertTrue($second['success']);
        self::assertFalse($second['created']);
        self::assertSame('SCORM activity already exists in the sync category', $second['message']);

        self::assertSame(1, builder::table('course')->where('shortname', 'goone_29271')->count());
    }

    public function test_single_activity_course_scorm_skips_view_on_first_access_only(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/scorm/locallib.php');
        $category = self::getDataGenerator()->create_category();
        $this->configure_sync($category->id, helper::CREATE_COURSE_SINGLE);

        self::assertTrue(helper::create_course_from_learning_object($this->make_hit(), generator::instance()->get_mock_api()));

        $course = builder::table('course')->where('shortname', 'goone_29271')->one();
        $scorm = builder::table('scorm')->where('course', $course->id)->one();
        // Single activity courses have no course page to return to when the SCORM pop-up closes,
        // so skipping the view page on every visit would relaunch the pop-up in a loop.
        self::assertEquals(SCORM_SKIPVIEW_FIRST, $scorm->skipview);
    }

    public function test_multi_activity_course_scorm_always_skips_view(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/scorm/locallib.php');
        $category = self::getDataGenerator()->create_category();
        $this->configure_sync($category->id, helper::CREATE_COURSE_MULTI, 'fullname');

        self::assertTrue(helper::create_course_from_learning_object($this->make_hit(), generator::instance()->get_mock_api()));

        $course = builder::table('course')->where('fullname', 'Responding to Seizures')->one();
        $scorm = builder::table('scorm')->where('course', $course->id)->one();
        self::assertEquals(SCORM_SKIPVIEW_ALWAYS, $scorm->skipview);
    }
}
