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

use totara_contentmarketplace\local;
use totara_contentmarketplace\local\contentmarketplace\search_results;
use contentmarketplace_goone\api;

defined('MOODLE_INTERNAL') || die();

final class search extends \totara_contentmarketplace\local\contentmarketplace\search {

    // Max API limit for search results is 50. However 48 happens to be a better fit for a grid of search results.
    // 48 divides by 4, 3, and 2 so then a full page of results will always finishes with a complete row.
    const SEARCH_PAGE_SIZE = 48;

    /** @var api|null */
    private $api;

    /**
     * search constructor.
     *
     * @param api|null $api API instance, created on demand when not supplied (injectable for testing)
     */
    public function __construct(?api $api = null) {
        $this->api = $api;
    }

    /**
     * @return api
     */
    protected function create_api(): api {
        return $this->api ?? new api();
    }

    /**
     * List sorting options available for the search results.
     *
     * @return string[]
     */
    public function sort_options(): array {
        $options = array(
            'created:desc',
            'relevance',
            'popularity',
            'price',
            'price:desc',
            'title',
        );
        return $options;
    }

    /**
     * @param string $keyword
     * @param string $sort
     * @param array $filter
     * @param int $page
     * @param bool $isfirstquerywithdefaultsort
     * @param string $mode
     * @param \context $context
     * @return search_results
     */
    public function query(string $keyword, string $sort, array $filter, int $page, bool $isfirstquerywithdefaultsort, string $mode, \context $context): search_results {
        $api = $this->create_api();
        $hits = array();
        $filterid = 0;

        if ($isfirstquerywithdefaultsort) {
            $sort = 'relevance';
        }

        if (strpos($sort, ':') !== false) {
            $parts = explode(':', $sort);
            $sort = $parts[0];
            $order = $parts[1];
        }

        $params = array(
            "keyword" => $keyword,
            "sort" => $sort,
            "offset" => $page * self::SEARCH_PAGE_SIZE,
            "limit" => self::SEARCH_PAGE_SIZE,
        );
        if (isset($order)) {
            $params['order'] = $order;
        }
        $params += $this->filter_query($filter);
        $availability_selection = $this->availability_selection($filter, $context, $mode);
        if ($availability_selection === null) {
            // No collections are available to this user: show nothing rather than everything.
            $results = new search_results();
            $results->hits = [];
            $results->filters = [
                [
                    'name' => 'availability',
                    'values' => [],
                ],
            ];
            $results->total = 0;
            $results->more = false;
            $results->sort = $sort;
            return $results;
        }
        $params += $this->availability_query($availability_selection);
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
        $params['type'] = ['course', 'document', 'link', 'interactive', 'text', 'video', 'audio'];

        $response = $api->get_learning_objects($params);
        foreach ($response->hits as $hit) {

            $delivery = array();
            if ($hit->relevance->duration > 0) {
                $title = self::duration($hit);
                $delivery[] = array("title" => $title);
            }
            if (!empty($delivery)) {
                $delivery[count($delivery) - 1]["last"] = true;
            }

            $hits[] = array(
                "id" => $hit->lo_id,
                "title" => $hit->core->title,
                "selectlabel" => get_string('selectcontent', 'contentmarketplace_goone', $hit->core->title),
                "image" => $hit->core->image->value ?? '',
                "provider" => array(
                    "name" => $hit->provider->name ?? '',
                ),
                "delivery" => $delivery,
                "delivery_has_items" => !empty($delivery),
                "price" => self::price($hit),
            );
        }

        $results = new search_results();
        $results->hits = $hits;

        $results->filters = [
            [
                'name' => 'availability',
                'values' => $this->availability_filter($params, $context)
            ]
        ];

        $results->total = $response->total;

        $results->more = $response->total > ($page + 1) * self::SEARCH_PAGE_SIZE;
        $results->sort = $sort;

        // Collection curation is handled by the embedded Go1 content hub
        // (see curate.php), so the explorer's add/remove-to-collection buttons,
        // which are driven by selectionmode, are left unset.

        return $results;
    }

    /**
     * @param array $params
     * @param \context $context
     * @return array
     */
    public function availability_filter(array $params, \context $context) {
        $api = $this->create_api();

        $values = [];
        $availablityoptions = contentmarketplace::content_availability_options($context);
        if (in_array('free', $availablityoptions)) {
            $values["free"] = local::format_integer($api->get_learning_objects_collection_count('free'));
        }

        if (in_array('subscribe', $availablityoptions)) {
            $values["subscribe"] = local::format_integer($api->get_learning_objects_collection_count('subscribe'));
        }

        if (in_array('custom', $availablityoptions)) {
            $values["custom"] = local::format_integer($api->get_learning_objects_collection_count('custom'));
        }

        return $values;
    }

    /**
     * @param array $filter
     * @param \context $context
     * @param string|null $mode
     * @return null|string
     */
    public function availability_selection(array $filter, \context $context, string $mode = null) {
        if (key_exists("availability", $filter)) {
            $selection = $filter["availability"];
            if (!in_array($selection, array("free", "subscribe", "custom"))) {
                $selection = null;
            }
        } else if ($mode == \totara_contentmarketplace\explorer::MODE_EXPLORE_COLLECTION) {
            $selection = 'custom';
        } else {
            $selection = null;
        }

        if (has_capability('totara/contentmarketplace:config', $context)) {
            if (!isset($selection)) {
                $selection = "custom";
            }
        } else if (has_capability('totara/contentmarketplace:add', $context)) {
            // Creators may only query the collections allowed by the content access settings,
            // so both the requested selection and the default are clamped to that list.
            $contentsettingscreators = (string) get_config('contentmarketplace_goone', 'content_settings_creators');
            $allowed = array_filter(explode(',', $contentsettingscreators));
            if (!isset($selection) || !in_array($selection, $allowed)) {
                if (in_array('custom', $allowed)) {
                    $selection = 'custom';
                } else {
                    $selection = reset($allowed) ?: null;
                }
            }
        } else {
            $selection = null;
        }

        return $selection;
    }

    /**
     * @param string $selection
     * @return array
     */
    public function availability_query($selection) {
        // A content availability option; api::collection_scope() turns it into an API collection scope.
        $query = ['collection' => $selection];
        return $query;
    }

    /**
     * Translate the explorer filter selections into API v3 query parameters.
     *
     * @param array $filter Explorer filter selections, keyed by filter name.
     * @return array API v3 query parameters.
     */
    public function filter_query(array $filter): array {
        $query = [];
        $names = [
            'tags' => 'topics',
            'language' => 'language',
            'provider' => 'providers',
        ];
        foreach ($names as $name => $param) {
            if (!empty($filter[$name])) {
                $query[$param] = $filter[$name];
            }
        }
        return $query;
    }

    /**
     * @param string $query
     * @param array $filter
     * @param string $mode
     * @param \context $context
     * @return array
     */
    public function select_all($query, array $filter, string $mode, \context $context) {
        $params = array(
            "keyword" => $query,
        );
        $params += $this->filter_query($filter);
        $availability_selection = $this->availability_selection($filter, $context, $mode);
        if ($availability_selection === null) {
            // No collections are available to this user: nothing can be selected.
            return [];
        }
        $params += $this->availability_query($availability_selection);

        $api = $this->create_api();
        return $api->list_ids_for_all_learning_objects($params);
    }

    /**
     * @param \stdClass $course
     * @return string
     */
    public static function price($course) {
        if (isset($course->subscription->licenses) and !is_null($course->subscription->licenses) and ($course->subscription->licenses === -1 or $course->subscription->licenses > 0)) {
            return get_string('price:included', 'contentmarketplace_goone');
        }
        if ($course->pricing->price === 0) {
            return get_string('price:free', 'contentmarketplace_goone');
        }
        if (empty($course->pricing->price) || empty($course->pricing->currency)) {
            return '';
        }
        $price = local::format_money($course->pricing->price, $course->pricing->currency);
        if (!$course->pricing->tax_included and $course->pricing->tax > 0) {
            $a = new \stdClass();
            $a->baseprice = $price;
            $a->tax = $course->pricing->tax;
            return get_string('pricewithtax', 'contentmarketplace_goone', $a);
        } else {
            return $price;
        }
    }

    /**
     * @param \stdClass $course
     * @return string
     */
    public static function duration($course) {
        if (empty($course->relevance) || empty($course->relevance->duration)) {
            return '';
        }
        return get_string('duration', 'contentmarketplace_goone', $course->relevance->duration);
    }

    /**
     * @param string|int $id
     * @param api|null $api API instance, created internally when not supplied
     * @return \stdClass|null
     */
    public function get_details(string $id, ?api $api = null) {
        try {
            $api = $api ?? $this->create_api();
            $lo = $api->get_learning_object($id);

            $data = new \stdClass();
            $data->success = true;
            $data->title = $lo->core->title;
            $data->description = clean_text($lo->core->description);
            $data->delivery = new \stdClass();
            $data->relevance = new \stdClass();
            $data->relevance->duration = $lo->relevance->duration ?? 0;
            $data->delivery_has_items = false;
            $data->items = 0;
            $data->has_items = false;
            $data->image = $lo->core->image->value ?? '';
            $data->has_image = !empty($data->image);

            // Effectively disabling ratings, got to fix broken logic in core fetch_details.php first.
            $data->reviews = new \stdClass();
            $data->reviews->rating = null;

            $data->provider = $lo->provider;
            $data->provider->id = $lo->core->provider_id ?? null;
            $data->pricing = $lo->pricing;
            $data->stars = !empty($lo->quality->user_rating->five_star_rating) ? $lo->quality->user_rating->five_star_rating : 0;
        } catch (\Throwable $ex) {
            // Any failure (API errors as well as unexpected data shapes) degrades to "no details".
            debugging($ex->getMessage(), DEBUG_DEVELOPER);
            return null;
        }

        return $data;
    }

    /**
     * @param string $mode
     * @param \context $context
     * @return array
     */
    public function availability_filter_seed($context, $mode) {
        $selection = $this->availability_selection([], $context, $mode);
        $filterid = 0;
        $free = [
            "htmlid" => 'tcm-filter-availability-' . $filterid++,
            "value" => "free",
            "label" => get_string("availability-filter:free", "contentmarketplace_goone"),
            "count" => "",
            "checked" => $selection === "free",
        ];

        $subscribe = [
            "htmlid" => 'tcm-filter-availability-' . $filterid++,
            "value" => "subscribe",
            "label" => get_string("availability-filter:subscribe", "contentmarketplace_goone"),
            "count" => "",
            "checked" => $selection === "subscribe",
        ];

        $custom = [
            "htmlid" => 'tcm-filter-availability-' . $filterid++,
            "value" => "custom",
            "label" => get_string("availability-filter:custom", "contentmarketplace_goone"),
            "count" => "",
            "checked" => $selection === "custom",
        ];

        $content_settings = get_config('contentmarketplace_goone', 'content_settings_creators');
        if (has_capability('totara/contentmarketplace:config', $context)) {
            $options = [$free, $subscribe, $custom];
        } else if (has_capability('totara/contentmarketplace:add', $context)) {
            $allowed_collections = explode(',', $content_settings);
            $options = [];
            if (in_array('free', $allowed_collections)) {
                $options[] = $free;
            }
            if (in_array('subscribe', $allowed_collections)) {
                $options[] = $subscribe;
            }
            if (in_array('custom', $allowed_collections)) {
                $options[] = $custom;
            }
        } else {
            $options = [];
        }

        $seed = [
            "name" => "availability",
            "options" => $options,
        ];
        return $seed;
    }

    /**
     * Build the tags filter seed from the API v3 topics facet.
     *
     * API v3 has no tag facet, so the filter offers the Go1 topics taxonomy
     * instead; selections are queried back through the topics parameter.
     *
     * @param \stdClass $response API response holding a facets->topics list.
     * @return array
     */
    public function tags_filter_seed($response) {
        $tags = [];
        $filterid = 0;
        if (!empty($response->facets->topics)) {
            foreach ($response->facets->topics as $bucket) {
                $tags[$bucket->key] = [
                    "htmlid" => 'tcm-filter-tag-' . $filterid++,
                    "value" => $bucket->key,
                    "label" => $bucket->key,
                    "checked" => false,
                ];
            }
        }
        $seed = array(
            "name" => "tags",
            "options" => $tags,
        );
        return $seed;
    }

    /**
     * Build the provider filter seed from the API v3 providers facet.
     *
     * @param \stdClass $response API response holding a facets->providers list.
     * @return array
     */
    public function provider_filter_seed($response) {
        $providers = [];
        $filterid = 0;
        if (!empty($response->facets->providers)) {
            foreach ($response->facets->providers as $bucket) {
                // Some catalogue providers come through with no name; a filter
                // option without a label is unusable, so they are skipped.
                if (trim((string) ($bucket->name ?? '')) === '') {
                    continue;
                }
                $providers[$bucket->key] = [
                    "htmlid" => 'tcm-filter-provider-' . $filterid++,
                    "value" => $bucket->key,
                    "label" => $bucket->name,
                    "checked" => false,
                ];
            }
        }
        $seed = [
            "name" => "provider",
            "options" => $providers,
        ];
        return $seed;
    }

    /**
     * Build the language filter seed from the API v3 language facet.
     *
     * @param \stdClass $response API response holding a facets->language list.
     * @return array
     */
    public function language_filter_seed($response) {
        $languages = [];
        $filterid = 0;
        $stringmanager = new string_manager();
        if (!empty($response->facets->language)) {
            foreach ($response->facets->language as $bucket) {
                $label = $stringmanager->get_language($bucket->key);
                $languages[$bucket->key] = [
                    "htmlid" => 'tcm-filter-language-' . $filterid++,
                    "value" => $bucket->key,
                    "label" => $label,
                    "checked" => false,
                ];
            }
        }
        $seed = [
            "name" => "language",
            "options" => $languages,
        ];
        return $seed;
    }

    /**
     * Collect the facet listings backing the filter options.
     *
     * API v3 has no collection scope spanning the whole catalogue (v2's "all"),
     * and the explorer populates its filter options only once per page load, so
     * the options must cover every collection the availability filter offers.
     * The facets of each allowed collection are fetched separately and merged,
     * summing the bucket counts and ordering by them.
     *
     * @param \context $context
     * @return \stdClass Response-shaped object holding the merged facets.
     */
    protected function collect_facets(\context $context): \stdClass {
        $api = $this->create_api();
        $facets = [
            'topics' => [],
            'language' => [],
            'providers' => [],
        ];
        foreach (contentmarketplace::content_availability_options($context) as $collection) {
            $collection_facets = $api->get_learning_objects_facets($collection);
            foreach (array_keys($facets) as $name) {
                if (empty($collection_facets->{$name})) {
                    continue;
                }
                foreach ($collection_facets->{$name} as $bucket) {
                    $key = $bucket->key;
                    if (isset($facets[$name][$key])) {
                        $facets[$name][$key]->count += $bucket->count ?? 0;
                    } else {
                        // Clone: the buckets are shared through the API response cache.
                        $merged = clone $bucket;
                        $merged->count = $bucket->count ?? 0;
                        $facets[$name][$key] = $merged;
                    }
                }
            }
        }
        foreach ($facets as $name => $buckets) {
            usort($buckets, function ($a, $b) {
                return $b->count <=> $a->count;
            });
            $facets[$name] = $buckets;
        }

        return (object) ['facets' => (object) $facets];
    }

    /**
     * @param string $mode
     * @param \context $context
     * @return array
     */
    public function get_filter_seeds($context, $mode) {
        $response = $this->collect_facets($context);

        $data = [];
        $data[] = $this->availability_filter_seed($context, $mode);
        $data[] = $this->tags_filter_seed($response);
        $data[] = $this->provider_filter_seed($response);
        $data[] = $this->language_filter_seed($response);
        return $data;
    }

}
