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

require('../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/mod/scorm/locallib.php');

use contentmarketplace_goone\form\create_course_controller;
use contentmarketplace_goone\form\create_course_form;
use contentmarketplace_goone\model\learning_object;
use contentmarketplace_goone\helper as helper;

$selection = required_param_array('selection', PARAM_ALPHANUMEXT);
$create = optional_param('create', create_course_form::CREATE_COURSE_MULTI_ACTIVITY, PARAM_INT);
$mode = optional_param('mode', \totara_contentmarketplace\explorer::MODE_CREATE_COURSE, PARAM_ALPHAEXT);

$category = optional_param('category', 0, PARAM_INT);
if (!$category) {
    $category = isset($selection[0]) ? optional_param('category_' . $selection[0], 0, PARAM_INT) : 0;
}

if ($category === 0) {
    $context = context_system::instance();
    $pageparams = [];
} else {
    $context = context_coursecat::instance($category);
    $pageparams = ['category' => $category];
}
$PAGE->set_context($context);
$PAGE->set_url(new \moodle_url('/totara/contentmarketplace/contentmarketplaces/goone/coursecreate.php', $pageparams));

require_login();
require_capability('totara/contentmarketplace:add', $context);

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

// Manual course creation is opt in. When it is off the course creation flow belongs to the
// Go1 content curation page, so anything landing here directly is sent back there.
if (!helper::is_manual_course_creation_enabled()) {
    redirect(new \moodle_url('/totara/contentmarketplace/contentmarketplaces/goone/curate.php'));
}

$PAGE->set_title(get_string('addcourse', 'contentmarketplace_goone'));
$PAGE->set_heading(get_string('addcourse', 'contentmarketplace_goone'));
$PAGE->set_pagelayout('noblocks');

list($currentdata, $params) = create_course_controller::get_current_data_and_params($selection, $create, $category, $mode);
$form = new create_course_form($currentdata, $params);

if ($form->is_cancelled()) {
    $url = new moodle_url('/totara/contentmarketplace/explorer.php', ['marketplace' => 'goone', 'mode' => $mode]);
    if (!empty($category)) {
        $url->param('category', $category);
    }
    redirect($url);
} else if ($data = $form->get_data()) {
    require_once($CFG->dirroot.'/course/modlib.php');

    $selection = $data->selection;
    helper::check_availability_of_learning_objects($selection);

    if (count($selection) == 1 || $data->create == create_course_form::CREATE_COURSE_MULTI_ACTIVITY) {
        $coursedata = new \stdClass();
        $suffix = count($selection) == 1 ? '_' .$selection[0] : '';
        $coursedata->category = $data->{'category' . $suffix};
        $coursedata->fullname = $data->{'fullname' . $suffix};
        $coursedata->shortname = $data->{'shortname' . $suffix};
        $coursedata->visible = true;
        $coursedata->audiencevisible = (int) get_config('moodlecourse', 'visiblelearning');

        $coursedata->enablecompletion = COMPLETION_ENABLED;
        $coursedata->completionstartonenrol = 1;

        if ($data->create == create_course_form::CREATE_COURSE_SINGLE_ACTIVITY) {
            $coursedata->format = 'singleactivity';
            $coursedata->activitytype = 'scorm';
            $section = 0;
        } else {
            $section = 1;
        }

        $container = \container_course\course_helper::create_course($coursedata);
        $course = course_get_format($container->id)->get_course();
        helper::enrol_course_creator($course);

        $api = new \contentmarketplace_goone\api();

        // Add course image using the first Learning Object.
        $firstlo = $api->get_learning_object(reset($selection));
        if (!empty($firstlo->core->image->value)) {
            helper::add_course_image_from_url($course->id, $firstlo->core->image->value);
        }

        foreach ($selection as $id) {
            $learningobject = $api->get_learning_object($id);
            $title = clean_param($learningobject->core->title, !empty($CFG->formatstringstriptags) ? PARAM_TEXT : PARAM_CLEANHTML);
            $descriptionhtml = clean_text((string) ($learningobject->core->description ?? ''));

            $learning_object_model = learning_object::load_by_external_id($id, $api);
            helper::add_scorm_module($course, $title, $id, $descriptionhtml, $learningobject->playback_behavior->assessable, $section, $learning_object_model, $api);
        }

        \core\notification::success(get_string('coursecreated', 'contentmarketplace_goone'));

        $coursecontext = context_course::instance($course->id, MUST_EXIST);
        $isviewing = is_viewing($coursecontext, NULL, 'moodle/role:assign');
        $isenrolled = is_enrolled($coursecontext, NULL, 'moodle/role:assign');
        if ($isviewing || $isenrolled) {
            $url = new \moodle_url('/course/view.php', ['id' => $course->id]);
        } else {
            $url = new \moodle_url('/course/index.php', ['categoryid' => $coursedata->category]);
        }
        redirect($url);

    } else {
        $api = new \contentmarketplace_goone\api();
        $courselinkshtml = [];
        foreach ($selection as $id) {
            $coursedata = new \stdClass();
            $coursedata->category = $data->{'category_' . $id};
            $coursedata->fullname = $data->{'fullname_' . $id};
            $coursedata->shortname = $data->{'shortname_' . $id};
            $coursedata->visible = true;
            $coursedata->audiencevisible = (int) get_config('moodlecourse', 'visiblelearning');

            $coursedata->enablecompletion = COMPLETION_ENABLED;
            $coursedata->completionstartonenrol = 1;

            $coursedata->format = 'singleactivity';
            $coursedata->activitytype = 'scorm';
            $container = \container_course\course_helper::create_course($coursedata);
            $course = course_get_format($container->id)->get_course();
            helper::enrol_course_creator($course);

            $learningobject = $api->get_learning_object($id);
            $title = clean_param($learningobject->core->title, !empty($CFG->formatstringstriptags) ? PARAM_TEXT : PARAM_CLEANHTML);
            $descriptionhtml = clean_text((string) ($learningobject->core->description ?? ''));

            // Add course image using the Learning Object.
            if (!empty($learningobject->core->image->value)) {
                helper::add_course_image_from_url($course->id, $learningobject->core->image->value);
            }

            $learning_object_model = learning_object::load_by_external_id($id, $api);
            helper::add_scorm_module($course, $title, $id, $descriptionhtml, $learningobject->playback_behavior->assessable, 0, $learning_object_model, $api);

            $courselinkshtml[] = s($coursedata->fullname);
        }

        $messagehtml = html_writer::tag('p', get_string('coursecreatedx', 'contentmarketplace_goone', count($selection)));
        $messagehtml .= html_writer::alist($courselinkshtml);
        \core\notification::success($messagehtml);
        $category = $data->{'category_' . $selection[0]};
        redirect(new \moodle_url('/course/index.php', ['categoryid' => $category]));
    }
}

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('addcourse', 'contentmarketplace_goone'));

echo $form->render();

echo $OUTPUT->footer();
