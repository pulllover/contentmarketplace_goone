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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

defined('MOODLE_INTERNAL') || die();

/**
 * Test the pure and config-driven helpers of the helper class.
 */
#[CoversClass(helper::class)]
#[Group('totara_contentmarketplace')]
class contentmarketplace_goone_helper_test extends testcase {

    protected function setUp(): void {
        parent::setUp();
        // The helper caches its config storage in a static, drop any leftover from other tests.
        helper::$config = null;
    }

    protected function tearDown(): void {
        helper::$config = null;
        parent::tearDown();
    }

    #[DataProvider('duration_provider')]
    public function test_generate_duration(int $mins, string $expected): void {
        self::assertSame($expected, helper::generate_duration($mins));
    }

    public static function duration_provider(): array {
        return [
            'zero' => [0, ''],
            'under an hour' => [30, '30 mins'],
            'one minute short of an hour' => [59, '59 mins'],
            'exactly one hour' => [60, '1 hr'],
            'between one and two hours' => [90, '1 hr 30 mins'],
            'exactly two hours' => [120, '2 hrs'],
            'exact multiple of an hour' => [180, '3 hrs'],
            'hours and minutes' => [145, '2 hrs 25 mins'],
        ];
    }

    public function test_sort_skills_sorts_by_confidence_descending(): void {
        $items = [
            (object) ['name' => 'low', 'confidence' => 0.1],
            (object) ['name' => 'high', 'confidence' => 0.9],
            (object) ['name' => 'mid', 'confidence' => 0.5],
        ];

        $sorted = helper::sort_skills($items);

        self::assertSame(['high', 'mid', 'low'], array_column($sorted, 'name'));
    }

    public function test_sort_skills_single_item_is_unchanged(): void {
        $items = [(object) ['name' => 'only', 'confidence' => 0.4]];
        self::assertSame($items, helper::sort_skills($items));
    }

    public function test_get_completionstatusrequired(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/scorm/lib.php');

        self::assertSame(2, helper::get_completionstatusrequired('passed'));
        self::assertSame(4, helper::get_completionstatusrequired('completed'));

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('Unknown completionstatus option: nosuchstatus');
        helper::get_completionstatusrequired('nosuchstatus');
    }

    public function test_deduplicate_course_shortname(): void {
        $generator = self::getDataGenerator();

        // No clash, name kept as is.
        self::assertSame('goone_unique', helper::deduplicate_course_shortname('goone_unique'));

        // One existing course, a numeric suffix is added.
        $generator->create_course(['shortname' => 'goone_dup']);
        self::assertSame('goone_dup_2', helper::deduplicate_course_shortname('goone_dup'));

        // Suffixed name taken as well, counter keeps incrementing.
        $generator->create_course(['shortname' => 'goone_dup_2']);
        self::assertSame('goone_dup_3', helper::deduplicate_course_shortname('goone_dup'));
    }

    public function test_generate_topics_escapes_names(): void {
        $hit = (object) [
            'topics' => (object) [
                'items' => [
                    (object) ['name' => 'PHP & SQL'],
                    (object) ['name' => '<script>alert(1)</script>'],
                ],
            ],
        ];

        $html = helper::generate_topics($hit);

        self::assertStringContainsString('Topics', $html);
        self::assertStringContainsString('PHP &amp; SQL', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<script>', $html);
    }

    public function test_generate_topics_without_topics_is_empty(): void {
        self::assertSame('', helper::generate_topics((object) []));
        self::assertSame('', helper::generate_topics((object) ['topics' => (object) ['items' => []]]));
    }

    public function test_generate_skills_covered_sorts_and_escapes(): void {
        $hit = (object) [
            'skills' => (object) [
                'items' => [
                    (object) ['name' => 'B <skill>', 'confidence' => 0.2],
                    (object) ['name' => 'A skill', 'confidence' => 0.9],
                ],
            ],
        ];

        $html = helper::generate_skills_covered($hit);

        self::assertStringContainsString('Skills covered', $html);
        self::assertStringNotContainsString('<skill>', $html);
        // Higher-confidence skill must be listed first.
        self::assertLessThan(strpos($html, 'B &lt;skill&gt;'), strpos($html, 'A skill'));
    }

    public function test_generate_skills_covered_without_skills_is_empty(): void {
        self::assertSame('', helper::generate_skills_covered((object) []));
    }

    public function test_generate_learning_outcomes_strips_tags(): void {
        $hit = (object) [
            'relevance' => (object) [
                'learning_outcomes' => [
                    'Learn <b>things</b>',
                    'Achieve & succeed',
                ],
            ],
        ];

        $html = helper::generate_learning_outcomes($hit);

        self::assertStringContainsString('Learning outcomes', $html);
        self::assertStringContainsString('Learn things', $html);
        self::assertStringNotContainsString('<b>', $html);
    }

    public function test_generate_learning_outcomes_without_outcomes_is_empty(): void {
        self::assertSame('', helper::generate_learning_outcomes((object) []));
        self::assertSame('', helper::generate_learning_outcomes((object) ['relevance' => (object) ['learning_outcomes' => 'not-an-array']]));
    }

    public function test_generate_description(): void {
        $hit = (object) [
            'provider' => (object) ['name' => 'Acme Learning'],
            'core' => (object) [
                'type' => 'course',
                'description' => '<p>A great course.</p><script>alert(1)</script>',
            ],
            'relevance' => (object) [
                'duration' => 90,
                'learning_outcomes' => ['Do things well'],
            ],
        ];

        $html = helper::generate_description($hit);

        self::assertStringContainsString('Acme Learning', $html);
        // Type is capitalised and combined with duration.
        self::assertStringContainsString('Course', $html);
        self::assertStringContainsString('1 hr 30 mins', $html);
        self::assertStringContainsString('Overview', $html);
        self::assertStringContainsString('A great course.', $html);
        self::assertStringContainsString('Do things well', $html);
        // Scripts must not survive cleaning.
        self::assertStringNotContainsString('<script>', $html);
    }

    public function test_generate_description_honours_formatstringstriptags(): void {
        global $CFG;

        $hit = (object) [
            'provider' => (object) ['name' => 'Acme <b>Learning</b>'],
            'core' => (object) [
                'type' => 'course',
                'description' => '<p>A great course.</p>',
            ],
            'relevance' => (object) ['duration' => 0],
        ];

        // With tag stripping enabled the provider name is reduced to plain text.
        $CFG->formatstringstriptags = 1;
        $html = helper::generate_description($hit);
        self::assertStringContainsString('Acme Learning', $html);
        self::assertStringNotContainsString('<b>', $html);

        // Without tag stripping the (purified) markup is kept.
        $CFG->formatstringstriptags = 0;
        $html = helper::generate_description($hit);
        self::assertStringContainsString('Acme <b>Learning</b>', $html);
    }

    #[DataProvider('image_headers_provider')]
    public function test_validate_image_headers(array $headers, string|false $expected): void {
        self::assertSame($expected, helper::validate_image_headers($headers));
    }

    public static function image_headers_provider(): array {
        return [
            'standard header case' => [
                ['HTTP/1.1 200 OK', 'Content-Type' => 'image/png'],
                'image/png',
            ],
            'lowercase header names' => [
                ['HTTP/1.1 200 OK', 'content-type' => 'image/jpeg', 'content-length' => '1024'],
                'image/jpeg',
            ],
            'uppercase header names' => [
                ['HTTP/1.1 200 OK', 'CONTENT-TYPE' => 'image/gif'],
                'image/gif',
            ],
            'media type with charset parameter' => [
                ['HTTP/1.1 200 OK', 'Content-Type' => 'image/svg+xml; charset=utf-8'],
                'image/svg+xml',
            ],
            'array valued content type' => [
                ['HTTP/1.1 200 OK', 'Content-Type' => ['image/webp', 'image/webp']],
                'image/webp',
            ],
            'non-200 response' => [
                ['HTTP/1.1 404 Not Found', 'Content-Type' => 'image/png'],
                false,
            ],
            'missing content type' => [
                ['HTTP/1.1 200 OK', 'Content-Length' => '1024'],
                false,
            ],
            'disallowed media type' => [
                ['HTTP/1.1 200 OK', 'Content-Type' => 'text/html'],
                false,
            ],
            'oversize content length' => [
                ['HTTP/1.1 200 OK', 'Content-Type' => 'image/png', 'content-length' => (string) (21 * 1024 * 1024)],
                false,
            ],
        ];
    }

    public function test_is_create_courses_on_and_configured(): void {
        // Nothing configured.
        self::assertFalse(helper::is_create_courses_on_and_configured());

        // Enabled but not configured.
        set_config('create_courses', 1, 'contentmarketplace_goone');
        self::assertFalse(helper::is_create_courses_on_and_configured());

        // Fully configured.
        set_config('course_category', 1, 'contentmarketplace_goone');
        set_config('course_type', helper::CREATE_COURSE_SINGLE, 'contentmarketplace_goone');
        set_config('sync_collections', 'custom', 'contentmarketplace_goone');
        self::assertTrue(helper::is_create_courses_on_and_configured());

        // Disabled again.
        set_config('create_courses', 0, 'contentmarketplace_goone');
        self::assertFalse(helper::is_create_courses_on_and_configured());
    }

    public function test_is_retired_content_on_and_configured(): void {
        // Nothing configured.
        self::assertFalse(helper::is_retired_content_on_and_configured());

        // Enabled, but no location or actions yet.
        set_config('retired_content_process', 1, 'contentmarketplace_goone');
        self::assertFalse(helper::is_retired_content_on_and_configured());

        // Location set, still no actions.
        set_config('retired_content_location', 'synccatonly', 'contentmarketplace_goone');
        self::assertFalse(helper::is_retired_content_on_and_configured());

        // With a non-move action it is fully configured.
        set_config('retired_content_retired_actions', 'hide', 'contentmarketplace_goone');
        self::assertTrue(helper::is_retired_content_on_and_configured());

        // A move action without a target category is not configured.
        set_config('retired_content_retired_actions', 'move', 'contentmarketplace_goone');
        self::assertFalse(helper::is_retired_content_on_and_configured());

        // Move action with a target category is configured.
        set_config('retired_content_actions_move_category', 2, 'contentmarketplace_goone');
        self::assertTrue(helper::is_retired_content_on_and_configured());
    }

    public function test_apply_course_completion_defaults_sets_completion_fields(): void {
        $coursedata = new stdClass();
        $coursedata->fullname = 'Kept as is';

        helper::apply_course_completion_defaults($coursedata);

        self::assertSame(COMPLETION_ENABLED, $coursedata->enablecompletion);
        self::assertSame(1, $coursedata->completionstartonenrol);
        self::assertSame(1, $coursedata->completionprogressonview);
        // Existing fields are untouched.
        self::assertSame('Kept as is', $coursedata->fullname);
    }

    public function test_set_single_activity_completion_creates_criterion_and_aggregations(): void {
        global $CFG;
        require_once($CFG->dirroot . '/lib/completionlib.php');
        self::setAdminUser();

        $generator = self::getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => COMPLETION_ENABLED]);
        $module = $generator->create_module('page', ['course' => $course->id]);

        helper::set_single_activity_completion($course, (int) $module->cmid);

        // Exactly one criterion, of activity type, pointing at the module.
        $criteria = builder::table('course_completion_criteria')->where('course', $course->id)->get()->all();
        self::assertCount(1, $criteria);
        $criterion = reset($criteria);
        self::assertEquals(COMPLETION_CRITERIA_TYPE_ACTIVITY, $criterion->criteriatype);
        self::assertEquals($module->cmid, $criterion->moduleinstance);
        self::assertSame('page', $criterion->module);

        // Overall aggregation: all criteria required.
        $overall = builder::table('course_completion_aggr_methd')
            ->where('course', $course->id)
            ->where_null('criteriatype')
            ->one();
        self::assertNotNull($overall);
        self::assertEquals(COMPLETION_AGGREGATION_ALL, $overall->method);

        // Activity aggregation: all activities required.
        $activity = builder::table('course_completion_aggr_methd')
            ->where('course', $course->id)
            ->where('criteriatype', COMPLETION_CRITERIA_TYPE_ACTIVITY)
            ->one();
        self::assertNotNull($activity);
        self::assertEquals(COMPLETION_AGGREGATION_ALL, $activity->method);
    }
}
