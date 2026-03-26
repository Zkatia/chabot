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
 * Minimal ASTUSSE gateway API client.
 *
 * @package     local_astusse
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_astusse;

defined('MOODLE_INTERNAL') || die();

/**
 * API client to validate Moodle -> JWT -> Gateway chain.
 */
class api_client {
    /** @var string */
    private $baseurl;
    /** @var int */
    private $timeoutseconds;

    /**
     * Constructor.
     *
     * @param string|null $baseurl
     * @param int|null $timeoutseconds
     */
    public function __construct(?string $baseurl = null, ?int $timeoutseconds = null) {
        $configuredbase = get_config('local_astusse', 'gateway_base_url') ?: 'http://localhost:8888';
        $configuredtimeout = (int)(get_config('local_astusse', 'gateway_timeout_seconds') ?: 30);

        $this->baseurl = rtrim((string)($baseurl ?? $configuredbase), '/');
        $this->timeoutseconds = (int)($timeoutseconds ?? $configuredtimeout);
        if ($this->timeoutseconds <= 0) {
            $this->timeoutseconds = 30;
        }
    }

    /**
     * Call GET /api/ping with provided bearer token.
     *
     * @param string $token
     * @return array
     */
    public function ping_with_token(string $token): array {
        return $this->request_json('GET', '/api/ping', $token, null);
    }

    /**
     * Generate current user token and call GET /api/ping.
     *
     * @param \stdClass $user
     * @return array
     */
    public function ping_for_user(\stdClass $user): array {
        $token = \local_astusse_generate_user_token($user);
        if ($token === false) {
            throw new \Exception('Unable to generate JWT token for current user.');
        }

        return $this->ping_with_token($token);
    }

    /**
     * Send one learner chat message to ASTUSSE API.
     *
     * @param \stdClass $user
     * @param string $message
     * @param string $agenttype
     * @param string $sessionid
     * @param string $courseid
     * @param string|null $trainerid
     * @return array
     */
    public function send_message_for_user(
        \stdClass $user,
        string $message,
        string $agenttype,
        string $sessionid,
        string $courseid,
        ?string $trainerid = null
    ): array {
        $token = \local_astusse_generate_user_token($user);
        if ($token === false) {
            throw new \Exception('Unable to generate JWT token for current user.');
        }

        $message = trim($message);
        $agenttype = trim($agenttype);
        $sessionid = trim($sessionid);
        $courseid = trim($courseid);
        $trainerid = $trainerid !== null ? trim($trainerid) : null;

        if ($message === '') {
            throw new \Exception('Message is required.');
        }
        if (!in_array($agenttype, ['explicatif', 'socratique'], true)) {
            throw new \Exception('Agent type must be explicatif or socratique.');
        }
        if ($sessionid === '') {
            throw new \Exception('Session ID is required.');
        }
        if ($courseid === '') {
            throw new \Exception('Course ID is required.');
        }

        $payload = [
            'message' => $message,
            'agentType' => $agenttype,
            'sessionId' => $sessionid,
            'courseId' => $courseid,
        ];
        if ($trainerid !== null && $trainerid !== '') {
            $payload['trainerId'] = $trainerid;
        }

        $response = $this->request_json('POST', '/api/chat', $token, $payload, $this->get_chat_timeout_seconds());
        $status = (int)($response['status'] ?? 0);
        if ($status < 200 || $status >= 300) {
            throw new \Exception($this->extract_error_message($response));
        }

        return $response;
    }

    /**
     * Fetch active trainer scope from API.
     *
     * @param \stdClass $user
     * @param string $trainerid
     * @return array
     */
    public function get_trainer_scope_for_user(\stdClass $user, string $trainerid): array {
        $token = \local_astusse_generate_user_token($user);
        if ($token === false) {
            throw new \Exception('Unable to generate JWT token for current user.');
        }

        $path = '/api/trainer/scope?trainerId=' . rawurlencode($trainerid);
        return $this->request_json('GET', $path, $token, null);
    }

    /**
     * Update trainer scope from API.
     *
     * @param \stdClass $user
     * @param string $trainerid
     * @param string $scope course|trainer|platform
     * @return array
     */
    public function update_trainer_scope_for_user(\stdClass $user, string $trainerid, string $scope): array {
        $token = \local_astusse_generate_user_token($user);
        if ($token === false) {
            throw new \Exception('Unable to generate JWT token for current user.');
        }

        $payload = [
            'trainerId' => $trainerid,
            'scope' => $scope,
        ];
        return $this->request_json('POST', '/api/trainer/scope', $token, $payload);
    }

    /**
     * Update global scope policy from API.
     *
     * @param \stdClass $user
     * @param bool $platformscopeallowed
     * @param bool $delegationenabled
     * @return array
     */
    public function update_scope_policy_for_user(
        \stdClass $user,
        bool $platformscopeallowed,
        bool $delegationenabled
    ): array {
        $token = \local_astusse_generate_user_token($user);
        if ($token === false) {
            throw new \Exception('Unable to generate JWT token for current user.');
        }

        $payload = [
            'platformScopeAllowed' => $platformscopeallowed,
            'delegationEnabled' => $delegationenabled,
        ];
        return $this->request_json('POST', '/api/admin/scope/policy', $token, $payload);
    }

    /**
     * Upload a document to RAG ingest endpoint with multiple course IDs.
     *
     * @param \stdClass $user
     * @param array $courseids List of Moodle course IDs.
     * @param string $filepath Temporary local file path.
     * @param string $filename Original filename.
     * @param string $mimetype File mime type.
     * @return array
     */
    public function ingest_document_for_user(
        \stdClass $user,
        array $courseids,
        string $filepath,
        string $filename,
        string $mimetype
    ): array {
        $token = \local_astusse_generate_user_token($user);
        if ($token === false) {
            throw new \Exception('Unable to generate JWT token for current user.');
        }

        $normalizedcourseids = [];
        foreach ($courseids as $courseid) {
            $courseid = (int)$courseid;
            if ($courseid > 0) {
                $normalizedcourseids[] = $courseid;
            }
        }
        $normalizedcourseids = array_values(array_unique($normalizedcourseids));
        if (empty($normalizedcourseids)) {
            throw new \Exception('At least one valid course ID is required.');
        }

        $trainerid = (string)$user->id;
        $queryparts = [];
        foreach ($normalizedcourseids as $courseid) {
            $queryparts[] = 'courseId=' . rawurlencode((string)$courseid);
        }
        $queryparts[] = 'trainerId=' . rawurlencode($trainerid);
        $path = '/api/rag/ingest?' . implode('&', $queryparts);

        return $this->request_multipart_file('POST', $path, $token, $filepath, $filename, $mimetype);
    }

    /**
     * Fetch a policy snapshot from backend using trainer scope endpoint.
     *
     * Backend does not expose a dedicated GET /api/admin/scope/policy endpoint yet.
     * We read policy flags from trainer scope response.
     *
     * @param \stdClass $user
     * @return array
     */
    public function get_scope_policy_snapshot_for_user(\stdClass $user): array {
        return $this->get_trainer_scope_for_user($user, (string)$user->id);
    }

    /**
     * Return API error message if available.
     *
     * @param array $response
     * @return string
     */
    public function extract_error_message(array $response): string {
        $status = (int)($response['status'] ?? 0);
        $json = $response['body_json'] ?? null;
        if (is_array($json)) {
            if (!empty($json['message'])) {
                return (string)$json['message'];
            }
            if (!empty($json['error_description'])) {
                return (string)$json['error_description'];
            }
            if (!empty($json['error'])) {
                return (string)$json['error'];
            }
        }
        if (!empty($response['body_json_error'])) {
            return 'HTTP ' . $status . ' (invalid JSON: ' . (string)$response['body_json_error'] . ')';
        }
        return 'HTTP ' . $status;
    }

    /**
     * Execute multipart/form-data request with one uploaded file.
     *
     * Query parameters should be passed directly in $path.
     *
     * @param string $method
     * @param string $path
     * @param string $token
     * @param string $filepath
     * @param string $filename
     * @param string $mimetype
     * @return array
     */
    private function request_multipart_file(
        string $method,
        string $path,
        string $token,
        string $filepath,
        string $filename,
        string $mimetype,
        ?int $timeoutseconds = null
    ): array {
        if ($filepath === '' || !is_readable($filepath)) {
            throw new \Exception('Uploaded file is not readable.');
        }

        if ($filename === '') {
            $filename = basename($filepath);
        }
        if ($mimetype === '') {
            $mimetype = 'application/octet-stream';
        }

        $url = $this->baseurl . $path;
        $headers = [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ];
        $postfields = [
            'file' => new \CURLFile($filepath, $mimetype, $filename),
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->normalize_timeout_seconds($timeoutseconds));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->get_connect_timeout_seconds($timeoutseconds));
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);

        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception('Gateway HTTP error: ' . $error);
        }

        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $json = json_decode($body, true);
        $jsonerror = json_last_error();
        $jsonerrormessage = $jsonerror === JSON_ERROR_NONE ? '' : json_last_error_msg();

        if ($jsonerror !== JSON_ERROR_NONE) {
            error_log('local_astusse multipart response JSON decode failed for ' . $url . ': ' . $jsonerrormessage);
        }

        return [
            'url' => $url,
            'status' => $status,
            'body_raw' => $body,
            'body_json' => is_array($json) ? $json : null,
            'body_json_error' => $jsonerrormessage,
        ];
    }

    /**
     * Execute HTTP request and decode JSON body if possible.
     *
     * @param string $method
     * @param string $path
     * @param string $token
     * @param array|null $payload
     * @return array
     */
    private function request_json(
        string $method,
        string $path,
        string $token,
        ?array $payload,
        ?int $timeoutseconds = null
    ): array {
        $url = $this->baseurl . $path;
        $headers = [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ];
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->normalize_timeout_seconds($timeoutseconds));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->get_connect_timeout_seconds($timeoutseconds));
        curl_setopt($ch, CURLOPT_ENCODING, '');
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception('Gateway HTTP error: ' . $error);
        }

        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $json = json_decode($body, true);
        $jsonerror = json_last_error();
        $jsonerrormessage = $jsonerror === JSON_ERROR_NONE ? '' : json_last_error_msg();

        if ($jsonerror !== JSON_ERROR_NONE) {
            error_log('local_astusse JSON decode failed for ' . $url . ': ' . $jsonerrormessage . '. Body preview: ' .
                substr($body, 0, 500));
        }

        return [
            'url' => $url,
            'status' => $status,
            'body_raw' => $body,
            'body_json' => is_array($json) ? $json : null,
            'body_json_error' => $jsonerrormessage,
        ];
    }

    /**
     * Resolve the total timeout used for chat requests.
     *
     * LLM calls regularly exceed 10 seconds in dev, so we enforce a safer floor
     * for the learner chat while keeping shorter timeouts for lighter endpoints.
     *
     * @return int
     */
    private function get_chat_timeout_seconds(): int {
        return max($this->timeoutseconds, 30);
    }

    /**
     * Normalize request timeout.
     *
     * @param int|null $timeoutseconds
     * @return int
     */
    private function normalize_timeout_seconds(?int $timeoutseconds = null): int {
        $timeout = (int)($timeoutseconds ?? $this->timeoutseconds);
        return $timeout > 0 ? $timeout : 30;
    }

    /**
     * Use a shorter connect timeout than the full request timeout.
     *
     * @param int|null $timeoutseconds
     * @return int
     */
    private function get_connect_timeout_seconds(?int $timeoutseconds = null): int {
        $timeout = $this->normalize_timeout_seconds($timeoutseconds);
        return min($timeout, 5);
    }
}
