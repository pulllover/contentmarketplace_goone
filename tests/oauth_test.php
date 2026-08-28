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

use contentmarketplace_goone\contentmarketplace;
use contentmarketplace_goone\mock_config_storage;
use contentmarketplace_goone\mock_playback_curl;
use contentmarketplace_goone\oauth;
use core_phpunit\testcase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

defined('MOODLE_INTERNAL') || die();

/**
 * Test the OAuth token handling against the Go1 auth service.
 */
#[CoversClass(oauth::class)]
#[Group('totara_contentmarketplace')]
class contentmarketplace_goone_oauth_test extends testcase {

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        require_once(__DIR__ . '/fixtures/mock_config_storage.php');
        require_once(__DIR__ . '/fixtures/mock_playback_curl.php');
    }

    /**
     * The options the playback curl expects for a POST to the auth endpoint.
     *
     * @param string $postfields
     * @return array
     */
    private function expected_post_options(string $postfields): array {
        return [
            'HEADER' => 0,
            'HTTPHEADER' => [
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
            'CURLOPT_POSTFIELDS' => $postfields,
        ];
    }

    public function test_get_authorize_url_without_existing_client(): void {
        global $CFG;

        $url = oauth::get_authorize_url(contentmarketplace::oauth_redirect_uri(), ['email' => 'admin@example.com']);
        $params = [];
        parse_str(parse_url($url->out(false), PHP_URL_QUERY), $params);

        self::assertSame('totara', $params['client_id']);
        self::assertStringContainsString(parse_url($CFG->wwwroot, PHP_URL_HOST), $params['new_client']);
        self::assertSame('code', $params['response_type']);
        // The state parameter round-trips through base64 encoded JSON and carries the anti-forgery nonce.
        $state = json_decode(base64_decode($params['state']), true);
        self::assertSame('admin@example.com', $state['email']);
        self::assertNotEmpty($state['totara_nonce']);
    }

    public function test_verify_authorize_state_accepts_the_issued_state_once(): void {
        $url = oauth::get_authorize_url(contentmarketplace::oauth_redirect_uri(), ['email' => 'admin@example.com']);
        $params = [];
        parse_str(parse_url($url->out(false), PHP_URL_QUERY), $params);

        // The state issued for this session verifies, but only once (replay is rejected).
        self::assertTrue(oauth::verify_authorize_state($params['state']));
        self::assertFalse(oauth::verify_authorize_state($params['state']));
    }

    public function test_verify_authorize_state_rejects_forged_state(): void {
        // No nonce has been issued to this session at all.
        $forged = base64_encode(json_encode(['email' => 'x@example.com', 'totara_nonce' => 'guessed']));
        self::assertFalse(oauth::verify_authorize_state($forged));

        // A nonce has been issued but the state carries a different one.
        oauth::get_authorize_url(contentmarketplace::oauth_redirect_uri(), []);
        self::assertFalse(oauth::verify_authorize_state($forged));

        // Missing or malformed state values are rejected.
        oauth::get_authorize_url(contentmarketplace::oauth_redirect_uri(), []);
        self::assertFalse(oauth::verify_authorize_state(''));
        oauth::get_authorize_url(contentmarketplace::oauth_redirect_uri(), []);
        self::assertFalse(oauth::verify_authorize_state(null));
        oauth::get_authorize_url(contentmarketplace::oauth_redirect_uri(), []);
        self::assertFalse(oauth::verify_authorize_state('%%%not-base64%%%'));
        oauth::get_authorize_url(contentmarketplace::oauth_redirect_uri(), []);
        self::assertFalse(oauth::verify_authorize_state(base64_encode('not json')));
        oauth::get_authorize_url(contentmarketplace::oauth_redirect_uri(), []);
        self::assertFalse(oauth::verify_authorize_state(base64_encode(json_encode(['email' => 'x@example.com']))));
    }

    public function test_get_authorize_url_reuses_existing_client(): void {
        set_config('oauth_client_id', 'existing-client', 'contentmarketplace_goone');
        set_config('oauth_client_secret', 'existing-secret', 'contentmarketplace_goone');

        $url = oauth::get_authorize_url(contentmarketplace::oauth_redirect_uri(), []);
        $params = [];
        parse_str(parse_url($url->out(false), PHP_URL_QUERY), $params);

        self::assertSame('existing-client', $params['client_id']);
        self::assertArrayNotHasKey('new_client', $params);
    }

    public function test_token_setup_stores_credentials_and_tokens(): void {
        $redirect_uri = contentmarketplace::oauth_redirect_uri();
        $postfields = json_encode([
            'client_id' => 'the-client',
            'client_secret' => 'the-secret',
            'code' => 'auth-code-1',
            'redirect_uri' => $redirect_uri->out(false),
            'grant_type' => 'authorization_code',
        ]);

        $curl = new mock_playback_curl($this);
        $url = oauth::ENDPOINT . '/oauth/token';
        $curl->record(
            $url,
            $this->expected_post_options($postfields),
            ['url' => $url, 'http_code' => 200, 'content_type' => 'application/json'],
            '{"access_token": "new-access", "refresh_token": "new-refresh"}'
        );

        $storage = new mock_config_storage();
        $oauth = new oauth($storage, $curl);
        $oauth->token_setup('the-client', 'the-secret', 'auth-code-1', $redirect_uri);

        self::assertSame('the-client', $storage->get('oauth_client_id'));
        self::assertSame('the-secret', $storage->get('oauth_client_secret'));
        self::assertSame('new-access', $storage->get('oauth_access_token'));
        self::assertSame('new-refresh', $storage->get('oauth_refresh_token'));
    }

    public function test_token_refresh_with_valid_refresh_token(): void {
        $postfields = json_encode([
            'client_id' => 'cid',
            'client_secret' => 'csecret',
            'refresh_token' => 'rtoken',
            'grant_type' => 'refresh_token',
        ]);

        $curl = new mock_playback_curl($this);
        $url = oauth::ENDPOINT . '/oauth/token';
        $curl->record(
            $url,
            $this->expected_post_options($postfields),
            ['url' => $url, 'http_code' => 200, 'content_type' => 'application/json'],
            '{"access_token": "refreshed-access", "refresh_token": "rotated-refresh"}'
        );

        $storage = new mock_config_storage([
            'oauth_client_id' => 'cid',
            'oauth_client_secret' => 'csecret',
            'oauth_refresh_token' => 'rtoken',
        ]);
        $oauth = new oauth($storage, $curl);
        $oauth->token_refresh();

        self::assertSame('refreshed-access', $storage->get('oauth_access_token'));
        self::assertSame('rotated-refresh', $storage->get('oauth_refresh_token'));
    }

    public function test_token_refresh_falls_back_to_client_credentials(): void {
        $refresh_postfields = json_encode([
            'client_id' => 'cid',
            'client_secret' => 'csecret',
            'refresh_token' => 'expired-rtoken',
            'grant_type' => 'refresh_token',
        ]);
        $client_credentials_postfields = json_encode([
            'client_id' => 'cid',
            'client_secret' => 'csecret',
            'grant_type' => 'client_credentials',
        ]);

        $curl = new mock_playback_curl($this);
        $url = oauth::ENDPOINT . '/oauth/token';
        // The refresh token has been invalidated upstream.
        $curl->record(
            $url,
            $this->expected_post_options($refresh_postfields),
            ['url' => $url, 'http_code' => 401, 'content_type' => 'application/json'],
            '{"message": "Invalid refresh token"}'
        );
        // The fallback client_credentials grant succeeds.
        $curl->record(
            $url,
            $this->expected_post_options($client_credentials_postfields),
            ['url' => $url, 'http_code' => 200, 'content_type' => 'application/json'],
            '{"access_token": "cc-access"}'
        );

        $storage = new mock_config_storage([
            'oauth_client_id' => 'cid',
            'oauth_client_secret' => 'csecret',
            'oauth_refresh_token' => 'expired-rtoken',
        ]);
        $oauth = new oauth($storage, $curl);
        $oauth->token_refresh();

        self::assertSame('cc-access', $storage->get('oauth_access_token'));
        // The dead refresh token has been cleared.
        self::assertSame('', $storage->get('oauth_refresh_token'));
    }

    public function test_session_config_move_to_db(): void {
        $session_storage = new \contentmarketplace_goone\config_session_storage();
        $session_storage->set('oauth_client_id', 'session-client');
        $session_storage->set('oauth_access_token', 'session-token');
        self::assertTrue(oauth::have_config_in_session());

        oauth::move_config_from_session_to_db();

        self::assertFalse(oauth::have_config_in_session());
        self::assertSame('session-client', get_config('contentmarketplace_goone', 'oauth_client_id'));
        self::assertSame('session-token', get_config('contentmarketplace_goone', 'oauth_access_token'));
    }
}
