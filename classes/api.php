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
 * API v3
 *
 * @author Sergey Vidusov <sergey.vidusov@androgogic.com>
 * @author Kirill Astashov <kirill.astashov@androgogic.com>
 * @package contentmarketplace_goone
 */

namespace contentmarketplace_goone;

defined('MOODLE_INTERNAL') || die();

final class api {

    const ENDPOINT = 'https://gateway.go1.com';
    const API_VERSION = '2025-01-01';
    const MAX_PAGE_SIZE = 10;
    const MAX_AVAILABLE_RESULTS = 10000;
    const CACHE_VALIDITY_COLLECTION_COUNT = 600; // seconds
    const TIMEOUT_DOWNLOAD = 300; // seconds; SCORM packages can be tens of megabytes

    public static $roles = [
        'Learner' => 'rol_01G3PZS7NZ0CF95J340P55FSZH',
        'Manager' => 'rol_01G3PZS7NZBW04V44QBM48PP0C',
        'Content administrator' => 'rol_01G3PZS7NXC78RP57SG0EXD0Z5',
        'Administrator' => 'rol_01G3PZS7NCBKB170TEP3CC2BBH'
    ];

    public static $roles_contentadmin = [
        'Content administrator' => 'rol_01G3PZS7NXC78RP57SG0EXD0Z5',
        'Administrator' => 'rol_01G3PZS7NCBKB170TEP3CC2BBH'
    ];

    /**
     * Maps a content availability option to the collection scope sent to the API.
     *
     * The keys are the plugin's own option names, held in the content access and
     * content sync settings; the values go out as the `collection` query parameter.
     *
     * The API accepts `free` and `custom`, but the published enum for API version
     * 2025-01-01 lists only `library`, `not_added_to_library`, `partner_content`
     * and `subscribe`. `library` is the documented equivalent of `custom`; `free`
     * has no documented equivalent, as the API offers no price filter. Should the
     * undocumented values be withdrawn, this table is the only place to change.
     */
    public static $collection_scopes = [
        'free' => 'free',
        'subscribe' => 'subscribe',
        'custom' => 'custom',
    ];

    /** @var oauth_rest_client */
    private $client;

    /** @var config_storage  */
    private $config;

    /** @var \cache Cache */
    private $cache;

    /**
     * The api constructor.
     *
     * @param config_storage|null $config
     */
    public function __construct(config_storage $config = null) {
        $this->config = isset($config) ? $config : new config_db_storage();
        $oauth = new oauth($this->config);
        $this->client = new oauth_rest_client(self::ENDPOINT, $oauth);
        $this->client->set_api_version(self::API_VERSION);
        $this->cache = \cache::make('contentmarketplace_goone', 'goonecollectioncount');
    }

    /**
     * Get an individual learning object.
     * @param string|int $id remote id of the learning object.
     * @return \stdClass Object returned from the Go1 web service.
     */
    public function get_learning_object(string $id) {
        if ((string)(int)$id != $id) {
            throw new \Exception('GO1 learning-objects are expected to have integer ids');
        }

        $params = [];
        $params['include'] = [
            'core',
            'pricing',
            'lifecycle',
            'skills',
            'quality',
            'playback_behavior',
            'images',
            'preview',
            'tags',
            'offerings',
            'revisions',
            'relevance',
            'provider',
            'topics'
        ];

        $id = (int)$id;
        $data = $this->client->get('learning-objects/' . $id, $params);
        $this->clean_learning_object($data);

        return $data;
    }

    /**
     * Smooths and cleans data on the given object (by reference)
     * @param \stdClass $data
     */
    private function clean_learning_object(&$data) {
        // Populate any missing non-guaranteed properties.
        $data->image = isset($data->image) ? $data->image : null;
        $data->playback_behavior = isset($data->playback_behavior) ? $data->playback_behavior : new \stdClass();
        $data->playback_behavior->assessable = isset($data->playback_behavior->assessable) ? $data->playback_behavior->assessable : null;
        $data->playback_behavior->mobile_friendly = isset($data->playback_behavior->mobile_friendly) ? $data->playback_behavior->mobile_friendly : null;
        $data->pricing = isset($data->pricing) ? $data->pricing : new \stdClass();
        $data->pricing->currency = isset($data->pricing->currency) ? $data->pricing->currency : null;
        $data->pricing->price = isset($data->pricing->price) ? $data->pricing->price : null;
        $data->pricing->tax = isset($data->pricing->tax) ? $data->pricing->tax : null;
        $data->pricing->tax_included = isset($data->pricing->tax_included) ? $data->pricing->tax_included : null;
        $data->provider = isset($data->provider) ? $data->provider : new \stdClass();
        $data->provider->logo = isset($data->provider->logo) ? $data->provider->logo : null;
        $data->provider->name = isset($data->provider->name) ? $data->provider->name : null;
        $data->quality = isset($data->quality) ? $data->quality : new \stdClass();
        $data->quality->user_rating = isset($data->quality->user_rating) ? $data->quality->user_rating : new \stdClass();
        $data->quality->user_rating->five_star_rating = isset($data->quality->user_rating->five_star_rating) ? $data->quality->user_rating->five_star_rating : new \stdClass();
        $data->quality->user_rating->ratings_count = isset($data->quality->user_rating->ratings_count) ? $data->quality->user_rating->ratings_count : new \stdClass();
    }

    /**
     * @param int $id
     * @return mixed
     */
    public function get_scorm(int $id) {
        $url = 'learning-objects/' . $id . '/scorm';
        $headers = [
            'Accept: application/zip',
        ];
        return $this->client->get($url, [], $headers, ['TIMEOUT' => self::TIMEOUT_DOWNLOAD]);
    }


    /**
     * Translate a content availability option into the collection scope the API expects.
     *
     * Options with no entry in self::$collection_scopes are passed through, so a
     * scope named as the API names it can be given directly.
     *
     * @param string $collection Content availability option, or an API collection scope.
     * @return string Collection scope for the `collection` query parameter.
     */
    public static function collection_scope(string $collection): string {
        return self::$collection_scopes[$collection] ?? $collection;
    }

    /**
     * Translate the collection in a set of query parameters, when one is present.
     *
     * @param array $params Query parameters.
     * @return array Query parameters holding an API collection scope.
     */
    private static function apply_collection_scope(array $params): array {
        if (isset($params['collection'])) {
            $params['collection'] = self::collection_scope((string)$params['collection']);
        }
        return $params;
    }

    /**
     * Perform a search for matching learning objects via API.
     * @param  array $params Search parameters
     * @return object         Data returned from API
     */
    public function get_learning_objects(array $params = []) {

        $params = self::apply_collection_scope($params);

        if (!isset($params['type'])) {
            $params['type'] = ['course', 'document', 'link', 'interactive', 'text', 'video', 'audio'];
        }

        $data = $this->client->get('learning-objects', $params);
        if (!isset($data->total) or !is_number($data->total)) {
            throw new \Exception('Response from GO1 API for "learning-objects" is missing expected value for "total"');
        }

        foreach ($data->hits as $hit) {
            $this->clean_learning_object($hit);
        }

        return $data;
    }


    /**
     * @param array $params The parameters to the API query.
     * @return int The total number of all packages for this account
     */
    public function get_learning_objects_total_count(array $params = []) {
        // Only the total is of interest here, so the smallest page the API accepts is requested.
        $params['limit'] = 1;
        $params['type'] = ['course', 'document', 'link', 'interactive', 'text', 'video', 'audio'];
        $cachekey = 'collection_count_total';
        $data = $this->get_cached_value($cachekey);
        if ($data === false) {
            $data = $this->get_learning_objects($params)->total;
            $this->set_cached_value($cachekey, $data, self::CACHE_VALIDITY_COLLECTION_COUNT);
        }
        return $data;
    }


    public function get_learning_objects_collection_count(string $collection) :int {
        $cachekey = 'collection_count_'.$collection;
        $cachedval = $this->get_cached_value($cachekey);
        if ($cachedval !== false) {
            return $cachedval;
        }
        $params = [];
        $params['type'] = ['course', 'document', 'link', 'interactive', 'text', 'video', 'audio'];
        $params['collection'] = self::collection_scope($collection);
        $data = $this->client->get('learning-objects', $params);
        $total = !empty($data->total) ? intval($data->total) : 0;
        $result = $this->set_cached_value($cachekey, $total, self::CACHE_VALIDITY_COLLECTION_COUNT);
        return $total;
    }

    /**
     * Fetch the facet listings (topics, language, providers) for one collection.
     *
     * Responses are cached briefly, like the collection counts, as the facets
     * back the explorer filter options and change rarely.
     *
     * @param string $collection Collection to scope the facets to.
     * @return \stdClass Facet bucket lists keyed by facet name.
     */
    public function get_learning_objects_facets(string $collection): \stdClass {
        $cachekey = 'facets_' . $collection;
        $cachedval = $this->get_cached_value($cachekey);
        if ($cachedval !== false) {
            return $cachedval;
        }
        // The API v3 minimum limit is 1; only the facets are of interest here.
        $params = [
            'offset' => 0,
            'limit' => 1,
            'facets' => ['topics', 'language', 'providers'],
            'collection' => $collection,
        ];
        $facets = $this->get_learning_objects($params)->facets ?? new \stdClass();
        $this->set_cached_value($cachekey, $facets, self::CACHE_VALIDITY_COLLECTION_COUNT);
        return $facets;
    }

    public function get_learning_objects_collection_count_retired(string $collection) :int {
        $data = $this->client->get('learning-objects', [
            'collection' => self::collection_scope($collection),
            'state' => ['retired'],
        ]);
        return !empty($data->total) ? intval($data->total) : 0;
    }

    /**
     * @param array $params The parameters to the API query.
     * @return array Listing of all the learning objects id's for the given filter
     */
    public function list_ids_for_all_learning_objects(array $params = []) {
        $ids = [];
        for ($page = 0; $page < self::MAX_AVAILABLE_RESULTS/self::MAX_PAGE_SIZE; $page += 1) {
            $params['offset'] = $page * self::MAX_PAGE_SIZE;
            $params['limit'] = self::MAX_PAGE_SIZE;
            $response = $this->get_learning_objects($params);
            foreach ($response->hits as $hit) {
                $ids[] = $hit->id;
            }
            if (count($ids) >= $response->total) {
                break;
            }
        }
        return $ids;
    }


    public function search_user(array $params): bool|\stdClass {
        $data = $this->client->get('user-accounts', $params);
        if (!empty($data->total)) {
            return $data;
        } else {
            return false;
        }
    }


    /**
     * @param string|int $id ID of the user in Go1
     *
     * @return \stdClass Object returned from the Go1 web service.
     */
    public function get_user(string|int $id): \stdClass|bool {
        $id = (int)$id;
        $data = $this->client->get('user-accounts/' . $id, ['include' => ['standard_fields', 'additional_fields']]);
        return $data;
    }


    /**
     * @param string|int $id ID of the user in Go1
     * @param \stdClass $user Object returned from the Go1 web service with some fields supposedly updated to push back to Go1.
     */
    public function update_user(string|int $id, \stdClass $data): void {
        $id = (int)$id;

        $params = [];
        if (!empty($data->email)) {
            $params['email'] = $data->email;
        }
        if (!empty($data->roles)) {
            $params['roles'] = $data->roles;
        }
        if (!empty($data->status)) {
            $params['status'] = $data->status;
        }
        if (!empty($data->locale)) {
            $params['locale'] = $data->locale;
        }
        if (!empty($data->standard_fields)) {
            $params['standard_fields'] = $data->standard_fields;
        }
        if (!empty($data->additional_fields)) {
            $params['additional_fields'] = $data->additional_fields;
        }
        $this->client->patch('user-accounts/' . $id, $params);
    }


    /**
     * Create user in Go1 and return Go1 user id for the purpose of managing content.
     *
     * @param \stdclass $user Full user record from DB
     * @param array $roles Array of roles as per self::roles, see https://developers.go1.com/api/rest/2025-01-01/user-accounts/#create
     *                      Example: ['rol_01G3PZS7NZBW04V44QBM48PP0C']
     *
     * @return int|bool Go1 user id of the created user or false on fail
     */
    public function create_user($user, array $roles): int|bool {
        $request = [
            'given_name' => $user->firstname,
            'family_name' => $user->lastname,
            'username' => $user->username,
            'email' => $user->email,
            'roles' => $roles
        ];

        $data = $this->client->post('user-accounts', $request);
        if (empty($data->id)) {
            return false;
        }
        return intval($data->id);
    }


    /**
     * API request to generate one-time login token for the given user.
     *
     * @param mixed $goone_userid
     *
     * @return string|bool One-time token, or false when the API did not return one
     */
    public function get_ott($goone_userid): string|bool {
        $data = $this->client->post('user-accounts/' . $goone_userid . '/login');
        if (empty($data->one_time_token)) {
            return false;
        }
        return $data->one_time_token;
    }


    /**
     * @param string|int $id ID of the enrolment in Go1
     *
     * @return \stdClass Object returned from the Go1 web service.
     */
    public function get_enrolment(string|int $id): \stdClass|bool {
        $id = (int)$id;
        $data = $this->client->get('enrollments/' . $id);
        return $data;
    }


    /**
     * Get the webhook, previously created by the integration.
     *
     * @return bool|object first webhook found or false
     */
    public function get_webhooks(): bool|object {
        $data = $this->client->get('webhooks');
        return $data;
    }


    /**
     * Create webhook.
     *
     * @param \stdClass|array $params
     *
     * @return void
     */
    public function create_webhook( \stdClass|array $params): void {
        $params = (array) $params;
        $this->client->post('webhooks', $params);
        return;
    }


    /**
     * Update existing Webhook.
     *
     * @param string $id - Webhook id
     * @param \stdClass|array $params - Webhook params
     *
     * @return void
     */
    public function update_webhook(string $id, \stdClass|array $params): void {
        $params = (array) $params;
        $this->client->patch('webhooks/' . $id, $params);
        return;
    }


    private function get_cached_value(string $cachekey) :mixed {
        if ($this->is_cache_valid($cachekey)) {
            return $this->cache->get($cachekey);
        }
        return false;
    }


    private function set_cached_value(string $cachekey, mixed $data, int $expiresin) :bool {
        $this->cache->set($cachekey, $data);
        // Set cache value expiry time.
        $expirykey = "{$cachekey}_expires";
        $expires = time() + $expiresin;
        $result = $this->cache->set($expirykey, $expires);
        return $result;
    }


    private function is_cache_valid(string $cachekey) :bool {
        $expirykey = "{$cachekey}_expires";
        $expires = $this->cache->get($expirykey);
        return $expires > time();
    }

}
