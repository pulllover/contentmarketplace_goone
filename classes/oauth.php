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
 * @author Kirill Astashov <kirill.astashov@androgogic.com>
 * @package contentmarketplace_goone
 */

namespace contentmarketplace_goone;

defined('MOODLE_INTERNAL') || die();

final class oauth {

    const ENDPOINT = 'https://auth.go1.com';

    /** @var config_storage */
    public $config = null;

    /** @var rest_client $client */
    public $client = null;

    /**
     * oauth constructor.
     *
     * @param config_storage $config
     * @param \curl|null $curl
     */
    public function __construct(config_storage $config, $curl = null) {
        $this->config = $config;
        $this->client = new rest_client(self::ENDPOINT, $curl);
    }

    /**
     * @param \moodle_url $redirect_uri
     * @param array $state
     * @return \moodle_url
     */
    public static function get_authorize_url($redirect_uri, $state) {
        global $CFG;

        $params = array();

        $oauth_client_id = get_config('contentmarketplace_goone', 'oauth_client_id');
        $oauth_client_secret = get_config('contentmarketplace_goone', 'oauth_client_secret');
        if (!empty($oauth_client_id) && !empty($oauth_client_secret)) {
            // Reuse existing client_id instead of requesting a new one.
            $params["client_id"] = $oauth_client_id;
        } else {
            $lmshost = parse_url($CFG->wwwroot, PHP_URL_HOST);
            $params["new_client"] = "Totara ({$lmshost})";
            $params["client_id"] = "totara";
        }
        
        $params["response_type"] = "code";
        $params["scope"] = "lo.read lo.write enrollment.read portal.read portal.write user.read user.write user.login webhook.read webhook.write";
        $params["redirect_uri"] = $redirect_uri;
        $state['totara_nonce'] = self::generate_authorize_nonce();
        $params["state"] = base64_encode(json_encode($state));

        return new \moodle_url(self::ENDPOINT . '/oauth/authorize', $params);
    }

    /**
     * Setup access and refresh tokens using authorization_code flow.
     * 
     * @param string $client_id
     * @param string $client_secret
     * @param string $code
     * @param \moodle_url $redirect_uri
     */
    public function token_setup($client_id, $client_secret, $code, \moodle_url $redirect_uri) {
        if (!empty($client_id)) {
            $this->config->set('oauth_client_id', $client_id);
        } else {
            $client_id = get_config('contentmarketplace_goone','oauth_client_id');
        }
        if (!empty($client_secret)) {
            $this->config->set('oauth_client_secret', $client_secret);
        } else {
            $client_secret = get_config('contentmarketplace_goone','oauth_client_secret');
        }
        $params = array(
            "client_id" => $client_id,
            "client_secret" => $client_secret,
            "code" => $code,
            "redirect_uri" => $redirect_uri->out(false),
            "grant_type" => "authorization_code",
        );
        $token = $this->client->post('oauth/token', $params);
        $this->config->set('oauth_access_token', $token->access_token);
        $this->config->set('oauth_refresh_token', $token->refresh_token);
    }

    /**
     * Refreshes the token and stores the new token.
     */
    public function token_refresh() {
        if ($this->config->get('oauth_refresh_token') != '') {
            $params = array(
                "client_id" => $this->config->get('oauth_client_id'),
                "client_secret" => $this->config->get('oauth_client_secret'),
                "refresh_token" => $this->config->get('oauth_refresh_token'),
                "grant_type" => "refresh_token",
            );
            try {
                $token = $this->client->post('oauth/token', $params);
                $this->config->set('oauth_access_token', $token->access_token);
                $this->config->set('oauth_refresh_token', $token->refresh_token);
                return;
            } catch (invalid_token_exception $e) {
                // Refresh token has been invalidated (expired?)
                // Clear it and try grant_type = client_credentials
                $this->config->set('oauth_refresh_token', '');
            }
        }
        $params = array(
            "client_id" => $this->config->get('oauth_client_id'),
            "client_secret" => $this->config->get('oauth_client_secret'),
            "grant_type" => "client_credentials",
        );
        $token = $this->client->post('oauth/token', $params);
        $this->config->set('oauth_access_token', $token->access_token);
    }

    /**
     * Generate an anti-forgery token for the OAuth authorize flow and store it in the current session.
     *
     * @return string
     */
    public static function generate_authorize_nonce(): string {
        global $SESSION;

        $nonce = random_string(32);
        $SESSION->contentmarketplace_goone_oauth_nonce = $nonce;
        return $nonce;
    }

    /**
     * Verify the state parameter returned by the Go1 authorize flow against the nonce stored in the session.
     * The stored nonce is consumed regardless of the outcome, so a state value can only ever be used once.
     *
     * @param string|null $state Raw state request parameter (base64 encoded JSON)
     *
     * @return bool
     */
    public static function verify_authorize_state(?string $state): bool {
        global $SESSION;

        $stored = $SESSION->contentmarketplace_goone_oauth_nonce ?? null;
        unset($SESSION->contentmarketplace_goone_oauth_nonce);

        if (empty($stored) || empty($state)) {
            return false;
        }

        $decoded = json_decode(base64_decode($state, true) ?: '');
        if (!is_object($decoded) || empty($decoded->totara_nonce) || !is_string($decoded->totara_nonce)) {
            return false;
        }

        return hash_equals($stored, $decoded->totara_nonce);
    }

    /**
     * Move all the config from session storage over to db storage.
     * Use this after OAuth authorization has been successfully completed
     * and the admin user has asked to save the configuration.
     */
    public static function move_config_from_session_to_db() {
        $sessionstorage = new config_session_storage();
        $dbstorage = new config_db_storage();
        $dbstorage->copy($sessionstorage);
        $sessionstorage->clear();
    }

    /**
     * @return bool
     */
    public static function have_config_in_session() {
        $sessionstorage = new config_session_storage();
        return $sessionstorage->exists();
    }

}
