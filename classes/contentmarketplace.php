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

use totara_contentmarketplace\plugininfo\contentmarketplace as contentmarketplace_plugininfo;
use contentmarketplace_goone\api;

final class contentmarketplace extends \totara_contentmarketplace\local\contentmarketplace\contentmarketplace {

    public const CONTENT_AVAILABILITY_SYNC = 1; // Syncing content from Go1
    public const CONTENT_AVAILABILITY_ADD = 2;  // Manually adding new activity

    public $name = 'goone';

    /** Use methods to access:
     *
     * get_collection_options()
     * get_collection_options_withtotals()
     * get_region_options()
     */
    public static $collection_options = [];
    public static $collection_options_withtotals = [];
    public static $region_options = [];

    public static $available_collections = [
        1 => ['free', 'subscribe', 'custom'],   // CONTENT_AVAILABILITY_SYNC
        2 => ['free', 'subscribe', 'custom']    // CONTENT_AVAILABILITY_ADD
    ];
    public static $default_collections = ['custom']; // Checked by default in the content_sync form.
    public static $available_regions = [
        'GLOBAL', 'AU', 'CA', 'GB', 'NZ', 'MY', 'UAE', 'US', 'ZA'
    ];

    /**
     * Returns the URL for the plugin.
     *
     * @return string
     */
    public function url() {
        return 'https://www.go1.com';
    }

    /**
     * Returns the path to a page used to create the course(es), relative to the site root.
     *
     * @return string
     */
     public function course_create_page() {
         return "/totara/contentmarketplace/contentmarketplaces/goone/coursecreate.php";
     }


     public function get_description_html() :string {
        global $OUTPUT;

        $is_plugin_enabled = contentmarketplace_plugininfo::plugin($this->name)->is_enabled();
        $browse_url = new \moodle_url('/totara/contentmarketplace/contentmarketplaces/goone/curate.php', ['returnto' => 'contentmarketplaces']);

        return $OUTPUT->render_from_template('contentmarketplace_goone/plugin_description', [
            'is_plugin_enabled' => $is_plugin_enabled,
            'browse_url' => $browse_url,
        ]);
     }


    /**
     * Returns the path to a page used to add the activity, relative to the site root.
     *
     * @return string
     */
    public function activity_add_page(): string {
        return "/totara/contentmarketplace/contentmarketplaces/goone/activitycreate.php";
    }


    /**
     * Returns a HTML snippet responsible for setting up the GO1 content marketplace data.
     * All related JavaScript has to be there as well.
     *
     * @param string $label
     * @return string Resulting HTML.
     */
    public function get_setup_html($label): string {
        global $OUTPUT;
        $data = new \stdClass();
        $data->oauth_authorize_url = oauth::get_authorize_url(self::oauth_redirect_uri(), self::oauth_user_state())->out(false);
        $data->label = $label;
        return $OUTPUT->render_from_template("contentmarketplace_goone/setup", $data);
    }


    /**
     * @return \moodle_url
     */
    public static function oauth_redirect_uri(): \moodle_url {
        return new \moodle_url("/totara/contentmarketplace/contentmarketplaces/goone/signin.php");
    }

    /**
     * @return array
     */
    public static function oauth_user_state(): array {
        global $USER, $CFG;

        require_once $CFG->dirroot . '/admin/registerlib.php';
        $regdata = get_registration_data();

        $state = [
            'full_name' => fullname($USER),
            'email' => $USER->email,
            'company' => $regdata['orgname'],
            'phone_number' => $USER->phone1,
            'country' => $USER->country,
            'customer_partner' => 'Totara Learn',
            'users_total' => $regdata['activeusercount'],
        ];

        return $state;
    }


    /**
     * Collections available for syncing and course/activity creation,
     * with total count of learning objects in each collection.
     *
     * @param int $mode one of self::CONTENT_AVAILABILITY_SYNC or self::CONTENT_AVAILABILITY_ADDACTIVITY
     *
     * @return array
     */
    public static function get_collection_options_withtotals(int $mode) :array {
        if (!empty(self::$collection_options_withtotals[$mode])) {
            return self::$collection_options_withtotals[$mode];
        }

        $collection_options = [];
        $api = new api();
        foreach (self::$available_collections[$mode] as $collection) {
            $count = $api->get_learning_objects_collection_count($collection);
            $collection_options[$collection] = get_string("collectionwithtotal:{$collection}", 'contentmarketplace_goone', $count);
        }
        self::$collection_options_withtotals[$mode] = $collection_options;

        return self::$collection_options_withtotals[$mode];
    }


    /**
     * Collections available for syncing and course/activity creation.
     *
    * @param int $mode one of self::CONTENT_AVAILABILITY_SYNC or self::CONTENT_AVAILABILITY_ADDACTIVITY
     *
     * @return array
     */
    public static function get_collection_options(int $mode) :array {
        if (!empty(self::$collection_options[$mode])) {
            return self::$collection_options[$mode];
        }

        $collection_options = [];
        foreach (self::$available_collections[$mode] as $collection) {
            $collection_options[$collection] = get_string("collection:{$collection}", 'contentmarketplace_goone');
        }
        self::$collection_options[$mode] = $collection_options;

        return self::$collection_options[$mode];
    }


    /**
     * Relevance regions available for filtering on course/activity creation.
     *
     * @return array
     */
    public static function get_region_options(): array {
        if (!empty(self::$region_options)) {
            return self::$region_options;
        }
        $region_options = [];
        foreach (self::$available_regions as $region) {
            $region_options[$region] = $region;
        }
        self::$region_options = $region_options;

        return self::$region_options;
    }


    /**
     * Return listing of content availability options for the current user in the given context.
     *
     * @param \context $context
     * @return string[] Listing of availability options
     */
    public static function content_availability_options(\context $context) {
        if (has_capability('totara/contentmarketplace:config', $context)) {
            return ['free', 'subscribe', 'custom'];
        } elseif (has_capability('totara/contentmarketplace:add', $context)) {
            $content_settings = (string) get_config('contentmarketplace_goone', 'content_settings_creators');
            return array_values(array_filter(explode(',', $content_settings)));
        }
        return [];
    }

    /**
     * @param \stdClass $data
     */
    public static function save_content_settings_data(\stdClass $data) {
        $content_settings_creators = (!empty($data->creators)) ? implode(',', $data->creators) : '';
        set_config('content_settings_creators', $content_settings_creators, 'contentmarketplace_goone');
        set_config('pay_per_seat', $data->pay_per_seat, 'contentmarketplace_goone');
    }


    /**
     * @param null|string $tab
     * @return \moodle_url
     */
    public function settings_url($tab = null) {
        $url = new \moodle_url("/totara/contentmarketplace/contentmarketplaces/goone/config.php");

        if (!empty($tab)) {
            $url->param('tab', $tab);
        }

        return $url;
    }
}
