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
 * Library functions for local_astusse plugin.
 *
 * This first version provides:
 * - RSA key management
 * - JWT helper functions
 * - Role extraction from Moodle contexts
 * - User JWT generation with RS256
 *
 * @package     local_astusse
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Return key directory and create it if needed.
 *
 * @return string
 */
function local_astusse_get_key_directory(): string {
    global $CFG;

    $keydir = $CFG->dataroot . '/astusse_jwt';
    if (!is_dir($keydir)) {
        make_writable_directory($keydir, true);
        @chmod($keydir, 0700);
    }

    return $keydir;
}

/**
 * Return private key path.
 *
 * @return string
 */
function local_astusse_get_private_key_path(): string {
    return local_astusse_get_key_directory() . '/private.pem';
}

/**
 * Return public key path.
 *
 * @return string
 */
function local_astusse_get_public_key_path(): string {
    return local_astusse_get_key_directory() . '/public.pem';
}

/**
 * Check whether both key files exist and are readable.
 *
 * @return bool
 */
function local_astusse_keys_exist(): bool {
    $privatekey = local_astusse_get_private_key_path();
    $publickey = local_astusse_get_public_key_path();

    return file_exists($privatekey) &&
        is_readable($privatekey) &&
        file_exists($publickey) &&
        is_readable($publickey);
}

/**
 * Generate RSA key pair.
 *
 * @param int $keysize Accepted values: 2048 or 3072.
 * @param bool $force If true, overwrite existing keys.
 * @return bool
 */
function local_astusse_generate_keys(int $keysize = 2048, bool $force = false): bool {
    if (!in_array($keysize, [2048, 3072], true)) {
        throw new coding_exception('Invalid key size. Allowed values: 2048 or 3072.');
    }

    $privatepath = local_astusse_get_private_key_path();
    $publicpath = local_astusse_get_public_key_path();

    if (!$force && file_exists($privatepath) && file_exists($publicpath)) {
        return true;
    }

    $keyconfig = [
        'private_key_bits' => $keysize,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ];

    $resource = openssl_pkey_new($keyconfig);
    if ($resource === false) {
        return false;
    }

    $privatepem = '';
    if (!openssl_pkey_export($resource, $privatepem)) {
        return false;
    }

    $details = openssl_pkey_get_details($resource);
    if (!$details || empty($details['key'])) {
        return false;
    }
    $publicpem = $details['key'];

    $privwritten = file_put_contents($privatepath, $privatepem);
    $pubwritten = file_put_contents($publicpath, $publicpem);
    if ($privwritten === false || $pubwritten === false) {
        return false;
    }

    @chmod($privatepath, 0600);
    @chmod($publicpath, 0644);

    return true;
}

/**
 * Return OpenSSL private key resource.
 *
 * @return OpenSSLAsymmetricKey|false
 */
function local_astusse_get_private_key() {
    $path = local_astusse_get_private_key_path();
    if (!file_exists($path)) {
        return false;
    }

    $pem = file_get_contents($path);
    if ($pem === false) {
        return false;
    }

    return openssl_pkey_get_private($pem);
}

/**
 * Return OpenSSL public key resource.
 *
 * @return OpenSSLAsymmetricKey|false
 */
function local_astusse_get_public_key() {
    $path = local_astusse_get_public_key_path();
    if (!file_exists($path)) {
        return false;
    }

    $pem = file_get_contents($path);
    if ($pem === false) {
        return false;
    }

    return openssl_pkey_get_public($pem);
}

/**
 * RFC 4648 URL-safe base64 encoding.
 *
 * @param string $data
 * @return string
 */
function local_astusse_base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Return JWKS key details from current public key.
 *
 * @return array|false
 */
function local_astusse_get_key_details() {
    $publickey = local_astusse_get_public_key();
    if ($publickey === false) {
        return false;
    }

    $details = openssl_pkey_get_details($publickey);
    if (!$details || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA) {
        return false;
    }

    $modulus = ltrim($details['rsa']['n'], "\0");
    $exponent = ltrim($details['rsa']['e'], "\0");
    $pem = $details['key'];

    return [
        'n' => local_astusse_base64url_encode($modulus),
        'e' => local_astusse_base64url_encode($exponent),
        'kid' => substr(hash('sha256', $pem), 0, 16),
    ];
}

/**
 * Return active kid used in both JWT and JWKS.
 *
 * @return string
 */
function local_astusse_get_active_kid(): string {
    $configured = get_config('local_astusse', 'key_id');
    if (!empty($configured)) {
        return trim((string)$configured);
    }

    $details = local_astusse_get_key_details();
    if (!empty($details['kid'])) {
        return $details['kid'];
    }

    return 'astusse-default';
}

/**
 * Build normalized role list expected by ASTUSSE services.
 *
 * @param stdClass $user
 * @return array
 */
function local_astusse_get_user_roles(stdClass $user): array {
    global $CFG;

    $mapping = [
        'manager' => 'ADMIN',
        'coursecreator' => 'TRAINER',
        'editingteacher' => 'TRAINER',
        'teacher' => 'TRAINER',
        'student' => 'STUDENT',
        'guest' => 'GUEST',
    ];

    $roleset = [];

    $systemcontext = context_system::instance();
    if (has_capability('moodle/site:config', $systemcontext, $user->id, false)) {
        $roleset['ADMIN'] = true;
    }

    $systemroles = get_user_roles($systemcontext, $user->id, false);
    foreach ($systemroles as $role) {
        local_astusse_add_mapped_role($roleset, $role->shortname, $mapping);
    }

    require_once($CFG->libdir . '/enrollib.php');
    $courses = enrol_get_users_courses($user->id, true, 'id');
    foreach ($courses as $course) {
        $coursecontext = context_course::instance($course->id);
        $courseroles = get_user_roles($coursecontext, $user->id, true);
        foreach ($courseroles as $role) {
            local_astusse_add_mapped_role($roleset, $role->shortname, $mapping);
        }
    }

    if (empty($roleset)) {
        if (isguestuser($user->id)) {
            $roleset['GUEST'] = true;
        } else {
            $roleset['STUDENT'] = true;
        }
    }

    $roles = array_keys($roleset);
    sort($roles);
    return $roles;
}

/**
 * Add one mapped role to normalized role set.
 *
 * @param array $roleset
 * @param string|null $shortname
 * @param array $mapping
 * @return void
 */
function local_astusse_add_mapped_role(array &$roleset, ?string $shortname, array $mapping): void {
    if ($shortname === null || $shortname === '') {
        return;
    }

    $normalized = strtolower(trim($shortname));
    if ($normalized === '') {
        return;
    }

    $target = $mapping[$normalized] ?? strtoupper($normalized);
    $roleset[$target] = true;
}

/**
 * Generate RS256 JWT for a Moodle user.
 *
 * @param stdClass $user
 * @return string|false
 */
function local_astusse_generate_user_token(stdClass $user) {
    global $CFG;

    $privatekey = local_astusse_get_private_key();
    if ($privatekey === false) {
        return false;
    }

    $issuer = get_config('local_astusse', 'issuer') ?: $CFG->wwwroot;
    $audience = get_config('local_astusse', 'audience') ?: 'astusse_services';
    $ttl = (int)(get_config('local_astusse', 'ttl_seconds') ?: 900);
    $kid = local_astusse_get_active_kid();

    $header = [
        'alg' => 'RS256',
        'typ' => 'JWT',
        'kid' => $kid,
    ];

    $now = time();
    $payload = [
        'iss' => $issuer,
        'sub' => (string)$user->id,
        'aud' => $audience,
        'iat' => $now,
        'exp' => $now + $ttl,
        'jti' => uniqid('', true),
        'preferred_username' => $user->username,
        'roles' => local_astusse_get_user_roles($user),
    ];

    if (!empty($user->email)) {
        $payload['email'] = $user->email;
    }
    if (!empty($user->firstname)) {
        $payload['given_name'] = $user->firstname;
    }
    if (!empty($user->lastname)) {
        $payload['family_name'] = $user->lastname;
    }

    $headerencoded = local_astusse_base64url_encode(json_encode($header));
    $payloadencoded = local_astusse_base64url_encode(json_encode($payload));
    $signinginput = $headerencoded . '.' . $payloadencoded;

    $signature = '';
    if (!openssl_sign($signinginput, $signature, $privatekey, OPENSSL_ALGO_SHA256)) {
        return false;
    }

    $signatureencoded = local_astusse_base64url_encode($signature);
    return $headerencoded . '.' . $payloadencoded . '.' . $signatureencoded;
}

/**
 * Sync admin scope policy settings to orchestration API.
 *
 * Called as admin setting updated callback.
 *
 * @return void
 */
function local_astusse_sync_scope_policy_from_settings(): void {
    global $USER;

    if (empty($USER) || empty($USER->id) || !isloggedin() || isguestuser()) {
        set_config('last_scope_sync_ok', 0, 'local_astusse');
        set_config('last_scope_sync_message', 'sync_skipped_no_user', 'local_astusse');
        set_config('last_scope_sync_at', time(), 'local_astusse');
        return;
    }

    try {
        $platformscopeallowed = (bool)(get_config('local_astusse', 'platform_scope_allowed') ?: 0);
        $delegationenabled = (bool)(get_config('local_astusse', 'delegation_enabled') ?: 0);

        $client = new \local_astusse\api_client();
        $response = $client->update_scope_policy_for_user($USER, $platformscopeallowed, $delegationenabled);
        $status = (int)($response['status'] ?? 0);

        if ($status < 200 || $status >= 300) {
            $message = $client->extract_error_message($response);
            set_config('last_scope_sync_ok', 0, 'local_astusse');
            set_config('last_scope_sync_message', 'sync_failed_' . $message, 'local_astusse');
            set_config('last_scope_sync_at', time(), 'local_astusse');
            debugging(
                'local_astusse: scope policy sync failed (' . $message . ').',
                DEBUG_DEVELOPER
            );
            return;
        }

        set_config('last_scope_sync_ok', 1, 'local_astusse');
        set_config('last_scope_sync_message', 'sync_ok', 'local_astusse');
        set_config('last_scope_sync_at', time(), 'local_astusse');
    } catch (\Throwable $e) {
        set_config('last_scope_sync_ok', 0, 'local_astusse');
        set_config('last_scope_sync_message', 'sync_exception_' . $e->getMessage(), 'local_astusse');
        set_config('last_scope_sync_at', time(), 'local_astusse');
        debugging('local_astusse: scope policy sync exception: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }
}

/**
 * Build rich HTML block for scope policy state in settings page.
 *
 * @return string
 */
function local_astusse_scope_policy_settings_state_html(): string {
    global $USER;

    $parts = [];

    $lastok = (int)(get_config('local_astusse', 'last_scope_sync_ok') ?: 0);
    $lastmessage = (string)(get_config('local_astusse', 'last_scope_sync_message') ?: '');
    $lastat = (int)(get_config('local_astusse', 'last_scope_sync_at') ?: 0);

    if ($lastat === 0 && $lastmessage === '') {
        $syncstatus = get_string('rag_scope_sync_status_pending', 'local_astusse');
    } else if (str_starts_with($lastmessage, 'sync_skipped_')) {
        $syncstatus = get_string('rag_scope_sync_status_skipped', 'local_astusse');
    } else if ($lastok) {
        $syncstatus = get_string('rag_scope_sync_status_ok', 'local_astusse');
    } else {
        $syncstatus = get_string('rag_scope_sync_status_ko', 'local_astusse');
    }

    if ($lastat > 0) {
        $syncat = userdate($lastat);
    } else {
        $syncat = get_string('rag_scope_sync_never', 'local_astusse');
    }

    $parts[] = html_writer::tag(
        'p',
        get_string('rag_scope_sync_line', 'local_astusse', (object)[
            'status' => $syncstatus,
            'time' => $syncat,
        ])
    );

    $localplatform = (bool)(get_config('local_astusse', 'platform_scope_allowed') ?: 0);
    $localdelegation = (bool)(get_config('local_astusse', 'delegation_enabled') ?: 0);

    try {
        if (!empty($USER) && !empty($USER->id) && isloggedin() && !isguestuser()) {
            $client = new \local_astusse\api_client();
            $snapshot = $client->get_scope_policy_snapshot_for_user($USER);
            $status = (int)($snapshot['status'] ?? 0);
            if ($status >= 200 && $status < 300 && !empty($snapshot['body_json']) && is_array($snapshot['body_json'])) {
                $json = $snapshot['body_json'];
                $backendplatform = !empty($json['platformScopeAllowed']);
                $backenddelegation = !empty($json['delegationEnabled']);

                if ($backendplatform === $localplatform && $backenddelegation === $localdelegation) {
                    $parts[] = html_writer::tag('p', get_string('rag_scope_backend_aligned', 'local_astusse'));
                } else {
                    $parts[] = html_writer::div(
                        get_string('rag_scope_backend_mismatch', 'local_astusse', (object)[
                            'localplatform' => $localplatform ? get_string('yes') : get_string('no'),
                            'localdelegation' => $localdelegation ? get_string('yes') : get_string('no'),
                            'backendplatform' => $backendplatform ? get_string('yes') : get_string('no'),
                            'backenddelegation' => $backenddelegation ? get_string('yes') : get_string('no'),
                        ]),
                        'alert alert-warning'
                    );
                }
            } else {
                $parts[] = html_writer::tag(
                    'p',
                    get_string('rag_scope_backend_unavailable', 'local_astusse', 'HTTP ' . $status)
                );
            }
        } else {
            $parts[] = html_writer::tag(
                'p',
                get_string('rag_scope_backend_unavailable', 'local_astusse', get_string('notloggedin', 'moodle'))
            );
        }
    } catch (\Throwable $e) {
        $parts[] = html_writer::tag(
            'p',
            get_string('rag_scope_backend_unavailable', 'local_astusse', s($e->getMessage()))
        );
    }

    return implode('', $parts);
}

/**
 * Add ASTUSSE pages in course settings navigation.
 *
 * @param settings_navigation $settingsnav
 * @param context $context
 * @return void
 */
function local_astusse_extend_settings_navigation(settings_navigation $settingsnav, context $context): void {
    global $PAGE;

    if ($context->contextlevel !== CONTEXT_COURSE) {
        return;
    }
    if (empty($PAGE) || empty($PAGE->course) || empty($PAGE->course->id)) {
        return;
    }

    $courseid = (int)$PAGE->course->id;
    if ($courseid === SITEID) {
        return;
    }

    $courseadmin = $settingsnav->find('courseadmin', navigation_node::TYPE_COURSE);

    if (has_capability('local/astusse:usechat', $context)) {
        $existing = $settingsnav->find('local_astusse_chat', navigation_node::TYPE_SETTING);
        if (!$existing) {
            $url = new moodle_url('/local/astusse/chat.php', ['courseid' => $courseid]);
            $node = navigation_node::create(
                get_string('chat:menu', 'local_astusse'),
                $url,
                navigation_node::TYPE_SETTING,
                null,
                'local_astusse_chat'
            );
            if ($courseadmin) {
                $courseadmin->add_node($node);
            } else {
                $settingsnav->add_node($node);
            }
        }
    }

    if (has_capability('local/astusse:managereferencetrainer', $context)) {
        $existing = $settingsnav->find('local_astusse_reference_trainer', navigation_node::TYPE_SETTING);
        if (!$existing) {
            $url = new moodle_url('/local/astusse/reference_trainer.php', ['courseid' => $courseid]);
            $node = navigation_node::create(
                get_string('referencetrainer:menu', 'local_astusse'),
                $url,
                navigation_node::TYPE_SETTING,
                null,
                'local_astusse_reference_trainer'
            );
            if ($courseadmin) {
                $courseadmin->add_node($node);
            } else {
                $settingsnav->add_node($node);
            }
        }
    }

    $delegationenabled = (bool)(get_config('local_astusse', 'delegation_enabled') ?: 0);
    if ($delegationenabled && has_capability('local/astusse:managetrainerscope', $context)) {
        $existing = $settingsnav->find('local_astusse_trainerscope', navigation_node::TYPE_SETTING);
        if (!$existing) {
            $url = new moodle_url('/local/astusse/trainer_scope.php', ['courseid' => $courseid]);
            $node = navigation_node::create(
                get_string('trainerscope:menu', 'local_astusse'),
                $url,
                navigation_node::TYPE_SETTING,
                null,
                'local_astusse_trainerscope'
            );
            if ($courseadmin) {
                $courseadmin->add_node($node);
            } else {
                $settingsnav->add_node($node);
            }
        }
    }

    if (has_capability('local/astusse:ingestdocument', $context)) {
        $existing = $settingsnav->find('local_astusse_ingest', navigation_node::TYPE_SETTING);
        if (!$existing) {
            $url = new moodle_url('/local/astusse/ingest.php', ['courseid' => $courseid]);
            $node = navigation_node::create(
                get_string('ingest:menu', 'local_astusse'),
                $url,
                navigation_node::TYPE_SETTING,
                null,
                'local_astusse_ingest'
            );
            if ($courseadmin) {
                $courseadmin->add_node($node);
            } else {
                $settingsnav->add_node($node);
            }
        }
    }
}

/**
 * Add ASTUSSE entries in course secondary navigation ("Plus" menu).
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 * @return void
 */
function local_astusse_extend_navigation_course(
    navigation_node $navigation,
    stdClass $course,
    context_course $context
): void {
    if (empty($course) || empty($course->id) || (int)$course->id === SITEID) {
        return;
    }

    $courseid = (int)$course->id;
    $delegationenabled = (bool)(get_config('local_astusse', 'delegation_enabled') ?: 0);

    if (has_capability('local/astusse:usechat', $context)) {
        $key = 'local_astusse_chat_course_nav';
        $existing = $navigation->find($key, navigation_node::TYPE_CUSTOM);
        if (!$existing) {
            $url = new moodle_url('/local/astusse/chat.php', ['courseid' => $courseid]);
            $node = navigation_node::create(
                get_string('chat:menu', 'local_astusse'),
                $url,
                navigation_node::TYPE_CUSTOM,
                null,
                $key
            );
            $navigation->add_node($node);
        }
    }

    if (has_capability('local/astusse:managereferencetrainer', $context)) {
        $key = 'local_astusse_reference_trainer_course_nav';
        $existing = $navigation->find($key, navigation_node::TYPE_CUSTOM);
        if (!$existing) {
            $url = new moodle_url('/local/astusse/reference_trainer.php', ['courseid' => $courseid]);
            $node = navigation_node::create(
                get_string('referencetrainer:menu', 'local_astusse'),
                $url,
                navigation_node::TYPE_CUSTOM,
                null,
                $key
            );
            $navigation->add_node($node);
        }
    }

    if ($delegationenabled && has_capability('local/astusse:managetrainerscope', $context)) {
        $key = 'local_astusse_trainerscope_course_nav';
        $existing = $navigation->find($key, navigation_node::TYPE_CUSTOM);
        if (!$existing) {
            $url = new moodle_url('/local/astusse/trainer_scope.php', ['courseid' => $courseid]);
            $node = navigation_node::create(
                get_string('trainerscope:menu', 'local_astusse'),
                $url,
                navigation_node::TYPE_CUSTOM,
                null,
                $key
            );
            $navigation->add_node($node);
        }
    }

    if (has_capability('local/astusse:ingestdocument', $context)) {
        $key = 'local_astusse_ingest_course_nav';
        $existing = $navigation->find($key, navigation_node::TYPE_CUSTOM);
        if (!$existing) {
            $url = new moodle_url('/local/astusse/ingest.php', ['courseid' => $courseid]);
            $node = navigation_node::create(
                get_string('ingest:menu', 'local_astusse'),
                $url,
                navigation_node::TYPE_CUSTOM,
                null,
                $key
            );
            $navigation->add_node($node);
        }
    }
}

/**
 * Return courses where the user can access ASTUSSE chat.
 *
 * @param stdClass $user
 * @return array<int,array<string,mixed>>
 */
function local_astusse_get_chat_accessible_courses(stdClass $user): array {
    global $CFG;

    require_once($CFG->libdir . '/enrollib.php');

    $courses = enrol_get_users_courses($user->id, true, 'id,fullname,shortname');
    $available = [];

    foreach ($courses as $course) {
        $courseid = (int)$course->id;
        if ($courseid === SITEID) {
            continue;
        }

        $context = context_course::instance($courseid);
        if (!has_capability('local/astusse:usechat', $context, $user->id)) {
            continue;
        }

        $available[$courseid] = [
            'id' => $courseid,
            'fullname' => format_string($course->fullname, true, ['context' => $context]),
            'shortname' => format_string($course->shortname ?? '', true, ['context' => $context]),
            'context' => $context,
        ];
    }

    uasort($available, static function(array $left, array $right): int {
        return strnatcasecmp($left['fullname'], $right['fullname']);
    });

    return array_values($available);
}
