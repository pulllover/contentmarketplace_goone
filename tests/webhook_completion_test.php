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

use contentmarketplace_goone\testing\generator;
use contentmarketplace_goone\webhook;
use core\orm\query\builder;
use core_phpunit\testcase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

defined('MOODLE_INTERNAL') || die();

/**
 * Test the webhook completion processing and Go1-to-Totara user matching.
 *
 * Uses the mocked Go1 user account 501 (email learner501@example.com, no external_user_id)
 * and 502 (external_user_id "extuser502"), and enrolments 9001 (passed) / 9002 (not passed)
 * for learning object 29271.
 */
#[CoversClass(webhook::class)]
#[Group('totara_contentmarketplace')]
class contentmarketplace_goone_webhook_completion_test extends testcase {

    protected function setUp(): void {
        parent::setUp();
        self::setAdminUser();
    }

    /**
     * Create a course containing a completion-tracked scorm module linked to the given Go1 learning object.
     *
     * @param int $external_id
     * @return array [course record, course module record]
     */
    private function create_goone_scorm_course(int $external_id): array {
        $generator = self::getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => COMPLETION_ENABLED]);
        $module = $generator->create_module('scorm', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 1,
        ]);

        $lo_id = builder::table('marketplace_goone_learning_object')->insert(['external_id' => $external_id]);
        builder::table('totara_contentmarketplace_course_module_source')->insert([
            'cm_id' => $module->cmid,
            'learning_object_id' => $lo_id,
            'marketplace_component' => 'contentmarketplace_goone',
        ]);

        $cm = builder::table('course_modules')->where('id', $module->cmid)->one();
        return [$course, $cm];
    }

    /**
     * @param int $enrolment_id
     * @param int $lo_id
     * @return stdClass Decoded webhook payload as Go1 would send it
     */
    private function make_payload(int $enrolment_id, int $lo_id): stdClass {
        return (object) [
            'event_type' => 'enrollment.complete',
            'data' => (object) [
                'id' => $enrolment_id,
                'lo_id' => $lo_id,
            ],
        ];
    }

    public function test_passed_enrolment_marks_completion_for_matched_user(): void {
        [$course, $cm] = $this->create_goone_scorm_course(29271);

        $user = self::getDataGenerator()->create_user(['email' => 'learner501@example.com']);
        self::getDataGenerator()->enrol_user($user->id, $course->id);

        $api = generator::instance()->get_mock_api();
        webhook::process_payload_completion($this->make_payload(9001, 29271), '{}', $api);

        $completion = builder::table('course_modules_completion')
            ->where('coursemoduleid', $cm->id)
            ->where('userid', $user->id)
            ->one();
        self::assertNotNull($completion);
        self::assertContains((int) $completion->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS]);

        // No webhook errors were logged.
        self::assertSame(0, builder::table('marketplace_goone_webhook_logs')->count());
    }

    public function test_not_passed_enrolment_is_ignored(): void {
        [$course, $cm] = $this->create_goone_scorm_course(29271);

        $user = self::getDataGenerator()->create_user(['email' => 'learner501@example.com']);
        self::getDataGenerator()->enrol_user($user->id, $course->id);

        $api = generator::instance()->get_mock_api();
        webhook::process_payload_completion($this->make_payload(9002, 29271), '{}', $api);

        self::assertNull(
            builder::table('course_modules_completion')
                ->where('coursemoduleid', $cm->id)
                ->where('userid', $user->id)
                ->one()
        );
    }

    public function test_unenrolled_user_is_skipped(): void {
        [, $cm] = $this->create_goone_scorm_course(29271);

        // User exists and matches, but is not enrolled in the course.
        $user = self::getDataGenerator()->create_user(['email' => 'learner501@example.com']);

        $api = generator::instance()->get_mock_api();
        webhook::process_payload_completion($this->make_payload(9001, 29271), '{}', $api);

        self::assertNull(
            builder::table('course_modules_completion')
                ->where('coursemoduleid', $cm->id)
                ->where('userid', $user->id)
                ->one()
        );
        self::assertSame(0, builder::table('marketplace_goone_webhook_logs')->count());
    }

    public function test_enrolment_api_error_is_logged_not_thrown(): void {
        // No mock fixture exists for enrolment 999999, so the API layer throws;
        // the webhook must log the failure instead of letting it escape as a 500.
        $api = generator::instance()->get_mock_api();

        webhook::process_payload_completion($this->make_payload(999999, 29271), '{"raw":"payload"}', $api);

        $log = builder::table('marketplace_goone_webhook_logs')->one();
        self::assertNotNull($log);
        self::assertStringContainsString('{"raw":"payload"}', $log->message);
    }

    public function test_unknown_learning_object_logs_error(): void {
        // No course/learning object rows exist at all.
        self::getDataGenerator()->create_user(['email' => 'learner501@example.com']);

        $api = generator::instance()->get_mock_api();
        webhook::process_payload_completion($this->make_payload(9001, 29271), '{"raw":"payload"}', $api);

        $log = builder::table('marketplace_goone_webhook_logs')->one();
        self::assertNotNull($log);
        self::assertStringContainsString('SCORMs not found by lo_id: 29271', $log->message);
        self::assertStringContainsString('{"raw":"payload"}', $log->message);
    }

    public function test_unmatchable_user_logs_error(): void {
        $this->create_goone_scorm_course(29271);
        // No Totara user matches learner501@example.com in any way.

        $api = generator::instance()->get_mock_api();
        webhook::process_payload_completion($this->make_payload(9001, 29271), '{}', $api);

        $log = builder::table('marketplace_goone_webhook_logs')->one();
        self::assertNotNull($log);
        self::assertStringContainsString('User not found', $log->message);
    }

    public function test_get_lms_user_matches_by_email(): void {
        $user = self::getDataGenerator()->create_user(['email' => 'learner501@example.com']);

        $api = generator::instance()->get_mock_api();
        $matched = webhook::get_lms_user(501, $api);

        self::assertNotFalse($matched);
        self::assertEquals($user->id, $matched->id);
    }

    public function test_get_lms_user_matches_by_email_as_username(): void {
        $user = self::getDataGenerator()->create_user(['username' => 'learner501@example.com']);

        $api = generator::instance()->get_mock_api();
        $matched = webhook::get_lms_user(501, $api);

        self::assertNotFalse($matched);
        self::assertEquals($user->id, $matched->id);
    }

    public function test_get_lms_user_matches_by_email_local_part_as_username(): void {
        $user = self::getDataGenerator()->create_user(['username' => 'learner501']);

        $api = generator::instance()->get_mock_api();
        $matched = webhook::get_lms_user(501, $api);

        self::assertNotFalse($matched);
        self::assertEquals($user->id, $matched->id);
    }

    public function test_get_lms_user_matches_by_external_user_id_as_username(): void {
        $user = self::getDataGenerator()->create_user(['username' => 'extuser502']);

        $api = generator::instance()->get_mock_api();
        $matched = webhook::get_lms_user(502, $api);

        self::assertNotFalse($matched);
        self::assertEquals($user->id, $matched->id);
    }

    public function test_get_lms_user_matches_by_name_as_last_resort(): void {
        $user = self::getDataGenerator()->create_user(['firstname' => 'Lea', 'lastname' => 'Rner']);

        $api = generator::instance()->get_mock_api();
        $matched = webhook::get_lms_user(501, $api);

        self::assertNotFalse($matched);
        self::assertEquals($user->id, $matched->id);
    }

    public function test_get_lms_user_ignores_deleted_user(): void {
        global $DB;

        $user = self::getDataGenerator()->create_user(['email' => 'learner501@example.com']);
        // Flag the record deleted directly so the email/username are kept intact,
        // as happens with imported or externally synced data.
        $DB->set_field('user', 'deleted', 1, ['id' => $user->id]);

        $api = generator::instance()->get_mock_api();

        self::assertFalse(webhook::get_lms_user(501, $api));
    }

    public function test_get_lms_user_ignores_suspended_user(): void {
        global $DB;

        $user = self::getDataGenerator()->create_user(['email' => 'learner501@example.com']);
        $DB->set_field('user', 'suspended', 1, ['id' => $user->id]);

        $api = generator::instance()->get_mock_api();

        self::assertFalse(webhook::get_lms_user(501, $api));
    }

    public function test_get_lms_user_cascade_skips_suspended_and_matches_active_user(): void {
        global $DB;

        // A suspended account matches by email, but the cascade must move on and
        // match the active account by the email local part as username.
        $suspended = self::getDataGenerator()->create_user(['email' => 'learner501@example.com']);
        $DB->set_field('user', 'suspended', 1, ['id' => $suspended->id]);
        $active = self::getDataGenerator()->create_user(['username' => 'learner501']);

        $api = generator::instance()->get_mock_api();
        $matched = webhook::get_lms_user(501, $api);

        self::assertNotFalse($matched);
        self::assertEquals($active->id, $matched->id);
    }

    public function test_get_lms_user_name_fallback_counts_only_active_users(): void {
        global $DB;

        // Two users share the name, but one is suspended, so the match is unambiguous.
        $suspended = self::getDataGenerator()->create_user(['firstname' => 'Lea', 'lastname' => 'Rner']);
        $DB->set_field('user', 'suspended', 1, ['id' => $suspended->id]);
        $active = self::getDataGenerator()->create_user(['firstname' => 'Lea', 'lastname' => 'Rner']);

        $api = generator::instance()->get_mock_api();
        $matched = webhook::get_lms_user(501, $api);

        self::assertNotFalse($matched);
        self::assertEquals($active->id, $matched->id);
    }

    public function test_get_lms_user_ambiguous_name_match_is_rejected(): void {
        self::getDataGenerator()->create_user(['firstname' => 'Lea', 'lastname' => 'Rner']);
        self::getDataGenerator()->create_user(['firstname' => 'Lea', 'lastname' => 'Rner']);

        $api = generator::instance()->get_mock_api();

        self::assertFalse(webhook::get_lms_user(501, $api));
    }

    public function test_log_error_inserts_and_prunes(): void {
        // An old entry beyond the 30 day retention window.
        builder::table('marketplace_goone_webhook_logs')->insert([
            'message' => 'ancient entry',
            'timecreated' => time() - 31 * DAYSECS,
        ]);

        webhook::log_error('fresh problem', '{"payload":1}');

        $messages = builder::table('marketplace_goone_webhook_logs')->get()->pluck('message');
        self::assertCount(1, $messages);
        self::assertStringContainsString('fresh problem', $messages[0]);
    }
}
