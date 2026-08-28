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
 * @author Sergey Vidusov <sergey.vidusov@androgogic.com>
 * @author Kirill Astashov <kirill.astashov@androgogic.com>
 * @package contentmarketplace_goone
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once($CFG->libdir . '/adminlib.php');

// admin_externalpage_setup('contentmarketplace_goone_page');
\core\setting\page\externalpage::setup(null, 'contentmarketplace_goone_page');

$context = context_system::instance();
require_capability('totara/contentmarketplace:config', $context);

$tab = optional_param('tab', 'general', PARAM_ALPHAEXT);

$tabs = array();
$basepage = '/totara/contentmarketplace/contentmarketplaces/goone/config.php';
$pages = array(
    'general',
    'content_sync',
    'retired_content',
    'content_access',
    'webhook'
);

foreach ($pages as $page) {
    $tabs[] = new tabobject(
        $page,
        new moodle_url($basepage, array(
            'tab' => $page,
        )),
        get_string($page, 'contentmarketplace_goone')
    );
}

$file = $CFG->dirroot . '/totara/contentmarketplace/contentmarketplaces/goone/tabs/' . $tab . '.php';
if (!in_array($tab, $pages) || !is_file($file)) {
    redirect($basepage);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(new lang_string('goonesettings', 'contentmarketplace_goone'));
echo $OUTPUT->tabtree($tabs, $tab);

include($file);

echo $OUTPUT->footer();