<?php
/**
 * This file is part of Totara Learn
 *
 * Copyright (C) 2021 onwards Totara Learning Solutions LTD
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
 * @author  Kirill Astashov <kirill.astashov@androgogic.com>
 * @package contentmarketplace_goone
 */
namespace contentmarketplace_goone\sync_action;

defined('MOODLE_INTERNAL') || die();

use core\orm\query\builder;
use progress_trace;
use totara_contentmarketplace\sync\sync_action;
use contentmarketplace_goone\contentmarketplace;
use contentmarketplace_goone\api;
use contentmarketplace_goone\config_db_storage as config;
use contentmarketplace_goone\helper;


/**
 * Class learning_asset
 * @package contentmarketplace_goone\action
 */
class sync_learning_objects extends sync_action {

    /** @var config  */
    private $config;

    /** @var api  */
    private $api;

    private $sync_collections = [];

    /**
     * sync_learning_asset constructor.
     * @param bool                $is_initial_run
     * @param int|null            $time_run
     * @param progress_trace|null $trace
     */
    public function __construct(
        bool $is_initial_run = false,
        ?progress_trace $trace = null
    ) {
        $this->config = new config();
        $this->api = new api();
        parent::__construct($is_initial_run, $trace);

    }

    /**
     * @return bool
     */
    public function is_skipped(): bool {
        // Skip if content sync is turned off or misconfigured.
        return !helper::is_create_courses_on_and_configured() && !helper::is_retired_content_on_and_configured();
    }


    /**
     * Set collection list to sync.
     *
     * @param array $sync_collections
     * @return void
     */
    public function set_sync_collections(array $sync_collections): void {
        if (sizeof($sync_collections)) {
            $this->sync_collections = $sync_collections;
        }
    }


    /**
     * Get collection list to sync.
     * Returns value set by set_sync_collections(), defaults to config setting.
     *
     *  @return array
     */
    public function get_sync_collections(): array {
        if (sizeof($this->sync_collections)) {
            return $this->sync_collections;
        }
        // Default to config setting
        $sync_collections_str = trim($this->config->get('sync_collections'));
        if (empty($sync_collections_str)) {
            return [];
        }
        $sync_collections =  explode(',', $sync_collections_str);
        return $sync_collections;
    }


    /**
     * @inheritDoc
     */
    public function invoke(): void {
        if ($this->is_skipped()) {
            // The sync action should be skipped.
            $this->trace->output("Go1 content sync skipped - not configured.");
            return;
        }

        $db = builder::get_db();

        $sync_collections = $this->get_sync_collections();

        $los_published_total = 0;
        $los_retired_total = 0;
        $failures = [];
        $mode = contentmarketplace::CONTENT_AVAILABILITY_SYNC;

        if (helper::is_create_courses_on_and_configured()) {
            $this->trace->output("Go1 learning object sync job started.");

            foreach ($sync_collections as $collection) {
                // Sanity check: collection name should be supported.
                if (!in_array($collection, contentmarketplace::$available_collections[$mode])) {
                    continue;
                }

                $this->trace->output("Syncing new content in collection: {$collection}", 4);
                try {
                    $result = $this->sync_collection_new_content($collection);
                } catch (\Throwable $e) {
                    $result = ['total' => 0, 'success' => false, 'message' => $e->getMessage()];
                }
                if (!empty($result['message'])) {
                    $this->trace->output($result['message'], 2);
                }
                if (empty($result['success'])) {
                    $failures[] = "new content in collection {$collection}: {$result['message']}";
                }
                $los_published_total += $result['total'];
            }
        }

        if (helper::is_retired_content_on_and_configured()) {
            foreach ($sync_collections as $collection) {
                // Sanity check: collection name should be supported.
                if (!in_array($collection, contentmarketplace::$available_collections[$mode])) {
                    continue;
                }

                $this->trace->output("Syncing retired content in collection: {$collection}", 2);

                /**** Remove _test when dev completed, switch to live function ****/
                try {
                    // $result = $this->sync_collection_retired_content_test($collection);
                    $result = $this->sync_collection_retired_content($collection);
                } catch (\Throwable $e) {
                    $result = ['total' => 0, 'message' => $e->getMessage()];
                }
                if (!empty($result['message'])) {
                    $this->trace->output($result['message'], 4);
                    $failures[] = "retired content in collection {$collection}: {$result['message']}";
                    break;
                }
                $los_retired_total += $result['total'];
            }
        }

        $this->trace->output("Finished. Processed {$los_published_total} published LOs, {$los_retired_total} retired LOs.");

        if ($failures) {
            // Fail the run visibly: the scheduled task must report the failure instead of
            // silently succeeding, and the last-synced marker must not move.
            throw new \moodle_exception('error:sync_failed', 'contentmarketplace_goone', '', implode('; ', $failures));
        }

        $this->config->set('last_synced_new_content', time());
    }


    /**
     * Get all learning objects that belong to the given collection, create courses/activities.
     *
     * @param string $collection - any of contentmarketplace_goone\contentmarketplace::$available_collections
     *
     * @return array [(int) 'total'      - number of processed learning objects,
     *                (string) 'message' - diagnostic message,
     *                (bool) 'success'   - success or abnormal termination
     *                ]
     */
    private function sync_collection_new_content(string $collection): array {
        $api = $this->api;
        $total = $api->get_learning_objects_collection_count($collection);
        $synced_los = 0;
        $created_courses = 0;

        if (!empty($total)) {
            $params = [];
            $params['collection'] = $collection;
            $params['limit'] = $api::MAX_PAGE_SIZE;
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
            $params['state'] = ['published'];
            $params['type'] = ['course', 'document', 'link', 'interactive', 'text', 'video', 'audio'];
            $content_regions = $this->config->get('content_regions');
            if (!empty($content_regions)) {
                $params['region_relevance'] = explode(',', $content_regions);
            }
            $params['use_scroll'] = true;

            try {
                do {
                    if (isset($next_scroll_id)) {
                        $params['scroll_id'] = $next_scroll_id;
                    }
                    $response = $api->get_learning_objects($params);
                    if (!sizeof ($response->hits)) {
                        break;
                    }
                    foreach ($response->hits as $hit) {
                        $result = helper::process_sync_learning_object($hit, $api);
                        if ($result['success'] !== true) {
                            return ['total' => $synced_los, 'message' => $result['message'], 'success' => false];
                        }
                        if ($result['created']) {
                            $created_courses++;
                        }
                        $synced_los++;
                        if ($synced_los % 1000 == 0) {
                            $this->trace->output("Processed: {$synced_los}, courses created: {$created_courses}", 4);
                        }
                    }
                    if (empty($response->next_scroll_id)) {
                        break;
                    }
                    $next_scroll_id = $response->next_scroll_id;
                } while ($synced_los < $response->total);
            } catch (\Exception $e) {
                $message = $e->getMessage();
                $this->trace->output("Processed: {$synced_los}, courses created: {$created_courses}", 4);
                return ['total' => $synced_los, 'message' => "Abnormal termination: " . $message, 'success' => false];
            }
        }
        $this->trace->output("Processed: {$synced_los}, courses created: {$created_courses}", 2);
        return ['total' => $synced_los, 'success' => true, 'message' => ''];
    }


    /**
     * Get retiring/retired learning objects that belong to the given collection, act on courses in Totara as configured.
     *
     * @param string $collection - any of contentmarketplace_goone\contentmarketplace::$available_collections
     *
     * @return array [(int) 'total' => number of processed learning objects,
     *                (string) 'message' => in case of error]
     */
    private function sync_collection_retired_content(string $collection): array {
        $api = $this->api;
        $total = $api->get_learning_objects_collection_count_retired($collection);
        $synced_los = 0;

        if (!empty($total)) {
            for ($page = 0; $page < $api::MAX_AVAILABLE_RESULTS/$api::MAX_PAGE_SIZE; $page += 1) {
                $params['collection'] = $collection;
                $params['state'] = ['retired'];
                $params['offset'] = $page * $api::MAX_PAGE_SIZE;
                $params['limit'] = $api::MAX_PAGE_SIZE;
                $params['include'] = [
                    'core',
                    'lifecycle',
                    'images'
                ];
                $params['type'] = ['course', 'document', 'link', 'interactive', 'text', 'video', 'audio'];
                $response = $api->get_learning_objects($params);
                foreach ($response->hits as $hit) {
                    $result = helper::process_retired_learning_object($hit);
                    if ($result !== true) {
                        return ['total' => $synced_los, 'message' => $result];
                    }
                    $synced_los++;
                }
                if ($synced_los >= $response->total) {
                    break;
                }
            }
        }
        return ['total' => $synced_los];
    }


    // !!! For use in DEV only !!!
    private function sync_collection_retired_content_test(string $collection): array {
        $api = $this->api;
        $synced_los = 0;
        $maxlos = 19;
        $params['collection'] = $collection;
        $params['limit'] = 3;
        $params['include'] = [
            'core',
            'lifecycle',
            'images'
       ];
       $params['type'] = ['course', 'document', 'link', 'interactive', 'text', 'video', 'audio'];
       $response = $api->get_learning_objects($params);
       foreach ($response->hits as $hit) {
            $hit->lifecycle = new \stdClass();
            $hit->lifecycle->pending_state = new \stdClass();
            $hit->lifecycle->pending_state->retired_time = date('Y-m-d', time() - 5 * 86400);
            $hit->lifecycle->pending_state->removed_time = date('Y-m-d', time() + 65 * 86400);
            // $hit->lifecycle->pending_state->removed_time = date('Y-m-d', time() - 1 * 86400);
            $result = helper::process_retired_learning_object($hit);
            if ($result !== true) {
                return ['total' => $synced_los, 'message' => $result];
            }
            $synced_los++;
            if ($synced_los > $maxlos) {
                return ['total' => $synced_los];
            }
       }
       return ['total' => $synced_los];
    }

}