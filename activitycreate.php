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

require('../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/mod/scorm/locallib.php');

use core\orm\query\builder;
use contentmarketplace_goone\model\learning_object;
use contentmarketplace_goone\helper;

// Learning object id, either via array, or via single value.
$selection_arr = optional_param_array('selection', [], PARAM_ALPHANUMEXT);
$id = optional_param('id', 0, PARAM_INT);

$mode = required_param('mode', PARAM_ALPHAEXT);  // Must be 'add-activity'.
$section = required_param('section', PARAM_INT); // Course section id.

$params = [];
$params['mode'] = $mode;
$params['section'] = $section;
$params['marketplace'] = 'goone';
// The explorer controller expects the section under 'section_id' (this page and the
// explorer JS use 'section'), so the back-to-explorer URL carries it under both names.
$failurl = new \moodle_url('/totara/contentmarketplace/explorer.php', $params + ['section_id' => $section]);

if ($mode != \totara_contentmarketplace\explorer::MODE_ADD_ACTIVITY) {
    \core\notification::error(get_string('error_adding_activity_invalid_mode', 'contentmarketplace_goone'));
    redirect($failurl);
}

if (empty($selection_arr) && empty($id)) {
    redirect($failurl);
}

if (!empty($selection_arr) && !empty($id)) {
    redirect($failurl);
}

if (empty($id)) {
    $id = $selection_arr[0];
}

$pageparams = $params;
$pageparams['section'] = $section;
$pageparams['id'] = $id;
$PAGE->set_url(new \moodle_url('/totara/contentmarketplace/contentmarketplaces/goone/activitycreate.php', $pageparams));

require_login();

$db = builder::get_db();

$course_id = $db->get_field('course_sections', 'course', ['id' => $section], MUST_EXIST);
$course = $db->get_record('course', ['id' => $course_id], '*', MUST_EXIST);
$context = context_course::instance($course_id);
$PAGE->set_context($context);

require_capability('mod/scorm:addinstance', $context);

// Check marketplaces are enabled.
\totara_contentmarketplace\local::require_contentmarketplace();

// Check Go1 marketplace plugin is enabled.
/** @var \totara_contentmarketplace\plugininfo\contentmarketplace $plugin */
$plugin = \core_plugin_manager::instance()->get_plugin_info("contentmarketplace_goone");
if ($plugin === null) {
    throw new coding_exception('The contentmarketplace_goone plugin is not yet installed.');
}
if (!$plugin->is_enabled()) {
    throw new \moodle_exception('error:disabledmarketplace', 'totara_contentmarketplace', '', $plugin->displayname);
}

$api = new \contentmarketplace_goone\api();
$learningobject = $api->get_learning_object($id);
$title = clean_param($learningobject->core->title, !empty($CFG->formatstringstriptags) ? PARAM_TEXT : PARAM_CLEANHTML);

// The explorer sends the user here via a plain GET link which cannot carry a sesskey,
// so the activity is only created once the request has been confirmed with a sesskey.
// Any request without one (including a forged cross-site link) gets a confirmation page instead.
// Note: confirm_sesskey() throws when the request carries no sesskey at all, so the
// parameter is read with optional_param() first and only validated when present.
$sesskey = optional_param('sesskey', '', PARAM_RAW);
if ($sesskey === '' || !confirm_sesskey($sesskey)) {
    $continueurl = new \moodle_url(
        '/totara/contentmarketplace/contentmarketplaces/goone/activitycreate.php',
        $pageparams + ['sesskey' => sesskey()]
    );
    $continue = new single_button($continueurl, get_string('addactivity_confirm_button', 'contentmarketplace_goone'), 'post');

    $a = new stdClass();
    $a->activity = $title;
    $a->course = format_string($course->fullname);

    $PAGE->set_title(get_string('addactivity_confirm_title', 'contentmarketplace_goone'));
    $PAGE->set_heading(format_string($course->fullname));

    echo $OUTPUT->header();
    echo $OUTPUT->confirm(get_string('addactivity_confirm', 'contentmarketplace_goone', $a), $continue, $failurl);
    echo $OUTPUT->footer();
    exit;
}
require_sesskey();

$descriptionhtml = clean_text($learningobject->core->description);
$learning_object_model = learning_object::load_by_external_id($id, $api);
$sectionno = $db->get_field('course_sections', 'section', ['id' => $section], MUST_EXIST);
helper::add_scorm_module($course, $title, $id, $descriptionhtml, $learningobject->playback_behavior->assessable, $sectionno, $learning_object_model, $api);

\core\notification::success(get_string('activitycreatedx', 'contentmarketplace_goone', $title));

redirect(new \moodle_url('/course/view.php', ['id' => $course_id]));
