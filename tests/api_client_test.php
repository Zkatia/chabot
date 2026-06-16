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

namespace local_astusse;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/astusse/classes/api_client.php');

/**
 * Tests for local_astusse api_client.
 *
 * @package     local_astusse
 * @copyright   2026 Ingenium Digital Learning
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \local_astusse\api_client
 */
final class api_client_test extends \advanced_testcase {
    public function test_extract_error_message_prefers_message_field(): void {
        $client = new api_client('http://example.test', 30);

        $message = $client->extract_error_message([
            'status' => 502,
            'body_json' => ['message' => 'Backend exploded'],
        ]);

        $this->assertSame('Backend exploded', $message);
    }

    public function test_extract_error_message_uses_json_error_description(): void {
        $client = new api_client('http://example.test', 30);

        $message = $client->extract_error_message([
            'status' => 401,
            'body_json' => ['error_description' => 'Token expired'],
        ]);

        $this->assertSame('Token expired', $message);
    }

    public function test_build_chat_payload_normalizes_trainer_id_and_fields(): void {
        $client = new api_client('http://example.test', 30);
        $method = new \ReflectionMethod(api_client::class, 'build_chat_payload');
        $method->setAccessible(true);

        $payload = $method->invoke($client, ' Bonjour ', 'explicatif', 'session-123', '42', ' 7 ');

        $this->assertSame([
            'message' => 'Bonjour',
            'agentType' => 'explicatif',
            'sessionId' => 'session-123',
            'courseId' => '42',
            'trainerId' => '7',
        ], $payload);
    }

    public function test_build_chat_payload_rejects_invalid_agent(): void {
        $client = new api_client('http://example.test', 30);
        $method = new \ReflectionMethod(api_client::class, 'build_chat_payload');
        $method->setAccessible(true);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Agent type must be explicatif or socratique.');

        $method->invoke($client, 'Bonjour', 'invalid', 'session-123', '42', null);
    }
}
