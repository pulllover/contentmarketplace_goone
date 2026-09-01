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
 * @author Sergey Vidusov <sergey.vidusov@androgogic.com>
 * @package contentmarketplace_goone
 */

defined('MOODLE_INTERNAL') || die();

$string['activitycreatedx'] = 'Go1 activity added: {$a}';
$string['addactivity_confirm'] = 'Add activity "{$a->activity}" to course "{$a->course}"?';
$string['addactivity_confirm_button'] = 'Add activity';
$string['addactivity_confirm_title'] = 'Add activity from Go1';
$string['addcourse'] = 'Add a new course';
$string['addcoursefromlibrary'] = 'Add course from Go1';
$string['addcoursego1'] = 'Add courses from the Go1 content marketplace';
$string['addcoursego1_description'] = 'Create single or multi-activity courses based on what is available in the Go1 content marketplace.';
$string['all_content'] = 'All content ({$a})';
$string['annualcost'] = 'Annual subscription cost';
$string['availability-filter:free'] = 'Free';
$string['availability-filter:custom'] = 'Custom library';
$string['availability-filter:subscribe'] = 'Included in subscription';
$string['browse_learning_content'] = 'Browse Go1 content';
$string['cachedef_goonecollectioncount'] = 'Count of Go1 learning objects per collection';
$string['clientid'] = 'Client ID';
$string['clientid_help'] = 'Provide this Client ID when requested by Go1 support.';
$string['collection:free'] = 'Free';
$string['collection:subscribe'] = 'Included in subscription';
$string['collection:custom'] = 'Custom library';
$string['collectionwithtotal:free'] = 'Free ({$a})';
$string['collectionwithtotal:subscribe'] = 'Included in subscription ({$a})';
$string['collectionwithtotal:custom'] = 'Custom library ({$a})';
$string['collections'] = 'Collections';
$string['collections_help'] = 'Recommended setting: "Custom library".
        Selecting large collections is not advised, as it may result in tens of thousand of courses created
        in the LMS automatically, which may prove tricky to manage and, depending on the system specifications,
        may adversely impact the performance of the LMS. If no collections are selected,
        content sync will not happen.';
$string['collections_sync'] = 'Sync collections';
$string['complete_connection'] = 'Scope confirmed, proceed to complete connection >>';
$string['content_access'] = 'Content access';
$string['content_access_settings'] = 'Content access settings';
$string['content_access_settings_description'] = 'When manually adding activities to existing
    courses from content marketplace, which Go1 content should these users be able to access?
    <strong>Note: </strong> site admins have access to all synced content regardless of these settings.';
$string['content_creators'] = 'Content creators';
$string['content_creators_help'] = 'Anyone with capability "totara/contentmarketplace:add"';
$string['content_regions'] = 'Content relevance regions';
$string['content_regions_help'] = 'If selected, only content relevant to specified regions will be synced.
    If nothing is selected, content relevant to any region will be synced.';
$string['content_sync'] = 'Content sync';
$string['content_sync_settings'] = 'Content sync settings';
$string['content_sync_settings_description'] = 'Content from Go1 can be synced to create courses in Totara automatically.
    Syncing is performed by pressing the Sync button in the content curation screen, or by the scheduled task <code>\totara_contentmarketplace\task\sync_task</code>';
$string['content_synced_success'] = 'Content synced successfully.';
$string['continue'] = 'Continue';
$string['course_category'] = 'Course category';
$string['course_category_help'] = 'New courses will be created in the selected course category.';
$string['course_category_notselected'] = 'Select category for new courses';
$string['course_creation'] = 'Course creation';
$string['course_creation_help'] = 'Include all content in a single course, or create a new course for each content item?';
$string['coursecreated'] = 'New course has been created';
$string['coursecreatedx'] = '{$a} new courses have been created';
$string['course_shortname'] = 'Course shortname';
$string['course_shortname_fullname'] = 'Populate with the title of the learning object';
$string['course_shortname_withloid'] = 'Generic, with learning object ID, e.g. <code>goone_1234567</code>';
$string['course_type'] = 'Course type';
$string['course_type_single'] = 'Single activity course';
$string['course_type_multi'] = 'Multiple activity course';
$string['course_type_notselected'] = 'Select type for new courses';
$string['create_course_manual'] = 'Manual course creation';
$string['create_course_manual_disabled'] = 'Disabled (redirect to content curation)';
$string['create_course_manual_enabled'] = 'Enabled';
$string['create_course_manual_help'] = 'When enabled, the "Add courses from the Go1 content marketplace" workflow opens the Go1 content explorer, where content can be selected and courses created manually. When disabled, the workflow redirects to the Go1 content curation page instead.';
$string['create_course_manual_invalid'] = 'Select whether manual course creation is enabled or disabled.';
$string['create_courses'] = 'Create courses';
$string['create_courses_help'] = 'Sync learning content from Go1 and create courses in the LMS from it.';
$string['currentplan'] = 'Current Plan';
$string['curate_content'] = 'Manage Go1 content library';
$string['duration'] = '{$a} minutes';
$string['error:invalid_token'] = 'There is an authentication problem when connecting to the Go1 servers. The Go1 content marketplace will need to be set up again.';
$string['error:invalidoauthstate'] = 'The response from the Go1 authorisation service could not be verified. Please restart the Go1 setup from the content marketplace setup page.';
$string['error:cannotobtainott'] = 'Failed to obtain user authentication token';
$string['error:pluginnotconfigured'] = 'Go1 content marketplace integration is not configured.';
$string['error:rest_client_timeout'] = 'Communication with Go1 server timed out. Please try again in a moment.';
$string['error:sync_failed'] = 'Go1 content sync failed: {$a}';
$string['error:unavailable_learning_object'] = 'Attempted to use unavailable learning object. Please return to the Go1 content marketplace.';
$string['error:missing_scope_header'] = 'Almost there — one step needed before we can connect';
$string['error:missing_scope'] = 'Your Go1 account was verified successfully, but the integration requires an additional
    permission (user.login scope) that hasn\'t been enabled on your Go1 client application yet.
    <p><br />To complete the setup:
    <ul>
    <li>Email <a href="mailto:support@go1.com">support@go1.com</a> with the subject line: "Enable user.login scope for Totara integration"
    <li>Include your Go1 Client_ID in the email: <code>{$a}</code>
    <li>The Go1 support team will enable the required scope on your account.
    <li>Once confirmed, return to this page and complete the connection.
    </ul>';
$string['error_adding_activity_invalid_mode'] = 'Error adding activity: invalid mode';
$string['explorego1marketplace'] = 'Go1 content marketplace';
$string['explorego1marketplacedesc'] = 'Explore content from the Go1 marketplace';
$string['filter:availability'] = 'Availability';
$string['filter:language'] = 'Languages';
$string['filter:provider'] = 'Providers';
$string['filter:tags'] = 'Topics';
$string['general'] = 'General';
$string['general_settings'] = 'General settings';
$string['general_settings_description'] = 'Settings that control how the Go1 content marketplace behaves across the site.';
$string['goone:curatecontent'] = 'Curate and sync Go1 content';
$string['goonesettings'] = 'Go1 settings';
$string['langwithcode'] = '{$a->lang} ({$a->country})';
$string['learners'] = 'Learners';
$string['lo_header_duration'] = 'Duration';
$string['lo_header_learningoutcomes'] = 'Learning outcomes';
$string['lo_header_overview'] = 'Overview';
$string['lo_header_skills_covered'] = 'Skills covered';
$string['lo_header_topics'] = 'Topics';
$string['lo_mins'] = '{$a} mins';
$string['lo_hr'] = '1 hr';
$string['lo_hrmins'] = '1 hr {$a} mins';
$string['lo_hrs'] = '{$a} hrs';
$string['lo_hrsmins'] = '{$a->hrs} hrs {$a->mins} mins';
$string['managesubscription'] = 'Manage subscription';
$string['no_content'] = 'No content marketplace';
$string['notapplicable'] = 'N/A';
$string['numberactiveusers'] = 'Number of active users';
$string['numberlicensedusers'] = 'Number of licensed users';
$string['online'] = 'Online';
$string['pay_per_seat'] = 'Pay per seat courses';
$string['pay_per_seat:admin'] = 'Request sent to admin';
$string['pay_per_seat:learner'] = 'Learner pays';
$string['pay_per_seat_help'] = 'When a course is not included in your subscription, how should learners access the course?';
$string['plugin_description_html'] = 'Compliance and professional development made easy. Access over 100K courses. <a href="https://www.go1.com/affiliate/totara" target="_blank">Find out more</a>';
$string['pluginname'] = 'Go1';
$string['portalurl'] = 'Portal URL';
$string['price:free'] = 'Free';
$string['price:included'] = 'Included';
$string['pricewithtax'] = '{$a->baseprice} (+{$a->tax}% tax)';
$string['region'] = 'Region';
$string['region:'] = 'Unknown';
$string['region:AU'] = 'Australia';
$string['region:EU'] = 'European Union';
$string['region:MY'] = 'Malaysia';
$string['region:OTHER'] = 'Rest of the world';
$string['region:UK'] = 'United Kingdom';
$string['region:US'] = 'United States of America';
$string['renewaldate'] = 'Renewal date';

$string['retired_content'] = 'Retired content';
$string['retired_content_removed_actions'] = 'Actions when Go1 content is removed';
$string['retired_content_category_notselected'] = 'Destination category is required when moving courses';
$string['retired_content_action_courseimage'] = 'Put "Leaving on..." on the course image (visible in Totara catalogue)';
$string['retired_content_action_description'] = 'Add notification to course description (visible in Totara catalogue)';
$string['retired_content_action_move'] = 'Move course to category';
$string['retired_content_action_hide'] = 'Hide course';
$string['retired_content_action_move_category'] = 'Destination course category';
$string['retired_content_action_move_category_help'] = 'If selecting "Move course to category", select the course category to move courses to.';
$string['retired_content_dateformat'] = 'Date format template';
$string['retired_content_dateformat_help'] = 'To be used when notifying learners about retired content.
    Use format template supported by PHP date_format(), see
    <a href="https://www.php.net/manual/en/datetime.format.php" target="_blank">php.net/manual/en/datetime.format.php</a>.
    <br />Default format: "{$a->dateformat_default}" ("{$a->example_default}")
    <br />Current format: "{$a->dateformat}" ("{$a->example}")';
$string['retired_content_location'] = 'Course location';
$string['retired_content_location_help'] = 'Specifies in which course categories Totara should be allowed to modify
    courses that use the retired learning objects.';
$string['retired_content_location_synccatonly'] = 'Update courses in the sync course category only (the one specified in Content Sync tab)';
$string['retired_content_location_alltotara'] = 'Update courses in all course categories across the LMS';
$string['retired_content_process'] = 'Process retired content';
$string['retired_content_process_help'] = 'Global switch that defines whether or not Totara should process decommissioned learning objects.';
$string['retired_content_retired_header'] = 'Decommissioned content';
$string['retired_content_settings'] = 'Retired content settings';
$string['retired_content_settings_description'] = 'When content (learning objects) is decommissioned in Go1,
    Totara can act on courses, that use the retired content, to let learners
    and course managers know, that the content is no longer available. The scheduled task checks Go1 sync collections
    only (see Content Sync tab), while notifications, that are received via Webhook, update courses with learning
    objects in any Go1 collection.';
$string['retired_content_retired_actions'] = 'Actions when Go1 content retires (is to be removed soon)';
$string['retired_content_template_courseimage1'] = 'Leaving on';
$string['retired_content_template_courseimage2'] = '{$a}';
$string['retired_content_template_coursesummary'] = '<h3 style="color:red;">Leaving on {$a}</h3>
    <p>This course is no longer relevant and is set to be decommissioned.</p>
    <hr />
    ';

$string['saveandexplorego1'] = 'Save and explore Go1';
$string['search:placeholder'] = 'Search course title, provider, or keyword';
$string['selectcontent'] = 'Select {$a}';
$string['settings_saved'] = 'Settings have been saved.';
$string['setup_page_header'] = 'Set up Go1 integration';
$string['sort:created:desc'] = 'Latest';
$string['sort:popularity'] = 'Popular';
$string['sort:price'] = 'Price (low to high)';
$string['sort:price:desc'] = 'Price (high to low)';
$string['sort:relevance'] = 'Ranking / most relevant';
$string['sort:title'] = 'Alphabetical (A to Z)';
$string['specific_collection'] = 'Specific collection';
$string['subscribed_content'] = 'Subscribed content ({$a})';
$string['subscription_details'] = 'Subscription details';
$string['synccontent'] = 'Sync content';
$string['syncing'] = 'Syncing...';
$string['unknownlanguage'] = 'Unknown';
$string['warningdisablemarketplace:body:html'] = '<p>You are about to disable the Go1 content marketplace. If you proceed, items from the marketplace will no longer be available to course creators for inclusion in newly created courses.</p>
<p>Users who have previously already started Go1 activities will continue to have access to that content, but they will not be able to start new Go1 activities.</p>
<p>Are you sure you wish to proceed?</p>';
$string['warningdisablemarketplace:title'] = 'Disable Go1 content';
$string['warningenablemarketplace:body:html'] = 'You are about to enable the Go1 content marketplace. If you proceed, items from the marketplace will be available to course creators for inclusion in newly created courses.';
$string['warningenablemarketplace:title'] = 'Enable Go1 content';

$string['webhook'] = 'Webhook';
$string['webhook_already_created'] = 'Webhook has been created:';
$string['webhook_create'] = 'Create Webhook';
$string['webhook_out_of_sync'] = '<strong>Note:</strong> The webhook settings in the LMS do not match the webhook. Press the Update Webhook button to synchronise.';
$string['webhook_update'] = 'Update Webhook';
$string['webhook_desc'] = 'Webhook is a way for Totara to receive and act upon events that occur in the Go1 system.
    When enabled, the webhook will subscribe to the following Go1 event: <code>enrollment.complete</code>.
    This will act as a redundancy in case Totara for some reason doesn\'t receive completion directly
    from the Go1 learning object.';

// Workflow
$string['workflow:description'] = 'Create an activity based on what is available in the GO1 content marketplace';
$string['workflow:name'] = 'Add an activity from the GO1 content marketplace';
$string['workflow:type'] = 'Add a new content marketplace activity';

// Deprecated since Totara 15

$string['warningdisablemarketplace:yes'] = 'Disable Go1';

// Deprecated since Totara 20

$string['account'] = 'Account';
