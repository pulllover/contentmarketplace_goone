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

use contentmarketplace_goone\api;
use contentmarketplace_goone\mock_config_storage;
use contentmarketplace_goone\mock_playback_curl;
use contentmarketplace_goone\oauth;
use contentmarketplace_goone\oauth_rest_client;
use contentmarketplace_goone\testing\generator;
use core_phpunit\testcase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

defined('MOODLE_INTERNAL') || die();

/**
 * Test the Go1 API wrapper.
 */
#[CoversClass(api::class)]
#[Group('totara_contentmarketplace')]
class contentmarketplace_goone_api_test extends testcase {

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        require_once(__DIR__ . '/fixtures/mock_config_storage.php');
        require_once(__DIR__ . '/fixtures/mock_playback_curl.php');
    }

    /**
     * Build an api instance whose HTTP layer is the given playback curl.
     *
     * @param mock_playback_curl $curl
     * @return api
     */
    private function make_api_with_playback_curl(mock_playback_curl $curl): api {
        $api = new api();
        $oauth = new oauth(new mock_config_storage(), $curl);
        $client = new oauth_rest_client(api::ENDPOINT, $oauth, $curl);

        $reflection = new ReflectionProperty(api::class, 'client');
        $reflection->setValue($api, $client);

        return $api;
    }

    /**
     * The options the playback curl expects for a GET issued through the rest client.
     *
     * @return array
     */
    private function expected_get_options(): array {
        return [
            'HEADER' => 0,
            'HTTPHEADER' => [
                'Authorization: Bearer --OAUTH_ACCESS_TOKEN--',
                'Accept: application/json',
            ],
            'FRESH_CONNECT' => true,
            'RETURNTRANSFER' => true,
            'FORBID_REUSE' => true,
            'SSL_VERIFYPEER' => true,
            'SSL_VERIFYHOST' => 2,
            'CONNECTTIMEOUT' => 0,
            'TIMEOUT' => 20,
            'CURLOPT_HTTPGET' => 1,
        ];
    }

    public function test_get_learning_object_rejects_non_integer_ids(): void {
        $api = generator::instance()->get_mock_api();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('GO1 learning-objects are expected to have integer ids');
        $api->get_learning_object('29271; DROP TABLE');
    }

    public function test_get_learning_object_populates_optional_properties(): void {
        $api = generator::instance()->get_mock_api();

        $lo = $api->get_learning_object('29271');

        self::assertSame('Mocked learning object 29271', $lo->core->title);
        // Optional blocks are always present after cleaning, even when the API omits them.
        self::assertObjectHasProperty('playback_behavior', $lo);
        self::assertObjectHasProperty('assessable', $lo->playback_behavior);
        self::assertObjectHasProperty('pricing', $lo);
        self::assertObjectHasProperty('currency', $lo->pricing);
        self::assertObjectHasProperty('provider', $lo);
        self::assertObjectHasProperty('quality', $lo);
    }

    public function test_get_learning_objects_requires_total(): void {
        $curl = new mock_playback_curl($this);
        $params = ['type' => ['course', 'document', 'link', 'interactive', 'text', 'video', 'audio']];
        $url = api::ENDPOINT . '/learning-objects?' . http_build_query($params, '', '&');
        $curl->record(
            $url,
            $this->expected_get_options(),
            ['url' => $url, 'http_code' => 200, 'content_type' => 'application/json'],
            '{"hits": []}'
        );
        $api = $this->make_api_with_playback_curl($curl);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('missing expected value for "total"');
        $api->get_learning_objects();
    }

    public function test_collection_count_is_cached(): void {
        $curl = new mock_playback_curl($this);
        $params = [
            'type' => ['course', 'document', 'link', 'interactive', 'text', 'video', 'audio'],
            'collection' => 'free',
        ];
        $url = api::ENDPOINT . '/learning-objects?' . http_build_query($params, '', '&');
        // Only ONE response is recorded: a second HTTP request would fail the test.
        $curl->record(
            $url,
            $this->expected_get_options(),
            ['url' => $url, 'http_code' => 200, 'content_type' => 'application/json'],
            '{"total": 42}'
        );
        $api = $this->make_api_with_playback_curl($curl);

        self::assertSame(42, $api->get_learning_objects_collection_count('free'));
        // Second call must be served from the cache.
        self::assertSame(42, $api->get_learning_objects_collection_count('free'));
    }

    public function test_get_learning_objects_facets_is_cached(): void {
        $curl = new mock_playback_curl($this);
        $params = [
            'offset' => 0,
            'limit' => 1,
            'facets' => ['topics', 'language', 'providers'],
            'collection' => 'free',
            'type' => ['course', 'document', 'link', 'interactive', 'text', 'video', 'audio'],
        ];
        $url = api::ENDPOINT . '/learning-objects?' . http_build_query($params, '', '&');
        // Only ONE response is recorded: a second HTTP request would fail the test.
        $curl->record(
            $url,
            $this->expected_get_options(),
            ['url' => $url, 'http_code' => 200, 'content_type' => 'application/json'],
            '{"total": 5, "hits": [], "facets": {"providers": [{"key": 42, "name": "Provider", "count": 5}]}}'
        );
        $api = $this->make_api_with_playback_curl($curl);

        $facets = $api->get_learning_objects_facets('free');
        self::assertSame(42, $facets->providers[0]->key);
        // Second call must be served from the cache.
        $facets = $api->get_learning_objects_facets('free');
        self::assertSame(42, $facets->providers[0]->key);
    }

    public function test_collection_count_retired_is_not_cached(): void {
        $curl = new mock_playback_curl($this);
        $params = ['collection' => 'free', 'state' => 'retired'];
        $url = api::ENDPOINT . '/learning-objects?' . http_build_query($params, '', '&');
        $info = ['url' => $url, 'http_code' => 200, 'content_type' => 'application/json'];
        $curl->record($url, $this->expected_get_options(), $info, '{"total": 7}');
        $curl->record($url, $this->expected_get_options(), $info, '{"total": 6}');
        $api = $this->make_api_with_playback_curl($curl);

        self::assertSame(7, $api->get_learning_objects_collection_count_retired('free'));
        self::assertSame(6, $api->get_learning_objects_collection_count_retired('free'));
    }

    public function test_get_scorm_uses_download_timeout(): void {
        $curl = new mock_playback_curl($this);
        $url = api::ENDPOINT . '/learning-objects/29271/scorm';
        $expected_options = $this->expected_get_options();
        $expected_options['HTTPHEADER'] = [
            'Accept: application/zip',
            'Authorization: Bearer --OAUTH_ACCESS_TOKEN--',
        ];
        $expected_options['TIMEOUT'] = api::TIMEOUT_DOWNLOAD;
        $curl->record(
            $url,
            $expected_options,
            ['url' => $url, 'http_code' => 200, 'content_type' => 'application/zip'],
            'ZIPDATA'
        );
        $api = $this->make_api_with_playback_curl($curl);

        self::assertSame('ZIPDATA', $api->get_scorm(29271));
    }

    public function test_create_user(): void {
        $curl = new mock_playback_curl($this);
        $url = api::ENDPOINT . '/user-accounts';
        $expected_options = [
            'HEADER' => 0,
            'HTTPHEADER' => [
                'Authorization: Bearer --OAUTH_ACCESS_TOKEN--',
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            'FRESH_CONNECT' => true,
            'RETURNTRANSFER' => true,
            'FORBID_REUSE' => true,
            'SSL_VERIFYPEER' => true,
            'SSL_VERIFYHOST' => 2,
            'CONNECTTIMEOUT' => 0,
            'TIMEOUT' => 20,
            'CURLOPT_POST' => 1,
            'CURLOPT_POSTFIELDS' => json_encode([
                'given_name' => 'Lea',
                'family_name' => 'Rner',
                'username' => 'learner1',
                'email' => 'learner1@example.com',
                'roles' => ['rol_01G3PZS7NZ0CF95J340P55FSZH'],
            ]),
        ];
        $curl->record(
            $url,
            $expected_options,
            ['url' => $url, 'http_code' => 201, 'content_type' => 'application/json'],
            '{"id": 777}'
        );
        $api = $this->make_api_with_playback_curl($curl);

        $user = (object) [
            'firstname' => 'Lea',
            'lastname' => 'Rner',
            'username' => 'learner1',
            'email' => 'learner1@example.com',
        ];
        self::assertSame(777, $api->create_user($user, ['rol_01G3PZS7NZ0CF95J340P55FSZH']));
    }
}
