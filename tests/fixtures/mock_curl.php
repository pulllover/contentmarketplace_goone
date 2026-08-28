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
 * @package contentmarketplace_goone
 */

namespace contentmarketplace_goone;

defined('MOODLE_INTERNAL') || die();

final class mock_curl {

    private static $mock_requests = null;

    /** @var array Info for the most recent request. */
    private $info = [];

    /** @var int cURL error number for the most recent request. */
    public $errno = CURLE_OK;

    private static function url($method, $path, $params = null) {
        if (is_null($params)) {
            return $method . ' ' . $path;
        } else {
            return $method . ' ' . $path . '?' . http_build_query($params, '', '&');
        }
    }

    private static function mock_requests() {
        if (is_null(self::$mock_requests)) {
            self::$mock_requests = [
                self::url('GET', '/learning-objects/1873868')
                => '/learning-objects/1873868/GET.json',

                self::url('GET', '/learning-objects/1873868/scorm')
                => '/learning-objects/1873868/scorm/GET.zip',

                self::url('GET', '/learning-objects/29271')
                => '/learning-objects/29271/GET.json',

                self::url('GET', '/learning-objects/29271/scorm')
                => '/learning-objects/29271/scorm/GET.zip',

                self::url('GET', '/learning-objects/1916572')
                => '/learning-objects/1916572/GET.json',

                self::url('GET', '/learning-objects/1916572/scorm')
                => '/learning-objects/1916572/scorm/GET.zip',

                self::url('GET', '/learning-objects/1881379')
                => '/learning-objects/1881379/GET.json',

                self::url('GET', '/learning-objects/1881379/scorm')
                => '/learning-objects/1881379/scorm/GET.zip',

                self::url('GET', '/learning-objects/1868492')
                => '/learning-objects/1868492/GET.json',

                self::url('GET', '/learning-objects/1868492/scorm')
                => '/learning-objects/1868492/scorm/GET.zip',

                self::url('GET', '/learning-objects/999001')
                => '/learning-objects/999001/GET.json',

                // Collection counts (see api::get_learning_objects_collection_count()).
                self::url('GET', '/learning-objects', [
                    'type' => ['course', 'document', 'link', 'interactive', 'text', 'video', 'audio'],
                    'collection' => 'custom',
                ])
                => '/learning-objects/GET-count-custom.json',

                self::url('GET', '/learning-objects', [
                    'type' => ['course', 'document', 'link', 'interactive', 'text', 'video', 'audio'],
                    'collection' => 'free',
                ])
                => '/learning-objects/GET-count-free.json',

                self::url('GET', '/learning-objects', [
                    'type' => ['course', 'document', 'link', 'interactive', 'text', 'video', 'audio'],
                    'collection' => 'subscribe',
                ])
                => '/learning-objects/GET-count-subscription.json',

                // Explorer filter seeds: facet listings per collection (see search::get_filter_seeds()).
                self::url('GET', '/learning-objects', [
                    'offset' => 0,
                    'limit' => 1,
                    'facets' => ['topics', 'language', 'providers'],
                    'collection' => 'custom',
                    'type' => ['course', 'document', 'link', 'interactive', 'text', 'video', 'audio'],
                ])
                => '/learning-objects/GET-count-with-facets-custom.json',

                self::url('GET', '/learning-objects', [
                    'offset' => 0,
                    'limit' => 1,
                    'facets' => ['topics', 'language', 'providers'],
                    'collection' => 'free',
                    'type' => ['course', 'document', 'link', 'interactive', 'text', 'video', 'audio'],
                ])
                => '/learning-objects/GET-count-with-facets-free.json',

                self::url('GET', '/learning-objects', [
                    'offset' => 0,
                    'limit' => 1,
                    'facets' => ['topics', 'language', 'providers'],
                    'collection' => 'subscribe',
                    'type' => ['course', 'document', 'link', 'interactive', 'text', 'video', 'audio'],
                ])
                => '/learning-objects/GET-count-with-facets-subscription.json',

                // Explorer search (see search::query()): default sort, first page, custom collection.
                self::url('GET', '/learning-objects', [
                    'sort' => 'created',
                    'offset' => 0,
                    'limit' => 48,
                    'order' => 'desc',
                    'collection' => 'custom',
                    'include' => [
                        'core', 'pricing', 'lifecycle', 'skills', 'quality', 'playback_behavior', 'images',
                        'preview', 'tags', 'offerings', 'revisions', 'relevance', 'provider', 'topics',
                    ],
                    'type' => ['course', 'document', 'link', 'interactive', 'text', 'video', 'audio'],
                ])
                => '/learning-objects/GET-listing-custom.json',

                // Explorer search with tag (topic), language and provider filters applied.
                self::url('GET', '/learning-objects', [
                    'sort' => 'created',
                    'offset' => 0,
                    'limit' => 48,
                    'order' => 'desc',
                    'topics' => ['Mock topic A'],
                    'language' => ['en', 'ja'],
                    'providers' => ['101'],
                    'collection' => 'custom',
                    'include' => [
                        'core', 'pricing', 'lifecycle', 'skills', 'quality', 'playback_behavior', 'images',
                        'preview', 'tags', 'offerings', 'revisions', 'relevance', 'provider', 'topics',
                    ],
                    'type' => ['course', 'document', 'link', 'interactive', 'text', 'video', 'audio'],
                ])
                => '/learning-objects/GET-listing-filtered.json',

                // Select-all id listing, first page of the custom collection.
                self::url('GET', '/learning-objects', [
                    'collection' => 'custom',
                    'offset' => 0,
                    'limit' => 10,
                    'type' => ['course', 'document', 'link', 'interactive', 'text', 'video', 'audio'],
                ])
                => '/learning-objects/GET-listing-small-v3.json',

                self::url('GET', '/user-accounts/501')
                => '/user-accounts/501/GET.json',

                self::url('GET', '/user-accounts/502')
                => '/user-accounts/502/GET.json',

                self::url('GET', '/enrollments/9001')
                => '/enrollments/9001/GET.json',

                self::url('GET', '/enrollments/9002')
                => '/enrollments/9002/GET.json',

                self::url('GET', '/user-accounts/503')
                => '/user-accounts/503/GET.json',

                self::url('GET', '/user-accounts', [
                    'email' => 'contentadmin503@example.com',
                    'include' => ['standard_fields'],
                ])
                => '/user-accounts/GET-search-contentadmin503.json',

                self::url('GET', '/user-accounts', [
                    'email' => 'learner501@example.com',
                    'include' => ['standard_fields'],
                ])
                => '/user-accounts/GET-search-learner501.json',

                self::url('GET', '/user-accounts', [
                    'email' => 'nogo1user@example.com',
                    'include' => ['standard_fields'],
                ])
                => '/user-accounts/GET-search-empty.json',

                self::url('POST', '/user-accounts')
                => '/user-accounts/POST.json',

                self::url('PATCH', '/user-accounts/501')
                => '/user-accounts/501/PATCH.json',

                self::url('POST', '/user-accounts/501/login')
                => '/user-accounts/501/login/POST.json',

                self::url('POST', '/user-accounts/503/login')
                => '/user-accounts/503/login/POST.json',

                self::url('POST', '/user-accounts/777/login')
                => '/user-accounts/777/login/POST.json',
            ];
        }
        return self::$mock_requests;
    }

    public static function get_base_filename($url, $options) {
        $method = self::get_method($options);
        if (\core_text::strpos($url, api::ENDPOINT) === 0) {
            $name = \core_text::substr($url, \core_text::strlen(api::ENDPOINT) + 1);
        } elseif (\core_text::strpos($url, oauth::ENDPOINT) === 0) {
            $name = \core_text::substr($url, \core_text::strlen(oauth::ENDPOINT) + 1);
        } else {
            throw new \Exception("Unknown host for: $url");
        }
        $request = $method . ' /' . $name;
        if (!array_key_exists($request, self::mock_requests())) {
            // A single resource (learning-object, its scorm package, user account
            // or enrolment) maps to one fixture regardless of the include[]
            // query parameters on the request.
            $pathonly = strtok($name, '?');
            if (preg_match('#^(learning-objects|user-accounts|enrollments)/\d+(/scorm|/login)?$#', $pathonly)) {
                $request = $method . ' /' . $pathonly;
            }
        }
        if (array_key_exists($request, self::mock_requests())) {
            return self::mock_requests()[$request];
        } else {
            throw new \Exception("Missing mock curl response for request: $url");
        }
    }

    public static function get_method($options) {
        if (array_key_exists('CUSTOMREQUEST', $options)) {
            return $options['CUSTOMREQUEST'];
        } elseif (array_key_exists('CURLOPT_HTTPGET', $options)) {
            return "GET";
        } elseif (array_key_exists('CURLOPT_POST', $options)) {
            return "POST";
        } else {
            throw new \Exception("Unknown HTTP method for options: " . json_encode($options));
        }
    }

    public static function get_extension($options) {
        if (isset($options['HTTPHEADER'])) {
            foreach ($options['HTTPHEADER'] as $header) {
                if (\core_text::strpos(\core_text::strtolower($header), 'accept: ') === 0) {
                    return \core_text::strtolower(explode('/', $header)[1]);
                }
            }
        }
        return 'json';
    }

    public static function get_content_type($path) {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        switch ($extension) {
            case 'json':
                return 'application/json';
            default:
                return 'application/octet-stream';
        }
    }

    public static function validate_oauth($url, $options) {
        if (\core_text::strpos($url, oauth::ENDPOINT) === 0) {
            return true;
        }
        if (array_key_exists('HTTPHEADER', $options)) {
            foreach ($options['HTTPHEADER'] as $header) {
                if ($header === 'Authorization: Bearer --ACCESS-TOKEN--') {
                    return true;
                }
            }
        }
        throw new \Exception("Missing OAuth Access Token");
    }

    private function request($url, $options = array()) {
        global $CFG;

        self::validate_oauth($url, $options);

        $basename = self::get_base_filename($url, $options);
        $path = "/totara/contentmarketplace/contentmarketplaces/goone/tests/behat/fixtures$basename";
        $path = $CFG->dirroot . clean_param($path, PARAM_PATH);
        if (!file_exists($path)) {
            throw new \Exception("File for mock curl response does not exist: $path");
        }

        $this->info = [
            'url' => $url,
            'http_code' => 200,
            'content_type' => self::get_content_type($path),
        ];
        $this->errno = CURLE_OK;
        return file_get_contents($path);
    }

    public function get($url, $params = array(), $options = array()) {
        $options['CURLOPT_HTTPGET'] = 1;
        return $this->request($url, $options);
    }

    public function post($url, $params = '', $options = array()) {
        $options['CURLOPT_POST'] = 1;
        $options['CURLOPT_POSTFIELDS'] = $params;
        return $this->request($url, $options);
    }

    public function patch($url, $params = '', $options = array()) {
        $options['CUSTOMREQUEST'] = 'PATCH';
        $options['CURLOPT_POSTFIELDS'] = $params;
        return $this->request($url, $options);
    }

    public function get_info() {
        return $this->info;
    }

}
