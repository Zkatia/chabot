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
 * Manual test page for JWT generation and gateway ping.
 *
 * @package     local_astusse
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/astusse:requesttoken', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/astusse/test_token.php'));
$PAGE->set_title(get_string('testpage:title', 'local_astusse'));
$PAGE->set_heading(get_string('testpage:heading', 'local_astusse'));

$action = optional_param('action', '', PARAM_ALPHA);
$result = null;
$error = '';

if ($action !== '') {
    if (!confirm_sesskey()) {
        $error = get_string('testpage:invalid_sesskey', 'local_astusse');
    } else {
        try {
            global $USER;

            if ($action === 'generate') {
                if (!local_astusse_keys_exist()) {
                    throw new Exception(get_string('error:keysmissing', 'local_astusse'));
                }

                $token = local_astusse_generate_user_token($USER);
                if ($token === false) {
                    throw new Exception(get_string('error:tokenfailed', 'local_astusse'));
                }

                $result = [
                    'status' => 200,
                    'endpoint' => '/local/astusse/token.php',
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => (int)(get_config('local_astusse', 'ttl_seconds') ?: 900),
                ];
            } else if ($action === 'ping') {
                $client = new \local_astusse\api_client();
                $result = $client->ping_for_user($USER);
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$gatewaybase = get_config('local_astusse', 'gateway_base_url') ?: 'http://localhost:8888';

echo $OUTPUT->header();
?>

<h2><?php echo s(get_string('testpage:heading', 'local_astusse')); ?></h2>
<p><?php echo s(get_string('testpage:intro', 'local_astusse')); ?></p>
<ul>
    <li><?php echo s(get_string('testpage:jwks_label', 'local_astusse')); ?>: <code>/local/astusse/jwks.php</code></li>
    <li><?php echo s(get_string('testpage:token_label', 'local_astusse')); ?>: <code>/local/astusse/token.php</code></li>
    <li><?php echo s(get_string('testpage:gateway_label', 'local_astusse')); ?>: <code><?php echo s($gatewaybase); ?></code></li>
</ul>

<div style="display:flex; gap:12px; margin: 16px 0;">
    <form method="post" action="">
        <input type="hidden" name="sesskey" value="<?php echo s(sesskey()); ?>">
        <input type="hidden" name="action" value="generate">
        <button type="submit"><?php echo s(get_string('testpage:generate', 'local_astusse')); ?></button>
    </form>

    <form method="post" action="">
        <input type="hidden" name="sesskey" value="<?php echo s(sesskey()); ?>">
        <input type="hidden" name="action" value="ping">
        <button type="submit"><?php echo s(get_string('testpage:ping', 'local_astusse')); ?></button>
    </form>
</div>

<?php if ($error !== ''): ?>
    <div style="margin-top: 16px; padding: 12px; background:#ffeeee; border:1px solid #cc0000; color:#900;">
        <strong><?php echo s(get_string('testpage:error_label', 'local_astusse')); ?>:</strong> <?php echo s($error); ?>
    </div>
<?php endif; ?>

<?php if ($result !== null): ?>
    <h3 style="margin-top:20px;"><?php echo s(get_string('testpage:result_label', 'local_astusse')); ?></h3>
    <pre style="background:#f5f5f5; border:1px solid #ddd; padding:12px; overflow:auto;"><?php echo s(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
<?php endif; ?>

<?php
echo $OUTPUT->footer();
