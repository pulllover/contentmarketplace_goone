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

/**
 * Trigger a Go1 custom collection sync from the curate page.
 *
 * @module contentmarketplace_goone/sync_content
 */
define(['jquery', 'core/str', 'core/config'], function ($, Str, mdlcfg) {

    var sync_content = {
        init: function (selector) {
            var context = $(selector);
            var synccontentStr;
            var syncingStr;
            var unknownerrorStr = '';

            $('#go1syncresult').hide();

            Str.get_strings([
                {
                    key: 'synccontent',
                    component: 'contentmarketplace_goone'
                },
                {
                    key: 'syncing',
                    component: 'contentmarketplace_goone'
                },
                {
                    key: 'unknownerror',
                    component: 'core'
                },
            ]).done(function (strs) {
                synccontentStr = strs[0];
                syncingStr = strs[1];
                unknownerrorStr = strs[2];
            });

            var showResult = function (success, message) {
                context.prop('disabled', false);
                context.attr('value', synccontentStr);
                if (success) {
                    $('#go1syncresult span').attr('data-flex-icon', 'core|notification-success');
                    $('#go1syncresult .alert').removeClass('alert-danger').addClass('alert-success');
                    $('#go1syncresult .flex-icon').removeClass('tfont-var-x-circle-fill').addClass('tfont-var-check-circle-fill');
                } else {
                    $('#go1syncresult span').attr('data-flex-icon', 'core|notification-error');
                    $('#go1syncresult .alert').removeClass('alert-success').addClass('alert-danger');
                    $('#go1syncresult .flex-icon').removeClass('tfont-var-check-circle-fill').addClass('tfont-var-x-circle-fill');
                }
                $('#go1syncresult .alert-message').text(message || unknownerrorStr);
                $('#go1syncresult').show();
            };

            context.on('click', function (e) {
                e.preventDefault();
                context.prop('disabled', true);
                context.attr('value', syncingStr);
                $('#go1syncresult').hide();
                $('#go1syncresult .alert-message').text('');
                $.post({
                    url: mdlcfg.wwwroot + "/totara/contentmarketplace/contentmarketplaces/goone/ajax/sync_content.php",
                    data: {
                        sesskey: mdlcfg.sesskey,
                    }
                }).done(function (data) {
                    showResult(Boolean(data && data.result), data && data.message);
                }).fail(function () {
                    // Transport level failure (timeout, server error): re-enable the button.
                    showResult(false, unknownerrorStr);
                });
            });
        }
    };

    return sync_content;

});
