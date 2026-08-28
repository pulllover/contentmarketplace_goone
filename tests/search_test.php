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

use contentmarketplace_goone\search;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

defined('MOODLE_INTERNAL') || die();

/**
 * Test search class
 */
#[Group('totara_contentmarketplace')]
class contentmarketplace_goone_search_test extends \core_phpunit\testcase {

    #[DataProvider('pricing_provider')]
    public function test_price($course, $expected) {
        $price = search::price($course);
        $this->assertSame($expected, $price);
    }

    public static function pricing_provider() {
        $price = \totara_contentmarketplace\local::format_money(1234.5, 'AUD');
        $price_with_tax = get_string(
            'pricewithtax',
            'contentmarketplace_goone',
            (object) ['baseprice' => $price, 'tax' => 10]
        );
        return [
            [
                json_decode('{
                    "pricing": {
                        "currency": "AUD",
                        "price": 1234.5,
                        "tax": 0,
                        "tax_included": true
                    },
                    "subscription": {
                        "licenses": -1,
                        "package": "premium"
                    }
                }'),
                'Included'
            ],
            [
                json_decode('{
                    "pricing": {
                        "currency": "AUD",
                        "price": 1234.5,
                        "tax": 0,
                        "tax_included": true
                    },
                    "subscription": {
                        "licenses": 10,
                        "package": "premium"
                    }
                }'),
                'Included'
            ],
            [
                json_decode('{
                    "pricing": {
                        "currency": "AUD",
                        "price": 0,
                        "tax": 0,
                        "tax_included": true
                    },
                    "subscription": {
                        "licenses": -1,
                        "package": "premium"
                    }
                }'),
                'Included'
            ],
            [
                json_decode('{
                    "pricing": {
                        "currency": "AUD",
                        "price": 1234.5,
                        "tax": 0,
                        "tax_included": true
                    },
                    "subscription": {
                        "licenses": 0,
                        "package": "premium"
                    }
                }'),
                $price
            ],
            [
                json_decode('{
                    "pricing": {
                        "currency": "AUD",
                        "price": 0,
                        "tax": 0,
                        "tax_included": true
                    },
                    "subscription": {
                        "licenses": 0,
                        "package": "premium"
                    }
                }'),
                'Free'
            ],
            [
                json_decode('{
                    "pricing": {
                        "currency": "AUD",
                        "price": 0,
                        "tax": 0,
                        "tax_included": true
                    },
                    "subscription": {
                        "licenses": null,
                        "package": null
                    }
                }'),
                'Free'
            ],
            [
                json_decode('{
                    "pricing": {
                        "currency": "AUD",
                        "price": 1234.5,
                        "tax": 0,
                        "tax_included": true
                    },
                    "subscription": {
                        "licenses": null,
                        "package": null
                    }
                }'),
                $price
            ],
            [
                json_decode('{
                    "pricing": {
                        "currency": "AUD",
                        "price": 1234.5,
                        "tax": 10,
                        "tax_included": true
                    },
                    "subscription": {
                        "licenses": null,
                        "package": null
                    }
                }'),
                $price
            ],
            [
                json_decode('{
                    "pricing": {
                        "currency": "AUD",
                        "price": 1234.5,
                        "tax": 10,
                        "tax_included": false
                    },
                    "subscription": {
                        "licenses": null,
                        "package": null
                    }
                }'),
                $price_with_tax
            ],
            [
                json_decode('{
                    "pricing": {
                        "currency": "AUD",
                        "price": 1234.5,
                        "tax": 0,
                        "tax_included": false
                    },
                    "subscription": {
                        "licenses": null,
                        "package": null
                    }
                }'),
                $price
            ],
            [
                json_decode('{
                    "pricing": {
                        "currency": "AUD",
                        "price": 0,
                        "tax": 10,
                        "tax_included": false
                    },
                    "subscription": {
                        "licenses": null,
                        "package": null
                    }
                }'),
                'Free'
            ],
            [
                json_decode('{
                    "pricing": {
                        "currency": null,
                        "price": null,
                        "tax": null,
                        "tax_included": null
                    },
                    "subscription": {
                        "licenses": null,
                        "package": null
                    }
                }'),
                ''
            ],
        ];
    }

    #[DataProvider('duration_provider')]
    public function test_duration($course, string $expected): void {
        self::assertSame($expected, search::duration($course));
    }

    public static function duration_provider(): array {
        return [
            'no relevance data' => [(object) [], ''],
            'zero duration' => [(object) ['relevance' => (object) ['duration' => 0]], ''],
            'duration in minutes' => [(object) ['relevance' => (object) ['duration' => 45]], '45 minutes'],
        ];
    }

    public function test_availability_selection_for_site_administrator(): void {
        self::setAdminUser();
        $context = context_system::instance();
        $search = new search();

        // An explicit valid filter wins.
        self::assertSame('free', $search->availability_selection(['availability' => 'free'], $context));
        self::assertSame('subscribe', $search->availability_selection(['availability' => 'subscribe'], $context));

        // An invalid filter value falls back to the default collection.
        self::assertSame('custom', $search->availability_selection(['availability' => 'bogus'], $context));

        // No filter defaults to the custom collection.
        self::assertSame('custom', $search->availability_selection([], $context));
    }

    public function test_availability_selection_for_content_creator(): void {
        global $DB;
        $context = context_system::instance();

        $user = self::getDataGenerator()->create_user();
        $role_id = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($role_id, $user->id, $context);
        assign_capability('totara/contentmarketplace:add', CAP_ALLOW, $role_id, $context);
        unassign_capability('totara/contentmarketplace:config', $role_id, $context->id);
        assign_capability('totara/contentmarketplace:config', CAP_PROHIBIT, $role_id, $context);
        self::setUser($user);

        $search = new search();

        // Creators restricted to the custom collection are always forced onto it.
        set_config('content_settings_creators', 'custom', 'contentmarketplace_goone');
        self::assertSame('custom', $search->availability_selection(['availability' => 'free'], $context));

        // With a wider allowance the requested filter is respected.
        set_config('content_settings_creators', 'free,custom', 'contentmarketplace_goone');
        self::assertSame('free', $search->availability_selection(['availability' => 'free'], $context));

        // A request for a collection outside the allowance is clamped to the default.
        self::assertSame('custom', $search->availability_selection(['availability' => 'subscribe'], $context));

        // When custom is not allowed, the default falls back to the first allowed collection.
        set_config('content_settings_creators', 'free,subscribe', 'contentmarketplace_goone');
        self::assertSame('free', $search->availability_selection([], $context));
        self::assertSame('free', $search->availability_selection(['availability' => 'custom'], $context));
        self::assertSame('subscribe', $search->availability_selection(['availability' => 'subscribe'], $context));

        // With no collections allowed at all there is no selection.
        set_config('content_settings_creators', '', 'contentmarketplace_goone');
        self::assertNull($search->availability_selection([], $context));
        self::assertNull($search->availability_selection(['availability' => 'free'], $context));
    }

    /**
     * @return search A search instance whose API calls are served by the mock curl fixtures
     */
    private function make_mock_search(): search {
        return new search(\contentmarketplace_goone\testing\generator::instance()->get_mock_api());
    }

    public function test_query_returns_hits_from_api(): void {
        self::setAdminUser();
        $search = $this->make_mock_search();

        $results = $search->query('', 'created:desc', [], 0, false,
            \totara_contentmarketplace\explorer::MODE_EXPLORE, context_system::instance());

        self::assertEquals(82137, $results->total);
        self::assertTrue($results->more);
        self::assertCount(48, $results->hits);

        $hit = $results->hits[0];
        self::assertNotEmpty($hit['id']);
        self::assertNotEmpty($hit['title']);
        self::assertArrayHasKey('image', $hit);
        self::assertArrayHasKey('price', $hit);
        self::assertNotEmpty($hit['provider']['name']);

        // The availability filter carries formatted counts for all three collections (admin).
        $availability = $results->filters[0]['values'];
        self::assertArrayHasKey('free', $availability);
        self::assertArrayHasKey('subscribe', $availability);
        self::assertArrayHasKey('custom', $availability);
    }

    public function test_query_with_filters_maps_them_to_v3_parameters(): void {
        self::setAdminUser();
        $search = $this->make_mock_search();

        // The explorer posts filter selections under their v2-era names; the query
        // must translate them to the v3 parameters (topics, language, providers).
        // The mock API only recognises the translated request, so a hit here
        // proves the mapping.
        $filter = [
            'tags' => ['Mock topic A'],
            'language' => ['en', 'ja'],
            'provider' => ['101'],
        ];
        $results = $search->query('', 'created:desc', $filter, 0, false,
            \totara_contentmarketplace\explorer::MODE_EXPLORE, context_system::instance());

        self::assertEquals(54, $results->total);
        self::assertCount(1, $results->hits);
        self::assertSame('Filtered listing hit', $results->hits[0]['title']);
    }

    public function test_query_with_no_allowed_collections_returns_nothing(): void {
        global $DB;
        $context = context_system::instance();

        $user = self::getDataGenerator()->create_user();
        $role_id = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($role_id, $user->id, $context);
        assign_capability('totara/contentmarketplace:add', CAP_ALLOW, $role_id, $context);
        unassign_capability('totara/contentmarketplace:config', $role_id, $context->id);
        assign_capability('totara/contentmarketplace:config', CAP_PROHIBIT, $role_id, $context);
        set_config('content_settings_creators', '', 'contentmarketplace_goone');
        self::setUser($user);

        $search = $this->make_mock_search();

        // No API request is made at all: the empty allowance means an empty result set.
        $results = $search->query('', 'created:desc', [], 0, false,
            \totara_contentmarketplace\explorer::MODE_EXPLORE, $context);
        self::assertSame(0, $results->total);
        self::assertSame([], $results->hits);
        self::assertFalse($results->more);

        // Select-all is equally empty.
        self::assertSame([], $search->select_all('', [],
            \totara_contentmarketplace\explorer::MODE_EXPLORE, $context));
    }

    public function test_get_filter_seeds(): void {
        self::setAdminUser();
        $search = $this->make_mock_search();

        $seeds = $search->get_filter_seeds(context_system::instance(),
            \totara_contentmarketplace\explorer::MODE_EXPLORE);

        self::assertCount(4, $seeds);
        [$availability, $tags, $provider, $language] = $seeds;

        self::assertSame('availability', $availability['name']);
        self::assertCount(3, $availability['options']);

        // The options span every collection offered by the availability filter
        // (free, subscribe and custom fixtures), merged and ordered by their
        // combined content counts.
        self::assertSame('tags', $tags['name']);
        self::assertSame(
            ['Mock topic A', 'Mock topic G', 'Mock topic E', 'Mock topic D', 'Mock topic C', 'Mock topic B', 'Mock topic F'],
            array_keys($tags['options'])
        );

        self::assertSame('provider', $provider['name']);
        // Providers 336331 and 336348 have a blank or missing name and must be skipped.
        self::assertSame([203, 202, 101, 204], array_keys($provider['options']));
        self::assertSame('LearningPlanet', $provider['options'][203]['label']);
        self::assertSame('Chart Learning Solutions', $provider['options'][202]['label']);

        self::assertSame('language', $language['name']);
        self::assertSame(['en', 'de', 'fr', 'es', 'ja'], array_keys($language['options']));
        self::assertSame('English', $language['options']['en']['label']);
    }

    public function test_get_filter_seeds_for_creator_with_restricted_collections(): void {
        global $DB;
        $context = context_system::instance();

        $user = self::getDataGenerator()->create_user();
        $role_id = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($role_id, $user->id, $context);
        assign_capability('totara/contentmarketplace:add', CAP_ALLOW, $role_id, $context);
        unassign_capability('totara/contentmarketplace:config', $role_id, $context->id);
        assign_capability('totara/contentmarketplace:config', CAP_PROHIBIT, $role_id, $context);
        set_config('content_settings_creators', 'free', 'contentmarketplace_goone');
        self::setUser($user);

        $search = $this->make_mock_search();
        $seeds = $search->get_filter_seeds($context, \totara_contentmarketplace\explorer::MODE_EXPLORE);
        [$availability, $tags, $provider, $language] = $seeds;

        // Only the free collection may be queried, so the options come from its facets alone.
        self::assertCount(1, $availability['options']);
        self::assertSame(['Mock topic A', 'Mock topic F'], array_keys($tags['options']));
        self::assertSame([202, 101, 203], array_keys($provider['options']));
        self::assertSame(['en', 'fr'], array_keys($language['options']));
    }

    public function test_get_filter_seeds_with_no_allowed_collections(): void {
        global $DB;
        $context = context_system::instance();

        $user = self::getDataGenerator()->create_user();
        $role_id = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($role_id, $user->id, $context);
        assign_capability('totara/contentmarketplace:add', CAP_ALLOW, $role_id, $context);
        unassign_capability('totara/contentmarketplace:config', $role_id, $context->id);
        assign_capability('totara/contentmarketplace:config', CAP_PROHIBIT, $role_id, $context);
        set_config('content_settings_creators', '', 'contentmarketplace_goone');
        self::setUser($user);

        // No collections are allowed: no API request is made and all seeds are empty.
        $search = $this->make_mock_search();
        $seeds = $search->get_filter_seeds($context, \totara_contentmarketplace\explorer::MODE_EXPLORE);
        [$availability, $tags, $provider, $language] = $seeds;

        self::assertSame([], $availability['options']);
        self::assertSame([], $tags['options']);
        self::assertSame([], $provider['options']);
        self::assertSame([], $language['options']);
    }

    public function test_select_all_returns_ids(): void {
        self::setAdminUser();
        $search = $this->make_mock_search();

        $ids = $search->select_all('', [],
            \totara_contentmarketplace\explorer::MODE_EXPLORE, context_system::instance());

        self::assertSame([111, 222], $ids);
    }

    public function test_get_details(): void {
        $api = \contentmarketplace_goone\testing\generator::instance()->get_mock_api();
        $search = new search();

        $details = $search->get_details('29271', $api);

        self::assertNotNull($details);
        self::assertTrue($details->success);
        self::assertSame('Mocked learning object 29271', $details->title);
        self::assertTrue($details->has_image);
    }

    public function test_get_details_without_image_or_relevance(): void {
        $api = \contentmarketplace_goone\testing\generator::instance()->get_mock_api();
        $search = new search();

        // Mocked learning object 999001 has no image and no relevance block.
        $details = $search->get_details('999001', $api);

        self::assertNotNull($details);
        self::assertSame('Learning object without an image', $details->title);
        self::assertSame('', $details->image);
        self::assertFalse($details->has_image);
        self::assertSame(0, $details->relevance->duration);
    }

    public function test_get_details_with_api_failure_returns_null(): void {
        $api = \contentmarketplace_goone\testing\generator::instance()->get_mock_api();
        $search = new search();

        // No mock fixture exists for this id, so the API layer throws.
        self::assertNull($search->get_details('999999', $api));
        $this->assertDebuggingCalled();
    }

    public function test_availability_selection_without_capabilities(): void {
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);

        $search = new search();
        self::assertNull($search->availability_selection(['availability' => 'free'], context_system::instance()));
    }
}
