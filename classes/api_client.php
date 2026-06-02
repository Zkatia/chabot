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
    private const CHAT_HISTORY_TTL_HEADER = 'X-Chat-History-Ttl';

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

        $payload = $this->build_chat_payload($message, $agenttype, $sessionid, $courseid, $trainerid);

        $response = $this->request_json('POST', '/api/chat', $token, $payload, $this->get_chat_timeout_seconds());
        $status = (int)($response['status'] ?? 0);
        if ($status < 200 || $status >= 300) {
            throw new \Exception($this->extract_error_message($response));
        }

        return $response;
    }

    /**
     * Stream one learner chat message to ASTUSSE API.
     *
     * The callback receives raw SSE bytes exactly as returned by the gateway.
     * Return false from the callback to abort the upstream request.
     *
     * @param \stdClass $user
     * @param string $message
     * @param string $agenttype
     * @param string $sessionid
     * @param string $courseid
     * @param string|null $trainerid
     * @param callable $onchunk
     * @return array
     */
    public function stream_message_for_user(
        \stdClass $user,
        string $message,
        string $agenttype,
        string $sessionid,
        string $courseid,
        ?string $trainerid,
        callable $onchunk
    ): array {
        $token = \local_astusse_generate_user_token($user);
        if ($token === false) {
            throw new \Exception('Unable to generate JWT token for current user.');
        }

        $payload = $this->build_chat_payload($message, $agenttype, $sessionid, $courseid, $trainerid);

        return $this->request_stream(
            'POST',
            '/api/chat/stream',
            $token,
            $payload,
            $onchunk,
            $this->get_stream_timeout_seconds()
        );
    }

    /**
     * List stored chat sessions for one course.
     *
     * @param \stdClass $user
     * @param string $courseid
     * @return array
     */
    public function list_chat_sessions_for_user(\stdClass $user, string $courseid): array {
        $token = \local_astusse_generate_user_token($user);
        if ($token === false) {
            throw new \Exception('Unable to generate JWT token for current user.');
        }

        $courseid = trim($courseid);
        if ($courseid === '') {
            throw new \Exception('Course ID is required.');
        }

        $response = $this->request_json(
            'GET',
            '/api/chat/sessions?courseId=' . rawurlencode($courseid),
            $token,
            null,
            $this->get_chat_timeout_seconds()
        );
        $status = (int)($response['status'] ?? 0);
        if ($status < 200 || $status >= 300) {
            throw new \Exception($this->extract_error_message($response));
        }

        return $response;
    }

    /**
     * Fetch one stored chat history by session.
     *
     * @param \stdClass $user
     * @param string $sessionid
     * @return array
     */
    public function get_chat_history_for_user(\stdClass $user, string $sessionid): array {
        $token = \local_astusse_generate_user_token($user);
        if ($token === false) {
            throw new \Exception('Unable to generate JWT token for current user.');
        }

        $sessionid = trim($sessionid);
        if ($sessionid === '') {
            throw new \Exception('Session ID is required.');
        }

        $response = $this->request_json(
            'GET',
            '/api/chat/history?sessionId=' . rawurlencode($sessionid),
            $token,
            null,
            $this->get_chat_timeout_seconds()
        );
        $status = (int)($response['status'] ?? 0);
        if ($status < 200 || $status >= 300) {
            throw new \Exception($this->extract_error_message($response));
        }

        return $response;
    }

    /**
     * Delete one stored chat history by session.
     *
     * @param \stdClass $user
     * @param string $sessionid
     * @return array
     */
    public function delete_chat_history_for_user(\stdClass $user, string $sessionid): array {
        $token = \local_astusse_generate_user_token($user);
        if ($token === false) {
            throw new \Exception('Unable to generate JWT token for current user.');
        }

        $sessionid = trim($sessionid);
        if ($sessionid === '') {
            throw new \Exception('Session ID is required.');
        }

        $response = $this->request_json(
            'DELETE',
            '/api/chat/history?sessionId=' . rawurlencode($sessionid),
            $token,
            null,
            $this->get_chat_timeout_seconds()
        );
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
        string $mimetype,
        ?int $sourcecmid = null,
        ?string $sourcetype = null
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

        // T1 review cycle tracking : tag the document with its originating Moodle module.
        // Optional - omitted for raw file uploads not tied to a specific cmid.
        if ($sourcecmid !== null && $sourcecmid > 0) {
            $queryparts[] = 'sourceCmid=' . rawurlencode((string)$sourcecmid);
        }
        if ($sourcetype !== null && $sourcetype !== '') {
            $queryparts[] = 'sourceType=' . rawurlencode($sourcetype);
        }

        $path = '/api/rag/ingest?' . implode('&', $queryparts);

        return $this->request_multipart_file('POST', $path, $token, $filepath, $filename, $mimetype);
    }

    /**
     * Log a resource consultation for the given apprenant (T1).
     *
     * Sends the event to POST /api/review/log_consultation. The backend dedups
     * on a 30s window and silently ignores resources not indexed in the RAG.
     *
     * Returns the standard request_json result : ['status' => HTTP, 'body_json' => ...].
     * Expected HTTP codes :
     *   - 202 : event handled (stored or deduplicated)
     *   - 204 : resource not indexed -> ignore silently
     *
     * @param \stdClass $user       Apprenant who consulted the resource.
     * @param int       $cmid       Moodle course module id of the resource.
     * @param string    $sourcetype Moodle module type ('page','resource',...).
     * @param int       $courseid   Course in which the resource was consulted.
     * @param int       $viewedat   Unix timestamp of the consultation.
     * @return array
     */
    public function log_consultation_for_user(
        \stdClass $user,
        int $cmid,
        string $sourcetype,
        int $courseid,
        int $viewedat
    ): array {
        $token = \local_astusse_generate_user_token($user);
        if ($token === false) {
            throw new \Exception('Unable to generate JWT token for current user.');
        }

        $payload = [
            'resourceCmid' => $cmid,
            'sourceType' => $sourcetype,
            'courseId' => $courseid,
            // ISO 8601 UTC with explicit Z, parseable by java.time.Instant.
            'consultedAt' => gmdate('Y-m-d\TH:i:s\Z', $viewedat),
        ];

        return $this->request_json('POST', '/api/review/log_consultation', $token, $payload);
    }

    /**
     * Check whether a review pop-up should be proposed to the apprenant (T2).
     *
     * Calls GET /api/review/get_pending_review with a short timeout : the page
     * must never wait on the AI API. Returns the standard request_json result.
     *
     * @param \stdClass $user        Apprenant.
     * @param int       $recencydays Recency window for the amorçage criterion.
     * @param int       $mineligible Minimum eligible resources to trigger the pop-up.
     * @param int       $timeoutseconds HTTP timeout (defaults to 2s, matches UX budget).
     * @return array
     */
    public function get_pending_review_for_user(
        \stdClass $user,
        int $recencydays,
        int $mineligible,
        int $maxresources = 3,
        int $timeoutseconds = 2
    ): array {
        $token = \local_astusse_generate_user_token($user);
        if ($token === false) {
            throw new \Exception('Unable to generate JWT token for current user.');
        }

        $path = '/api/review/get_pending_review'
            . '?recencyDays=' . rawurlencode((string)$recencydays)
            . '&minEligible=' . rawurlencode((string)$mineligible)
            . '&maxResources=' . rawurlencode((string)$maxresources);

        return $this->request_json('GET', $path, $token, null, $timeoutseconds);
    }

    /**
     * T3 etape 2 : recupere les questions du quiz pre-genere a la connexion.
     *
     * Timeout 8s : la session peut etre encore GENERATING (le plugin sait re-poller).
     *
     * @param \stdClass $user           Apprenant.
     * @param string    $quizsessionid  UUID transmis par get_pending_review.
     * @param int       $timeoutseconds Timeout HTTP.
     * @return array
     */
    public function fetch_quiz_for_user(
        \stdClass $user,
        string $quizsessionid,
        int $timeoutseconds = 8
    ): array {
        $token = \local_astusse_generate_user_token($user);
        if ($token === false) {
            throw new \Exception('Unable to generate JWT token for current user.');
        }
        $payload = ['quizSessionId' => $quizsessionid];
        return $this->request_json('POST', '/api/review/generate_interleaved_quiz',
            $token, $payload, $timeoutseconds);
    }

    /**
     * T3 etape 3 : envoie une reponse per-question. Inclut le LLM-juge cote API
     * pour les libres, donc timeout 10s pour absorber les 6s de juge + reseau.
     *
     * @param \stdClass    $user           Apprenant.
     * @param string       $quizsessionid  UUID de la session.
     * @param string       $questionid     UUID de la question repondue.
     * @param ?int         $useranswerindex Index choisi (QCM) ou null.
     * @param ?string      $useranswertext  Texte libre (LIBRE) ou null.
     * @param int          $responsetimems  Duree de reflexion mesuree client.
     * @param int          $timeoutseconds  Timeout HTTP (defaut 10s).
     * @return array
     */
    public function send_quiz_answer_for_user(
        \stdClass $user,
        string $quizsessionid,
        string $questionid,
        ?int $useranswerindex,
        ?string $useranswertext,
        int $responsetimems,
        int $timeoutseconds = 10
    ): array {
        $token = \local_astusse_generate_user_token($user);
        if ($token === false) {
            throw new \Exception('Unable to generate JWT token for current user.');
        }
        $payload = [
            'quizSessionId'    => $quizsessionid,
            'questionId'       => $questionid,
            'userAnswerIndex'  => $useranswerindex,
            'userAnswerText'   => $useranswertext,
            'responseTimeMs'   => $responsetimems,
        ];
        return $this->request_json('POST', '/api/review/record_quiz_answer',
            $token, $payload, $timeoutseconds);
    }

    /**
     * T3 etape 7 : charge le contexte d'une session quiz (questions, reponses
     * de l'apprenant, verdicts) pour pre-remplir le chat "Demander au tuteur".
     *
     * @param \stdClass $user           Apprenant.
     * @param string    $quizsessionid  UUID de la session.
     * @param int       $timeoutseconds Timeout HTTP.
     * @return array
     */
    public function fetch_quiz_context_for_user(
        \stdClass $user,
        string $quizsessionid,
        int $timeoutseconds = 3
    ): array {
        $token = \local_astusse_generate_user_token($user);
        if ($token === false) {
            throw new \Exception('Unable to generate JWT token for current user.');
        }
        $path = '/api/review/quiz_context/' . rawurlencode($quizsessionid);
        return $this->request_json('GET', $path, $token, null, $timeoutseconds);
    }

    /**
     * T3 etape 4 : finalise la session et recupere le bilan. Endpoint
     * transactionnel cote API (FSRS + UPSERT fsrs_state), idempotent.
     *
     * @param \stdClass $user           Apprenant.
     * @param string    $quizsessionid  UUID de la session a finaliser.
     * @param int       $timeoutseconds Timeout HTTP.
     * @return array
     */
    public function finalize_quiz_for_user(
        \stdClass $user,
        string $quizsessionid,
        int $timeoutseconds = 5
    ): array {
        $token = \local_astusse_generate_user_token($user);
        if ($token === false) {
            throw new \Exception('Unable to generate JWT token for current user.');
        }
        $payload = ['quizSessionId' => $quizsessionid];
        return $this->request_json('POST', '/api/review/record_quiz_result',
            $token, $payload, $timeoutseconds);
    }

    /**
     * Backfill source_cmid and source_type on an existing rag_document, identified
     * by its ingestion job id (= local_astusse_ingest_jobs.backendjobid).
     *
     * Used by the one-shot scheduled task {@see \local_astusse\task\backfill_rag_source_cmid}.
     * Requires the JWT to carry the ADMIN role (typical Moodle site admin).
     *
     * Returns the standard request_json result : ['status' => HTTP, 'body_json' => ...].
     * Expected HTTP codes :
     *   - 204 : updated
     *   - 200 : already filled (no-op)
     *   - 404 : job id unknown on backend
     *
     * @param \stdClass $user        Admin user used to mint the JWT.
     * @param string    $backendjobid Backend job UUID (= rag_ingestion_jobs.job_id).
     * @param int       $sourcecmid  Moodle cmid of the source module.
     * @param string    $sourcetype  Moodle sourcetype ('page','resource','h5pactivity',...).
     * @return array
     */
    public function backfill_rag_source_for_user(
        \stdClass $user,
        string $backendjobid,
        int $sourcecmid,
        string $sourcetype
    ): array {
        $token = \local_astusse_generate_user_token($user);
        if ($token === false) {
            throw new \Exception('Unable to generate JWT token for admin user.');
        }
        if (trim($backendjobid) === '') {
            throw new \Exception('backendjobid is required for source backfill.');
        }

        $path = '/api/admin/rag/documents/by-job/' . rawurlencode($backendjobid) . '/source';
        $payload = [
            'sourceCmid' => $sourcecmid,
            'sourceType' => $sourcetype,
        ];

        return $this->request_json('POST', $path, $token, $payload);
    }

    /**
     * Fetch the global scope policy from the admin endpoint.
     *
     * @param \stdClass $user
     * @return array
     */
    public function get_scope_policy_snapshot_for_user(\stdClass $user): array {
        $token = \local_astusse_generate_user_token($user);
        if ($token === false) {
            throw new \Exception('Unable to generate JWT token for current user.');
        }

        return $this->request_json('GET', '/api/admin/scope/policy', $token, null);
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
        $headers = $this->build_request_headers($token, 'application/json', $payload !== null, $path);

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

        // An empty body (e.g. 202 Accepted / 204 No Content) is legitimate and is NOT a
        // decode error worth logging — otherwise endpoints that return no content spam the logs.
        if (trim((string)$body) !== '' && $jsonerror !== JSON_ERROR_NONE) {
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
     * Execute streaming HTTP request.
     *
     * @param string $method
     * @param string $path
     * @param string $token
     * @param array|null $payload
     * @param callable $onchunk
     * @param int|null $timeoutseconds
     * @return array
     */
    private function request_stream(
        string $method,
        string $path,
        string $token,
        ?array $payload,
        callable $onchunk,
        ?int $timeoutseconds = null
    ): array {
        $url = $this->baseurl . $path;
        $headers = $this->build_request_headers($token, 'text/event-stream', $payload !== null, $path);

        $httpstatus = 0;
        $responsecontenttype = '';
        $bufferedresponse = '';
        $forwardedbytes = 0;
        $abortedbycallback = false;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->normalize_timeout_seconds($timeoutseconds));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->get_connect_timeout_seconds($timeoutseconds));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $headerline) use (&$httpstatus, &$responsecontenttype) {
            $length = strlen($headerline);
            $trimmed = trim($headerline);

            if ($trimmed === '') {
                return $length;
            }

            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $trimmed, $matches)) {
                $httpstatus = (int)$matches[1];
                return $length;
            }

            if (stripos($trimmed, 'Content-Type:') === 0) {
                $responsecontenttype = trim(substr($trimmed, strlen('Content-Type:')));
            }

            return $length;
        });

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (
            &$httpstatus,
            &$responsecontenttype,
            &$bufferedresponse,
            &$forwardedbytes,
            &$abortedbycallback,
            $onchunk
        ) {
            $isupstreamsse = stripos($responsecontenttype, 'text/event-stream') !== false;
            $issuccess = $httpstatus >= 200 && $httpstatus < 300;

            if ($issuccess && $isupstreamsse) {
                $accepted = $onchunk($data);
                if ($accepted === false) {
                    $abortedbycallback = true;
                    return 0;
                }
                $forwardedbytes += strlen($data);
                return strlen($data);
            }

            $bufferedresponse .= $data;
            if (strlen($bufferedresponse) > 8192) {
                $bufferedresponse = substr($bufferedresponse, 0, 8192);
            }
            return strlen($data);
        });

        $body = curl_exec($ch);
        $curlerrno = curl_errno($ch);
        $curlerror = curl_error($ch);
        $httpstatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $effectivecontenttype = $responsecontenttype !== ''
            ? $responsecontenttype
            : (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($body === false && !($abortedbycallback && $curlerrno === CURLE_WRITE_ERROR)) {
            throw new \Exception('Gateway HTTP error: ' . $curlerror);
        }

        return [
            'url' => $url,
            'status' => $httpstatus,
            'content_type' => $effectivecontenttype,
            'body_preview' => $bufferedresponse,
            'bytes_forwarded' => $forwardedbytes,
            'aborted_by_callback' => $abortedbycallback,
            'curl_errno' => $curlerrno,
            'curl_error' => $curlerror,
        ];
    }

    /**
     * Build common request headers and attach optional chat history TTL override.
     *
     * @param string $token
     * @param string $accept
     * @param bool $hasjsonbody
     * @param string $path
     * @return array
     */
    private function build_request_headers(string $token, string $accept, bool $hasjsonbody, string $path): array {
        $headers = [
            'Authorization: Bearer ' . $token,
            'Accept: ' . $accept,
        ];
        if ($hasjsonbody) {
            $headers[] = 'Content-Type: application/json';
        }

        $ttloverride = $this->get_chat_history_ttl_header_value($path);
        if ($ttloverride !== '') {
            $headers[] = self::CHAT_HISTORY_TTL_HEADER . ': ' . $ttloverride;
        }

        return $headers;
    }

    /**
     * Return the configured chat history TTL header value for chat endpoints only.
     *
     * @param string $path
     * @return string
     */
    private function get_chat_history_ttl_header_value(string $path): string {
        if (strpos($path, '/api/chat') !== 0) {
            return '';
        }

        $configured = trim((string)(get_config('local_astusse', 'chat_history_ttl') ?: 'PT24H'));
        $allowed = ['PT24H', 'PT48H', 'PT72H', 'unlimited'];
        return in_array($configured, $allowed, true) ? $configured : 'PT24H';
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
     * Resolve the total timeout used for chat streaming requests.
     *
     * @return int
     */
    private function get_stream_timeout_seconds(): int {
        return max($this->timeoutseconds, 120);
    }

    /**
     * Validate and build the common chat payload.
     *
     * @param string $message
     * @param string $agenttype
     * @param string $sessionid
     * @param string $courseid
     * @param string|null $trainerid
     * @return array
     */
    private function build_chat_payload(
        string $message,
        string $agenttype,
        string $sessionid,
        string $courseid,
        ?string $trainerid = null
    ): array {
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

        return $payload;
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
