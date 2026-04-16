<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Settings for local_astusse plugin.
 *
 * @package     local_astusse
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/local/astusse/lib.php');

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_astusse', get_string('pluginname', 'local_astusse'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configtext(
        'local_astusse/issuer',
        get_string('issuer', 'local_astusse'),
        get_string('issuer_desc', 'local_astusse'),
        $CFG->wwwroot,
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'local_astusse/audience',
        get_string('audience', 'local_astusse'),
        get_string('audience_desc', 'local_astusse'),
        'astusse_services',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_astusse/ttl_seconds',
        get_string('ttl_seconds', 'local_astusse'),
        get_string('ttl_seconds_desc', 'local_astusse'),
        900,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_astusse/key_id',
        get_string('key_id', 'local_astusse'),
        get_string('key_id_desc', 'local_astusse'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_astusse/gateway_base_url',
        get_string('gateway_base_url', 'local_astusse'),
        get_string('gateway_base_url_desc', 'local_astusse'),
        'http://localhost:8888',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'local_astusse/gateway_timeout_seconds',
        get_string('gateway_timeout_seconds', 'local_astusse'),
        get_string('gateway_timeout_seconds_desc', 'local_astusse'),
        30,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configselect(
        'local_astusse/chat_history_ttl',
        get_string('chat_history_ttl', 'local_astusse'),
        get_string('chat_history_ttl_desc', 'local_astusse'),
        'PT24H',
        [
            'PT24H' => get_string('chat_history_ttl_24h', 'local_astusse'),
            'PT48H' => get_string('chat_history_ttl_48h', 'local_astusse'),
            'PT72H' => get_string('chat_history_ttl_72h', 'local_astusse'),
            'unlimited' => get_string('chat_history_ttl_unlimited', 'local_astusse'),
        ]
    ));

    $keydir = $CFG->dataroot . '/astusse_jwt';
    $settings->add(new admin_setting_description(
        'local_astusse/key_directory',
        get_string('key_directory', 'local_astusse'),
        get_string('key_directory_desc', 'local_astusse') . '<br><code>' . s($keydir) . '</code>'
    ));

    $settings->add(new admin_setting_heading(
        'local_astusse/rag_scope_heading',
        get_string('rag_scope_heading', 'local_astusse'),
        get_string('rag_scope_heading_desc', 'local_astusse')
    ));

    $platformscope = new admin_setting_configcheckbox(
        'local_astusse/platform_scope_allowed',
        get_string('platform_scope_allowed', 'local_astusse'),
        get_string('platform_scope_allowed_desc', 'local_astusse'),
        0
    );
    $platformscope->set_updatedcallback('local_astusse_sync_scope_policy_from_settings');
    $settings->add($platformscope);

    $delegation = new admin_setting_configcheckbox(
        'local_astusse/delegation_enabled',
        get_string('delegation_enabled', 'local_astusse'),
        get_string('delegation_enabled_desc', 'local_astusse'),
        1
    );
    $delegation->set_updatedcallback('local_astusse_sync_scope_policy_from_settings');
    $settings->add($delegation);

    $settings->add(new admin_setting_description(
        'local_astusse/rag_scope_sync_state',
        get_string('rag_scope_sync_state_title', 'local_astusse'),
        local_astusse_scope_policy_settings_state_html()
    ));
}
