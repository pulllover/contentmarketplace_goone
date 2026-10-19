<?php
/**
 * This file is part of Totara Learn
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
 * @package    contentmarketplace_goone
 * @copyright  2026 onwards Androgogic Pty Ltd
 * @author     Kirill Astashov <kirill.astashov@androgogic.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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

// The button syncs new content, so course creation must be on and configured.
if (!\contentmarketplace_goone\helper::is_create_courses_on_and_configured()) {
    $data->result = false;
    $data->message = get_string('error:sync_not_configured', 'contentmarketplace_goone');
    echo json_encode($data);
    exit;
}

// The button syncs only the custom collection, so it must be selected for sync.
$sync_collections = explode(',', (string) get_config('contentmarketplace_goone', 'sync_collections'));
if (!in_array('custom', $sync_collections, true)) {
    $data->result = false;
    $data->message = get_string('error:sync_custom_not_selected', 'contentmarketplace_goone');
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
    $created = $sync->get_created_courses_total();
    if ($created > 0) {
        $data->message = get_string('content_synced_success_courses', 'contentmarketplace_goone', $created);
    } else {
        $data->message = get_string('content_synced_success_nocourses', 'contentmarketplace_goone');
    }
} catch (Throwable $e) {
    $data->result = false;
    $is_sync_in_progress = $e instanceof moodle_exception && $e->errorcode === 'error:sync_in_progress';
    if ($is_sync_in_progress || get_config('contentmarketplace_goone', 'debug_requests')) {
        $data->message = $e->getMessage();
    } else {
        $data->message = get_string('error:sync_failed_general', 'contentmarketplace_goone');
    }
}

echo json_encode($data);
