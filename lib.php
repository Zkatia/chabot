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
 * @copyright   2026 Ingenium Digital Learning
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Charge les assets de la charte ASTUSSE « autoporteur » sur une page :
 * la feuille de styles du plugin + les polices Geist / Geist Mono.
 *
 * A appeler avant $OUTPUT->header(). Les pages doivent envelopper leur
 * contenu dans un conteneur portant la classe « local-astusse-charte ».
 *
 * @param moodle_page $page
 * @return void
 */
function local_astusse_require_charte_assets($page): void {
    $page->requires->css(new moodle_url('/local/astusse/styles.css'));
    // Polices Geist / Geist Mono embarquées localement (aucun appel externe).
    $page->requires->css(new moodle_url('/local/astusse/fonts/geist.css'));
}

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
    if (is_siteadmin($user->id) || has_capability('moodle/site:config', $systemcontext, $user->id)) {
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
 * Resolve the reference trainer context attached to one course.
 *
 * @param int $courseid
 * @return array{status: array, trainerid: ?string}
 */
function local_astusse_get_reference_trainer_context(int $courseid): array {
    $status = \local_astusse\reference_trainer_service::get_status($courseid);
    $trainerid = $status['state'] === 'valid' ? (string)$status['trainerid'] : null;

    return [
        'status' => $status,
        'trainerid' => $trainerid,
    ];
}

/**
 * Resolve the user used to sync admin policy toward the backend.
 *
 * @return stdClass|null
 */
function local_astusse_get_scope_sync_user(): ?stdClass {
    global $USER;

    $systemcontext = context_system::instance();
    if (!empty($USER) && !empty($USER->id) && isloggedin() && !isguestuser()) {
        if (is_siteadmin($USER->id) || has_capability('moodle/site:config', $systemcontext, $USER->id)) {
            return $USER;
        }
    }

    if (function_exists('get_admin')) {
        $adminuser = get_admin();
        if (!empty($adminuser) && !empty($adminuser->id)) {
            return $adminuser;
        }
    }

    return null;
}

/**
 * Sync admin scope policy settings to orchestration API.
 *
 * Called as admin setting updated callback.
 *
 * @return void
 */
function local_astusse_sync_scope_policy_from_settings(): void {
    $syncuser = local_astusse_get_scope_sync_user();
    if ($syncuser === null) {
        set_config('last_scope_sync_ok', 0, 'local_astusse');
        set_config('last_scope_sync_message', 'sync_skipped_no_admin_user', 'local_astusse');
        set_config('last_scope_sync_at', time(), 'local_astusse');
        return;
    }

    try {
        $platformscopeallowed = (bool)(get_config('local_astusse', 'platform_scope_allowed') ?: 0);
        $delegationenabled = (bool)(get_config('local_astusse', 'delegation_enabled') ?: 0);

        $client = new \local_astusse\api_client();
        $response = $client->update_scope_policy_for_user($syncuser, $platformscopeallowed, $delegationenabled);
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
    $parts = [];

    $localplatform = (bool)(get_config('local_astusse', 'platform_scope_allowed') ?: 0);
    $localdelegation = (bool)(get_config('local_astusse', 'delegation_enabled') ?: 0);

    // Read backend state first, then sync if there is a mismatch.
    $backendplatform = null;
    $backenddelegation = null;
    $backendfetchok = false;

    try {
        $syncuser = local_astusse_get_scope_sync_user();
        if ($syncuser !== null) {
            $client = new \local_astusse\api_client();
            $snapshot = $client->get_scope_policy_snapshot_for_user($syncuser);
            $snapshotstatus = (int)($snapshot['status'] ?? 0);
            if (
                $snapshotstatus >= 200 && $snapshotstatus < 300
                    && !empty($snapshot['body_json']) && is_array($snapshot['body_json'])
            ) {
                $json = $snapshot['body_json'];
                $backendplatform = !empty($json['platformScopeAllowed']);
                $backenddelegation = !empty($json['delegationEnabled']);
                $backendfetchok = true;
            }
        }
    } catch (\Throwable $e) {
        // Backend unreachable; treat as not fetched and handle below.
        $backendfetchok = false;
    }

    // If backend state differs from local, force a sync POST then re-read.
    if ($backendfetchok && ($backendplatform !== $localplatform || $backenddelegation !== $localdelegation)) {
        local_astusse_sync_scope_policy_from_settings();

        // Re-read backend state after sync.
        try {
            if ($syncuser !== null) {
                $snapshot = $client->get_scope_policy_snapshot_for_user($syncuser);
                $snapshotstatus = (int)($snapshot['status'] ?? 0);
                if (
                    $snapshotstatus >= 200 && $snapshotstatus < 300
                        && !empty($snapshot['body_json']) && is_array($snapshot['body_json'])
                ) {
                    $json = $snapshot['body_json'];
                    $backendplatform = !empty($json['platformScopeAllowed']);
                    $backenddelegation = !empty($json['delegationEnabled']);
                }
            }
        } catch (\Throwable $e) {
            // Ignore re-read failure: the previously read state remains authoritative.
            debugging('local_astusse: scope policy re-read failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    // Build sync status line.
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

    // Build backend comparison block.
    try {
        if ($syncuser === null) {
            $syncuser = local_astusse_get_scope_sync_user();
        }
        if ($syncuser !== null) {
            if ($backendfetchok) {
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
                    get_string('rag_scope_backend_unavailable', 'local_astusse', 'fetch failed')
                );
            }
        } else {
            $parts[] = html_writer::tag(
                'p',
                get_string('rag_scope_backend_unavailable', 'local_astusse', 'No admin sync user available')
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
 * Return the list of ingestable course resources for a given course.
 *
 * Supported module types: resource (file), page, scorm.
 * Each entry contains: cmid, name, modname, section, mimetype (for resource).
 *
 * @param int $courseid
 * @return array List of resource descriptors.
 */
function local_astusse_get_course_ingestable_resources(int $courseid): array {
    $modinfo = get_fast_modinfo($courseid);
    $supportedtypes = [
        'resource', 'page', 'scorm', 'h5pactivity', 'url', 'book',
        'glossary', 'lesson', 'quiz', 'assign', 'wiki', 'folder',
    ];
    $resources = [];

    foreach ($modinfo->get_cms() as $cminfo) {
        if (!in_array($cminfo->modname, $supportedtypes, true)) {
            continue;
        }
        if (!$cminfo->uservisible) {
            continue;
        }
        if ($cminfo->deletioninprogress) {
            continue;
        }

        $entry = [
            'cmid' => (int)$cminfo->id,
            'name' => format_string($cminfo->name),
            'modname' => $cminfo->modname,
            'sectionnum' => (int)$cminfo->sectionnum,
            'sectionname' => '',
            'icon' => '',
        ];

        // Section name.
        $sectioninfo = $modinfo->get_section_info($cminfo->sectionnum);
        if ($sectioninfo) {
            $entry['sectionname'] = get_section_name($courseid, $sectioninfo);
        }

        // Icon URL.
        $entry['icon'] = (string)$cminfo->get_icon_url();

        // For resource modules, extract the main file info.
        if ($cminfo->modname === 'resource') {
            $entry['filename'] = '';
            $entry['mimetype'] = '';
            $context = context_module::instance($cminfo->id);
            $fs = get_file_storage();
            $files = $fs->get_area_files($context->id, 'mod_resource', 'content', 0, 'sortorder DESC, id ASC', false);
            $mainfile = reset($files);
            if ($mainfile) {
                $entry['filename'] = $mainfile->get_filename();
                $entry['mimetype'] = $mainfile->get_mimetype();
            }
        }

        $resources[] = $entry;
    }

    // Sort by section number then name.
    usort($resources, function ($a, $b) {
        $sectioncmp = $a['sectionnum'] <=> $b['sectionnum'];
        if ($sectioncmp !== 0) {
            return $sectioncmp;
        }
        return strnatcasecmp($a['name'], $b['name']);
    });

    return $resources;
}

/**
 * Extract the content of a course module for RAG ingestion.
 *
 * Returns an array with 'filepath', 'filename', 'mimetype' keys pointing
 * to a temporary file ready to be sent to the backend, or null on failure.
 *
 * @param int $cmid Course module ID.
 * @param int $courseid Course ID (for validation).
 * @param \stdClass|null $job Optional job row carrying extra context (e.g. fileareaitemid for folder).
 * @return array|null
 */
function local_astusse_extract_module_content(int $cmid, int $courseid, ?\stdClass $job = null): ?array {
    global $DB;

    $cm = get_coursemodule_from_id('', $cmid, $courseid, false, MUST_EXIST);

    if ($cm->modname === 'resource') {
        return local_astusse_extract_resource_content($cm);
    }
    if ($cm->modname === 'page') {
        return local_astusse_extract_page_content($cm);
    }
    if ($cm->modname === 'scorm') {
        return local_astusse_extract_scorm_content($cm);
    }
    if ($cm->modname === 'h5pactivity') {
        return local_astusse_extract_h5p_content($cm);
    }
    if ($cm->modname === 'url') {
        return local_astusse_extract_url_content($cm);
    }
    if ($cm->modname === 'book') {
        return local_astusse_extract_book_content($cm);
    }
    if ($cm->modname === 'glossary') {
        return local_astusse_extract_glossary_content($cm);
    }
    if ($cm->modname === 'lesson') {
        return local_astusse_extract_lesson_content($cm);
    }
    if ($cm->modname === 'quiz') {
        return local_astusse_extract_quiz_content($cm);
    }
    if ($cm->modname === 'assign') {
        return local_astusse_extract_assign_content($cm);
    }
    if ($cm->modname === 'wiki') {
        return local_astusse_extract_wiki_content($cm);
    }
    if ($cm->modname === 'folder') {
        return local_astusse_extract_folder_content($cm, $job);
    }

    return null;
}

/**
 * Check whether a hostname resolves to a public, routable IP address.
 *
 * Blocks loopback, link-local, RFC1918 private, multicast and reserved ranges
 * to prevent SSRF against the Moodle host's internal network.
 *
 * @param string $host
 * @return bool
 */
function local_astusse_is_public_host(string $host): bool {
    $ips = @gethostbynamel($host);
    if (!is_array($ips) || empty($ips)) {
        // Try IPv6.
        $records = @dns_get_record($host, DNS_AAAA);
        if (!empty($records)) {
            $ips = array_column($records, 'ipv6');
        }
    }
    if (empty($ips)) {
        return false;
    }
    foreach ($ips as $ip) {
        // FILTER_FLAG_NO_PRIV_RANGE excludes RFC1918 (10/8, 172.16/12, 192.168/16).
        // FILTER_FLAG_NO_RES_RANGE excludes loopback, link-local, etc.
        if (
            filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) === false
        ) {
            return false;
        }
    }
    return true;
}

/**
 * Fetch the visible text of an external HTTPS URL with safety guardrails.
 *
 * Returns null on any failure (timeout, non-2xx, oversized, SSRF block, etc.).
 *
 * @param string $url
 * @return string|null
 */
function local_astusse_fetch_external_url_text(string $url): ?string {
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host'])) {
        return null;
    }
    if (($parts['scheme'] ?? '') !== 'https') {
        // Only HTTPS allowed.
        return null;
    }
    if (!local_astusse_is_public_host($parts['host'])) {
        return null;
    }

    $maxbytes = 5 * 1024 * 1024;
    $body = '';
    $oversized = false;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 25);
    curl_setopt($ch, CURLOPT_USERAGENT, 'local_astusse/1.0 (+https://moodle.org)');
    curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
    curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: text/html,application/xhtml+xml',
        'Accept-Language: fr,en;q=0.8',
    ]);
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) use (&$body, &$oversized, $maxbytes) {
        $len = strlen($chunk);
        if (strlen($body) + $len > $maxbytes) {
            $oversized = true;
            return 0; // Abort transfer.
        }
        $body .= $chunk;
        return $len;
    });

    $ok = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contenttype = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    // When oversized we aborted on purpose mid-stream; what we already buffered may
    // still be useful, so we only bail out on a genuine transfer or HTTP error.
    if (!$oversized && ($ok === false || $status < 200 || $status >= 300)) {
        return null;
    }
    if ($body === '') {
        return null;
    }
    if (stripos($contenttype, 'html') === false && stripos($contenttype, 'xml') === false) {
        // Not an HTML/XHTML payload — skip (no PDF/text fetcher for now).
        return null;
    }

    $text = local_astusse_extract_text_from_html($body);
    return $text !== '' ? $text : null;
}

/**
 * Extract text from an `mod_url` activity: metadata + (when possible) fetched
 * content of the external URL, parsed as HTML.
 *
 * Always returns at least the metadata so the RAG knows the resource exists,
 * even when the remote site is unreachable.
 *
 * @param stdClass $cm
 * @return array|null
 */
function local_astusse_extract_url_content(stdClass $cm): ?array {
    global $DB;

    $record = $DB->get_record('url', ['id' => $cm->instance], 'id, name, intro, introformat, externalurl');
    if (!$record) {
        return null;
    }
    $external = trim((string)$record->externalurl);
    if ($external === '') {
        return null;
    }

    $parts = [];
    $parts[] = 'Titre : ' . $cm->name;
    if (!empty($record->intro)) {
        $intro = format_text($record->intro, $record->introformat ?? FORMAT_HTML, ['noclean' => true]);
        $intro = trim(preg_replace('/\s+/', ' ', strip_tags($intro)));
        $intro = html_entity_decode($intro, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($intro !== '') {
            $parts[] = 'Description : ' . $intro;
        }
    }
    $parts[] = 'URL : ' . $external;

    $remotetext = local_astusse_fetch_external_url_text($external);
    if ($remotetext !== null && mb_strlen($remotetext) > 20) {
        $parts[] = "\n---\n";
        $parts[] = 'Contenu de la page :';
        $parts[] = $remotetext;
    }

    $merged = implode("\n", $parts);
    $filename = clean_filename($cm->name) . '_url.txt';
    $tmppath = make_request_directory() . DIRECTORY_SEPARATOR . $filename;
    file_put_contents($tmppath, $merged);

    return [
        'filepath' => $tmppath,
        'filename' => $filename,
        'mimetype' => 'text/plain',
    ];
}

/**
 * Extract text and detect remote URLs from an AICC package.
 *
 * AICC packages carry metadata in .crs (INI), .des (CSV) and .au (CSV) files
 * and no HTML. Many AICC courses are thin wrappers around a remote video
 * (LinkedIn Learning, YouTube…); we at least surface the title + description
 * so the RAG knows the resource exists, and report the remote domain if any
 * Web_Launch URL is found.
 *
 * @param array $files Array of stored_file objects from the SCORM package.
 * @return array {texts: string[], remotedomain: string|null}
 */
function local_astusse_extract_aicc_content(array $files): array {
    $texts = [];
    $remotedomain = null;

    foreach ($files as $file) {
        $fname = strtolower($file->get_filename());
        $ext = pathinfo($fname, PATHINFO_EXTENSION);
        if (!in_array($ext, ['crs', 'au', 'des'], true)) {
            continue;
        }
        $raw = $file->get_content();
        if (trim($raw) === '') {
            continue;
        }

        if ($ext === 'crs') {
            // INI-like. Course_Title = ..., and [Course_Description] section.
            if (preg_match('/^\s*Course_Title\s*=\s*(.+?)\s*$/mi', $raw, $m)) {
                $texts[] = trim($m[1]);
            }
            if (preg_match('/\[Course_Description\]\s*(.*?)(?=\[|\z)/is', $raw, $m)) {
                $desc = trim(preg_replace('/\s+/', ' ', $m[1]));
                if ($desc !== '') {
                    $texts[] = $desc;
                }
            }
        } else if ($ext === 'des' || $ext === 'au') {
            // CSV with a header row then data rows.
            $lines = preg_split('/\r\n|\r|\n/', trim($raw));
            if (count($lines) < 2) {
                continue;
            }
            $header = str_getcsv(array_shift($lines));
            $headerlower = array_map('strtolower', $header);
            foreach ($lines as $line) {
                if (trim($line) === '') {
                    continue;
                }
                $row = str_getcsv($line);
                foreach ($headerlower as $idx => $col) {
                    if (!isset($row[$idx])) {
                        continue;
                    }
                    $value = trim($row[$idx]);
                    if ($value === '') {
                        continue;
                    }
                    if (in_array($col, ['title', 'description'], true) && mb_strlen($value) >= 3) {
                        $texts[] = $value;
                    }
                    if ($col === 'file_name' && preg_match('#^https?://#i', $value)) {
                        $parts = parse_url($value);
                        if (!empty($parts['host']) && $remotedomain === null) {
                            $remotedomain = $parts['host'];
                        }
                    }
                }
            }
        }
    }

    $texts = array_values(array_unique(array_filter($texts, static function ($t) {
        return mb_strlen($t) >= 3;
    })));

    return [
        'texts' => $texts,
        'remotedomain' => $remotedomain,
    ];
}

/**
 * Extract indexable text from an `mod_h5pactivity` activity.
 *
 * H5P content is packaged as a `.h5p` zip archive. The human-readable text is
 * stored in `content/content.json` inside the archive. We copy the package to
 * a temporary location, extract the JSON, and reuse the generic JSON text
 * walker (same logic as for SCORM Articulate Rise).
 *
 * @param stdClass $cm
 * @return array|null
 */
function local_astusse_extract_h5p_content(stdClass $cm): ?array {
    if (!class_exists('ZipArchive')) {
        return null;
    }

    $context = context_module::instance($cm->id);
    $fs = get_file_storage();
    $files = $fs->get_area_files(
        $context->id,
        'mod_h5pactivity',
        'package',
        0,
        'sortorder DESC, id ASC',
        false
    );
    $h5pfile = reset($files);
    if (!$h5pfile) {
        return null;
    }

    $tmpdir = make_request_directory();
    $tmpzip = $tmpdir . DIRECTORY_SEPARATOR . 'package.h5p';
    if (!$h5pfile->copy_content_to($tmpzip)) {
        return null;
    }

    $zip = new ZipArchive();
    if ($zip->open($tmpzip) !== true) {
        return null;
    }

    $contentjson = $zip->getFromName('content/content.json');
    $metadatajson = $zip->getFromName('h5p.json');
    $zip->close();

    $texts = [];

    if (is_string($metadatajson) && $metadatajson !== '') {
        $metadata = json_decode($metadatajson, true);
        if (is_array($metadata)) {
            foreach (['title', 'description'] as $metakey) {
                if (!empty($metadata[$metakey]) && is_string($metadata[$metakey])) {
                    $value = trim(preg_replace('/\s+/', ' ', strip_tags($metadata[$metakey])));
                    if ($value !== '') {
                        $texts[] = $value;
                    }
                }
            }
        }
    }

    if (is_string($contentjson) && $contentjson !== '') {
        $contenttexts = [];
        $decoded = json_decode($contentjson, true);
        if (is_array($decoded)) {
            local_astusse_collect_json_texts($decoded, $contenttexts);
        }
        if (!empty($contenttexts)) {
            $texts = array_merge($texts, $contenttexts);
        }
    }

    if (empty($texts)) {
        return null;
    }

    $merged = implode("\n", array_unique($texts));
    $filename = clean_filename($cm->name) . '_h5p.txt';
    $tmppath = make_request_directory() . DIRECTORY_SEPARATOR . $filename;
    file_put_contents($tmppath, $merged);

    return [
        'filepath' => $tmppath,
        'filename' => $filename,
        'mimetype' => 'text/plain',
    ];
}

/**
 * Extract file content from a resource (file) module.
 *
 * @param stdClass $cm
 * @return array|null
 */
function local_astusse_extract_resource_content(stdClass $cm): ?array {
    $context = context_module::instance($cm->id);
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_resource', 'content', 0, 'sortorder DESC, id ASC', false);
    $mainfile = reset($files);
    if (!$mainfile) {
        return null;
    }

    $filename = $mainfile->get_filename();
    $mimetype = $mainfile->get_mimetype();
    $tmppath = make_request_directory() . DIRECTORY_SEPARATOR . $filename;
    if (!$mainfile->copy_content_to($tmppath)) {
        return null;
    }

    return [
        'filepath' => $tmppath,
        'filename' => $filename,
        'mimetype' => $mimetype,
    ];
}

/**
 * Extract content from a page module as an HTML file.
 *
 * @param stdClass $cm
 * @return array|null
 */
function local_astusse_extract_page_content(stdClass $cm): ?array {
    global $DB;

    $page = $DB->get_record('page', ['id' => $cm->instance], 'id, name, content, contentformat', MUST_EXIST);
    $content = format_text($page->content, $page->contentformat, ['noclean' => true]);

    if (trim($content) === '') {
        return null;
    }

    $html = '<!DOCTYPE html><html><head><meta charset="utf-8">'
        . '<title>' . s($page->name) . '</title></head>'
        . '<body>' . $content . '</body></html>';

    $filename = clean_filename($page->name) . '.html';
    $tmppath = make_request_directory() . DIRECTORY_SEPARATOR . $filename;
    file_put_contents($tmppath, $html);

    return [
        'filepath' => $tmppath,
        'filename' => $filename,
        'mimetype' => 'text/html',
    ];
}

/**
 * Extract content from a book module by concatenating all visible chapters.
 *
 * @param stdClass $cm
 * @return array|null
 */
function local_astusse_extract_book_content(stdClass $cm): ?array {
    global $DB;

    $book = $DB->get_record('book', ['id' => $cm->instance], 'id, name, intro, introformat', MUST_EXIST);
    $chapters = $DB->get_records(
        'book_chapters',
        ['bookid' => $book->id, 'hidden' => 0],
        'pagenum ASC',
        'id, title, content, contentformat, subchapter'
    );

    if (empty($chapters)) {
        throw new \local_astusse\exception\permanent_extraction_exception(
            get_string('book:empty_no_chapters', 'local_astusse')
        );
    }

    $bodyparts = [];
    if (!empty($book->intro)) {
        $intro = format_text($book->intro, $book->introformat ?? FORMAT_HTML, ['noclean' => true]);
        if (trim(strip_tags($intro)) !== '') {
            $bodyparts[] = '<div class="book-intro">' . $intro . '</div>';
        }
    }

    foreach ($chapters as $chapter) {
        $tag = !empty($chapter->subchapter) ? 'h3' : 'h2';
        $bodyparts[] = '<' . $tag . '>' . s($chapter->title) . '</' . $tag . '>';
        $bodyparts[] = format_text($chapter->content, $chapter->contentformat ?? FORMAT_HTML, ['noclean' => true]);
    }

    $body = implode("\n", $bodyparts);
    if (trim(strip_tags($body)) === '') {
        return null;
    }

    $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . s($cm->name)
        . '</title></head><body><h1>' . s($cm->name) . '</h1>' . $body . '</body></html>';

    $filename = clean_filename($cm->name) . '_book.html';
    $tmppath = make_request_directory() . DIRECTORY_SEPARATOR . $filename;
    file_put_contents($tmppath, $html);

    return [
        'filepath' => $tmppath,
        'filename' => $filename,
        'mimetype' => 'text/html',
    ];
}

/**
 * Extract content from a glossary module by concatenating all approved entries.
 *
 * @param stdClass $cm
 * @return array|null
 */
function local_astusse_extract_glossary_content(stdClass $cm): ?array {
    global $DB;

    $glossary = $DB->get_record('glossary', ['id' => $cm->instance], 'id, name, intro, introformat', MUST_EXIST);
    $entries = $DB->get_records(
        'glossary_entries',
        ['glossaryid' => $glossary->id, 'approved' => 1],
        'concept ASC',
        'id, concept, definition, definitionformat'
    );

    if (empty($entries)) {
        throw new \local_astusse\exception\permanent_extraction_exception(
            get_string('glossary:empty_no_entries', 'local_astusse')
        );
    }

    $bodyparts = [];
    if (!empty($glossary->intro)) {
        $intro = format_text($glossary->intro, $glossary->introformat ?? FORMAT_HTML, ['noclean' => true]);
        if (trim(strip_tags($intro)) !== '') {
            $bodyparts[] = '<div class="glossary-intro">' . $intro . '</div>';
        }
    }

    $bodyparts[] = '<dl>';
    foreach ($entries as $entry) {
        $definition = format_text($entry->definition, $entry->definitionformat ?? FORMAT_HTML, ['noclean' => true]);
        $bodyparts[] = '<dt>' . s($entry->concept) . '</dt>';
        $bodyparts[] = '<dd>' . $definition . '</dd>';
    }
    $bodyparts[] = '</dl>';

    $body = implode("\n", $bodyparts);
    if (trim(strip_tags($body)) === '') {
        return null;
    }

    $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . s($cm->name)
        . '</title></head><body><h1>' . s($cm->name) . '</h1>' . $body . '</body></html>';

    $filename = clean_filename($cm->name) . '_glossary.html';
    $tmppath = make_request_directory() . DIRECTORY_SEPARATOR . $filename;
    file_put_contents($tmppath, $html);

    return [
        'filepath' => $tmppath,
        'filename' => $filename,
        'mimetype' => 'text/html',
    ];
}

/**
 * Extract content from a lesson module: page contents + correct answers (score > 0).
 *
 * Pages are emitted in the lesson's logical order by walking the prev/next linked list
 * from the entry page (prevpageid = 0). Skips structural qtypes (cluster boundaries,
 * end-of-branch markers).
 *
 * @param stdClass $cm
 * @return array|null
 */
function local_astusse_extract_lesson_content(stdClass $cm): ?array {
    global $DB;

    $lesson = $DB->get_record('lesson', ['id' => $cm->instance], 'id, name, intro, introformat', MUST_EXIST);
    $allpages = $DB->get_records(
        'lesson_pages',
        ['lessonid' => $lesson->id],
        'id ASC',
        'id, qtype, title, contents, contentsformat, prevpageid, nextpageid'
    );

    if (empty($allpages)) {
        throw new \local_astusse\exception\permanent_extraction_exception(
            get_string('lesson:empty_no_pages', 'local_astusse')
        );
    }

    $bypage = [];
    foreach ($allpages as $p) {
        $bypage[$p->id] = $p;
    }
    $ordered = [];
    $visited = [];
    foreach ($allpages as $p) {
        if ((int)$p->prevpageid === 0) {
            $current = $p;
            while ($current && !isset($visited[$current->id])) {
                $visited[$current->id] = true;
                $ordered[] = $current;
                $next = (int)$current->nextpageid;
                $current = $next > 0 && isset($bypage[$next]) ? $bypage[$next] : null;
            }
            break;
        }
    }
    // Append unvisited pages (alternative branches, clusters) at the end.
    foreach ($allpages as $p) {
        if (!isset($visited[$p->id])) {
            $ordered[] = $p;
        }
    }

    // ENDOFBRANCH=21, CLUSTER=30, ENDOFCLUSTER=31 are structural — no pedagogical content.
    $skipqtypes = [21, 30, 31];

    $bodyparts = [];
    if (!empty($lesson->intro)) {
        $intro = format_text($lesson->intro, $lesson->introformat ?? FORMAT_HTML, ['noclean' => true]);
        if (trim(strip_tags($intro)) !== '') {
            $bodyparts[] = '<div class="lesson-intro">' . $intro . '</div>';
        }
    }

    $correctlabel = get_string('lesson:correctanswerlabel', 'local_astusse');
    foreach ($ordered as $page) {
        if (in_array((int)$page->qtype, $skipqtypes, true)) {
            continue;
        }
        $bodyparts[] = '<h2>' . s($page->title) . '</h2>';
        if (!empty($page->contents)) {
            $bodyparts[] = format_text($page->contents, $page->contentsformat ?? FORMAT_HTML, ['noclean' => true]);
        }
        $answers = $DB->get_records(
            'lesson_answers',
            ['lessonid' => $lesson->id, 'pageid' => $page->id],
            'id ASC',
            'id, answer, answerformat, score'
        );
        $correct = [];
        foreach ($answers as $ans) {
            if ((int)$ans->score <= 0 || empty($ans->answer)) {
                continue;
            }
            $text = trim(preg_replace(
                '/\s+/',
                ' ',
                strip_tags(format_text($ans->answer, $ans->answerformat ?? FORMAT_HTML, ['noclean' => true]))
            ));
            if ($text !== '') {
                $correct[] = $text;
            }
        }
        if (!empty($correct)) {
            $bodyparts[] = '<p><strong>' . $correctlabel . '</strong> '
                . s(implode(' / ', $correct)) . '</p>';
        }
    }

    $body = implode("\n", $bodyparts);
    if (trim(strip_tags($body)) === '') {
        return null;
    }

    $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . s($cm->name)
        . '</title></head><body><h1>' . s($cm->name) . '</h1>' . $body . '</body></html>';

    $filename = clean_filename($cm->name) . '_lesson.html';
    $tmppath = make_request_directory() . DIRECTORY_SEPARATOR . $filename;
    file_put_contents($tmppath, $html);

    return [
        'filepath' => $tmppath,
        'filename' => $filename,
        'mimetype' => 'text/html',
    ];
}

/**
 * Extract content from a quiz module: question texts paired with their correct answers.
 *
 * Skips `random` qtypes (runtime placeholders) and `description` (no Q/A). For `match`,
 * pairs from qtype_match_subquestions are appended. The general feedback is included
 * when present (useful as model answer for essay-type questions).
 *
 * @param stdClass $cm
 * @return array|null
 */
function local_astusse_extract_quiz_content(stdClass $cm): ?array {
    global $DB;

    $quiz = $DB->get_record('quiz', ['id' => $cm->instance], 'id, name, intro, introformat', MUST_EXIST);

    // Resolve current questions through the Moodle 4.x question bank chain:
    // quiz_slots → question_references → question_versions (latest 'ready') → question.
    $sql = "SELECT qs.slot, qv.version, q.id AS questionid, q.qtype, q.name AS qname,
                   q.questiontext, q.questiontextformat,
                   q.generalfeedback, q.generalfeedbackformat
              FROM {quiz_slots} qs
              JOIN {question_references} qr
                ON qr.component = 'mod_quiz'
               AND qr.questionarea = 'slot'
               AND qr.itemid = qs.id
              JOIN {question_versions} qv
                ON qv.questionbankentryid = qr.questionbankentryid
               AND qv.status = 'ready'
               AND (qr.version IS NULL OR qv.version = qr.version)
              JOIN {question} q ON q.id = qv.questionid
             WHERE qs.quizid = :quizid
          ORDER BY qs.slot ASC, qv.version DESC";
    $rows = $DB->get_records_sql($sql, ['quizid' => $quiz->id]);
    if (empty($rows)) {
        throw new \local_astusse\exception\permanent_extraction_exception(
            get_string('quiz:empty_no_questions', 'local_astusse')
        );
    }

    // Deduplicate by slot (when version IS NULL we may get several rows per slot;
    // keep the highest version since rows are ordered by qv.version DESC).
    $byslot = [];
    foreach ($rows as $row) {
        $slotkey = (int)$row->slot;
        if (!isset($byslot[$slotkey])) {
            $byslot[$slotkey] = $row;
        }
    }
    ksort($byslot);

    $bodyparts = [];
    if (!empty($quiz->intro)) {
        $intro = format_text($quiz->intro, $quiz->introformat ?? FORMAT_HTML, ['noclean' => true]);
        if (trim(strip_tags($intro)) !== '') {
            $bodyparts[] = '<div class="quiz-intro">' . $intro . '</div>';
        }
    }

    $correctlabel = get_string('quiz:correctanswerlabel', 'local_astusse');
    $usablequestions = 0;
    foreach ($byslot as $slot => $row) {
        if ($row->qtype === 'random' || $row->qtype === 'description') {
            continue;
        }
        $usablequestions++;

        $bodyparts[] = '<h2>' . get_string('quiz:questionnumber', 'local_astusse', $slot) . '</h2>';
        $qtext = format_text($row->questiontext, $row->questiontextformat ?? FORMAT_HTML, ['noclean' => true]);
        $bodyparts[] = $qtext;

        $correct = [];

        $answers = $DB->get_records(
            'question_answers',
            ['question' => $row->questionid],
            'id ASC',
            'id, answer, answerformat, fraction'
        );
        foreach ($answers as $ans) {
            if ((float)$ans->fraction <= 0) {
                continue;
            }
            $text = trim(preg_replace(
                '/\s+/',
                ' ',
                strip_tags(format_text($ans->answer ?? '', $ans->answerformat ?? FORMAT_HTML, ['noclean' => true]))
            ));
            if ($text !== '') {
                $correct[] = $text;
            }
        }

        // Match-type questions store pairs in qtype_match_subquestions.
        if ($row->qtype === 'match') {
            $matches = $DB->get_records(
                'qtype_match_subquestions',
                ['questionid' => $row->questionid],
                'id ASC',
                'id, questiontext, questiontextformat, answertext'
            );
            foreach ($matches as $m) {
                $qpart = trim(preg_replace(
                    '/\s+/',
                    ' ',
                    strip_tags(format_text($m->questiontext ?? '', $m->questiontextformat ?? FORMAT_HTML, ['noclean' => true]))
                ));
                $apart = trim((string)$m->answertext);
                if ($qpart !== '' && $apart !== '') {
                    $correct[] = $qpart . ' → ' . $apart;
                }
            }
        }

        if (!empty($correct)) {
            $bodyparts[] = '<p><strong>' . $correctlabel . '</strong> '
                . s(implode(' / ', $correct)) . '</p>';
        }

        if (!empty($row->generalfeedback)) {
            $gf = format_text($row->generalfeedback, $row->generalfeedbackformat ?? FORMAT_HTML, ['noclean' => true]);
            if (trim(strip_tags($gf)) !== '') {
                $bodyparts[] = '<p><em>' . $gf . '</em></p>';
            }
        }
    }

    if ($usablequestions === 0) {
        throw new \local_astusse\exception\permanent_extraction_exception(
            get_string('quiz:empty_no_usable_questions', 'local_astusse')
        );
    }

    $body = implode("\n", $bodyparts);
    if (trim(strip_tags($body)) === '') {
        throw new \local_astusse\exception\permanent_extraction_exception(
            get_string('quiz:empty_no_usable_questions', 'local_astusse')
        );
    }

    $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . s($cm->name)
        . '</title></head><body><h1>' . s($cm->name) . '</h1>' . $body . '</body></html>';

    $filename = clean_filename($cm->name) . '_quiz.html';
    $tmppath = make_request_directory() . DIRECTORY_SEPARATOR . $filename;
    file_put_contents($tmppath, $html);

    return [
        'filepath' => $tmppath,
        'filename' => $filename,
        'mimetype' => 'text/html',
    ];
}

/**
 * Extract content from an assignment module: intro + activity instructions.
 *
 * @param stdClass $cm
 * @return array|null
 */
function local_astusse_extract_assign_content(stdClass $cm): ?array {
    global $DB;

    $assign = $DB->get_record(
        'assign',
        ['id' => $cm->instance],
        'id, name, intro, introformat, activity, activityformat',
        MUST_EXIST
    );

    $bodyparts = [];
    if (!empty($assign->intro)) {
        $intro = format_text($assign->intro, $assign->introformat ?? FORMAT_HTML, ['noclean' => true]);
        if (trim(strip_tags($intro)) !== '') {
            $bodyparts[] = '<div class="assign-intro">' . $intro . '</div>';
        }
    }
    if (!empty($assign->activity)) {
        $activity = format_text($assign->activity, $assign->activityformat ?? FORMAT_HTML, ['noclean' => true]);
        if (trim(strip_tags($activity)) !== '') {
            $bodyparts[] = '<div class="assign-activity">' . $activity . '</div>';
        }
    }

    if (empty($bodyparts)) {
        throw new \local_astusse\exception\permanent_extraction_exception(
            get_string('assign:empty_no_instructions', 'local_astusse')
        );
    }

    $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . s($cm->name)
        . '</title></head><body><h1>' . s($cm->name) . '</h1>'
        . implode("\n", $bodyparts) . '</body></html>';

    $filename = clean_filename($cm->name) . '_assign.html';
    $tmppath = make_request_directory() . DIRECTORY_SEPARATOR . $filename;
    file_put_contents($tmppath, $html);

    return [
        'filepath' => $tmppath,
        'filename' => $filename,
        'mimetype' => 'text/html',
    ];
}

/**
 * Extract content from a wiki module by concatenating all pages across all subwikis.
 *
 * Uses `cachedcontent` (Moodle's pre-rendered HTML) to avoid re-parsing creole/nwiki.
 *
 * @param stdClass $cm
 * @return array|null
 */
function local_astusse_extract_wiki_content(stdClass $cm): ?array {
    global $DB;

    $wiki = $DB->get_record('wiki', ['id' => $cm->instance], 'id, name, intro, introformat', MUST_EXIST);

    $sql = "SELECT wp.id, wp.title, wp.cachedcontent, wp.timecreated
              FROM {wiki_pages} wp
              JOIN {wiki_subwikis} wsw ON wsw.id = wp.subwikiid
             WHERE wsw.wikiid = :wikiid
          ORDER BY wp.timecreated ASC, wp.id ASC";
    $pages = $DB->get_records_sql($sql, ['wikiid' => $wiki->id]);

    if (empty($pages)) {
        throw new \local_astusse\exception\permanent_extraction_exception(
            get_string('wiki:empty_no_pages', 'local_astusse')
        );
    }

    $bodyparts = [];
    if (!empty($wiki->intro)) {
        $intro = format_text($wiki->intro, $wiki->introformat ?? FORMAT_HTML, ['noclean' => true]);
        if (trim(strip_tags($intro)) !== '') {
            $bodyparts[] = '<div class="wiki-intro">' . $intro . '</div>';
        }
    }

    foreach ($pages as $page) {
        $cached = (string)($page->cachedcontent ?? '');
        if (trim(strip_tags($cached)) === '') {
            continue;
        }
        $bodyparts[] = '<h2>' . s($page->title) . '</h2>';
        $bodyparts[] = $cached;
    }

    $body = implode("\n", $bodyparts);
    if (trim(strip_tags($body)) === '') {
        throw new \local_astusse\exception\permanent_extraction_exception(
            get_string('wiki:empty_no_pages', 'local_astusse')
        );
    }

    $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . s($cm->name)
        . '</title></head><body><h1>' . s($cm->name) . '</h1>' . $body . '</body></html>';

    $filename = clean_filename($cm->name) . '_wiki.html';
    $tmppath = make_request_directory() . DIRECTORY_SEPARATOR . $filename;
    file_put_contents($tmppath, $html);

    return [
        'filepath' => $tmppath,
        'filename' => $filename,
        'mimetype' => 'text/html',
    ];
}

/**
 * List the visible files contained in a folder activity, ready to be expanded into
 * one ingestion job per file.
 *
 * @param int $cmid Course-module id of the folder activity.
 * @return array[] Each entry: ['fileid' => int, 'filename' => string, 'mimetype' => string, 'filesize' => int]
 */
function local_astusse_list_folder_files(int $cmid): array {
    $context = context_module::instance($cmid);
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_folder', 'content', 0, 'filepath, filename', false);
    $entries = [];
    foreach ($files as $file) {
        if ($file->is_directory()) {
            continue;
        }
        $entries[] = [
            'fileid' => (int)$file->get_id(),
            'filename' => $file->get_filename(),
            'mimetype' => (string)$file->get_mimetype(),
            'filesize' => (int)$file->get_filesize(),
        ];
    }
    return $entries;
}

/**
 * Extract a single file from a folder activity, identified by the stored_file id
 * carried in the job's `fileareaitemid` column.
 *
 * Folder activities expand into N jobs at queue time (one per file), so each job
 * resolves to exactly one binary that is forwarded as-is to the backend.
 *
 * @param stdClass $cm
 * @param stdClass|null $job The job row, must carry `fileareaitemid` = stored_file id.
 * @return array|null
 */
function local_astusse_extract_folder_content(stdClass $cm, ?\stdClass $job = null): ?array {
    if ($job === null || empty($job->fileareaitemid)) {
        throw new \local_astusse\exception\permanent_extraction_exception(
            get_string('folder:missing_fileid', 'local_astusse')
        );
    }

    $fileid = (int)$job->fileareaitemid;
    $context = context_module::instance($cm->id);
    $fs = get_file_storage();

    // Iterate the folder's content area (same call site as list_folder_files) and
    // match by stored_file id. Falls back to matching by filename to recover from
    // a folder that was repackaged between queue and run.
    $files = $fs->get_area_files($context->id, 'mod_folder', 'content', 0, 'filepath, filename', false);
    $expectedname = (string)($job->filename ?? '');
    $file = null;
    $namematch = null;
    foreach ($files as $candidate) {
        if ($candidate->is_directory()) {
            continue;
        }
        if ((int)$candidate->get_id() === $fileid) {
            $file = $candidate;
            break;
        }
        if ($expectedname !== '' && $candidate->get_filename() === $expectedname && $namematch === null) {
            $namematch = $candidate;
        }
    }
    if ($file === null && $namematch !== null) {
        $file = $namematch;
    }

    if ($file === null) {
        $detail = $expectedname !== '' ? $expectedname : ('id=' . $fileid);
        throw new \local_astusse\exception\permanent_extraction_exception(
            get_string('folder:file_missing', 'local_astusse', $detail)
        );
    }

    $filename = $file->get_filename();
    $tmppath = make_request_directory() . DIRECTORY_SEPARATOR . clean_filename($filename);
    if (!$file->copy_content_to($tmppath)) {
        throw new \Exception('Unable to copy folder file to temp path');
    }

    return [
        'filepath' => $tmppath,
        'filename' => $filename,
        'mimetype' => $file->get_mimetype(),
    ];
}

/**
 * Extract indexable content from a SCORM package.
 *
 * Modern SCORM packages often have empty HTML shells that load content via JS.
 * This function extracts text from multiple sources:
 * 1. HTML files with real visible text (not just scripts/styles)
 * 2. JSON data files (common in Articulate, iSpring, etc.)
 * 3. XML content files
 * 4. Plain text files
 *
 * @param stdClass $cm
 * @return array|null
 */
function local_astusse_extract_scorm_content(stdClass $cm): ?array {
    $context = context_module::instance($cm->id);
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_scorm', 'content', 0, 'filepath, filename', false);

    // First pass: detect Articulate Rise base64 payload.
    // Three known patterns across Rise versions:
    // 1. und.js (old Rise):     __resolveJsonp("course:und","<base64>")
    // 2. index.html (old Rise): window.courseData = "<base64>"
    // 3. index.html (Rise 360): deserialize("<base64>") inside an inline <script>
    // (the script also defines `function deserialize(str){ atob; JSON.parse }`)
    // If found, use only that content — it contains the entire course text.
    foreach ($files as $file) {
        $raw = $file->get_content();
        $base64payload = null;

        if (preg_match('#__resolveJsonp\s*\(\s*"[^"]*"\s*,\s*"([A-Za-z0-9+/=]{100,})"#', $raw, $jsonpmatch)) {
            $base64payload = $jsonpmatch[1];
        } else if (preg_match('#window\.courseData\s*=\s*"([A-Za-z0-9+/=]{100,})"#', $raw, $coursedatamatch)) {
            $base64payload = $coursedatamatch[1];
        } else if (preg_match('#deserialize\s*\(\s*"([A-Za-z0-9+/=]{100,})"#', $raw, $desermatch)) {
            $base64payload = $desermatch[1];
        }

        if ($base64payload !== null) {
            $decoded = base64_decode($base64payload, true);
            if ($decoded !== false) {
                $text = local_astusse_extract_text_from_json($decoded);
                if (mb_strlen($text) >= 10) {
                    $filename = clean_filename($cm->name) . '_scorm.txt';
                    $tmppath = make_request_directory() . DIRECTORY_SEPARATOR . $filename;
                    file_put_contents($tmppath, $text);
                    return [
                        'filepath' => $tmppath,
                        'filename' => $filename,
                        'mimetype' => 'text/plain',
                    ];
                }
            }
        }
    }

    // Second pass: detect Articulate Storyline.
    // Storyline packages contain `.js` files using the pattern:
    // window.globalProvideData('<key>', '<JSON>')
    // Only the 'slide' and 'data' blobs hold pedagogical text; 'frame' and
    // 'paths' carry the player UI strings and SVG paths, which we must skip.
    $storylinekeys = ['slide', 'data'];
    $storylinetexts = [];
    foreach ($files as $file) {
        $fname = strtolower($file->get_filename());
        if (pathinfo($fname, PATHINFO_EXTENSION) !== 'js') {
            continue;
        }
        $raw = $file->get_content();
        if (strpos($raw, 'globalProvideData') === false) {
            continue;
        }
        if (
            !preg_match_all(
                "/globalProvideData\s*\(\s*'([^']+)'\s*,\s*'(.*?)'\s*\)/s",
                $raw,
                $gpdmatches
            )
        ) {
            continue;
        }
        foreach ($gpdmatches[1] as $idx => $gpdkey) {
            if (!in_array($gpdkey, $storylinekeys, true)) {
                continue;
            }
            $jsonstr = stripslashes($gpdmatches[2][$idx]);
            $decoded = json_decode($jsonstr, true);
            if (!is_array($decoded)) {
                continue;
            }
            local_astusse_collect_json_texts($decoded, $storylinetexts);
        }
    }
    if (!empty($storylinetexts)) {
        $storylinetexts = array_values(array_unique($storylinetexts));
        $merged = implode("\n", $storylinetexts);
        $filename = clean_filename($cm->name) . '_scorm.txt';
        $tmppath = make_request_directory() . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($tmppath, $merged);
        return [
            'filepath' => $tmppath,
            'filename' => $filename,
            'mimetype' => 'text/plain',
        ];
    }

    // Third pass: generic SCORM extraction for other packages.
    $textparts = [];
    $skippatterns = ['/lib/', '/vendor/', '/jquery', '/scorm_support/', '/tincan/', '/lms/'];
    $skipfiles = ['imsmanifest.xml', 'lms.js', 'scormdriver.js', 'player.js',
        'frame.js', 'app.js', 'tc-config.js', 'configuration.js'];

    foreach ($files as $file) {
        $fname = strtolower($file->get_filename());
        $fpath = strtolower($file->get_filepath() . $fname);
        $ext = pathinfo($fname, PATHINFO_EXTENSION);

        // Skip library/framework files.
        $skip = false;
        foreach ($skippatterns as $pattern) {
            if (strpos($fpath, $pattern) !== false) {
                $skip = true;
                break;
            }
        }
        if ($skip || in_array($fname, $skipfiles, true)) {
            continue;
        }

        $raw = $file->get_content();
        if (trim($raw) === '') {
            continue;
        }

        if (in_array($ext, ['html', 'htm'], true)) {
            // First try visible text.
            $text = local_astusse_extract_text_from_html($raw);
            // Skip generic "JavaScript is required" boilerplate that React apps
            // emit by default — this is a signal of a SCORM-proxy shell, not real content.
            $normalized = mb_strtolower($text);
            $isnoscriptonly = preg_match('/\b(enable|requires?|needs?)\s+javascript\b/', $normalized)
                && mb_strlen($text) < 120;
            if (!$isnoscriptonly && mb_strlen($text) >= 20) {
                $textparts[] = $text;
            }
            // Also extract JSON data embedded in <script> tags (Articulate Rise, etc.).
            if (preg_match_all('#<script[^>]*>(.*?)</script>#is', $raw, $scriptmatches)) {
                foreach ($scriptmatches[1] as $scriptbody) {
                    // Try direct JSON parse.
                    $jsontext = local_astusse_extract_text_from_json($scriptbody);
                    if (mb_strlen($jsontext) >= 20) {
                        $textparts[] = $jsontext;
                    }
                    // Try JS-embedded JSON (var x = {...}).
                    $jstext = local_astusse_extract_text_from_js($scriptbody);
                    if (mb_strlen($jstext) >= 20) {
                        $textparts[] = $jstext;
                    }
                }
            }
        } else if ($ext === 'json') {
            $text = local_astusse_extract_text_from_json($raw);
            if (mb_strlen($text) >= 20) {
                $textparts[] = $text;
            }
        } else if ($ext === 'js') {
            // Articulate Storyline or other JS content files.
            $text = local_astusse_extract_text_from_js($raw);
            if (mb_strlen($text) >= 20) {
                $textparts[] = $text;
            }
        } else if ($ext === 'xml') {
            $text = strip_tags($raw);
            $text = preg_replace('/\s+/', ' ', $text);
            $text = trim($text);
            if (mb_strlen($text) >= 50) {
                $textparts[] = $text;
            }
        } else if ($ext === 'txt') {
            if (mb_strlen(trim($raw)) >= 20) {
                $textparts[] = trim($raw);
            }
        }
    }

    // If the generic pass found nothing, try two additional fallbacks:
    // (a) AICC packages (.crs / .au / .des files, no HTML)
    // (b) SCORM-proxy detection (local .html loading a remote <script src>).
    if (empty($textparts)) {
        $aicc = local_astusse_extract_aicc_content($files);
        if (!empty($aicc['texts'])) {
            // We found at least the title/description: use it as minimal content,
            // but if the .au points to an external URL the index will be thin.
            $textparts = $aicc['texts'];
            if ($aicc['remotedomain'] !== null) {
                $textparts[] = get_string(
                    'ingest:aicc_remote_hint',
                    'local_astusse',
                    $aicc['remotedomain']
                );
            }
        } else {
            $remotedomain = null;
            if ($aicc['remotedomain'] !== null) {
                // AICC pointing to a remote URL but with no readable title either.
                $remotedomain = $aicc['remotedomain'];
            } else {
                // SCORM-shell: HTML loading content from a remote script.
                foreach ($files as $file) {
                    $fname = strtolower($file->get_filename());
                    if (!in_array(pathinfo($fname, PATHINFO_EXTENSION), ['html', 'htm'], true)) {
                        continue;
                    }
                    $raw = $file->get_content();
                    if (
                        preg_match(
                            '#<script[^>]+src\s*=\s*["\']https?://([^/"\'\s]+)#i',
                            $raw,
                            $srcmatch
                        )
                    ) {
                        $remotedomain = $srcmatch[1];
                        break;
                    }
                }
            }
            if ($remotedomain !== null) {
                throw new \local_astusse\exception\permanent_extraction_exception(
                    get_string('ingest:scorm_proxy_detected', 'local_astusse', $remotedomain)
                );
            }
            return null;
        }
    }

    // Deduplicate and merge.
    $textparts = array_unique($textparts);
    $merged = implode("\n\n---\n\n", $textparts);

    $filename = clean_filename($cm->name) . '_scorm.txt';
    $tmppath = make_request_directory() . DIRECTORY_SEPARATOR . $filename;
    file_put_contents($tmppath, $merged);

    return [
        'filepath' => $tmppath,
        'filename' => $filename,
        'mimetype' => 'text/plain',
    ];
}

/**
 * Extract visible text from HTML using DOM parsing.
 *
 * Walks the DOM tree and collects text nodes, skipping script, style,
 * svg, head, and other non-visible elements. This is the correct approach
 * instead of regex on HTML.
 *
 * @param string $html
 * @return string
 */
function local_astusse_extract_text_from_html(string $html): string {
    if (trim($html) === '') {
        return '';
    }

    $doc = new DOMDocument();
    // Suppress warnings for malformed HTML. Force UTF-8.
    @$doc->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);

    $texts = [];
    local_astusse_walk_dom_for_text($doc->documentElement, $texts);

    $result = implode(' ', $texts);
    // Decode remaining HTML entities (e.g. &amp; &eacute; &#39;).
    $result = html_entity_decode($result, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // Replace non-breaking spaces and other Unicode whitespace with regular spaces.
    $result = str_replace(["\xC2\xA0", "\xE2\x80\xAF", "\xE2\x80\x89"], ' ', $result);
    // Collapse whitespace.
    $result = preg_replace('/\s+/', ' ', $result);
    return trim($result);
}

/**
 * Recursively walk a DOMNode tree and collect visible text.
 *
 * Skips: script, style, svg, head, noscript, iframe, object, embed, canvas.
 *
 * @param DOMNode|null $node
 * @param array $texts
 * @return void
 */
function local_astusse_walk_dom_for_text(?DOMNode $node, array &$texts): void {
    if ($node === null) {
        return;
    }

    // Skip invisible/non-content elements.
    $skiptags = ['script', 'style', 'svg', 'head', 'noscript', 'iframe', 'object', 'embed', 'canvas', 'meta', 'link'];
    if ($node instanceof DOMElement && in_array(strtolower($node->tagName), $skiptags, true)) {
        return;
    }

    // Collect text nodes.
    if ($node instanceof DOMText) {
        $text = trim($node->textContent);
        if ($text !== '') {
            $texts[] = $text;
        }
        return;
    }

    // Recurse into children.
    if ($node->hasChildNodes()) {
        foreach ($node->childNodes as $child) {
            local_astusse_walk_dom_for_text($child, $texts);
        }
    }
}

/**
 * Extract text values from a JSON string.
 *
 * Walks the JSON structure and collects string values that look like
 * human-readable content. Uses DOM parsing for any HTML fragments found.
 *
 * @param string $json
 * @return string
 */
function local_astusse_extract_text_from_json(string $json): string {
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return '';
    }

    $texts = [];
    local_astusse_collect_json_texts($data, $texts);

    return implode("\n", $texts);
}

/**
 * Recursively collect human-readable strings from a decoded JSON structure.
 *
 * Filters out: short strings, URLs, hex sequences, base64, coordinates,
 * file paths, CSS values, and other non-content data.
 *
 * @param mixed $data Decoded JSON value (scalar, array or object) to walk.
 * @param array $texts Accumulator that collected strings are appended to, by reference.
 * @param string|null $key Key of the current value within its parent, used to skip non-content fields.
 * @return void
 */
function local_astusse_collect_json_texts($data, array &$texts, ?string $key = null): void {
    if (is_string($data)) {
        // Only extract values from known content keys.
        $contentkeys = [
            // Shared content keys (SCORM Rise, generic JSON).
            'title', 'heading', 'paragraph', 'description', 'caption',
            'feedback', 'text', 'content', 'body', 'label', 'alt',
            'matchTitle',
            // H5P pedagogical keys (Dialog Cards, Quiz, Question Set, etc.).
            'answer', 'question', 'correctAnswer', 'hint', 'tip',
            'summary', 'intro', 'introduction', 'statement', 'explanation',
            'taskDescription',
            // Articulate Storyline: on-screen text is stored as object altText.
            'altText',
        ];
        if ($key !== null && !in_array($key, $contentkeys, true)) {
            return;
        }

        $clean = $data;
        // If value contains HTML tags, parse with DOM.
        if (preg_match('/<[a-z]/i', $clean)) {
            $clean = local_astusse_extract_text_from_html($clean);
        }
        $clean = preg_replace('/\s+/', ' ', trim($clean));

        if (!local_astusse_is_human_readable_text($clean)) {
            return;
        }
        $texts[] = $clean;
        return;
    }

    if (is_array($data)) {
        foreach ($data as $k => $value) {
            local_astusse_collect_json_texts($value, $texts, is_string($k) ? $k : $key);
        }
    }
}

/**
 * Determine if a string looks like human-readable text vs technical data.
 *
 * @param string $text
 * @return bool
 */
function local_astusse_is_human_readable_text(string $text): bool {
    // Too short.
    if (mb_strlen($text) < 8) {
        return false;
    }
    // URL.
    if (preg_match('#^https?://#i', $text)) {
        return false;
    }
    // Hex/encoded data.
    if (preg_match('/^[a-f0-9]{12,}$/i', $text)) {
        return false;
    }
    // Base64 data URI.
    if (preg_match('/^data:/i', $text)) {
        return false;
    }
    // File path or asset reference.
    if (preg_match('#\.(png|jpg|jpeg|gif|svg|mp3|mp4|wav|woff|ttf|eot|css|js)$#i', $text)) {
        return false;
    }
    // Numeric coordinates or dimensions (e.g. "117.250046,43.499996").
    if (preg_match('/^[\d.,\s\-]+$/', $text)) {
        return false;
    }
    // CSS-like values (e.g. "#FFFFFF", "rgb(", "0px").
    if (preg_match('/^#[a-f0-9]{3,8}$/i', $text) || preg_match('/^\d+px/', $text)) {
        return false;
    }
    // Code identifiers: camelCase, snake_case without spaces.
    if (mb_strlen($text) < 30 && !preg_match('/\s/', $text) && preg_match('/[A-Z_.]/', $text)) {
        return false;
    }
    // Alphanumeric IDs without spaces (e.g. "ckps1fgq0002w3a6g94t02sd9", "auth0|5c6bd...").
    if (!preg_match('/\s/', $text) && preg_match('/^[a-zA-Z0-9|_\-+\/=.]+$/', $text)) {
        return false;
    }
    // SVG path data.
    if (preg_match('/^[MLHVCSQTAZ\d.,\s\-]+$/i', $text) && !preg_match('/[a-z]{3,}/i', $text)) {
        return false;
    }
    // Must contain at least one letter and at least one space (sentence-like).
    if (!preg_match('/[a-zA-ZÀ-ÿ]/', $text)) {
        return false;
    }
    if (!preg_match('/\s/', $text) && mb_strlen($text) < 40) {
        return false;
    }

    return true;
}

/**
 * Extract text content from a JavaScript file.
 *
 * Handles patterns in SCORM authoring tools:
 * 1. JSON data embedded in JS variables or function calls
 * 2. HTML fragments in string literals
 * 3. Sentence-like quoted strings
 *
 * @param string $js
 * @return string
 */
function local_astusse_extract_text_from_js(string $js): string {
    $texts = [];

    // Extract HTML fragments from string literals and parse with DOM.
    if (preg_match_all('#["\'](<[a-z][^"\']{20,})["\']#is', $js, $htmlmatches)) {
        foreach ($htmlmatches[1] as $htmlfragment) {
            $clean = local_astusse_extract_text_from_html($htmlfragment);
            if (mb_strlen($clean) >= 10 && local_astusse_is_human_readable_text($clean)) {
                $texts[] = $clean;
            }
        }
    }

    // Extract longer quoted strings that look like sentences.
    if (preg_match_all('#["\']([^"\']{30,}?)["\']#s', $js, $strmatches)) {
        foreach ($strmatches[1] as $str) {
            $clean = trim($str);
            // If it contains HTML, parse it.
            if (preg_match('/<[a-z]/i', $clean)) {
                $clean = local_astusse_extract_text_from_html($clean);
            }
            if (local_astusse_is_human_readable_text($clean)) {
                $texts[] = $clean;
            }
        }
    }

    $texts = array_unique($texts);
    return implode("\n", $texts);
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

    uasort($available, static function (array $left, array $right): int {
        return strnatcasecmp($left['fullname'], $right['fullname']);
    });

    return array_values($available);
}

/**
 * Return the Moodle-side ingestion upload size cap in bytes.
 *
 * The authoritative limit is enforced by the orchestration backend via
 * `SPRING_SERVLET_MULTIPART_MAX_FILE_SIZE` (default 50MB). This constant is the
 * matching pre-check at the Moodle boundary so a file that would be rejected by
 * the backend does not travel through the upload → filearea → cron pipeline.
 *
 * Change this value only if the backend limit is raised above 50MB. When the
 * backend limit is lowered, the backend will return HTTP 413 and jobs will be
 * marked as failed immediately — no client change required.
 *
 * @return int Size in bytes.
 */
function local_astusse_get_ingest_max_upload_bytes(): int {
    return 50 * 1024 * 1024;
}

/**
 * Return a user-friendly error message for a gateway HTTP status.
 *
 * @param int $status
 * @return string
 */
function local_astusse_ingest_http_error_message(int $status): string {
    switch ($status) {
        case 400:
            return get_string('ingest:error_http_400', 'local_astusse');
        case 401:
            return get_string('ingest:error_http_401', 'local_astusse');
        case 403:
            return get_string('ingest:error_http_403', 'local_astusse');
        case 404:
            return get_string('ingest:error_http_404', 'local_astusse');
        case 408:
            return get_string('ingest:error_http_408', 'local_astusse');
        case 413:
            return get_string('ingest:error_http_413', 'local_astusse');
        case 429:
            return get_string('ingest:error_http_429', 'local_astusse');
        case 500:
        case 502:
        case 503:
        case 504:
            return get_string('ingest:error_http_5xx', 'local_astusse');
        default:
            if ($status > 0) {
                return get_string('ingest:error_http_unknown', 'local_astusse', (string)$status);
            }
            return get_string('ingest:error_submit', 'local_astusse');
    }
}

/**
 * Insert an ingestion job row and enqueue the matching ad-hoc task.
 *
 * @param array $fields Row values for {local_astusse_ingest_jobs}.
 *                      Must include: userid, courseid, targetcourseids (array of int),
 *                      sourcetype, filename, mimetype, filesize.
 *                      May include: sourcecmid, fileareaitemid.
 * @return int Newly created job id.
 */
function local_astusse_create_ingest_job(array $fields): int {
    global $DB;

    $now = time();
    $record = (object)[
        'userid' => (int)($fields['userid'] ?? 0),
        'courseid' => (int)($fields['courseid'] ?? 0),
        'targetcourseids' => implode(',', array_map('intval', (array)($fields['targetcourseids'] ?? []))),
        'sourcetype' => (string)($fields['sourcetype'] ?? ''),
        'sourcecmid' => isset($fields['sourcecmid']) ? (int)$fields['sourcecmid'] : null,
        'fileareaitemid' => isset($fields['fileareaitemid']) ? (int)$fields['fileareaitemid'] : null,
        'filename' => (string)($fields['filename'] ?? ''),
        'mimetype' => (string)($fields['mimetype'] ?? 'application/octet-stream'),
        'filesize' => (int)($fields['filesize'] ?? 0),
        'status' => 'queued',
        'attempts' => 0,
        'timecreated' => $now,
    ];

    $jobid = (int)$DB->insert_record('local_astusse_ingest_jobs', $record);

    $task = new \local_astusse\task\ingest_document_task();
    $task->set_custom_data(['jobid' => $jobid]);
    $task->set_userid($record->userid);
    \core\task\manager::queue_adhoc_task($task);

    return $jobid;
}

/**
 * Persist an uploaded draft file into the `local_astusse/ingestqueue` file area
 * keyed by the given job id, then update the job row with `fileareaitemid`.
 *
 * @param \stored_file $draftfile The source file in the draft area.
 * @param int $jobid Job id to use as the target itemid.
 * @param int $userid Owner of the destination user context.
 * @return void
 */
function local_astusse_store_ingest_upload(\stored_file $draftfile, int $jobid, int $userid): void {
    global $DB;

    $usercontext = context_user::instance($userid);
    $fs = get_file_storage();

    $existing = $fs->get_area_files(
        $usercontext->id,
        'local_astusse',
        \local_astusse\task\ingest_document_task::FILEAREA,
        $jobid,
        'id',
        false
    );
    if (!empty($existing)) {
        $fs->delete_area_files(
            $usercontext->id,
            'local_astusse',
            \local_astusse\task\ingest_document_task::FILEAREA,
            $jobid
        );
    }

    $filerecord = (object)[
        'contextid' => $usercontext->id,
        'component' => 'local_astusse',
        'filearea' => \local_astusse\task\ingest_document_task::FILEAREA,
        'itemid' => $jobid,
        'filepath' => '/',
        'filename' => $draftfile->get_filename(),
    ];
    $fs->create_file_from_storedfile($filerecord, $draftfile);

    $DB->update_record('local_astusse_ingest_jobs', (object)[
        'id' => $jobid,
        'fileareaitemid' => $jobid,
    ]);
}

/**
 * Return a one-line summary of a job for display purposes.
 *
 * @param \stdClass $job
 * @return array Associative array with 'statuslabel', 'statusclass', 'sourcelabel', 'courses'.
 */
function local_astusse_describe_ingest_job(\stdClass $job): array {
    $statuslabels = [
        'queued' => get_string('jobs:status_queued', 'local_astusse'),
        'running' => get_string('jobs:status_running', 'local_astusse'),
        'succeeded' => get_string('jobs:status_succeeded', 'local_astusse'),
        'failed' => get_string('jobs:status_failed', 'local_astusse'),
    ];
    $statusclasses = [
        'queued' => 'badge badge-secondary',
        'running' => 'badge badge-info',
        'succeeded' => 'badge badge-success',
        'failed' => 'badge badge-danger',
    ];
    $sourcelabels = [
        'upload' => get_string('jobs:source_upload', 'local_astusse'),
        'resource' => get_string('ingest:course_resources_type_resource', 'local_astusse'),
        'page' => get_string('ingest:course_resources_type_page', 'local_astusse'),
        'scorm' => get_string('ingest:course_resources_type_scorm', 'local_astusse'),
        'h5pactivity' => get_string('ingest:course_resources_type_h5pactivity', 'local_astusse'),
        'url' => get_string('ingest:course_resources_type_url', 'local_astusse'),
        'book' => get_string('ingest:course_resources_type_book', 'local_astusse'),
        'glossary' => get_string('ingest:course_resources_type_glossary', 'local_astusse'),
        'lesson' => get_string('ingest:course_resources_type_lesson', 'local_astusse'),
        'quiz' => get_string('ingest:course_resources_type_quiz', 'local_astusse'),
        'assign' => get_string('ingest:course_resources_type_assign', 'local_astusse'),
        'wiki' => get_string('ingest:course_resources_type_wiki', 'local_astusse'),
        'folder' => get_string('ingest:course_resources_type_folder', 'local_astusse'),
    ];

    $status = (string)$job->status;
    $sourcetype = (string)$job->sourcetype;
    $targets = array_values(array_filter(array_map('intval', explode(',', (string)$job->targetcourseids))));

    return [
        'statuslabel' => $statuslabels[$status] ?? $status,
        'statusclass' => $statusclasses[$status] ?? 'badge badge-secondary',
        'sourcelabel' => $sourcelabels[$sourcetype] ?? $sourcetype,
        'courses' => $targets,
    ];
}

/**
 * T2 : inject the spaced-repetition pop-up loader on the first page rendered
 * after login (typically the dashboard /my).
 *
 * Invoked from the core\hook\output\before_footer_html_generation hook during
 * footer generation (see classes/hook_callbacks.php). The login observer arms a
 * session flag ; we consume it here so the loader is injected once per login.
 * The actual eligibility check happens asynchronously in popup_check.php, so the
 * page render never waits on the AI API.
 *
 * @return void JS is added via $PAGE->requires.
 */
function local_astusse_inject_review_popup(): void {
    global $PAGE, $SESSION, $CFG;

    // Real logged-in users only, on standard HTML pages.
    if (!isloggedin() || isguestuser()) {
        return;
    }
    if (CLI_SCRIPT || (defined('AJAX_SCRIPT') && AJAX_SCRIPT) || (defined('WS_SERVER') && WS_SERVER)) {
        return;
    }

    // T5 (bypass post-snooze) : on injecte le JS dans deux cas :
    // (a) 1ere page apres login (flag pose par login_observer, comportement T2 historique)
    // (b) une snooze "Plus tard" posee aujourd'hui a expire — le JS doit pouvoir
    // redemander un pop-up sans necessiter logout/login.
    //
    // La pref local_astusse_review_snooze_until est posee par review_snooze.php au
    // clic Plus tard (timestamp UNIX = now + 4h). On l'efface quand l'injection est
    // declenchee par ce mecanisme pour eviter de re-injecter en boucle. Si le user
    // re-clique Plus tard, la pref sera reecrite.
    $flagset = !empty($SESSION->{\local_astusse\observer\login_observer::SESSION_FLAG});
    $snoozeuntil = (int)get_user_preferences('local_astusse_review_snooze_until', 0);
    $snoozeexpired = ($snoozeuntil > 0 && $snoozeuntil <= time());

    if (!$flagset && !$snoozeexpired) {
        return;
    }
    if ($flagset) {
        unset($SESSION->{\local_astusse\observer\login_observer::SESSION_FLAG});
    }
    if ($snoozeexpired) {
        unset_user_preference('local_astusse_review_snooze_until');
    }

    // Global opt-out (preference managed in T5 ; default off).
    if (get_user_preferences('local_astusse_review_optout', 0)) {
        return;
    }

    // The loader reads M.cfg.wwwroot + M.cfg.sesskey itself, so no extra config
    // is passed here (avoids a JS init race). Loaded in the footer = DOM ready.
    // T3 etape 6 fix : les strings du quiz/bilan sont inlinees dans la reponse
    // de popup_check.php (champ "strings"), pas via M.str -- evite les cache issues
    // entre jsrev Moodle et cache navigateur sur le bundle strings JS.
    // Cache buster via ?v=<version> : force le navigateur a re-fetcher a chaque
    // bump de version.php. Les strings du quiz sont inlinees dans la reponse de
    // popup_check.php (champ "strings"), donc aucune dependance a M.str / cache
    // navigateur sur le bundle strings JS.
    $pluginversion = (string)get_config('local_astusse', 'version');
    // Loaded as a plain versioned script rather than an AMD module on purpose: it is a
    // self-contained pop-up with no AMD dependencies, and the explicit ?v=<version> cache
    // buster gives finer control over browser caching than the shared AMD bundle.
    $PAGE->requires->js(
        new moodle_url('/local/astusse/js/spaced_repetition_popup.js', ['v' => $pluginversion])
    );
}

/**
 * T3 etape 7 : compose un message brouillon pour le chat "Demander au tuteur"
 * a partir du contexte d'une session quiz.
 *
 * Appelle GET /api/review/quiz_context/{sessionId}. Si l'API est down, retourne
 * une chaine vide (defensif : on ouvre quand meme le chat).
 *
 * Format genere (FR) :
 *
 *   Aide-moi a comprendre ces points sur lesquels j'ai eu du mal lors du quiz :
 *   1. <prompt>
 *      Ma reponse : <userAnswer>
 *      (Reponse incorrecte / Reponse partielle)
 *   ...
 *
 * @param \stdClass $user
 * @param string    $quizsessionid
 * @return string  Brouillon a injecter dans le textarea, ou '' si rien d'exploitable.
 */
function local_astusse_build_quiz_tutor_draft(\stdClass $user, string $quizsessionid): string {
    try {
        $client = new \local_astusse\api_client();
        $result = $client->fetch_quiz_context_for_user($user, $quizsessionid);
    } catch (\Throwable $e) {
        debugging('local_astusse build_quiz_tutor_draft: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return '';
    }
    $status = (int)($result['status'] ?? 0);
    if ($status !== 200) {
        return '';
    }
    $body = is_array($result['body_json'] ?? null) ? $result['body_json'] : null;
    if (!$body || empty($body['entries'])) {
        return '';
    }

    // Focus sur les questions ratees ou en attente. Si tout est correct,
    // l'apprenant n'a probablement pas besoin du tuteur -- on retourne quand
    // meme un brouillon generique pour ne pas casser le flux.
    $failed = [];
    $pending = [];
    foreach ($body['entries'] as $e) {
        if (!isset($e['correct'])) {
            $pending[] = $e;
        } else if ($e['correct'] === false) {
            $failed[] = $e;
        }
    }
    $focus = array_merge($failed, $pending);
    if (empty($focus)) {
        return get_string('tutor:draft_intro_allcorrect', 'local_astusse');
    }

    $lines = [get_string('tutor:draft_intro', 'local_astusse'), ''];
    $i = 1;
    foreach ($focus as $e) {
        $prompt = trim((string)($e['prompt'] ?? ''));
        $useranswer = trim((string)($e['userAnswer'] ?? ''));
        $verdict = isset($e['correct']) && $e['correct'] === false
            ? get_string('tutor:draft_verdict_incorrect', 'local_astusse')
            : get_string('tutor:draft_verdict_pending', 'local_astusse');
        if ($prompt === '') {
            continue;
        }
        $lines[] = $i . '. ' . $prompt;
        if ($useranswer !== '') {
            $lines[] = '   ' . get_string(
                'tutor:draft_my_answer',
                'local_astusse',
                (object)['answer' => $useranswer]
            );
        }
        $lines[] = '   ' . $verdict;
        $lines[] = '';
        $i++;
    }
    return rtrim(implode("\n", $lines));
}

/**
 * T3 etape 6 : resout les titres Moodle (cours + ressource + URL) pour une liste
 * de cmids, en un seul aller-retour DB. Renvoyer un map cmid => [name, course, url].
 *
 * Defensive : un cmid invalide / inaccessible donne simplement un fallback "Resource #N".
 *
 * @param int[] $cmids
 * @return array<int, array{name:string, course:string, url:string}>
 */
function local_astusse_resolve_cmid_titles(array $cmids): array {
    global $DB, $USER;

    $cmids = array_values(array_unique(array_filter(array_map('intval', $cmids), function ($v) {
        return $v > 0;
    })));
    if (empty($cmids)) {
        return [];
    }

    // Map each cmid to its course in a single query, so we can load course
    // modinfo (which carries the per-user visibility decision) per course.
    [$insql, $params] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED);
    $coursemap = $DB->get_records_sql(
        'SELECT cm.id AS cmid, cm.course
         FROM {course_modules} cm
         WHERE cm.id ' . $insql,
        $params
    );

    $out = [];
    $modinfocache = [];
    foreach ($coursemap as $row) {
        $cmid = (int)$row->cmid;
        $courseid = (int)$row->course;
        try {
            if (!isset($modinfocache[$courseid])) {
                $modinfocache[$courseid] = get_fast_modinfo($courseid, $USER->id);
            }
            $cm = $modinfocache[$courseid]->get_cm($cmid);
        } catch (\Throwable $e) {
            // Course or module gone: leave it to the fallback below.
            continue;
        }
        // Never expose a resource the current user is not allowed to see
        // (hidden module, availability restriction, no access to the course).
        if (!$cm->uservisible) {
            continue;
        }
        $course = $modinfocache[$courseid]->get_course();
        $out[$cmid] = [
            'name'      => format_string($cm->name),
            'course'    => format_string($course->fullname),
            'url'       => $cm->url ? $cm->url->out(false) : '',
            'courseUrl' => (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false),
        ];
    }
    // Fallback for unresolved or non-visible cmids: keep an opaque placeholder.
    foreach ($cmids as $cmid) {
        if (!isset($out[$cmid])) {
            $out[$cmid] = ['name' => 'Resource #' . $cmid, 'course' => '', 'url' => '', 'courseUrl' => ''];
        }
    }
    return $out;
}

/**
 * T5 — Ajoute un lien "Préférences de révision" dans le menu utilisateur.
 *
 * Permet a l'apprenant d'acceder a sa page d'opt-out global + reactivation
 * des ressources annulees ou maitrisees.
 *
 * @param navigation_node $navigation
 * @param stdClass        $user
 * @param context_user    $usercontext
 * @param stdClass        $course
 * @param context_course  $coursecontext
 * @return void
 */
function local_astusse_extend_navigation_user_settings(
    navigation_node $navigation,
    stdClass $user,
    context_user $usercontext,
    stdClass $course,
    context_course $coursecontext
): void {
    global $USER;

    // Visible uniquement par l'apprenant lui-meme (pas le menu d'edition admin
    // d'un autre user). On evite ainsi qu'un manager modifie les prefs d'autrui.
    if ((int)$user->id !== (int)$USER->id) {
        return;
    }

    $url = new moodle_url('/local/astusse/review_preferences.php');
    $node = navigation_node::create(
        get_string('prefs:title', 'local_astusse'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'local_astusse_review_prefs'
    );
    $navigation->add_node($node);
}
