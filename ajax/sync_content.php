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
 */

define('AJAX_SCRIPT', true);
require_once(dirname(dirname(dirname(dirname(dirname(dirname(__file__)))))) . '/config.php');

$context = context_system::instance();
$PAGE->set_context($context);

require_login();
require_capability('contentmarketplace/goone:curatecontent', $context);

$data = new stdClass();

if (!confirm_sesskey()) {
    $data->result = false;
    $data->message = get_string('invalidsesskey', 'error');
    echo json_encode($data);
    exit;
}

$sync = new \contentmarketplace_goone\sync_action\sync_learning_objects();

// On AJAX call, sync only changes in custom collection (library).
// There may be thousands of unsynced LOs in other collections, let scheduled task deal with those.
$sync->set_sync_collections(['custom']);

try {
    $sync->invoke();
    $data->result = true;
    $data->message = get_string('content_synced_success', 'contentmarketplace_goone');
} catch (Throwable $e) {
    $data->result = false;
    $data->message = $e->getMessage();
}

echo json_encode($data);
