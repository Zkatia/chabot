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
            if ($snapshotstatus >= 200 && $snapshotstatus < 300
                    && !empty($snapshot['body_json']) && is_array($snapshot['body_json'])) {
                $json = $snapshot['body_json'];
                $backendplatform = !empty($json['platformScopeAllowed']);
                $backenddelegation = !empty($json['delegationEnabled']);
                $backendfetchok = true;
            }
        }
    } catch (\Throwable $e) {
        // Backend unreachable, handled below.
    }

    // If backend state differs from local, force a sync POST then re-read.
    if ($backendfetchok && ($backendplatform !== $localplatform || $backenddelegation !== $localdelegation)) {
        local_astusse_sync_scope_policy_from_settings();

        // Re-read backend state after sync.
        try {
            if ($syncuser !== null) {
                $snapshot = $client->get_scope_policy_snapshot_for_user($syncuser);
                $snapshotstatus = (int)($snapshot['status'] ?? 0);
                if ($snapshotstatus >= 200 && $snapshotstatus < 300
                        && !empty($snapshot['body_json']) && is_array($snapshot['body_json'])) {
                    $json = $snapshot['body_json'];
                    $backendplatform = !empty($json['platformScopeAllowed']);
                    $backenddelegation = !empty($json['delegationEnabled']);
                }
            }
        } catch (\Throwable $e) {
            // Ignore re-read failure.
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
    $supportedtypes = ['resource', 'page', 'scorm'];
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
 * @return array|null
 */
function local_astusse_extract_module_content(int $cmid, int $courseid): ?array {
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

    return null;
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
    // Two patterns:
    //   1. und.js:    __resolveJsonp("course:und","<base64>")
    //   2. index.html: window.courseData = "<base64>"
    // If found, use only that content — it contains the entire course text.
    foreach ($files as $file) {
        $raw = $file->get_content();
        $base64payload = null;

        if (preg_match('#__resolveJsonp\s*\(\s*"[^"]*"\s*,\s*"([A-Za-z0-9+/=]{100,})"#', $raw, $jsonpmatch)) {
            $base64payload = $jsonpmatch[1];
        } else if (preg_match('#window\.courseData\s*=\s*"([A-Za-z0-9+/=]{100,})"#', $raw, $coursedatamatch)) {
            $base64payload = $coursedatamatch[1];
        }

        if ($base64payload !== null) {
            $decoded = base64_decode($base64payload, true);
            if ($decoded !== false) {
                $text = local_astusse_extract_text_from_json($decoded);

                error_log('>>>>>>>>>>>> RAW JSON len=' . strlen($decoded));
                $lograw = $decoded;
                $i = 0;
                while (mb_strlen($lograw) > 0) {
                    error_log('>>>>>>>>>>>> RAW PART ' . $i . ': ' . mb_substr($lograw, 0, 4000));
                    $lograw = mb_substr($lograw, 4000);
                    $i++;
                }
                error_log('<<<<<<<<<<<< FILTERED TEXT len=' . mb_strlen($text));
                $logfiltered = $text;
                $i = 0;
                while (mb_strlen($logfiltered) > 0) {
                    error_log('<<<<<<<<<<<< FILTERED PART ' . $i . ': ' . mb_substr($logfiltered, 0, 4000));
                    $logfiltered = mb_substr($logfiltered, 4000);
                    $i++;
                }

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

    // Second pass: generic SCORM extraction for non-Rise packages.
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
            if (mb_strlen($text) >= 20) {
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

    if (empty($textparts)) {
        return null;
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
 * @param mixed $data
 * @param array $texts
 * @return void
 */
function local_astusse_collect_json_texts($data, array &$texts, ?string $key = null): void {
    if (is_string($data)) {
        // Only extract values from known content keys.
        $contentkeys = [
            'title', 'heading', 'paragraph', 'description', 'caption',
            'feedback', 'text', 'content', 'body', 'label', 'alt',
            'matchTitle',
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

    uasort($available, static function(array $left, array $right): int {
        return strnatcasecmp($left['fullname'], $right['fullname']);
    });

    return array_values($available);
}
