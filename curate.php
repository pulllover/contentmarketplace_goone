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
 * @author Kirill Astashov <kirill.astashov@androgogic.com>
 * @package contentmarketplace_goone
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/config.php');

use contentmarketplace_goone\api;
use contentmarketplace_goone\embed;
use contentmarketplace_goone\oauth;
use contentmarketplace_goone\contentmarketplace;

$returnto = optional_param('returnto', '', PARAM_TEXT);
$category = optional_param('category', 0, PARAM_INT);

$context = context_system::instance();

require_login();
require_capability('contentmarketplace/goone:curatecontent', $context);

$oauth_client_id = get_config('contentmarketplace_goone', 'oauth_client_id');
if (empty($oauth_client_id)) {
    throw new moodle_exception('error:pluginnotconfigured', 'contentmarketplace_goone');
}

/** @var moodle_page $PAGE */
$PAGE->set_context($context);
$PAGE->set_url('/totara/contentmarketplace/contentmarketplaces/goone/curate.php');
$PAGE->set_title(get_string('curate_content', 'contentmarketplace_goone'));
$PAGE->set_heading(get_string('curate_content', 'contentmarketplace_goone'));
$PAGE->set_pagelayout('noblocks');
switch ($returnto) {
    case 'catmanage':
        $params = [];
        if (!empty($category)) {
            $params['categoryid'] = $category;
        }
        $topurl = new moodle_url('/course/management.php', $params);
        $PAGE->navbar->add(get_string('coursemgmt', 'admin'), $topurl);
        $PAGE->navbar->add(get_string('addcoursefromlibrary', 'contentmarketplace_goone'));
        break;
    case 'contentmarketplaces':
        $PAGE->navbar->add(get_string('administrationsite'));
        $PAGE->navbar->add(get_string('plugins', 'admin'));
        $PAGE->navbar->add(get_string('contentmarketplace', 'totara_contentmarketplace'));
        $returnto_url = new moodle_url('/totara/contentmarketplace/marketplaces.php');
        $PAGE->navbar->add(get_string('manage_content_marketplaces', 'totara_contentmarketplace'), $returnto_url);
        break;
    default:
    break;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('curate_content', 'contentmarketplace_goone'));

$ott = '';
$api = new api();
try {
    $ott = embed::generate_user_login_ott($api);
} catch (\contentmarketplace_goone\missing_scope_exception $e) {
    echo "<br />";
    echo $OUTPUT->heading(get_string('error:missing_scope_header', 'contentmarketplace_goone'), 4);
    $client_id = get_config('contentmarketplace_goone', 'oauth_client_id');
    echo get_string('error:missing_scope', 'contentmarketplace_goone', $client_id);
    $data = new \stdClass();
    $data->oauth_authorize_url = oauth::get_authorize_url(contentmarketplace::oauth_redirect_uri(), contentmarketplace::oauth_user_state())->out(false);
    $data->label = get_string('complete_connection', 'contentmarketplace_goone');
    echo $OUTPUT->render_from_template("contentmarketplace_goone/setup", $data);
    echo $OUTPUT->footer();
    exit;
}
if (empty($ott)) {
    throw new moodle_exception('error:cannotobtainott', 'contentmarketplace_goone');
}

$data = new \stdClass();
$data->ott = $ott;
$data->button_url = $CFG->wwwroot . '/totara/contentmarketplace/contentmarketplaces/goone/curate.php';
$data->sesskey = sesskey();

echo $OUTPUT->render_from_template('contentmarketplace_goone/curate', $data);
echo $OUTPUT->footer();
