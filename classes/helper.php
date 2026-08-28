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

namespace contentmarketplace_goone;

defined('MOODLE_INTERNAL') || die();

use totara_contentmarketplace\model\course_module_source;
use core_container\module\module;
use core_container\factory;
use container_course\module\course_module;
use contentmarketplace_goone\model\learning_object;
use contentmarketplace_goone\api;
use core\orm\query\builder;

class helper {

    const CREATE_COURSE_SINGLE = 'single';
    const CREATE_COURSE_MULTI = 'multi';

    /** Value of the create_course_manual setting: courses are created by hand from content picked in the Go1 explorer. */
    const CREATE_COURSE_MANUAL_ENABLED = 1;

    /** Value of the create_course_manual setting: the course creation flow redirects to the Go1 content curation page. */
    const CREATE_COURSE_MANUAL_DISABLED = 0;

    /** @var config_storage  */
    public static $config;

    /**
     * Whether courses can be created manually from content selected in the Go1 content explorer.
     * When disabled the course creation flow redirects to the Go1 content curation page.
     *
     * @return bool
     */
    public static function is_manual_course_creation_enabled(): bool {
        $value = get_config('contentmarketplace_goone', 'create_course_manual');
        return (int) $value === self::CREATE_COURSE_MANUAL_ENABLED;
    }

    /**
     * Check this GO1 account can access the learning object.
     * (Used to guard against unexpected learning objects being used to create content.)
     *
     * @param array $ids Go1 learning object ids
     * @throws moodle_exception
     * @return bool always true
     */
    public static function check_availability_of_learning_objects($ids) {
        $api = new api();
        foreach ($ids as $id) {
            // Throws API exception if learning object $id does not exist or is not available.
            $api->get_learning_object($id);
        }
        return true;
    }


    /**
     * @param object $course
     * @param string $name
     * @param int $itemid
     * @param string $descriptionhtml
     * @param int $assessable
     * @param int $section
     * @param learning_object|null $learning_object_model
     * @param api|null $api API instance, created internally when not supplied
     * @return module
     */
    static function add_scorm_module($course, $name, $itemid, $descriptionhtml, $assessable, $section = 0, $learning_object_model = null, ?api $api = null) {
        global $CFG;

        require_once($CFG->dirroot.'/mod/scorm/lib.php');
        require_once($CFG->dirroot.'/lib/completionlib.php');

        $db = builder::get_db();

        $moduleinfo = new \stdClass();
        $moduleinfo->name = $name;
        $moduleinfo->modulename = 'scorm';
        $moduleinfo->module = $db->get_field('modules', 'id', ['name' => 'scorm'], MUST_EXIST);
        $moduleinfo->cmidnumber = "";

        $moduleinfo->visible = 1;
        $moduleinfo->section = $section;

        $moduleinfo->intro = $descriptionhtml;
        $moduleinfo->introformat = FORMAT_HTML;
        $moduleinfo->showdescription = 0;

        $moduleinfo->popup = 1;
        $moduleinfo->width = 100;
        $moduleinfo->height = 100;
        $moduleinfo->skipview = 2;
        $moduleinfo->hidebrowse = 1;
        $moduleinfo->displaycoursestructure = 0;
        $moduleinfo->hidetoc = 3;
        $moduleinfo->nav = 1;
        $moduleinfo->displayactivityname = false;
        $moduleinfo->displayattemptstatus = 1;
        $moduleinfo->forcenewattempt = 1;
        $moduleinfo->maxattempt = 0;

        $moduleinfo->scormtype = SCORM_TYPE_LOCAL;

        $api = $api ?? new \contentmarketplace_goone\api();
        $scormzip = $api->get_scorm($itemid);
        $packagefile = self::storedfile($name, $itemid, $scormzip);
        $moduleinfo->packagefile = $packagefile->get_itemid();
        scorm_add_trusted_package_contenthash($packagefile->get_contenthash());

        if ($assessable) {
            $moduleinfo->grademethod = GRADEHIGHEST;
            $moduleinfo->maxgrade = 100;
            $moduleinfo->completionstatusrequired = self::get_completionstatusrequired('passed');
        } else {
            $moduleinfo->grademethod = GRADESCOES;
            $moduleinfo->completionstatusrequired = self::get_completionstatusrequired('completed');
        }
        $moduleinfo->completion = COMPLETION_TRACKING_AUTOMATIC;
        $moduleinfo->completionscoredisabled = 1;

        $container = factory::from_record($course);
        /** @var course_module $module */
        $module = $container->add_module($moduleinfo);

        if ($learning_object_model) {
            course_module_source::create($module, $learning_object_model);
        }

        return $module;
    }


    /**
     * Ensure that the given shortname is not used in any course, make it unique by adding a number.
     *
     * @param string $shortname
     *
     * @return string
     */
    public static function deduplicate_course_shortname(string $shortname): string {
        $db = builder::get_db();
        $checked = false;
        $shortname_new = $shortname;
        $i = 1;
        while (!$checked) {
            if ($db->record_exists('course', ['shortname' => $shortname_new])) {
                $i++;
                $shortname_new = $shortname . "_{$i}";
            } else {
                $checked = true;
            }
        }
        return $shortname_new;
    }

    /**
     * @param string $name
     * @param int $packageid
     * @param string $scorm File content
     *
     * @return \stored_file
     */
    public static function storedfile(string $name, int $packageid, string $scorm) :\stored_file {
        global $USER;

        $fs = get_file_storage();

        $itemid = file_get_unused_draft_itemid();
        $usercontext = \context_user::instance($USER->id);
        $now = time();

        /** @var totara_contentmarketplace\plugininfo\contentmarketplace $plugin */
        $plugin = \core_plugin_manager::instance()->get_plugin_info("contentmarketplace_goone");
        $marketplace = $plugin->contentmarketplace();

        // Prepare file record.
        $record = new \stdClass();
        $record->filepath = "/";
        $record->filename = clean_filename($name . ".zip");
        $record->component = 'user';
        $record->filearea = 'draft';
        $record->itemid = $itemid;
        $record->license = "allrightsreserved";
        $record->author = "Content marketplace";
        $record->contextid = $usercontext->id;
        $record->timecreated = $now;
        $record->timemodified = $now;
        $record->userid = $USER->id;
        $record->sortorder = 0;
        $record->source = $marketplace->get_source($packageid);

        return $fs->create_file_from_string($record, $scorm);
    }


    /**
     * @param string $option
     *
     * @return int key of the option
     */
    public static function get_completionstatusrequired(string $option) :int {
        foreach (scorm_status_options() as $key => $value) {
            if ($value == $option) {
                return $key;
            }
        }
        throw new \coding_exception('Unknown completionstatus option: ' . $option);
    }


    public static function enrol_course_creator($course) {
        global $CFG, $USER;
        $context = \context_course::instance($course->id, MUST_EXIST);
        if (!empty($CFG->creatornewroleid) and !is_viewing($context, NULL, 'moodle/role:assign') and !is_enrolled($context, NULL, 'moodle/role:assign')) {
            // Deal with course creators - enrol them internally with default role.
            enrol_try_internal_enrol($course->id, $USER->id, $CFG->creatornewroleid);
        }
    }


    /**
     * Does necessary actions on the retired Learning Object as per the plugin configuration.
     *
     * @param \stdClass $hit Learning Object as returned by the GO1 API
     *
     * @return bool true on success
     */
    public static function process_retired_learning_object(\stdClass $hit): bool {
        if (!self::is_retired_content_on_and_configured()) {
            return false;
        }

        if (!isset(self::$config)) {
            self::$config = new config_db_storage();
        }
        $db = builder::get_db();

        $retired_content_retired_actions_config = self::$config->get('retired_content_retired_actions');
        $retired_content_retired_actions = !empty($retired_content_retired_actions_config) ? explode(',', $retired_content_retired_actions_config) : [];
        $retired_content_removed_actions_config = self::$config->get('retired_content_removed_actions');
        $retired_content_removed_actions = !empty($retired_content_removed_actions_config) ? explode(',', $retired_content_removed_actions_config) : [];
        $retired_content_location = self::$config->get('retired_content_location');
        $coursecat = self::$config->get('course_category');

        $new_retired_time = strtotime($hit->lifecycle->pending_state->retired_time);
        if (!empty($hit->lifecycle->pending_state->removed_time)) {
            $new_removed_time = strtotime($hit->lifecycle->pending_state->removed_time);
        } else {
            // Should not happen, this is just a safeguard.
            $new_removed_time = time() + 365 * 86400;
        }
        if ($new_retired_time > time()) {
            // Not retired yet, nothing to do.
            return true;
        }

        // Get LO record from DB.
        $lo = $db->get_record('marketplace_goone_learning_object', ['external_id' => $hit->lo_id]);
        if (!$lo) {
            // LO is not in use.
            return true;
        }
        $retired_actioned = (!empty($lo->retired_actioned)) ? explode(',', $lo->retired_actioned) : [];
        $removed_actioned = (!empty($lo->removed_actioned)) ? explode(',', $lo->removed_actioned) : [];
        // Check if there's anything to do here.
        $todo_retired = array_diff($retired_content_retired_actions, $retired_actioned);
        $todo_removed = ($new_removed_time <= time()) ? array_diff($retired_content_removed_actions, $removed_actioned) : [];
        if (empty($todo_retired) && empty($todo_removed)) {
            // Nothing to do.
            return true;
        }
        $imageurl = !empty($hit->core->image->value) ? $hit->core->image->value : '';

        // Find courses that use the supplied LO.
        $sql = "SELECT tccms.id as tccms_id, c.id as course_id, mglo.id as mglo_id
            FROM {totara_contentmarketplace_course_module_source} tccms
            INNER JOIN {marketplace_goone_learning_object} mglo ON tccms.learning_object_id = mglo.id
            INNER JOIN {course_modules} cm ON tccms.cm_id = cm.id
            INNER JOIN {course} c ON cm.course = c.id
            WHERE 1=1
                AND mglo.external_id = :loid
                AND tccms.marketplace_component = 'contentmarketplace_goone'
            ";
        $params = [
            'loid' => $hit->lo_id,
        ];
        if ($retired_content_location == 'synccatonly') {
            $sql .= " AND c.category = :category ";
            $params['category'] = $coursecat;
        }
        $rows = $db->get_records_sql($sql, $params);
        if (empty($rows)) {
            // No courses.
            return true;
        }

        $processed_courses = [];

        foreach ($rows as $row) {
            if (in_array($row->course_id, $processed_courses)) {
                continue;
            }
            $course = $db->get_record('course', ['id' => $row->course_id], '*', MUST_EXIST);
            $course_updated = false;
            $event_triggered = false;

            // Execute pending retired LO actions.
            foreach ($todo_retired as $action) {
                $result = self::process_retired_course_action($course, $action, $new_removed_time, $imageurl);
                $course_updated = $course_updated || $result['course_updated'];
                $event_triggered = $event_triggered || $result['event_triggered'];
            }

            // Additionally, if LO is removed, execute remove actions.
            if ($new_removed_time < time()) {
                foreach ($todo_removed as $action) {
                    // Check if action has been executed as part of $todo_retired.
                    if (!in_array($action, $todo_retired)) {
                        $result = self::process_retired_course_action($course, $action, $new_removed_time, $imageurl);
                        $course_updated = $course_updated || $result['course_updated'];
                        $event_triggered = $event_triggered || $result['event_triggered'];
                    }
                }
            }

            if ($course_updated) {
                if (!$event_triggered) {
                    // Trigger course_updated event.
                    /** @var course_updated $event */
                    $event = \core\event\course_updated::create(
                        [
                            'objectid' => $row->course_id,
                            'context' => \context_course::instance($row->course_id),
                            'other' => [
                                'shortname' => $course->shortname,
                                'fullname' => $course->fullname
                            ]
                        ]
                    );
                    $event->set_legacy_logdata([$row->course_id, 'course', 'update', "edit.php?id={$row->course_id}", $row->course_id]);
                    $event->trigger();
                }
                \core_container\cache_helper::rebuild_container_cache($row->course_id, true);
                $processed_courses[] = $row->course_id;
            }
        }

        // Work out the new lists of actions already executed on this LO.
        $new_retired_actioned = array_merge($todo_retired, $retired_actioned);
        $new_retired_actioned = array_unique($new_retired_actioned);
        sort($new_retired_actioned);
        $new_removed_actioned = array_merge($todo_removed, $removed_actioned);
        $new_removed_actioned = array_unique($new_removed_actioned);
        sort($new_removed_actioned);

        // Update LO.
        $r = new \stdClass();
        $r->id = $lo->id;
        $r->retired_time = $new_retired_time;
        $r->retired_actioned = implode(',', $new_retired_actioned);
        $r->removed_time = $new_removed_time;
        $r->removed_actioned = implode(',', $new_removed_actioned);
        $db->update_record('marketplace_goone_learning_object', $r);

        return true;
    }


    /**
     * Execute a single action on the Totara course when Go1 learning object is retired or removed.
     *
     * @param \stdClass $course as DB record
     * @param string $action
     * @param int $removed_time UNIX timestamp of content removal
     * @param string $imageurl to use as background when adding banner to course image
     *
     * @return array [
     *                  'course_updated' => bool, if course has been updated by the function
     *                  'event_triggered' => bool, if course_updated event has been triggered somewhere within the function
     *                ]
     */
    public static function process_retired_course_action(\stdClass $course, string $action, int $removed_time, string $imageurl): array {
        global $CFG;

        if (!isset(self::$config)) {
            self::$config = new config_db_storage();
        }
        $db = builder::get_db();

        $dateformat = self::$config->get('retired_content_dateformat');
        if (empty($dateformat)) {
            $dateformat = 'j F Y';
        }
        $removed_date_str = date($dateformat, $removed_time);

        $course_updated = false;
        $event_triggered = false;

        switch ($action) {
            // Add "Leaving on..." banner to course image.
            case 'courseimage':
                $result = self::add_message_to_courseimage($course->id, $imageurl, $removed_date_str);
                if ($result) {
                    $course_updated = true;
                    $event_triggered = false;
                }
                break;

            // Add notification to course description.
            case 'description':
                $message = get_string('retired_content_template_coursesummary', 'contentmarketplace_goone', $removed_date_str);
                $new_course = new \stdClass;
                $new_course->id = $course->id;
                $new_course->summary = $message . $course->summary;
                $db->update_record('course', $new_course);
                $course_updated = true;
                $event_triggered = false;
                break;

            // Move course to another category.
            case 'move':
                $categoryid = self::$config->get('retired_content_actions_move_category');
                // Sanity check to ensure the course cat has not been deleted and is properly configured.
                $cat = $db->get_record('course_categories', ['id' => $categoryid]);
                if (!$cat) {
                    $course_updated = false;
                } else {
                    require_once("{$CFG->dirroot}/course/lib.php");
                    $course_updated = move_courses([$course->id], $categoryid);
                    if ($course_updated) {
                        $event_triggered = true;
                    }
                }
                break;

            // Hide course (change visibility).
            case 'hide':
                $new_course = new \stdClass;
                $new_course->id = $course->id;
                $new_course->visible = '0';
                $new_course->visibleold = $course->visible;
                if (!empty($CFG->audiencevisibility)) {
                    $new_course->audiencevisible = COHORT_VISIBLE_NOUSERS;
                }
                $db->update_record('course', $new_course);
                $course_updated = true;
                $event_triggered = false;
                break;

            default:
                break;
        }

        $result = [
            'course_updated' => $course_updated,
            'event_triggered' => $event_triggered
        ];
        return $result;
    }


    public static function add_message_to_courseimage($courseid, $imageurl, $removed_date_str): bool {

        if (!isset(self::$config)) {
            self::$config = new config_db_storage();
        }

        if (($content_type = self::validate_image_url($imageurl)) === false) {
            return false;
        }

        switch ($content_type) {
            // SVG - download as-is
            case 'image/svg':
            case 'image/svg+xml':
                $str = download_file_content($imageurl);
                break;

            // Everything else (raster formats) - download and convert to SVG
            default:
                $str = download_file_content($imageurl);
                $image = imagecreatefromstring($str);
                if (empty($image)) {
                    return false;
                }

                $width = 640;

                // Resize original image to the standard width (640px) for the catalogue
                $image = imagescale($image, $width);

                $height = imagesy($image);

                // Capture image output as PNG
                ob_start();
                imagepng($image);
                $image_str = ob_get_contents();
                ob_end_clean();

                // Wrap PNG image in SVG container
                $str = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>
                    <!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">
                    <svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg"
                        xmlns:xlink="http://www.w3.org/1999/xlink"
                        x="0px" y="0px" width="'.$width.'px" height="'.$height.'px" viewBox="0 0 '.$width.' '.$height.'"
                        enable-background="new 0 0 '.$width.' '.$height.'" xml:space="preserve">
                        <image id="image0" width="'.$width.'" height="'.$height.'" x="0" y="0"
                        xlink:href="data:image/png;base64,'.base64_encode($image_str).'" />
                    </svg>
                    ';
                break;
        }

        $updatedSvg = self::add_banner_to_svg($str, $removed_date_str);
        if ($updatedSvg === false) {
            return false;
        }

        // Save image to course
        $fs = get_file_storage();
        $context = \context_course::instance($courseid);

        $fs->delete_area_files($context->id, 'course', 'images');
        $filerecord = [
            'contextid' => $context->id,
            'component' => 'course',
            'filearea' => 'images',
            'filename' => basename(parse_url($imageurl, PHP_URL_PATH)) . '.svg',
            'mimetype' => 'image/svg+xml',
            'itemid' => 0,
            'filepath' => '/'
        ];
        $imagefile = $fs->create_file_from_string($filerecord, $updatedSvg);

        return true;
    }


    /**
     * Add the "Leaving on ..." banner to the given SVG image.
     *
     * @param string $str SVG markup
     * @param string $removed_date_str Formatted removal date used in the banner text
     *
     * @return string|false Updated SVG markup, or false when the image cannot be processed
     *                      (unparseable markup or unknown canvas dimensions)
     */
    public static function add_banner_to_svg(string $str, string $removed_date_str): string|false {
        $previous = libxml_use_internal_errors(true);
        $svg = simplexml_load_string($str);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if ($svg === false) {
            return false;
        }

        $width = null;
        $height = null;
        $attributes = $svg->attributes();

        // Try viewBox first
        if (isset($attributes['viewBox'])) {
            $viewbox_parts = preg_split('/\s+/', trim((string) $attributes['viewBox']));
            if (count($viewbox_parts) === 4) {
                $width = (float)$viewbox_parts[2];
                $height = (float)$viewbox_parts[3];
            }
        }

        // Fallback: width and height attributes
        if (empty($width) && isset($attributes['width']) && isset($attributes['height'])) {
            $width = (float) preg_replace('/[^\d.]/', '', (string)$attributes['width']);
            $height = (float) preg_replace('/[^\d.]/', '', (string)$attributes['height']);
        }

        // Without known canvas dimensions the banner cannot be positioned.
        if (empty($width) || empty($height) || $width <= 0 || $height <= 0) {
            return false;
        }

        // Definitions
        $fontsize_ratio = 0.06; // Relative to image width
        $font = dirname(dirname(__file__)) . '/lib/fonts/Verdana.ttf';
        $fontSize = $width * $fontsize_ratio;
        $padding = $fontSize / 3;

        $text1 = get_string('retired_content_template_courseimage1', 'contentmarketplace_goone', $removed_date_str);
        $text2 = get_string('retired_content_template_courseimage2', 'contentmarketplace_goone', $removed_date_str);

        // Get text dimensions
        $bbox1 = imagettfbbox($fontSize, 0, $font, $text1);
        $bbox2 = imagettfbbox($fontSize, 0, $font, $text2);

        $textWidth = max($bbox1[2] - $bbox1[0], $bbox2[2] - $bbox2[0]);
        $textHeight = ($bbox1[1] - $bbox1[7]) + ($bbox2[1] - $bbox2[7]) + $padding;

        // Define white rectangle dimensions
        $rectWidth = $textWidth + $padding;
        $rectHeight = $textHeight + $padding * 2;

        // Calculate rectangle position
        $rectX = ($width - $rectWidth) / 2;
        $rectY = ($height - $rectHeight) / 2;

        // Calculate text positions
        $textX1 = $textX2 = $width/2; // Will centre align from here
        $textY1 = $rectY + $padding + ($bbox1[1] - $bbox1[7]);
        $textY2 = $textY1 + ($bbox2[1] - $bbox2[7]);

        // Add <style> with CDATA
        $styleContent = "
            text.go1notice {
                font-size: {$fontSize}px;
                font-family: Verdana;
                fill: red;
                text-anchor: middle;
            }
            rect.go1notice {
                fill: white;
            }
            ";
        $style = $svg->addChild('style');
        $styleNode = dom_import_simplexml($style);
        $styleNode->appendChild($styleNode->ownerDocument->createCDATASection($styleContent));

        // Add rectangle
        $rect = $svg->addChild('rect');
        $rect->addAttribute('class', 'go1notice');
        $rect->addAttribute('x', $rectX);
        $rect->addAttribute('y', $rectY);
        $rect->addAttribute('height', $rectHeight);
        $rect->addAttribute('width', $rectWidth);

        // Add text1 and text2
        $text = $svg->addChild('text', $text1);
        $text->addAttribute('class', 'go1notice');
        $text->addAttribute('x', $textX1);
        $text->addAttribute('y', $textY1);

        $text = $svg->addChild('text', $text2);
        $text->addAttribute('class', 'go1notice');
        $text->addAttribute('x', $textX2);
        $text->addAttribute('y', $textY2);

        return $svg->asXML();
    }


    /**
     * Validate the HTTP response headers of an image URL.
     *
     * @param array $headers Headers as returned by get_headers($url, true)
     *
     * @return string|false Lowercased media type on success, false when the response is not
     *                      a 200, not an allowed image type, or larger than 20 MB
     */
    public static function validate_image_headers(array $headers): string|false {
        $allowed_mime_types = [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
            'image/bmp',
            'image/svg',
            'image/svg+xml'
        ];

        if (!isset($headers[0]) || strpos($headers[0], '200') === false) {
            return false;
        }

        // Header names are case-insensitive, normalise them before reading.
        $headers = array_change_key_case($headers, CASE_LOWER);

        if (!isset($headers['content-type'])) {
            return false;
        }
        $content_type = is_array($headers['content-type']) ? reset($headers['content-type']) : $headers['content-type'];
        // Parameters such as "; charset=utf-8" are not part of the media type.
        $content_type = strtolower(trim(explode(';', $content_type)[0]));
        if (!in_array($content_type, $allowed_mime_types)) {
            return false;
        }

        // Check content length (max 20 MB)
        if (isset($headers['content-length'])) {
            $content_length = is_array($headers['content-length'])
                ? end($headers['content-length'])
                : $headers['content-length'];
            if ((int) $content_length > 20 * 1024 * 1024) {
                return false;
            }
        }

        return $content_type;
    }


    /**
     * Validate that the provided URL contains a valid and reasonably sized image.
     *
     * @param string $url
     *
     * @return bool|string - false on fail to validate, content-type on success
     */
    public static function validate_image_url(string $url): bool|string {
        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Use get_headers to check response and content type
        $headers = @get_headers($url, true);
        if ($headers === false) {
            return false;
        }
        $content_type = self::validate_image_headers($headers);
        if ($content_type === false) {
            return false;
        }

        // Retrieve the image data
        $image_data = download_file_content($url);
        if ($image_data === false) {
            return false;
        }

        // Perform a virus scan (if antivirus is configured).
        // The file lives in a unique per-request directory, so parallel syncs cannot
        // collide and core cleans it up automatically even when the scanner throws.
        $filepath = make_request_directory() . '/go1image';
        file_put_contents($filepath, $image_data);
        try {
            \core\antivirus\manager::scan_file($filepath, 'go1image', true);
        } catch (\core\antivirus\scanner_exception $e) {
            return false;
        }

        // SVG is a special case
        if ($content_type == 'image/svg+xml' || $content_type == 'image/svg') {
            $xml = simplexml_load_string($image_data);
            if ($xml === false) {
                return false;
            }
            return $content_type;
        }

        // Check image size - valid and at least 20 px each dimension
        $image_info = @getimagesizefromstring($image_data);
        if ($image_info === false) {
            return false;
        }

        $width = $image_info[0];
        $height = $image_info[1];

        if ($width < 20 || $height < 20) {
            return false;
        }

        return $content_type;
    }


    /**
     * @param \stdClass $hit
     * @param $api
     *
     * @return array
     */
    public static function process_sync_learning_object(\stdClass $hit, $api): array {
        global $CFG;

        if (!isset(self::$config)) {
            self::$config = new config_db_storage();
        }
        $create_courses = self::$config->get('create_courses');
        $coursecat = self::$config->get('course_category');
        $course_type = self::$config->get('course_type');
        if (empty($create_courses) || empty($coursecat) || empty($course_type)) {
            return ['success' => false, 'created' => false, 'message' => 'Plugin sync not configured properly'];
        }

        $db = builder::get_db();

        // Ensure that SCORM/course with this learning object has not been created yet in the sync category.
        $sql = "SELECT c.id
            FROM {totara_contentmarketplace_course_module_source} tccms
            INNER JOIN {marketplace_goone_learning_object} mglo ON tccms.learning_object_id = mglo.id
            INNER JOIN {course_modules} cm ON tccms.cm_id = cm.id
            INNER JOIN {course} c ON cm.course = c.id
            WHERE 1=1
                AND mglo.external_id = :loid
                AND tccms.marketplace_component = 'contentmarketplace_goone'
                AND c.category = :category
            ";
        $params = [
            'loid' => $hit->lo_id,
            'category' => $coursecat
        ];
        $rows = $db->get_records_sql($sql, $params);
        if (!empty($rows)) {
            // When implementing course/activity update (if required), insert your code here.
            // As only course creation has been implemented, bailing out for now.
            return ['success' => true, 'created' => false, 'message' => 'SCORM activity already exists in the sync category'];
        }

        // Create course as required.
        $result = self::create_course_from_learning_object($hit, $api);
        if (!$result) {
            return ['success' => false, 'created' => false, 'message' => 'Course not created'];
        }

        return ['success' => true, 'created' => true, 'message' => 'Course created'];
    }


    /**
     * @param \stdClass $hit
     * @param mixed $api
     *
     * @return bool
     */
    public static function create_course_from_learning_object(\stdClass $hit, $api): bool {
        global $CFG;

        $loid = $hit->lo_id;

        if (!isset(self::$config)) {
            self::$config = new config_db_storage();
        }
        $coursecat = self::$config->get('course_category');
        $course_type = self::$config->get('course_type');
        $course_shortname = self::$config->get('course_shortname');

        $db = builder::get_db();

        require_once($CFG->dirroot . '/mod/scorm/locallib.php');
        require_once($CFG->dirroot.'/lib/completionlib.php');

        if ($course_shortname == 'withloid') {
            $shortname = 'goone_' . $hit->lo_id;
        } else {
            // 'fullname' or undefined
            $shortname = clean_param(substr($hit->core->title, 0, 200), !empty($CFG->formatstringstriptags) ? PARAM_TEXT : PARAM_CLEANHTML);
        }
        $shortname = self::deduplicate_course_shortname($shortname);
        $title = clean_param($hit->core->title, !empty($CFG->formatstringstriptags) ? PARAM_TEXT : PARAM_CLEANHTML);
        $descriptionhtml = self::generate_description($hit);

        $coursedata = new \stdClass();
        $coursedata->category = $coursecat;
        $coursedata->fullname = $hit->core->title;
        $coursedata->shortname = $shortname;
        $coursedata->visible = true;
        $coursedata->summary = $descriptionhtml;
        $coursedata->summaryformat = 1;
        $coursedata->enablecompletion = COMPLETION_ENABLED;
        $coursedata->completionstartonenrol = 1;
        $coursedata->completionprogressonview = 1;
        $coursedata->audiencevisible = (int) get_config('moodlecourse', 'visiblelearning');

        if ($course_type == self::CREATE_COURSE_SINGLE) {
            $coursedata->format = 'singleactivity';
            $coursedata->activitytype = 'scorm';
            $section = 0;
        } else {
            $section = 1;
        }
        $container = \container_course\course_helper::create_course($coursedata);

        $course = course_get_format($container->id)->get_course();

        // Add course image.
        if (!empty($hit->core->image->value)) {
            self::add_course_image_from_url($course->id, $hit->core->image->value);
        }

        // Add SCORM module.
        $learning_object_model = learning_object::load_by_external_id($loid, $api);
        $module = self::add_scorm_module($course, $title, $loid, $descriptionhtml, $hit->playback_behavior->assessable, $section, $learning_object_model, $api);

        // For single activity course, automatically set the added SCORM module as course completion criterion.
        if ($course_type == self::CREATE_COURSE_SINGLE) {
            $cmid = $module->get_id();

            require_once($CFG->dirroot.'/completion/criteria/completion_criteria_activity.php');

            $data = new \stdClass();
            $data->id = $course->id;
            $data->overall_aggregation = 1;
            $data->criteria_activity_value[$cmid] = 1;
            $data->activity_aggregation = 1;

            $transaction = $db->start_delegated_transaction();

            // Set activity completion criterion.
            $criterion = new \completion_criteria_activity();
            $criterion->update_config($data);

            // Set overall aggregation.
            $aggdata = array(
                'course'        => $course->id,
                'criteriatype'  => null
            );
            $aggregation = new \completion_aggregation($aggdata);
            $aggregation->setMethod($data->overall_aggregation);
            $aggregation->save();

            // Set activity aggregation.
            $aggdata['criteriatype'] = COMPLETION_CRITERIA_TYPE_ACTIVITY;
            $aggregation = new \completion_aggregation($aggdata);
            $aggregation->setMethod($data->activity_aggregation);
            $aggregation->save();

            $transaction->allow_commit();
        }

        return true;
    }


    /**
     * Add image to course from the given URL.
     *
     * @param int $courseid
     * @param string $url - URL to image file
     *
     * @return void
     */
    public static function add_course_image_from_url(int $courseid, string $url): void {
        if (($content_type = self::validate_image_url($url)) === false) {
            return;
        }
        $fs = get_file_storage();
        $context = \context_course::instance($courseid);

        if ($content_type == 'image/svg+xml' || $content_type == 'image/svg') {
            $image_str = download_file_content($url);
            $filename = basename(parse_url($url, PHP_URL_PATH));
        } else {
            $str = download_file_content($url);
            $image = imagecreatefromstring($str);
            if (empty($image)) {
                return;
            }

            // Resize original image to the standard width (640px) for the catalogue
            $image = imagescale($image, 640);

            // Capture image output as JPG
            ob_start();
            imagejpeg($image, null, 85);
            $image_str = ob_get_contents();
            ob_end_clean();
            $content_type = 'image/jpeg';
            $filename = basename(parse_url($url, PHP_URL_PATH)) . '.jpg';
        }

        $filerecord = [
            'contextid' => $context->id,
            'component' => 'course',
            'filearea' => 'images',
            'itemid' => 0,
            'filepath' => '/',
            'mimetype' => $content_type,
            'filename' => $filename
        ];
        $imagefile = $fs->create_file_from_string($filerecord, $image_str);

    }


    /**
     * Generate description when creating course/activity.
     *
     * @param \stdClass $hit hit from response from Go1
     *
     * @return string
     */
    public static function generate_description(\stdClass $hit): string {
        global $CFG;

        $param_provider = clean_param($hit->provider->name, !empty($CFG->formatstringstriptags) ? PARAM_TEXT : PARAM_CLEANHTML);
        $param_type = clean_param($hit->core->type, PARAM_CLEANHTML);
        $param_type = mb_strtoupper(mb_substr($param_type, 0, 1)) . mb_substr($param_type, 1); // Capitalise 1st letter.
        $param_duration = self::generate_duration(clean_param($hit->relevance->duration, PARAM_INT));
        $param_description = clean_text($hit->core->description);
        $param_learning_outcomes = self::generate_learning_outcomes($hit);
        $param_skills_covered = self::generate_skills_covered($hit);
        $param_topics = self::generate_topics($hit);
        $lo_header_overview = get_string('lo_header_overview', 'contentmarketplace_goone');

        $html = "
            <p>
                {$param_provider}<br />
                {$param_type} · {$param_duration}
            </p>
            <h3>{$lo_header_overview}</h3>
            <p>{$param_description}</p>
            {$param_topics}
            {$param_skills_covered}
            {$param_learning_outcomes}
        ";

        return $html;
    }


    /**
     * Generate/format outcomes HTML for description when creating course/activity.
     *
     * @param \stdClass $hit
     *
     * @return string
     */
    public static function generate_learning_outcomes(\stdClass $hit): string {
        if (empty($hit->relevance->learning_outcomes) || !is_array($hit->relevance->learning_outcomes)) {
            return '';
        }
        $list = '';
        $list .= '<h3>' . get_string('lo_header_learningoutcomes', 'contentmarketplace_goone') . '</h3>';
        $list .= '<ul>';
        foreach ($hit->relevance->learning_outcomes as $outcome) {
            $outcome = clean_param($outcome, PARAM_TEXT);
            $list .= "<li>{$outcome}</li>";
        }
        $list .= '</ul>';

        return $list;
    }


    /**
     * Checks if sync job for course creation is turned on and configured.
     *
     * @return bool
     */
    public static function is_create_courses_on_and_configured(): bool {
        if (!isset(self::$config)) {
            self::$config = new config_db_storage();
        }
        $create_courses = self::$config->get('create_courses');
        $course_category = self::$config->get('course_category');
        $course_type = self::$config->get('course_type');
        $sync_collections = self::$config->get('sync_collections');

        if (empty($create_courses)) {
            return false;
        }

        if (!$course_category || !$course_type|| !$sync_collections) {
            return false;
        }
        return true;
    }

    /**
     * Checks if processing of retiring/retired content is turned on and configured.
     *
     * @return bool
     */
    public static function is_retired_content_on_and_configured(): bool {
        if (!isset(self::$config)) {
            self::$config = new config_db_storage();
        }
        $retired_content_process = self::$config->get('retired_content_process');
        $retired_content_retired_actions_config = self::$config->get('retired_content_retired_actions');
        $retired_content_retired_actions = !empty($retired_content_retired_actions_config) ? explode(',', $retired_content_retired_actions_config) : [];
        $retired_content_removed_actions_config = self::$config->get('retired_content_removed_actions');
        $retired_content_removed_actions = !empty($retired_content_removed_actions_config) ? explode(',', $retired_content_removed_actions_config) : [];
        $retired_content_location = self::$config->get('retired_content_location');
        $retired_content_actions_move_category = self::$config->get('retired_content_actions_move_category');

        if (empty($retired_content_process)) {
            return false;
        }

        if (empty($retired_content_location)) {
            return false;
        }

        if (empty($retired_content_retired_actions) && empty($retired_content_removed_actions)) {
            return false;
        }

        if ((in_array('move', $retired_content_retired_actions) || in_array('move', $retired_content_removed_actions)) && empty($retired_content_actions_move_category)) {
            return false;
        }

        return true;
    }


    /**
     * Generate/format topics HTML for description when creating course/activity.
     *
     * @param \stdClass $hit
     *
     * @return string
     */
    public static function generate_topics(\stdClass $hit): string {
        if (!isset($hit->topics) || empty($hit->topics->items) || !is_array($hit->topics->items)) {
            return '';
        }
        $items = $hit->topics->items;
        $list = '';
        $list .= '<h3>' . get_string('lo_header_topics', 'contentmarketplace_goone') . '</h3>';
        $list .= '<ul>';
        foreach ($items as $item) {
            $list .= "<li>" . s($item->name) . "</li>";
        }
        $list .= '</ul>';

        return $list;
    }


    /**
     * Generate/format skills covered HTML for description when creating course/activity.
     *
     * @param \stdClass $hit
     *
     * @return string
     */
    public static function generate_skills_covered(\stdClass $hit): string {
        if (!isset($hit->skills->items) || empty($hit->skills->items) || !is_array($hit->skills->items)) {
            return '';
        }
        $items = self::sort_skills($hit->skills->items);
        $list = '';
        $list .= '<h3>' . get_string('lo_header_skills_covered', 'contentmarketplace_goone') . '</h3>';
        $list .= '<ul>';
        foreach ($items as $item) {
            $list .= "<li>" . s($item->name) . "</li>";
        }
        $list .= '</ul>';

        return $list;
    }


    /**
     * Generate/format duration HTML for description when creating course/activity.
     *
     * @param int $mins
     *
     * @return string
     */
    public static function generate_duration(int $mins): string {
        if ($mins == 0) {
            return '';
        } elseif ($mins < 60) {
            return get_string('lo_mins', 'contentmarketplace_goone', $mins);
        } elseif ($mins == 60) {
            return get_string('lo_hr', 'contentmarketplace_goone');
        } elseif ($mins < 120) {
            return get_string('lo_hrmins', 'contentmarketplace_goone', $mins - 60);
        } elseif (($mins % 60) == 0) {
            return get_string('lo_hrs', 'contentmarketplace_goone', $mins / 60);
        } else {
            $a = new \stdClass();
            $a->hrs = (int)floor($mins / 60);
            $a->mins = $mins % 60;
            return get_string('lo_hrsmins', 'contentmarketplace_goone', $a);
        }
    }


    /**
     * Sort skill covered by confidens, descending (most important on top).
     *
     * @param array $items
     *
     * @return array
     */
    public static function sort_skills(array $items): array {
        if (sizeof($items) == 1) {
            return $items;
        }
        for ($x = 0; $x < sizeof($items); $x++) {
            for ($y = 1; $y < sizeof($items); $y++) {
                if ($items[$y-1]->confidence < $items[$y]->confidence) {
                    $tmp = $items[$y-1];
                    $items[$y-1] = $items[$y];
                    $items[$y] = $tmp;
                }
            }
        }
        return $items;
    }

}