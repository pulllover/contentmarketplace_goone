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
use core\orm\query\builder;
use core_phpunit\testcase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

defined('MOODLE_INTERNAL') || die();

/**
 * Test processing of retired/removed Go1 learning objects against local courses.
 */
#[CoversClass(helper::class)]
#[Group('totara_contentmarketplace')]
class contentmarketplace_goone_helper_retired_content_test extends testcase {

    protected function setUp(): void {
        parent::setUp();
        helper::$config = null;
        // The scorm generator requires a current user.
        self::setAdminUser();
    }

    protected function tearDown(): void {
        helper::$config = null;
        parent::tearDown();
    }

    /**
     * Configure retired content processing.
     *
     * @param string $actions Comma separated retired actions
     * @param string $location
     */
    private function configure_retired_content(string $actions, string $location = 'alltotara'): void {
        set_config('retired_content_process', 1, 'contentmarketplace_goone');
        set_config('retired_content_location', $location, 'contentmarketplace_goone');
        set_config('retired_content_retired_actions', $actions, 'contentmarketplace_goone');
        set_config('retired_content_removed_actions', '', 'contentmarketplace_goone');
    }

    /**
     * Create a course containing a scorm module that is tracked as originating from the given Go1 learning object.
     *
     * @param int $external_id Go1 learning object id
     * @param array $course_options
     * @return stdClass The course record
     */
    private function create_goone_course(int $external_id, array $course_options = []): stdClass {
        $generator = self::getDataGenerator();
        $course = $generator->create_course($course_options);
        $module = $generator->create_module('scorm', ['course' => $course->id]);

        $lo = builder::table('marketplace_goone_learning_object')->where('external_id', $external_id)->one();
        if (!$lo) {
            $lo_id = builder::table('marketplace_goone_learning_object')->insert(['external_id' => $external_id]);
        } else {
            $lo_id = $lo->id;
        }
        builder::table('totara_contentmarketplace_course_module_source')->insert([
            'cm_id' => $module->cmid,
            'learning_object_id' => $lo_id,
            'marketplace_component' => 'contentmarketplace_goone',
        ]);

        return $course;
    }

    /**
     * Build a Go1 "hit" for a learning object that retired in the past.
     *
     * @param int $external_id
     * @param int $retired_time
     * @param int $removed_time
     * @return stdClass
     */
    private function make_retired_hit(int $external_id, int $retired_time, int $removed_time): stdClass {
        return (object) [
            'lo_id' => $external_id,
            'core' => (object) ['image' => (object) ['value' => '']],
            'lifecycle' => (object) [
                'pending_state' => (object) [
                    'retired_time' => date('c', $retired_time),
                    'removed_time' => date('c', $removed_time),
                ],
            ],
        ];
    }

    public function test_process_retired_learning_object_not_configured(): void {
        $hit = $this->make_retired_hit(111, time() - DAYSECS, time() + 30 * DAYSECS);
        self::assertFalse(helper::process_retired_learning_object($hit));
    }

    public function test_process_retired_learning_object_untracked_lo_is_ignored(): void {
        $this->configure_retired_content('description,hide');
        $hit = $this->make_retired_hit(222, time() - DAYSECS, time() + 30 * DAYSECS);

        self::assertTrue(helper::process_retired_learning_object($hit));
        self::assertSame(0, builder::table('marketplace_goone_learning_object')->count());
    }

    public function test_process_retired_learning_object_future_retirement_does_nothing(): void {
        $this->configure_retired_content('description,hide');
        $course = $this->create_goone_course(333);
        $hit = $this->make_retired_hit(333, time() + 10 * DAYSECS, time() + 40 * DAYSECS);

        self::assertTrue(helper::process_retired_learning_object($hit));

        $course_record = builder::table('course')->where('id', $course->id)->one();
        self::assertEquals(1, $course_record->visible);
        self::assertStringNotContainsString('Leaving on', (string) $course_record->summary);
    }

    public function test_process_retired_learning_object_applies_actions_once(): void {
        $this->configure_retired_content('description,hide');
        $course = $this->create_goone_course(444, ['summary' => '<p>Original summary</p>']);
        $hit = $this->make_retired_hit(444, time() - DAYSECS, time() + 30 * DAYSECS);

        $sink = $this->redirectEvents();
        self::assertTrue(helper::process_retired_learning_object($hit));
        $events = $sink->get_events();
        $sink->close();

        // Course is hidden and the summary carries the leaving notice followed by the original text.
        $course_record = builder::table('course')->where('id', $course->id)->one();
        self::assertEquals(0, $course_record->visible);
        self::assertStringContainsString('Leaving on', $course_record->summary);
        self::assertStringContainsString('Original summary', $course_record->summary);

        // A course_updated event was triggered for the course.
        $updated_events = array_filter($events, function ($event) use ($course) {
            return $event instanceof \core\event\course_updated && (int) $event->objectid === (int) $course->id;
        });
        self::assertCount(1, $updated_events);

        // The learning object row records the executed actions.
        $lo = builder::table('marketplace_goone_learning_object')->where('external_id', 444)->one();
        self::assertSame('description,hide', $lo->retired_actioned);

        // Re-processing is idempotent: the notice is not prepended a second time.
        self::assertTrue(helper::process_retired_learning_object($hit));
        $course_record = builder::table('course')->where('id', $course->id)->one();
        self::assertSame(1, substr_count($course_record->summary, 'Leaving on'));
    }

    public function test_process_retired_learning_object_synccatonly_skips_other_categories(): void {
        $generator = self::getDataGenerator();
        $sync_category = $generator->create_category();
        $other_category = $generator->create_category();

        $this->configure_retired_content('hide', 'synccatonly');
        set_config('course_category', $sync_category->id, 'contentmarketplace_goone');

        $course = $this->create_goone_course(555, ['category' => $other_category->id]);
        $hit = $this->make_retired_hit(555, time() - DAYSECS, time() + 30 * DAYSECS);

        self::assertTrue(helper::process_retired_learning_object($hit));

        // The course lives outside the sync category so it must remain visible.
        $course_record = builder::table('course')->where('id', $course->id)->one();
        self::assertEquals(1, $course_record->visible);
    }

    public function test_process_retired_course_action_description(): void {
        $this->configure_retired_content('description');
        $course = self::getDataGenerator()->create_course(['summary' => '<p>Keep me</p>']);
        $course_record = builder::table('course')->where('id', $course->id)->one();

        $result = helper::process_retired_course_action($course_record, 'description', time() + 30 * DAYSECS, '');

        self::assertTrue($result['course_updated']);
        self::assertFalse($result['event_triggered']);
        $updated = builder::table('course')->where('id', $course->id)->one();
        self::assertStringContainsString('Leaving on', $updated->summary);
        self::assertStringContainsString('Keep me', $updated->summary);
    }

    public function test_process_retired_course_action_hide(): void {
        $this->configure_retired_content('hide');
        $course = self::getDataGenerator()->create_course();
        $course_record = builder::table('course')->where('id', $course->id)->one();

        $result = helper::process_retired_course_action($course_record, 'hide', time() + 30 * DAYSECS, '');

        self::assertTrue($result['course_updated']);
        $updated = builder::table('course')->where('id', $course->id)->one();
        self::assertEquals(0, $updated->visible);
        self::assertEquals(1, $updated->visibleold);
    }

    public function test_process_retired_course_action_move(): void {
        $generator = self::getDataGenerator();
        $target_category = $generator->create_category();

        $this->configure_retired_content('move');
        set_config('retired_content_actions_move_category', $target_category->id, 'contentmarketplace_goone');

        $course = $generator->create_course();
        $course_record = builder::table('course')->where('id', $course->id)->one();

        $result = helper::process_retired_course_action($course_record, 'move', time() + 30 * DAYSECS, '');

        self::assertTrue($result['course_updated']);
        self::assertTrue($result['event_triggered']);
        $updated = builder::table('course')->where('id', $course->id)->one();
        self::assertEquals($target_category->id, $updated->category);
    }

    public function test_process_retired_course_action_move_with_missing_category(): void {
        $this->configure_retired_content('move');
        set_config('retired_content_actions_move_category', 999999, 'contentmarketplace_goone');

        $course = self::getDataGenerator()->create_course();
        $course_record = builder::table('course')->where('id', $course->id)->one();

        $result = helper::process_retired_course_action($course_record, 'move', time() + 30 * DAYSECS, '');

        self::assertFalse($result['course_updated']);
        $updated = builder::table('course')->where('id', $course->id)->one();
        self::assertEquals($course->category, $updated->category);
    }

    public function test_process_retired_course_action_courseimage_with_invalid_url(): void {
        $this->configure_retired_content('courseimage');
        $course = self::getDataGenerator()->create_course();
        $course_record = builder::table('course')->where('id', $course->id)->one();

        // An empty/invalid image URL fails validation before any network access.
        $result = helper::process_retired_course_action($course_record, 'courseimage', time() + 30 * DAYSECS, '');

        self::assertFalse($result['course_updated']);
    }

    public function test_add_banner_to_svg_with_viewbox(): void {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 360"><rect width="640" height="360"/></svg>';

        $result = helper::add_banner_to_svg($svg, '1 December 2026');

        self::assertNotFalse($result);
        self::assertStringContainsString('Leaving on', $result);
        self::assertStringContainsString('1 December 2026', $result);
        self::assertStringContainsString('go1notice', $result);
    }

    public function test_add_banner_to_svg_with_width_and_height_attributes(): void {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="640px" height="360px"><rect width="640" height="360"/></svg>';

        $result = helper::add_banner_to_svg($svg, '1 December 2026');

        self::assertNotFalse($result);
        self::assertStringContainsString('Leaving on', $result);
    }

    public function test_add_banner_to_svg_without_dimensions_fails_gracefully(): void {
        // Neither a viewBox nor width/height attributes: the banner cannot be positioned.
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><rect width="640" height="360"/></svg>';

        self::assertFalse(helper::add_banner_to_svg($svg, '1 December 2026'));
    }

    public function test_add_banner_to_svg_with_unparseable_markup_fails_gracefully(): void {
        self::assertFalse(helper::add_banner_to_svg('this is not svg <at all', '1 December 2026'));
    }

    public function test_process_retired_course_action_unknown_action(): void {
        $this->configure_retired_content('hide');
        $course = self::getDataGenerator()->create_course();
        $course_record = builder::table('course')->where('id', $course->id)->one();

        $result = helper::process_retired_course_action($course_record, 'nosuchaction', time() + 30 * DAYSECS, '');

        self::assertFalse($result['course_updated']);
        self::assertFalse($result['event_triggered']);
    }
}
