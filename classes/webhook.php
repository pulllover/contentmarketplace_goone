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
 * Helper class related to webhook.
 *
 * @author Kirill Astashov <kirill.astashov@androgogic.com>
 * @package contentmarketplace_goone
 */

namespace contentmarketplace_goone;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir.'/completionlib.php');

use core\orm\query\builder;
use contentmarketplace_goone\api;

class webhook {

    /**
     * Maximum age (in seconds) of a webhook signature timestamp that will be
     * accepted, to guard against replay of a previously-signed request.
     */
    const WEBHOOK_TOLERANCE_SECONDS = 600;

    /**
     * Get Webhook from Go1.
     *
     * @param $api
     *
     * @return bool|object Webhook or false if not exists
     */
    public static function get_webhook($api): bool|object {
        $data = $api->get_webhooks();
        if (empty($data->total)) {
            return false;
        }
        return $data->hits[0];
    }



    /**
     * Generate a random string to use when signing webhooks.
     * Internal method. Use get_webhook_secret_key()
     *
     * @param int $n Number of characters in the secret key.
     *
     * @return string
     */
    public static function generate_webhook_secret_key($n = 20): string {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*(){}[]_+-';
        $max = strlen($characters) - 1;
        $randsecret = '';

        for ($i = 0; $i < $n; $i++) {
            $index = random_int(0, $max);
            $randsecret .= $characters[$index];
        }

        return $randsecret;
    }


    /**
     * Get secret key to use when signing webhooks. Generate key if the config value does not exist.
     *
     * @return string
     */
    public static function get_webhook_secret_key(): string {
        $key = get_config('contentmarketplace_goone', 'webhook_secret_key');
        if (!$key) {
            $key = self::generate_webhook_secret_key();
            set_config('webhook_secret_key', $key, 'contentmarketplace_goone');
        }
        return $key;
    }


    /**
     * Create webhook. Won't check if it already exists.
     *
     * @param mixed $api
     *
     * @return void
     */
    public static function create_webhook($api): void {
        $params = self::get_webhook_params();
        $api->create_webhook($params);
    }


    /**
     * Update existing webhook with the standard params for this site.
     *
     * @param string $id
     * @param mixed $api
     *
     * @return void
     */
    public static function update_webhook(string $id, $api): void {
        $params = self::get_webhook_params();
        $api->update_webhook($id, $params);
    }


    /**
     * Return standard Webhook params for this site.
     *
     * @return \stdClass
     */
    public static function get_webhook_params(): \stdClass {
        global $CFG;

        $params = new \stdClass();
        $params->name = "Totara LMS integration webhook";
        $params->url = $CFG->wwwroot . '/totara/contentmarketplace/contentmarketplaces/goone/payload.php';
        $params->secret_key = self::get_webhook_secret_key();
        $params->event_types = ["enrollment.complete"];

        return $params;
    }


    /**
     * Check whether an existing Go1 webhook matches the settings held by this site.
     *
     * @param object $webhook Webhook as returned by Go1.
     *
     * @return bool True when the webhook is in sync with this site's settings.
     */
    public static function is_synchronised(object $webhook): bool {
        $secret_key = get_config('contentmarketplace_goone', 'webhook_secret_key');
        if (empty($secret_key)) {
            return false;
        }

        if (!isset($webhook->secret_key) || !hash_equals($secret_key, (string) $webhook->secret_key)) {
            return false;
        }

        $params = self::get_webhook_params();

        if (!isset($webhook->url) || (string) $webhook->url !== $params->url) {
            return false;
        }

        $event_types = isset($webhook->event_types) ? (array) $webhook->event_types : [];
        foreach ($params->event_types as $event_type) {
            if (!in_array($event_type, $event_types, true)) {
                return false;
            }
        }

        return true;
    }


    /**
     * Process POST request coming from Go1 via webhook.
     *
     * @return void
     */
    public static function process_request(): void {
        $request = file_get_contents('php://input');
        $header = isset($_SERVER['HTTP_GO1_SIGNATURE']) ? (string) $_SERVER['HTTP_GO1_SIGNATURE'] : '';

        $error = self::verify_signature($request, $header, self::get_webhook_secret_key());
        if ($error !== '') {
            self::log_error($error, $request);
            return;
        }

        self::process_payload($request);
    }


    /**
     * Verify the signature of a webhook request against the shared secret.
     * See https://developers.go1.com/docs/developer-tools/webhooks/security/#Signatures
     *
     * @param string $request Raw request body.
     * @param string $header Value of the Go1 signature header, "t=<timestamp>,v1=<signature>".
     * @param string $secret_key Shared secret used to sign the request.
     * @return string Empty string when the signature is valid, otherwise a description of the failure.
     */
    public static function verify_signature(string $request, string $header, string $secret_key): string {
        if ($header === '') {
            return 'Missing Go1 signature header.';
        }

        // Parse the header into ordered values, skipping any segment with no "=".
        $signature = [];
        foreach (explode(',', $header) as $value) {
            $item = explode('=', $value, 2);
            if (count($item) === 2) {
                $signature[] = $item[1];
            }
        }
        if (!isset($signature[0], $signature[1])) {
            return 'Malformed Go1 signature header.';
        }

        $timestamp = $signature[0];

        // Only a timestamp within the tolerance window is accepted.
        if (!is_numeric($timestamp) || abs(time() - (int) $timestamp) > self::WEBHOOK_TOLERANCE_SECONDS) {
            return 'Go1 signature timestamp outside the accepted tolerance.';
        }

        $calculated = hash_hmac('sha256', (string) $timestamp . "." . $request, $secret_key);
        if (!hash_equals($calculated, $signature[1])) {
            return 'Failed to verify signature.';
        }

        return '';
    }


    /**
     * Process request payload after signature has been verified.
     *
     * @param string $request - raw request string
     *
     * @return void
     */
    public static function process_payload(string $request): void {
        $postjson = json_decode($request);
        if (!is_object($postjson) || empty($postjson->event_type)) {
            self::log_error('Malformed webhook payload.', $request);
            return;
        }
        switch ($postjson->event_type) {
            case 'enrollment.complete':
                self::process_payload_completion($postjson, $request);
                break;
            default:
                // Only subscribed event types are processed, anything else is logged.
                self::log_error('Unhandled webhook event type: ' . $postjson->event_type, $request);
                break;
        }
    }


    /**
     * Process an "enrollment.complete" webhook payload.
     *
     * @param \stdClass $postjson Decoded payload
     * @param string $request Raw request body (for error logging)
     * @param api|null $api API instance, created internally when not supplied
     *
     * @return void
     */
    public static function process_payload_completion($postjson, string $request, ?api $api = null): void {
        $db = builder::get_db();
        $api = $api ?? new api();

        try {
            $enrolinfo = $api->get_enrolment($postjson->data->id);
            if (empty($enrolinfo->pass)) {
                return;
            }

            $go1_userid = $enrolinfo->user_account_id;
            $lo_id = $postjson->data->lo_id;

            $user = self::get_lms_user($go1_userid, $api);
            if (!$user) {
               throw new \Exception('User not found: '. $enrolinfo->user_account_id);
            }
            $sql = "SELECT s.id, s.course
                FROM {scorm} s
                INNER JOIN {modules} m on m.name = 'scorm'
                INNER JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = s.id
                INNER JOIN {totara_contentmarketplace_course_module_source} tccm
                    ON (tccm.cm_id = cm.id AND tccm.marketplace_component = 'contentmarketplace_goone')
                INNER JOIN {marketplace_goone_learning_object} mglo ON mglo.id = tccm.learning_object_id
                WHERE 1=1
                    AND mglo.external_id = :lo_id";
            $params = ['lo_id' => $lo_id];
            $scorms = $db->get_records_sql($sql, $params);
            if (!$scorms) {
                throw new \Exception('SCORMs not found by lo_id: ' . $lo_id);
            }
            $mod = $db->get_record('modules', array('name' => 'scorm'));
            foreach ($scorms as $scorm) {
                $cm = $db->get_record('course_modules', array('course' => $scorm->course, 'module' => $mod->id, 'instance' => $scorm->id));
                $cmc = $db->get_record('course_modules_completion', array('coursemoduleid' => $cm->id, 'userid' => $user->id));
                $course = $db->get_record('course', ['id' => $scorm->course]);

                if ($cm->completion == COMPLETION_TRACKING_NONE) {
                    continue;
                }

                // Ensure the user is enrolled in course.
                $context = \context_course::instance($scorm->course);
                if (!is_enrolled($context, $user)) {
                    continue;
                }

                if (!$cmc || !in_array($cmc->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS])) {
                    $completion = new \completion_info($course);
                    $completion->set_module_viewed($cm, $user->id);
                    $cm->completion = COMPLETION_TRACKING_MANUAL; // In-memory only: Force update on update_state()
                    $newstate = COMPLETION_COMPLETE;
                    $completion->update_state($cm, $newstate, $user->id);
                }
            }
        } catch (\Throwable $e) {
            // The webhook endpoint must always fail soft into the log, never with a 500.
            self::log_error($e->getMessage(), $request);
        }
    }


    /**
     * Get Totara user record given Go1 user ID.
     *
     * @param string|int $gooneuserid
     * @param api|null $api API instance, created internally when not supplied
     *
     * @return \stdClass|false
     */
    public static function get_lms_user(string|int $gooneuserid, ?api $api = null): \stdClass|false {
        $db = builder::get_db();

        $api = $api ?? new api();
        $userinfofromgo = $api->get_user($gooneuserid);
        $email = $userinfofromgo->user->email;
        $firstname = $userinfofromgo->user->given_name;
        $lastname = $userinfofromgo->user->family_name;
        $username = isset($userinfofromgo->custom_fields->external_user_id) ? $userinfofromgo->custom_fields->external_user_id : '';
        if (empty($username) && isset($userinfofromgo->standard_fields->external_user_id)) {
            $username = $userinfofromgo->standard_fields->external_user_id;
        }

        $active = array('deleted' => 0, 'suspended' => 0);

        if (!empty($username)) {
            $user = $db->get_record('user', array('username' => $username) + $active);
        }

        // Try email as-is.
        if (empty($user)) {
            $user = $db->get_record('user', array('email' => $email) + $active);
        }

        if (empty($user)) {
            $user = $db->get_record('user', array('username' => $email) + $active);
        }

        // Try email from external_user_id.
        if (empty($user) && !empty($username)) {
            $user = $db->get_record('user', array('email' => $username) + $active);
        }
        if (empty($user) && !empty($username)) {
            $user = $db->get_record('user', array('username' => $username) + $active);
        }

        // Try the user part of the email as username.
        if (empty($user)) {
            $regexp = '/([^@]+)@.*/i';
            if (preg_match($regexp, $email, $p) && !empty($p[1])) {
                $username = $p[1];
                $user = $db->get_record('user', array('username' => $username) + $active);
            }
        }

        // Try firstname and lastname as last resort, but proceed only if one match.
        if (empty($user)) {
            $users = $db->get_records('user', array('firstname' => $firstname, 'lastname' => $lastname) + $active);
            if (sizeof($users) == 1) {
                reset($users);
                $user = current($users);
            }
        }

        if ($user) {
            return $user;
        } else {
            return false;
        }
    }


    public static function log_error(string $error, string $request): void {
        $db = builder::get_db();

        // Insert log entry.
        $log = new \stdClass();
        $log->message = $error . "\n" . $request;
        $log->timecreated = time();
        $db->insert_record('marketplace_goone_webhook_logs', $log);

        // Prune old logs.
        $time = time() - 30 * 86400;
        $db->execute("DELETE FROM {marketplace_goone_webhook_logs} WHERE timecreated < :time", ['time' => $time]);
    }

}