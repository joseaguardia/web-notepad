<?php

// Settings can be overridden with environment variables (Docker/Kubernetes); the values here are the defaults.

// Path to the directory to save the notes in, without trailing slash.
// Should be outside the document root, if possible.
$save_path = rtrim(getenv('NOTES_PATH') ?: '/var/lib/notas', '/');

// Shown in the footer next to the GitHub link. Bump it on each release.
$app_version = '2.0';
$repo_url = 'https://github.com/joseaguardia/web-notepad';

// Attachment limits: size of each file, and total size of all attachments of all notes.
$max_file_size = (int) (getenv('MAX_FILE_SIZE_MB') ?: 5) * 1024 * 1024;
$max_total_size = (int) (getenv('MAX_TOTAL_SIZE_MB') ?: 100) * 1024 * 1024;

// Maximum size of each note's text (notes are not counted in $max_total_size),
// and maximum number of notes at the same time (a note exists if it has text or attachments).
$max_note_size = (int) (getenv('MAX_NOTE_SIZE_MB') ?: 2) * 1024 * 1024;
$max_notes = (int) (getenv('MAX_NOTES') ?: 25);

// Notes not changed (text saved, attachment added or deleted) for this many minutes are deleted; 0 disables it.
$ttl_env = getenv('NOTE_TTL_MINUTES');
$note_ttl = ($ttl_env === false || $ttl_env === '' ? 45 : (int) $ttl_env) * 60;

// Secrets: a note turned into a one-time link. They live in "$save_path/.secrets/", outside the note TTL,
// until opened or until the chosen expiry. Once opened, its files can be downloaded for $secret_window seconds.
$max_secrets = (int) (getenv('MAX_SECRETS') ?: 25);
$secret_expiries = [1, 24, 168];
$secret_window = 600;

// Disable caching. The note URL is its only secret, so never send it as a referrer.
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: frame-ancestors 'self'");

// Version of a note: hash of its contents, or empty if it does not exist.
function note_version($path) {
    return is_file($path) ? md5_file($path) : '';
}

// Last modification time of a note as a Unix timestamp, or 0 if it does not exist.
function note_mtime($path) {
    clearstatcache(true, $path);
    return is_file($path) ? filemtime($path) : 0;
}

// Attachments of a note live in "<note>.files/", which can never clash with a note name (no dots allowed).
// Stored names are restricted to a safe charset and can never start with a dot (temp files do).
function valid_file_name($name) {
    return is_string($name) && preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,99}$/D', $name);
}

function sanitize_file_name($name) {
    $name = preg_replace('/^.*[\/\\\\]/', '', (string) $name);
    $name = ltrim(substr(preg_replace('/[^a-zA-Z0-9._-]+/', '_', $name), -100), '._-');
    return valid_file_name($name) ? $name : 'file';
}

function list_files($dir) {
    $files = [];
    foreach (is_dir($dir) ? scandir($dir) : [] as $name) {
        if (valid_file_name($name) && is_file("$dir/$name")) {
            $files[] = ['name' => $name, 'size' => filesize("$dir/$name"), 'mtime' => filemtime("$dir/$name")];
        }
    }
    return $files;
}

function files_version($files) {
    return $files ? md5(json_encode($files)) : '';
}

// Last change of the attachments directory (upload or delete), which counts for the note expiry.
function files_changed($dir) {
    clearstatcache(true, $dir);
    return is_dir($dir) ? filemtime($dir) : 0;
}

function send_files($dir) {
    $files = list_files($dir);
    header('Content-Type: application/json');
    print json_encode(['version' => files_version($files), 'files' => $files, 'changed' => files_changed($dir)]);
    die;
}

// Names of the existing notes: those with a text file, attachments, or both.
function note_names($save_path) {
    $names = [];
    foreach (scandir($save_path) ?: [] as $entry) {
        if (preg_match('/^([a-zA-Z0-9_-]+)(\.files)?$/D', $entry, $match)) {
            $names[$match[1]] = true;
        }
    }
    return $names;
}

// Refuse to create a new note when the limit is reached; existing notes can always be changed.
function check_note_limit($save_path, $note, $max_notes) {
    $names = note_names($save_path);
    if (!isset($names[$note]) && count($names) >= $max_notes) {
        fail(507, "Too many notes (max $max_notes at the same time)");
    }
}

// Delete the text and attachments of a note (or of a secret, which has the same layout).
function delete_note($text, $dir) {
    if (is_file($text)) {
        unlink($text);
    }
    foreach (list_files($dir) as $file) {
        unlink("$dir/{$file['name']}");
    }
    @rmdir($dir);
}

// A secret is "$save_path/.secrets/<id>/" holding "data" (the note text and the list of files, encrypted),
// one encrypted file per attachment named "0", "1"…, "expires" (Unix time) and, once opened, "opened".
// The key is not stored: it only travels in the link, after the "#", and comes back in the POST body.
// Ids are 128 random bits and keys 256, both in base64url.
function valid_secret_id($id) {
    return is_string($id) && preg_match('/^[a-zA-Z0-9_-]{22}$/D', $id);
}

function new_secret_id() {
    return rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
}

function secret_ids($secrets_path) {
    return array_values(array_filter(is_dir($secrets_path) ? scandir($secrets_path) : [], 'valid_secret_id'));
}

function secret_expires($dir) {
    return is_file("$dir/expires") ? (int) file_get_contents("$dir/expires") : 0;
}

// Directory of a live secret in the given state (opened or not), or null if missing or expired.
function find_secret($secrets_path, $id, $opened) {
    $dir = "$secrets_path/$id";
    if (!valid_secret_id($id) || !is_dir($dir) || is_file("$dir/opened") !== $opened || secret_expires($dir) < time()) {
        return null;
    }
    return $dir;
}

function delete_secret($dir) {
    foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $entry) {
        is_dir("$dir/$entry") ? delete_secret("$dir/$entry") : unlink("$dir/$entry");
    }
    @rmdir($dir);
}

// AES-256-GCM: stored as IV (12 bytes), tag (16) and ciphertext. unseal() returns false with a wrong key.
function seal($plain, $key) {
    $iv = random_bytes(12);
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return $iv . $tag . $cipher;
}

function unseal($sealed, $key) {
    return openssl_decrypt(substr($sealed, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($sealed, 0, 12), substr($sealed, 12, 16));
}

// Key sent by the browser in the POST body, or null if missing or malformed.
function posted_key() {
    $key = is_string($_POST['key'] ?? null) ? base64_decode(strtr($_POST['key'], '-_', '+/'), true) : false;
    return $key !== false && strlen($key) === 32 ? $key : null;
}

// Total size of all attachments, of notes and of secrets.
function attachments_size($save_path, $except) {
    $total = 0;
    foreach (array_merge(glob("$save_path/*.files/*") ?: [], glob("$save_path/.secrets/*/[0-9]*") ?: []) as $file) {
        if ($file !== $except) {
            $total += filesize($file);
        }
    }
    return $total;
}

// Always as a download and sandboxed, so an uploaded HTML or SVG can never run in this origin.
function send_attachment($file, $name) {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . filesize($file));
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'; sandbox");
    readfile($file);
    die;
}

// Delete the notes not changed for $ttl seconds (0 keeps them), text and attachments, and the expired secrets.
// Runs at most once a minute. A note's last change is the newest mtime of its text file and its attachments
// directory (the directory changes on every upload, delete, and when an upload starts its temp file).
function delete_expired($save_path, $ttl) {
    $stamp = "$save_path/.cleanup";
    clearstatcache();
    if (is_file($stamp) && filemtime($stamp) > time() - 60) {
        return;
    }
    touch($stamp);
    $lock = fopen($save_path . '/.lock', 'c');
    flock($lock, LOCK_EX);
    foreach ($ttl > 0 ? array_keys(note_names($save_path)) : [] as $name) {
        $text = "$save_path/$name";
        $dir = "$text.files";
        $changed = max(is_file($text) ? filemtime($text) : 0, is_dir($dir) ? filemtime($dir) : 0);
        if ($changed && $changed < time() - $ttl) {
            delete_note($text, $dir);
        }
    }
    foreach (secret_ids("$save_path/.secrets") as $id) {
        if (secret_expires("$save_path/.secrets/$id") < time()) {
            delete_secret("$save_path/.secrets/$id");
        }
    }
    flock($lock, LOCK_UN);
    fclose($lock);
}

function fail($code, $message) {
    http_response_code($code);
    header('Content-Type: text/plain');
    print $message;
    die;
}

$method = $_SERVER['REQUEST_METHOD'];
$secrets_path = "$save_path/.secrets";
delete_expired($save_path, $note_ttl);

// Secrets, reached at "<base>/?s=<id>" (any note URL works too). A GET only shows a page with an "open" button,
// so link previews do not burn it. Opening (POST) moves it to a new random id that only the opener knows,
// marked as opened: the text comes in the answer and the files can be downloaded with that id for a while.
// Both need the key in the POST body; a wrong key leaves the secret untouched.
$secret = null;
if (isset($_GET['s'])) {
    if (isset($_GET['file']) && $method === 'POST') {
        $dir = find_secret($secrets_path, $_GET['s'], true);
        if (!$dir || !is_string($_GET['file']) || !ctype_digit($_GET['file']) || !is_file($file = "$dir/{$_GET['file']}")) {
            fail(404, 'Not found');
        }
        if (!($key = posted_key()) || ($data = unseal(file_get_contents($file), $key)) === false) {
            fail(403, 'Wrong key');
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment');
        header("Content-Security-Policy: default-src 'none'; sandbox");
        print $data;
        die;
    }
    if (isset($_GET['open']) && $method === 'POST') {
        $lock = fopen($save_path . '/.lock', 'c');
        flock($lock, LOCK_EX);
        if (!$dir = find_secret($secrets_path, $_GET['s'], false)) {
            fail(404, 'This secret does not exist: it was already opened or it expired.');
        }
        $data = is_file("$dir/data") ? file_get_contents("$dir/data") : '';
        if (!($key = posted_key()) || ($data = unseal($data, $key)) === false) {
            fail(403, 'This link is not complete: check that it was copied whole, including the part after the "#".');
        }
        $data = json_decode($data, true);
        $token = new_secret_id();
        $opened = "$secrets_path/$token";
        rename($dir, $opened);
        unlink("$opened/data");
        file_put_contents("$opened/expires", time() + $secret_window);
        touch("$opened/opened");
        if (!$data['files']) {
            delete_secret($opened);
        }
        header('Content-Type: application/json');
        print json_encode(['token' => $token, 'text' => $data['text'], 'files' => $data['files'], 'until' => time() + $secret_window]);
        die;
    }
    if ($method !== 'GET' && $method !== 'HEAD') {
        fail(405, 'Method not allowed');
    }
    $dir = find_secret($secrets_path, $_GET['s'], false);
    $secret = ['id' => $dir ? $_GET['s'] : '', 'expires' => $dir ? secret_expires($dir) : 0];
    if (!$dir) {
        http_response_code(404);
    }

    // Only the page below is left: drop every other parameter so no note route can run.
    $_GET = [];
}

// If no note name is provided, or if the name is too long, or if it contains invalid characters.
// The D modifier stops $ from accepting a trailing newline.
if (!$secret && (!is_string($_GET['note'] ?? null) || !preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $_GET['note']))) {

    // Generate a name with 5 random unambiguous characters. Redirect to it.
    $chars = '234579abcdefghjkmnpqrstwxyz';
    $name = '';
    for ($i = 0; $i < 5; $i++) {
        $name .= $chars[random_int(0, strlen($chars) - 1)];
    }
    header("Location: $name");
    die;
}

// The secret page uses a path that can never exist (note names have no dots), so it shows no note.
$path = $save_path . '/' . ($secret ? '.none' : $_GET['note']);
$files_dir = $path . '.files';

// Upload an attachment: the raw request body is the file, e.g. curl --upload-file photo.png '<url>?upload=photo.png'.
// The rewrite also maps PUT/POST <url>/<file> here, so curl --upload-file photo.png <url>/ names it automatically
// (and GET <url>/<file> to ?file=<file>, the download below).
// file_uploads is disabled in PHP on purpose, so no multipart parsing is involved.
if (isset($_GET['upload']) && ($method === 'POST' || $method === 'PUT')) {
    if (!is_string($_GET['upload'])) {
        fail(400, 'Invalid file name');
    }
    $length = $_SERVER['CONTENT_LENGTH'] ?? '';
    if ($length !== '' && (int) $length > $max_file_size) {
        fail(413, 'File too large (max ' . ($max_file_size >> 20) . ' MB)');
    }
    // Check the note limit and create the directory under the lock, then release it.
    $lock = fopen($save_path . '/.lock', 'c');
    flock($lock, LOCK_EX);
    check_note_limit($save_path, $_GET['note'], $max_notes);
    if (!is_dir($files_dir) && !@mkdir($files_dir, 0755) && !is_dir($files_dir)) {
        fail(500, 'Cannot create attachments directory');
    }
    flock($lock, LOCK_UN);
    $name = sanitize_file_name($_GET['upload']);

    // Stream to a hidden temp file first, without holding the lock, so a slow upload never blocks note saves.
    // On failure, also remove the directory if it was left empty, so it does not count as a note.
    $temp = $files_dir . '/.' . bin2hex(random_bytes(8)) . '.tmp';
    register_shutdown_function(function() use ($temp, $files_dir) {
        if (is_file($temp)) {
            unlink($temp);
            @rmdir($files_dir);
        }
    });
    $in = fopen('php://input', 'rb');
    $out = fopen($temp, 'xb');
    $copied = stream_copy_to_stream($in, $out, $max_file_size + 1);
    fclose($out);
    if ($copied > $max_file_size) {
        fail(413, 'File too large (max ' . ($max_file_size >> 20) . ' MB)');
    }
    if ($copied === 0 || $copied === false) {
        fail(400, 'Empty file');
    }
    if ($length !== '' && $copied !== (int) $length) {
        fail(400, 'Incomplete upload');
    }

    $lock = fopen($save_path . '/.lock', 'c');
    flock($lock, LOCK_EX);
    $target = "$files_dir/$name";
    if (attachments_size($save_path, $target) + $copied > $max_total_size) {
        fail(507, 'Attachment storage full (max ' . ($max_total_size >> 20) . ' MB in total)');
    }
    rename($temp, $target);
    send_files($files_dir);
}

// Delete one attachment, or all of them with ?empty.
if ((isset($_GET['delete']) || isset($_GET['empty'])) && $method === 'POST') {
    $lock = fopen($save_path . '/.lock', 'c');
    flock($lock, LOCK_EX);
    if (isset($_GET['empty'])) {
        foreach (list_files($files_dir) as $file) {
            unlink("$files_dir/{$file['name']}");
        }
    } elseif (valid_file_name($_GET['delete']) && is_file("$files_dir/{$_GET['delete']}")) {
        unlink("$files_dir/{$_GET['delete']}");
    }
    @rmdir($files_dir);
    send_files($files_dir);
}

// Download an attachment.
if (isset($_GET['file'])) {
    if (!valid_file_name($_GET['file']) || !is_file($file = "$files_dir/{$_GET['file']}")) {
        fail(404, 'Not found');
    }
    send_attachment($file, $_GET['file']);
}

// List of attachments as JSON. With since=<version> answers 204 when unchanged (used by the browser's polling).
if (isset($_GET['files'])) {
    if (isset($_GET['since']) && $_GET['since'] === files_version(list_files($files_dir))) {
        http_response_code(204);
        die;
    }
    send_files($files_dir);
}

// Turn the note into a secret that expires in ?secret=<hours> if not opened: its text and attachments
// move to a new random id and the note is gone. Refused with 409 if the text changed since "base".
if (isset($_GET['secret']) && $method === 'POST') {
    $hours = is_string($_GET['secret']) ? (int) $_GET['secret'] : 0;
    if (!in_array($hours, $secret_expiries, true)) {
        fail(400, 'Invalid expiry');
    }
    $lock = fopen($save_path . '/.lock', 'c');
    flock($lock, LOCK_EX);
    if (isset($_POST['base']) && $_POST['base'] !== note_version($path)) {
        fail(409, 'This note changed on another device');
    }
    if (!is_file($path) && !list_files($files_dir)) {
        fail(404, 'The note is empty');
    }
    if (count(secret_ids($secrets_path)) >= $max_secrets) {
        fail(507, "Too many secrets (max $max_secrets at the same time)");
    }
    if (!is_dir($secrets_path) && !@mkdir($secrets_path, 0755) && !is_dir($secrets_path)) {
        fail(500, 'Cannot create secrets directory');
    }
    $id = new_secret_id();
    $key = random_bytes(32);
    $dir = "$secrets_path/$id";
    mkdir($dir, 0755);
    $expires = time() + $hours * 3600;
    file_put_contents("$dir/expires", $expires);
    $files = list_files($files_dir);
    foreach ($files as $i => $file) {
        file_put_contents("$dir/$i", seal(file_get_contents("$files_dir/{$file['name']}"), $key));
    }
    $text = is_file($path) ? file_get_contents($path) : '';
    file_put_contents("$dir/data", seal(json_encode(['text' => $text, 'files' => $files], JSON_INVALID_UTF8_SUBSTITUTE), $key));
    delete_note($path, $files_dir);
    header('Content-Type: application/json');
    print json_encode(['id' => $id, 'key' => rtrim(strtr(base64_encode($key), '+/', '-_'), '='), 'expires' => $expires]);
    die;
}

// Renew the note: reset its idle time without changing it. Touches the text file if there is one
// (so other tabs see it through X-Note-Mtime), otherwise the attachments directory.
if (isset($_GET['renew']) && $method === 'POST') {
    $lock = fopen($save_path . '/.lock', 'c');
    flock($lock, LOCK_EX);
    if (is_file($path)) {
        touch($path);
    } elseif (is_dir($files_dir)) {
        touch($files_dir);
    } else {
        fail(404, 'Not found');
    }
    header('Content-Type: application/json');
    print json_encode(['mtime' => note_mtime($path), 'changed' => files_changed($files_dir)]);
    die;
}

if ($method === 'POST') {
    $text = $_POST['text'] ?? file_get_contents('php://input');
    if (!is_string($text)) {
        fail(400, 'Invalid text');
    }
    if (strlen($text) > $max_note_size) {
        fail(413, 'Note too large (max ' . ($max_note_size >> 20) . ' MB)');
    }

    // Serialize the version check and the write across concurrent requests.
    $lock = fopen($save_path . '/.lock', 'c');
    flock($lock, LOCK_EX);

    // Reject the save if the note changed since the client loaded it.
    // Clients that send no base version (e.g. curl) skip the check.
    if (isset($_POST['base']) && $_POST['base'] !== note_version($path)) {
        http_response_code(409);
        die;
    }

    // Write to a temp file and rename it, so readers never see a half-written note.
    // An empty text deletes the note.
    if (strlen($text)) {
        if (!is_file($path)) {
            check_note_limit($save_path, $_GET['note'], $max_notes);
        }
        file_put_contents($path . '.tmp', $text);
        rename($path . '.tmp', $path);
    } elseif (is_file($path)) {
        unlink($path);
    }

    // Return the new version so the client can send it as base next time.
    header('X-Note-Mtime: ' . note_mtime($path));
    print note_version($path);
    die;
}

// Print raw file when explicitly requested, or if the client is curl or wget.
$agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (!$secret && (isset($_GET['raw']) || strpos($agent, 'curl') === 0 || strpos($agent, 'Wget') === 0)) {

    // Read the note once so the text and its version always match.
    $text = is_file($path) ? file_get_contents($path) : false;
    $version = $text === false ? '' : md5($text);
    header('X-Note-Version: ' . $version);
    header('X-Note-Mtime: ' . note_mtime($path));

    // Nothing to send if the client already has this version (used by the browser to poll for changes).
    if (isset($_GET['since']) && $_GET['since'] === $version) {
        http_response_code(204);
    } elseif ($text !== false) {
        header('Content-type: text/plain');
        print $text;
    } else {
        http_response_code(404);
    }
    die;
}

// Read the note once so the shown text and its version always match.
$initial_text = is_file($path) ? file_get_contents($path) : false;
$initial_version = $initial_text === false ? '' : md5($initial_text);
$initial_mtime = note_mtime($path);
$initial_files = list_files($files_dir);
?><!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php print $secret ? 'Secret' : htmlspecialchars($_GET['note'], ENT_QUOTES, 'UTF-8'); ?></title>
<link rel="icon" href="favicon.ico" sizes="any">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap">
<script>
// Set the theme before the first paint: saved choice, otherwise the system preference
(function() {
    var saved = null;
    try { saved = localStorage.getItem('theme'); } catch (e) {}
    document.documentElement.dataset.theme = saved || (matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
})();
</script>
<style>
html,
body {
    height: 100%;
}
/* Shared by the editor and its hidden mirror, which must wrap text identically
   (#mirror .marker height must match the line height).
   Padding: right keeps text clear of the action buttons (offset 30px + width 64px + gap),
   left leaves room for the copy-block gutter. */
:root {
    --mono: 'JetBrains Mono', monospace;
    --editor-font: 17px/1.6 var(--mono);
    --editor-padding: 20px 120px 20px 56px;
    /* Space below the editor and the files panel, room for the footer */
    --footer-height: 56px;
}
@media (max-width: 800px) {
    :root {
        --editor-font: 15px/1.6 var(--mono);
        --editor-padding: 16px 104px 16px 56px;
    }
}
/* Color tokens. Triplets ("r, g, b") are used as rgb(var(--x)) or rgba(var(--x), alpha). */
:root {
    color-scheme: dark;
    --bg: #004254;
    --bg-from: #004d61;
    --bg-to: #002f3d;
    --surface: 0, 25, 35;
    --text: 227, 226, 218;
    --footer-text: 227, 226, 218;
    --primary: 79, 209, 232;
    --neutral: 229, 231, 235;
    --teal: 45, 212, 191;
    --ok: 110, 231, 183;
    --warn: 245, 165, 36;
    --danger: 242, 139, 150;
    --expires-text: 214, 246, 252;
    --selection: #0a6f88;
    --selection-text: #fff;
    --code-bg: rgba(0, 0, 0, 0.25);
    --shadow: rgba(0, 0, 0, 0.35);
    --shadow-soft: rgba(0, 0, 0, 0.25);
    --backdrop: rgba(0, 0, 0, 0.55);
}
:root[data-theme="light"] {
    color-scheme: light;
    /* Brand palette: Azul Oscuro page background, white surfaces, Azul Oscuro text,
       Azul Claro highlights and Azul Amazónico for the attachments accent */
    --bg: #004254;
    --bg-from: #004254;
    --bg-to: #004254;
    --surface: 255, 255, 255;
    --text: 0, 25, 35;
    --footer-text: 227, 226, 218;
    --primary: 0, 176, 189;
    --neutral: 0, 25, 35;
    --teal: 0, 66, 84;
    --ok: 5, 150, 105;
    --warn: 217, 119, 6;
    --danger: 220, 38, 38;
    --expires-text: 214, 246, 252;
    --selection: #b3e7eb;
    --selection-text: rgb(0, 25, 35);
    --code-bg: rgba(0, 66, 84, 0.08);
    --shadow: rgba(0, 25, 35, 0.12);
    --shadow-soft: rgba(0, 25, 35, 0.10);
    --backdrop: rgba(0, 25, 35, 0.45);
}
button {
    font-family: inherit;
}
body {
    margin: 0;
    font-family: var(--mono);
    background: var(--bg) linear-gradient(160deg, var(--bg-from) 0%, var(--bg-to) 100%) fixed;
}
.container {
    position: absolute;
    top: 20px;
    right: 20px;
    bottom: var(--footer-height);
    left: 20px;
}
#modified {
    position: absolute;
    right: 20px;
    bottom: 12px;
    left: 20px;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    font: 16px/24px var(--mono);
    color: rgb(var(--footer-text));
}
/* Dimmed per child rather than on #modified, so the commands popover stays opaque */
#modified > :not(#cli):not(#saved):not(#secret-info),
#saved > :not(#expires),
#cli-toggle {
    opacity: 0.6;
}
#modified svg {
    flex-shrink: 0;
}
/* Fixed minimum width (fits the time plus the #expires pill), so the theme and repo
   links do not move when "Unsaved note" turns into the time */
#saved {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    min-width: 200px;
}
#repo {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    gap: 6px;
    margin-right: 16px;
    color: inherit;
    text-decoration: none;
    transition: color 0.15s;
}
/* Shows the icon of the theme it switches to */
#theme {
    display: flex;
    flex-shrink: 0;
    padding: 0;
    margin-right: 8px;
    color: inherit;
    background: transparent;
    border: 0;
    cursor: pointer;
    transition: color 0.15s;
}
[data-theme="dark"] #theme .icon-moon,
[data-theme="light"] #theme .icon-sun {
    display: none;
}
#theme:hover,
#repo:hover,
#cli-toggle:hover,
#cli.open #cli-toggle {
    color: rgb(var(--primary));
}
/* Time left until the note is deleted for not being changed: a pill whose background
   empties from full (just changed) to nothing (deleted), with the icon and minutes on top */
#expires {
    position: relative;
    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    gap: 4px;
    width: 104px;
    height: 20px;
    margin-left: 8px;
    overflow: hidden;
    font-size: 13px;
    line-height: 20px;
    color: rgb(var(--expires-text));
    background: rgba(var(--primary), 0.12);
    padding: 0;
    font-family: inherit;
    border: 0;
    border-radius: 999px;
    cursor: pointer;
    transition: color 0.3s, background 0.3s, box-shadow 0.15s;
}
#expires:hover {
    box-shadow: 0 0 0 1px rgba(var(--primary), 0.6);
}
#expires.soon:hover {
    box-shadow: 0 0 0 1px rgba(var(--danger), 0.6);
}
#expires[hidden] {
    display: none;
}
#expires.soon {
    color: rgb(var(--danger));
    background: rgba(var(--danger), 0.12);
}
#expires-fill {
    position: absolute;
    top: 0;
    bottom: 0;
    left: 0;
    background: rgba(var(--primary), 0.35);
    transition: width 1s linear, background 0.3s;
}
#expires.soon #expires-fill {
    background: rgba(var(--danger), 0.25);
}
#expires svg,
#expires-label {
    position: relative;
}
#status {
    flex-shrink: 0;
    width: 10px;
    height: 10px;
    margin-right: 4px;
    border-radius: 50%;
    background: currentColor;
    box-shadow: 0 0 8px currentColor;
    transition: color 0.3s;
}
#status.saved {
    color: rgb(var(--ok));
}
#status.saving {
    color: rgb(var(--warn));
    animation: pulse 1s ease-in-out infinite alternate;
}
#status.conflict,
#status.error {
    color: rgb(var(--danger));
}
@keyframes pulse {
    to {
        opacity: 0.3;
    }
}
/* curl commands: a footer button that shows them in a popover on hover (or click, pinned) */
#cli {
    position: relative;
    margin-right: auto;
}
#cli-toggle {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 0;
    font: inherit;
    color: inherit;
    background: transparent;
    border: 0;
    cursor: pointer;
    transition: color 0.15s;
}
#cli.open #cli-toggle {
    opacity: 1;
}
/* The bottom padding bridges the gap to the button, so hover is not lost on the way up */
#cli-panel {
    position: absolute;
    bottom: 100%;
    left: 0;
    z-index: 5;
    display: none;
    padding-bottom: 10px;
}
@media (hover: hover) {
    #cli:hover #cli-panel {
        display: block;
    }
}
#cli.open #cli-panel {
    display: block;
}
/* Label, command and copy button columns */
#cli-list {
    display: grid;
    grid-template-columns: max-content minmax(0, 1fr) auto;
    align-items: center;
    gap: 6px 10px;
    width: max-content;
    max-width: calc(100vw - 40px);
    box-sizing: border-box;
    padding: 12px 10px 12px 14px;
    font-size: 14px;
    line-height: 20px;
    color: rgb(var(--text));
    background: rgb(var(--surface));
    border: 1px solid rgba(var(--text), 0.12);
    border-radius: 10px;
    box-shadow: 0 8px 24px var(--shadow);
}
#cli-list span {
    opacity: 0.6;
    cursor: pointer;
}
#cli-list code {
    justify-self: start;
    max-width: 100%;
    box-sizing: border-box;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    padding: 2px 8px;
    font: inherit;
    background: var(--code-bg);
    border-radius: 4px;
    cursor: pointer;
    transition: color 0.15s, background 0.15s;
}
#cli-list code:hover {
    color: rgb(var(--teal));
    background: rgba(var(--teal), 0.15);
}
#cli-list .file-action {
    width: 28px;
    height: 28px;
}
#content {
    margin: 0;
    padding: var(--editor-padding);
    overflow-y: auto;
    resize: none;
    width: 100%;
    height: 100%;
    box-sizing: border-box;
    background: rgb(var(--surface));
    color: rgb(var(--text));
    caret-color: rgb(var(--primary));
    border: 1px solid rgba(var(--primary), 0.15);
    border-radius: 12px;
    box-shadow: 0 12px 32px var(--shadow);
    outline: none;
    font: var(--editor-font);
    transition: border-color 0.2s, box-shadow 0.2s;
}
#content:focus {
    border-color: rgba(var(--primary), 0.3);
}
#content::selection {
    background: var(--selection);
    color: var(--selection-text);
}
#printable {
    display: none;
}
/* Hidden copy of the editor text, used to measure where each block starts.
   Clipped to the editor height, otherwise a long note makes the whole page scroll. */
#mirror {
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    overflow: hidden;
    visibility: hidden;
    pointer-events: none;
    box-sizing: border-box;
    padding: var(--editor-padding);
    font: var(--editor-font);
    white-space: pre-wrap;
    overflow-wrap: break-word;
}
#mirror .marker {
    display: inline-block;
    width: 0;
    height: 1.6em;
    vertical-align: top;
}
#gutter {
    position: absolute;
    top: 1px;
    left: 1px;
    bottom: 1px;
    width: 50px;
    overflow: hidden;
    pointer-events: none;
}
#gutter::after {
    content: '';
    position: absolute;
    top: 12px;
    right: 0;
    bottom: 12px;
    width: 1px;
    background: rgba(var(--text), 0.08);
}
#gutter-inner {
    position: relative;
}
.block-bar {
    position: absolute;
    left: 4px;
    width: 3px;
    border-radius: 2px;
    background: rgb(var(--primary));
    opacity: 0;
    transition: opacity 0.15s;
}
.copy-btn:hover + .block-bar {
    opacity: 0.8;
}
/* Blocks that just arrived from another device light up for a moment: the gutter bar
   and a soft background behind the text (#glow, a layer over the editor scrolled like the gutter),
   fading in and out */
.block-bar.flash {
    animation: flash-bar 2.4s ease-in-out;
}
@keyframes flash-bar {
    0%, 100% {
        opacity: 0;
        box-shadow: 0 0 0 rgb(var(--primary));
    }
    20% {
        opacity: 1;
        box-shadow: 0 0 10px rgb(var(--primary));
    }
}
#glow {
    position: absolute;
    inset: 1px;
    overflow: hidden;
    border-radius: 12px;
    pointer-events: none;
}
#glow-inner {
    position: relative;
}
.block-glow {
    position: absolute;
    left: 50px;
    right: 0;
    border-radius: 6px;
    background: linear-gradient(90deg, rgba(var(--primary), 0.22), rgba(var(--primary), 0.08) 60%, rgba(var(--primary), 0));
    opacity: 0;
    animation: flash-glow 2.4s ease-in-out;
}
@keyframes flash-glow {
    20% {
        opacity: 1;
    }
}
.copy-btn.copied + .block-bar {
    background: rgb(var(--ok));
    opacity: 0.8;
}
.copy-btn {
    position: absolute;
    left: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    padding: 0;
    color: rgb(var(--text));
    background: transparent;
    border: 0;
    border-radius: 6px;
    opacity: 0.4;
    cursor: pointer;
    pointer-events: auto;
    transition: opacity 0.15s, background 0.15s;
}
.copy-btn:hover {
    opacity: 1;
    background: rgba(var(--primary), 0.12);
}
.icon-check,
.copied .icon-copy {
    display: none;
}
.copied .icon-check {
    display: block;
}
.copy-btn.copied {
    color: rgb(var(--ok));
    opacity: 1;
}
.action {
    position: absolute;
    right: 30px;
    z-index: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
    width: 64px;
    height: 64px;
    padding: 0;
    cursor: pointer;
    --accent: var(--primary);
    color: rgb(var(--accent));
    background: rgba(var(--accent), 0.08);
    border: 1px solid rgba(var(--accent), 0.35);
    border-radius: 12px;
    box-shadow: 0 6px 18px var(--shadow-soft);
    backdrop-filter: blur(4px);
    transition: background 0.15s, border-color 0.15s, transform 0.15s;
}
.action:hover {
    background: rgba(var(--accent), 0.2);
    border-color: rgba(var(--accent), 0.7);
    transform: translateY(-1px);
}
.action:disabled {
    opacity: 0.5;
    cursor: default;
}
.action small {
    position: absolute;
    top: 50%;
    right: calc(100% + 10px);
    padding: 4px 10px;
    font-size: 14px;
    line-height: 20px;
    white-space: nowrap;
    color: rgb(var(--accent));
    background: rgb(var(--surface));
    border: 1px solid rgba(var(--accent), 0.35);
    border-radius: 6px;
    opacity: 0;
    pointer-events: none;
    transform: translate(4px, -50%);
    transition: opacity 0.15s, transform 0.15s;
}
.action:hover small {
    opacity: 1;
    transform: translate(0, -50%);
}
#copy-all {
    bottom: 128px;
    --accent: var(--neutral);
}
#refresh {
    top: 40px;
    display: none;
}
#clear {
    bottom: 40px;
    --accent: var(--danger);
}
#refresh.conflict {
    display: flex;
    --accent: var(--warn);
    background: rgba(var(--accent), 0.25);
    border-color: rgb(var(--accent));
}
#attach {
    bottom: 216px;
    --accent: var(--teal);
}
#make-secret {
    bottom: 304px;
}
/* Cards: the dialog that turns the note into a secret, and the page of a secret before opening it */
.card {
    box-sizing: border-box;
    width: min(460px, calc(100vw - 32px));
    padding: 24px;
    font: 15px/22px var(--mono);
    color: rgb(var(--text));
    background: rgb(var(--surface));
    border: 1px solid rgba(var(--primary), 0.25);
    border-radius: 12px;
    box-shadow: 0 12px 32px var(--shadow);
}
.card h2 {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0 0 12px;
    font-size: 18px;
    font-weight: 500;
    color: rgb(var(--primary));
}
.card p {
    margin: 0 0 16px;
}
.card .muted {
    opacity: 0.6;
}
.card .error {
    color: rgb(var(--danger));
}
.choices {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.choices.end {
    justify-content: flex-end;
    margin-top: 16px;
}
.card-button {
    flex: 1;
    height: 38px;
    padding: 0 14px;
    font-size: 14px;
    cursor: pointer;
    --accent: var(--primary);
    color: rgb(var(--accent));
    background: rgba(var(--accent), 0.08);
    border: 1px solid rgba(var(--accent), 0.35);
    border-radius: 8px;
    transition: background 0.15s, border-color 0.15s;
}
.card-button.secondary {
    flex: 0 0 auto;
    --accent: var(--neutral);
}
.card-button:not(:disabled):hover {
    background: rgba(var(--accent), 0.2);
    border-color: rgba(var(--accent), 0.7);
}
.card-button:disabled {
    opacity: 0.5;
    cursor: default;
}
.link {
    display: flex;
    align-items: center;
    gap: 6px;
}
.link code {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    padding: 6px 10px;
    font: 14px/22px var(--mono);
    background: var(--code-bg);
    border-radius: 6px;
}
.link .file-action {
    width: 32px;
    height: 32px;
}
#secret-dialog {
    margin: auto;
}
#secret-expires {
    margin: 12px 0 0;
}
#secret-dialog::backdrop {
    background: var(--backdrop);
}
/* Secret page: only the card until it is opened, then the usual editor (read-only) and files panel */
#gate {
    position: absolute;
    top: 20px;
    right: 0;
    bottom: var(--footer-height);
    left: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}
body.secret-view:not(.opened) .container,
body.secret-view:not(.opened) #files,
body.opened #gate {
    display: none;
}
/* The one-time warning on the secret page */
.once {
    display: flex;
    gap: 12px;
    margin: 0 0 16px;
    padding: 12px 14px;
    background: rgba(var(--warn), 0.12);
    border: 1px solid rgba(var(--warn), 0.5);
    border-radius: 8px;
}
.once svg {
    flex-shrink: 0;
    color: rgb(var(--warn));
}
.once strong {
    display: block;
    margin-bottom: 2px;
    font-size: 16px;
    font-weight: 500;
    color: rgb(var(--warn));
}
#secret-info {
    margin-right: auto;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}
/* Attachments panel: hidden until the first file, then splits the page about 80/20
   (side by side, or stacked on narrow screens) */
:root {
    --files-width: max(280px, 20%);
    --files-height: 35%;
}
#files {
    position: absolute;
    top: 20px;
    right: 20px;
    bottom: var(--footer-height);
    width: calc(var(--files-width) - 20px);
    display: none;
    flex-direction: column;
    gap: 12px;
    box-sizing: border-box;
    padding: 14px;
    font: 15px/21px var(--mono);
    color: rgb(var(--text));
    background: rgb(var(--surface));
    border: 1px solid rgba(var(--primary), 0.15);
    border-radius: 12px;
    box-shadow: 0 12px 32px var(--shadow);
}
body.has-files .container {
    right: calc(var(--files-width) + 16px);
}
body.has-files #files {
    display: flex;
}
@media (max-width: 800px) {
    #files {
        top: auto;
        left: 20px;
        width: auto;
        height: var(--files-height);
    }
    body.has-files .container {
        right: 20px;
        bottom: calc(var(--files-height) + var(--footer-height) + 16px);
    }
}
#files-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    font-weight: 500;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: rgba(var(--text), 0.6);
}
#files-count {
    padding: 0 8px;
    font-size: 12px;
    line-height: 18px;
    letter-spacing: 0;
    color: rgb(var(--teal));
    background: rgba(var(--teal), 0.12);
    border-radius: 999px;
}
#files-list {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
}
.file {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 6px;
    padding: 8px 4px 8px 10px;
    background: rgba(var(--primary), 0.08);
    border-radius: 8px;
}
.file:hover {
    background: rgba(var(--primary), 0.15);
}
.file-icon {
    flex-shrink: 0;
    opacity: 0.55;
}
.file .meta {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-width: 0;
}
.file .name {
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}
.file .name {
    color: rgb(var(--text));
    text-decoration: none;
    transition: color 0.15s;
}
a.name:hover {
    color: rgb(var(--teal));
    text-decoration: underline;
}
.file .details {
    display: flex;
    align-items: center;
    gap: 2px;
}
/* Fixed width (monospace) so the buttons line up across cards */
.file .size {
    min-width: 7ch;
    margin-right: 4px;
    font-size: 12px;
    line-height: 18px;
    opacity: 0.5;
}
/* Small icon buttons next to the size: download and copy the curl command */
.file-action {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    padding: 0;
    color: rgb(var(--text));
    background: transparent;
    border: 0;
    border-radius: 5px;
    opacity: 0.45;
    cursor: pointer;
    transition: opacity 0.15s, color 0.15s, background 0.15s;
}
.file-action:hover {
    color: rgb(var(--teal));
    opacity: 1;
    background: rgba(var(--teal), 0.15);
}
.file-action.copied {
    color: rgb(var(--ok));
    opacity: 1;
}
.file .remove {
    flex-shrink: 0;
    width: 32px;
    height: 32px;
    padding: 0;
    font-size: 26px;
    line-height: 1;
    color: rgb(var(--text));
    background: transparent;
    border: 0;
    border-radius: 6px;
    opacity: 0.35;
    cursor: pointer;
    transition: opacity 0.15s, color 0.15s, background 0.15s;
}
.file .remove:hover {
    color: rgb(var(--danger));
    opacity: 1;
    background: rgba(var(--danger), 0.15);
}
.file.uploading {
    opacity: 0.6;
}
/* Outlined version of the .action buttons, full width */
#empty {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    height: 38px;
    font-size: 14px;
    cursor: pointer;
    --accent: var(--danger);
    color: rgb(var(--accent));
    background: transparent;
    border: 1px solid rgba(var(--accent), 0.3);
    border-radius: 8px;
    transition: background 0.15s, border-color 0.15s;
}
#empty:disabled {
    opacity: 0.4;
    cursor: default;
}
#empty:not(:disabled):hover {
    background: rgba(var(--accent), 0.12);
    border-color: rgba(var(--accent), 0.7);
}
#drop {
    position: fixed;
    inset: 0;
    z-index: 10;
    display: none;
    align-items: center;
    justify-content: center;
    font: 26px var(--mono);
    color: rgb(var(--primary));
    background: rgba(var(--surface), 0.85);
    border: 3px dashed rgb(var(--primary));
    border-radius: 12px;
}
body.dragging #drop {
    display: flex;
}
@media print {
    .container,
    #files,
    #modified {
        display: none;
    }
    #printable {
        display: block;
        white-space: pre-wrap;
        word-break: break-word;
    }
}
</style>
</head>
<body<?php if ($secret) { print ' class="secret-view"'; } ?>>
<div class="container">
<textarea id="content"<?php if ($secret) { print ' readonly placeholder="No text, only files"'; } ?>><?php
if ($initial_text !== false) {
    print htmlspecialchars($initial_text, ENT_QUOTES, 'UTF-8');
}
?></textarea>
<div id="glow"><div id="glow-inner"></div></div>
<div id="gutter"><div id="gutter-inner"></div></div>
<div id="mirror" aria-hidden="true"></div>
<button id="copy-all" class="action" type="button" title="Copy all"><svg class="icon-copy" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg><svg class="icon-check" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg><small>copy all</small></button>
<?php if (!$secret): ?>
<button id="refresh" class="action" type="button" title="This note changed on another device: click to load the server version"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg><small>refresh</small></button>
<button id="clear" class="action" type="button" title="Clear note"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m16 22-1-4"/><path d="M19 14a1 1 0 0 0 1-1v-1a2 2 0 0 0-2-2h-3a1 1 0 0 1-1-1V4a2 2 0 0 0-4 0v5a1 1 0 0 1-1 1H6a2 2 0 0 0-2 2v1a1 1 0 0 0 1 1"/><path d="M5 14h14l1.973 6.767A1 1 0 0 1 20 22H4a1 1 0 0 1-.973-1.233z"/><path d="m8 22 1-4"/></svg><small>clear</small></button>
<button id="attach" class="action" type="button" title="Attach file (or drop files anywhere)"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg><small>attach file</small></button>
<button id="make-secret" class="action" type="button" title="Make it a secret: turn this note into a one-time link"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4z"/><circle cx="16.5" cy="7.5" r=".5" fill="currentColor"/></svg><small>make it a secret</small></button>
<input id="file-input" type="file" multiple hidden>
<?php endif; ?>
</div>
<div id="files">
<div id="files-title">Files <span id="files-count">0</span></div>
<div id="files-list"></div>
<?php if (!$secret): ?>
<button id="empty" type="button" title="Delete all files (the note text is kept)"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>empty</button>
<?php endif; ?>
</div>
<?php if ($secret): ?>
<div id="gate"><div class="card">
<h2><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>Secret</h2>
<?php if ($secret['id']): ?>
<p>Someone shared a secret with you.</p>
<div class="once"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg><div><strong>It can be opened only once</strong>Opening it deletes it from the server, so copy or download what you need right away.</div></div>
<p id="gate-expires" class="muted"></p>
<p id="gate-error" class="error" hidden></p>
<div class="choices"><button id="open-secret" class="card-button" type="button">open secret</button></div>
<?php else: ?>
<p>This secret does not exist: it was already opened or it expired.</p>
<?php endif; ?>
</div></div>
<?php else: ?>
<div id="drop">Drop files to attach them (max <?php print $max_file_size >> 20; ?> MB each)</div>
<dialog id="secret-dialog" class="card">
<div id="secret-ask">
<h2><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>Make it a secret</h2>
<p>The text and files of this note become a link that can be opened only once, and the note is deleted.</p>
<p class="muted">If nobody opens it, it expires in:</p>
<div class="choices"><?php foreach ($secret_expiries as $hours): ?><button class="card-button" type="button" data-hours="<?php print $hours; ?>"><?php print $hours === 1 ? '1 hour' : ($hours < 168 ? ($hours / 24) . ' day' . ($hours > 24 ? 's' : '') : ($hours / 168) . ' week' . ($hours > 168 ? 's' : '')); ?></button><?php endforeach; ?></div>
<p id="secret-error" class="error" hidden></p>
<div class="choices end"><button class="card-button secondary secret-close" type="button">cancel</button></div>
</div>
<div id="secret-done" hidden>
<h2><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>Secret ready</h2>
<p>Share this link. It can be opened only once.</p>
<div class="link"><code id="secret-link"></code><button id="secret-copy" class="file-action" type="button" title="Copy link"></button></div>
<p id="secret-expires" class="muted"></p>
<div class="choices end"><button class="card-button secondary secret-close" type="button">close</button></div>
</div>
</dialog>
<?php endif; ?>
<div id="modified"><?php if ($secret): ?><span id="secret-info"></span><?php else: ?><div id="cli"><div id="cli-panel"><div id="cli-list"></div></div><button id="cli-toggle" type="button" aria-expanded="false" title="Command-line usage"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 17 10 11 4 5"/><line x1="12" x2="20" y1="19" y2="19"/></svg>commands help</button></div><?php endif; ?><button id="theme" type="button" title="Switch light/dark theme"><svg class="icon-sun" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg><svg class="icon-moon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg></button><a id="repo" href="<?php print $repo_url; ?>" target="_blank" rel="noopener noreferrer" title="Source code on GitHub"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.93.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.22.165.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>v<?php print $app_version; ?></a><?php if (!$secret): ?><span id="saved"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M12 7v5l3 2"/></svg><span id="modified-time" title="Last modified"></span><button id="expires" type="button" hidden><span id="expires-fill"></span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg><span id="expires-label"></span></button></span><span id="status"></span><?php endif; ?></div>
<pre id="printable"></pre>
<script>
function updateStatus() {
    var state = conflict ? 'conflict' : (saveError ? 'error' : (content !== textarea.value ? 'saving' : 'saved'));
    statusDot.className = state;
    statusDot.title = {
        saved: 'Saved',
        saving: 'Saving…',
        conflict: 'Not saved: changed on another device',
        error: 'Not saved: ' + saveError
    }[state];
}

// Reason the server refused the last save (size or note count limit), or empty.
function setSaveError(message) {
    saveError = message;
    updateStatus();
}

function setConflict(value) {
    conflict = value;
    updateStatus();
    refreshButton.classList.toggle('conflict', value);
}

// Show the server copy of the note from a ?raw response, keeping the cursor and scroll in place.
function applyRemote(request) {
    var text = request.status === 200 ? request.responseText : '';
    var old = textarea.value;
    if (text !== old && document.activeElement === textarea) {
        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        var scroll = textarea.scrollTop;
        var prefix = 0;
        while (prefix < old.length && prefix < text.length && old[prefix] === text[prefix]) {
            prefix++;
        }

        // Positions after the first change shift by the length difference.
        var shift = function(pos) {
            return pos <= prefix ? pos : Math.min(text.length, Math.max(prefix, pos + text.length - old.length));
        };
        textarea.value = text;
        textarea.setSelectionRange(shift(start), shift(end));
        textarea.scrollTop = scroll;
    } else {
        textarea.value = text;
    }
    content = text;
    version = request.getResponseHeader('X-Note-Version') || '';
    setMtime(request.getResponseHeader('X-Note-Mtime'));
    updateStatus();
    updateGutter();

    // Light up the blocks that were not in the old text.
    var known = {};
    findBlocks(old).forEach(function(block) {
        known[old.slice(block.start, block.end)] = true;
    });
    findBlocks(text).forEach(function(block, i) {
        if (!known[text.slice(block.start, block.end)]) {
            var bar = blockBars[i];
            bar.classList.add('flash');
            var glow = document.createElement('div');
            glow.className = 'block-glow';
            glow.style.top = (parseFloat(bar.style.top) - 4) + 'px';
            glow.style.height = (parseFloat(bar.style.height) + 8) + 'px';
            glow.onanimationend = function() {
                this.remove();
            };
            glowInner.appendChild(glow);
        }
    });
}

// Load changes made on other devices, but only when everything typed here is already saved.
function checkRemote() {
    if (checking || document.hidden || conflict || saving || content !== textarea.value) {
        return;
    }
    checking = true;
    var sent = version;
    var request = new XMLHttpRequest();
    request.open('GET', window.location.pathname + '?raw&since=' + encodeURIComponent(sent), true);
    request.onload = function() {
        checking = false;

        // Ignore the answer if something changed here while it was on its way.
        if (version !== sent || conflict || saving || content !== textarea.value) {
            return;
        }
        if (request.status === 200 || request.status === 404) {
            applyRemote(request);
        } else if (request.status === 204) {

            // Same text, but it may have been renewed elsewhere.
            setMtime(request.getResponseHeader('X-Note-Mtime'));
        }
    };
    request.onerror = function() {
        checking = false;
    };
    request.send();
}

function uploadContent() {

    // While in conflict, autosave is paused so the server copy is not overwritten.
    if (saving || conflict || content === textarea.value) {
        return;
    }

    // Over the size limit the server would answer 413: don't send it, flag it until the text shrinks.
    if (new Blob([textarea.value]).size > MAX_NOTE_SIZE) {
        setSaveError('Note too large (max ' + (MAX_NOTE_SIZE >> 20) + ' MB)');
        return;
    }
    saving = true;
    var temp = textarea.value;
    var request = new XMLHttpRequest();
    request.open('POST', window.location.href, true);
    request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
    request.onload = function() {
        saving = false;
        if (request.status === 200) {
            content = temp;
            version = request.responseText;
            setMtime(request.getResponseHeader('X-Note-Mtime'));
            setSaveError('');
        } else if (request.status === 409) {

            // The note changed on another device since it was loaded here.
            setConflict(true);
        } else if (request.status === 413 || request.status === 507) {

            // Size or note count limit: keep trying (cheap below the size limit) and show the reason.
            setSaveError(request.responseText);
        }
    };
    request.onerror = function() {
        saving = false;
    };
    request.send('text=' + encodeURIComponent(temp) + '&base=' + encodeURIComponent(version));
}

// Blocks of text separated by blank lines, as [start, end) offsets.
function findBlocks(text) {
    var lines = text.split('\n');
    var blocks = [];
    var pos = 0;
    var current = null;
    for (var i = 0; i < lines.length; i++) {
        var line = lines[i];
        if (line.trim() === '') {
            if (current) {
                blocks.push(current);
                current = null;
            }
        } else if (!current) {
            current = { start: pos, end: pos + line.length };
        } else {
            current.end = pos + line.length;
        }
        pos += line.length + 1;
    }
    if (current) {
        blocks.push(current);
    }
    return blocks;
}

// Fallback for non-secure contexts, where navigator.clipboard is unavailable.
function legacyCopy(text) {
    var start = textarea.selectionStart, end = textarea.selectionEnd;
    var temp = document.createElement('textarea');
    temp.value = text;
    temp.style.position = 'fixed';
    temp.style.opacity = '0';
    document.body.appendChild(temp);
    temp.select();
    var ok = false;
    try {
        ok = document.execCommand('copy');
    } catch (e) {}
    document.body.removeChild(temp);
    textarea.focus();
    textarea.setSelectionRange(start, end);
    return ok;
}

function copyBlock(text, button) {
    function done() {
        button.classList.add('copied');
        setTimeout(function() {
            button.classList.remove('copied');
        }, 1000);
    }
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(done, function() {
            if (legacyCopy(text)) {
                done();
            }
        });
    } else if (legacyCopy(text)) {
        done();
    }
}

var COPY_ICONS = '<svg class="icon-copy" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>'
    + '<svg class="icon-check" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';

function syncGutterScroll() {
    gutterInner.style.transform = glowInner.style.transform = 'translateY(' + (-textarea.scrollTop) + 'px)';
}

// Rebuild the copy buttons, placing each one at the first line of its block.
function updateGutter() {
    var text = textarea.value;
    var blocks = findBlocks(text);

    // The mirror wraps text exactly like the textarea, so markers give line positions.
    mirror.style.width = textarea.clientWidth + 'px';
    mirror.textContent = '';
    var markers = [];
    var endMarkers = [];
    var last = 0;
    function addMarker(list) {
        var marker = document.createElement('span');
        marker.className = 'marker';
        mirror.appendChild(marker);
        list.push(marker);
    }
    blocks.forEach(function(block) {
        mirror.appendChild(document.createTextNode(text.slice(last, block.start)));
        addMarker(markers);
        mirror.appendChild(document.createTextNode(text.slice(block.start, block.end)));
        addMarker(endMarkers);
        last = block.end;
    });
    mirror.appendChild(document.createTextNode(text.slice(last)));

    gutterInner.textContent = '';
    blockBars = [];
    blocks.forEach(function(block, i) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'copy-btn';
        button.title = 'Copy this block';
        button.innerHTML = COPY_ICONS;
        button.style.top = markers[i].offsetTop + 'px';

        // Keep the focus (and cursor) in the editor.
        button.onmousedown = function(e) {
            e.preventDefault();
        };
        button.onclick = function() {
            copyBlock(text.slice(block.start, block.end), button);
        };
        gutterInner.appendChild(button);

        // Bar spanning the block, shown while hovering its copy button.
        var bar = document.createElement('div');
        bar.className = 'block-bar';
        bar.style.top = markers[i].offsetTop + 'px';
        bar.style.height = (endMarkers[i].offsetTop + endMarkers[i].offsetHeight - markers[i].offsetTop) + 'px';
        gutterInner.appendChild(bar);
        blockBars.push(bar);
    });
    syncGutterScroll();
}

var blockBars = [];
var gutterQueued = false;
function scheduleGutter() {
    if (!gutterQueued) {
        gutterQueued = true;
        requestAnimationFrame(function() {
            gutterQueued = false;
            updateGutter();
        });
    }
}

// Latest change of the note text or any of its files.
function showModified() {
    var latest = files.reduce(function(max, file) {
        return Math.max(max, file.mtime);
    }, mtime);
    modifiedLabel.textContent = latest
        ? new Date(latest * 1000).toLocaleTimeString('en-GB', {hour: '2-digit', minute: '2-digit'})
        : 'Unsaved note';
    showExpiry(Math.max(latest, filesChanged));
}

// Time left until the server deletes the note for not being changed (same rule as delete_expired_notes()).
// Uses the server clock: serverOffset corrects the difference with this device's clock.
function showExpiry(changed) {
    expires.hidden = !NOTE_TTL || !changed;
    if (expires.hidden) {
        return;
    }
    var deadline = changed + NOTE_TTL;
    var seconds = Math.min(NOTE_TTL, Math.max(0, deadline - (Date.now() / 1000 + serverOffset)));
    var left = Math.ceil(seconds / 60);
    expiresFill.style.width = (seconds / NOTE_TTL * 100) + '%';
    expires.classList.toggle('soon', left <= 10);
    expires.title = 'Auto-delete in ' + (seconds < 60 ? 'less than a minute' : left + (left === 1 ? ' minute' : ' minutes'))
        + '. Click to renew it (or edit the note).';
    expiresLabel.textContent = (seconds < 60 ? '<1' : left) + ' min';
}

function setMtime(header) {
    mtime = parseInt(header, 10) || 0;
    showModified();
}

function formatSize(bytes) {
    if (bytes < 1024) {
        return bytes + ' B';
    }
    return bytes < 1048576 ? Math.round(bytes / 1024) + ' KB' : (bytes / 1048576).toFixed(1) + ' MB';
}

// Names always go through textContent, never innerHTML.
var FILE_ICONS = {
    image: '<rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>',
    text: '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
    archive: '<rect width="20" height="5" x="2" y="3" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/><path d="M10 12h4"/>',
    file: '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/>'
};

// Icon chosen by extension; only constant markup goes through innerHTML.
function fileIcon(name) {
    var ext = ((name.match(/\.([a-z0-9]+)$/i) || [])[1] || '').toLowerCase();
    var kind = /^(png|jpe?g|gif|webp|svg|bmp|heic|avif)$/.test(ext) ? 'image'
        : /^(txt|md|log|conf|cfg|ini|json|ya?ml|xml|csv|pdf|docx?|odt|sh|py|js|php|html?|css)$/.test(ext) ? 'text'
        : /^(zip|gz|tgz|tar|7z|rar|xz|bz2)$/.test(ext) ? 'archive' : 'file';
    return '<svg class="file-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' + FILE_ICONS[kind] + '</svg>';
}

function fileRow(name, detail, href) {
    var row = document.createElement('div');
    row.className = 'file';
    row.innerHTML = fileIcon(name);
    var meta = document.createElement('div');
    meta.className = 'meta';
    row.appendChild(meta);
    var label = document.createElement(href ? 'a' : 'span');
    label.className = 'name';
    label.textContent = name;
    label.title = name;
    if (href) {
        label.href = href;
        label.title = 'Download ' + name;
    }
    meta.appendChild(label);
    var details = document.createElement('div');
    details.className = 'details';
    meta.appendChild(details);
    var size = document.createElement('span');
    size.className = 'size';
    size.textContent = detail;
    details.appendChild(size);
    return row;
}

var DOWNLOAD_ICON = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 15V3"/><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/></svg>';
var COMMAND_ICONS = '<svg class="icon-copy" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 17 10 11 4 5"/><line x1="12" x2="20" y1="19" y2="19"/></svg>'
    + '<svg class="icon-check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';

function renderFiles() {
    filesList.textContent = '';
    files.forEach(function(file) {
        var href = window.location.pathname + '?file=' + encodeURIComponent(file.name);
        var chip = fileRow(file.name, formatSize(file.size), href);
        var details = chip.querySelector('.details');
        var download = document.createElement('a');
        download.className = 'file-action';
        download.href = href;
        download.title = 'Download';
        download.innerHTML = DOWNLOAD_ICON;
        details.appendChild(download);
        var command = document.createElement('button');
        command.type = 'button';
        command.className = 'file-action';
        command.title = 'Copy download command (curl)';
        command.innerHTML = COMMAND_ICONS;
        command.onclick = function() {
            copyBlock('curl -O ' + noteUrl + '/' + file.name, command);
        };
        details.appendChild(command);
        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'remove';
        remove.title = 'Delete file';
        remove.textContent = '×';
        remove.onclick = function() {
            fileRequest('POST', '?delete=' + encodeURIComponent(file.name));
        };
        chip.appendChild(remove);
        filesList.appendChild(chip);
    });
    uploads.forEach(function(upload) {
        var chip = fileRow(upload.file.name, upload.progress + '%');
        chip.classList.add('uploading');
        filesList.appendChild(chip);
    });
    filesCount.textContent = files.length;
    showModified();
    emptyButton.disabled = !files.length;

    // Resizing the editor rewraps its text, so the copy buttons must be placed again.
    var hasFiles = files.length + uploads.length > 0;
    if (hasFiles !== document.body.classList.contains('has-files')) {
        document.body.classList.toggle('has-files', hasFiles);
        scheduleGutter();
    }
}

function applyFiles(request) {
    var data = JSON.parse(request.responseText);
    files = data.files;
    filesVersion = data.version;
    filesChanged = data.changed || 0;
    renderFiles();
}

// Request answered with the attachment list (delete, or poll for changes from other devices).
function fileRequest(method, query) {
    var request = new XMLHttpRequest();
    request.open(method, window.location.pathname + query, true);
    request.onload = function() {
        if (request.status === 200) {
            applyFiles(request);
        }
    };
    request.send();
    return request;
}

function checkFiles() {
    if (checkingFiles || document.hidden) {
        return;
    }
    checkingFiles = true;
    fileRequest('GET', '?files&since=' + encodeURIComponent(filesVersion)).onloadend = function() {
        checkingFiles = false;
    };
}

// Upload queued files one at a time.
function nextUpload() {
    var upload = uploads[0];
    if (!upload || upload.request) {
        return;
    }
    var request = upload.request = new XMLHttpRequest();
    request.open('POST', window.location.pathname + '?upload=' + encodeURIComponent(upload.file.name), true);
    request.setRequestHeader('Content-Type', 'application/octet-stream');
    request.upload.onprogress = function(e) {
        if (e.lengthComputable) {
            upload.progress = Math.round(e.loaded * 100 / e.total);
            renderFiles();
        }
    };
    request.onloadend = function() {
        uploads.shift();
        if (request.status === 200) {
            applyFiles(request);
        } else {
            renderFiles();
            alert('Could not upload ' + upload.file.name + ': ' + (request.responseText || 'network error'));
        }
        nextUpload();
    };
    request.send(upload.file);
}

function uploadFiles(list) {
    Array.prototype.forEach.call(list, function(file) {
        if (file.size > MAX_FILE_SIZE) {
            alert(file.name + ' is too large (max ' + formatSize(MAX_FILE_SIZE) + ').');
        } else {
            uploads.push({file: file, progress: 0});
        }
    });
    renderFiles();
    nextUpload();
}

function isFileDrag(e) {
    return e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types, 'Files') !== -1;
}

var textarea = document.getElementById('content');
var gutterInner = document.getElementById('gutter-inner');
var glowInner = document.getElementById('glow-inner');
var mirror = document.getElementById('mirror');
var printable = document.getElementById('printable');
// Theme toggle: a choice equal to the system preference is forgotten, so the page follows the system again
var systemLight = matchMedia('(prefers-color-scheme: light)');
function systemTheme() {
    return systemLight.matches ? 'light' : 'dark';
}
document.getElementById('theme').addEventListener('click', function() {
    var theme = document.documentElement.dataset.theme === 'light' ? 'dark' : 'light';
    document.documentElement.dataset.theme = theme;
    try {
        if (theme === systemTheme()) {
            localStorage.removeItem('theme');
        } else {
            localStorage.setItem('theme', theme);
        }
    } catch (e) {}
});
systemLight.addEventListener('change', function() {
    var saved = null;
    try { saved = localStorage.getItem('theme'); } catch (e) {}
    if (!saved) {
        document.documentElement.dataset.theme = systemTheme();
    }
});

updateGutter();
if (document.fonts) {
    document.fonts.ready.then(scheduleGutter);
    document.fonts.addEventListener('loadingdone', scheduleGutter);
}
textarea.addEventListener('input', scheduleGutter);
textarea.addEventListener('scroll', syncGutterScroll);
window.addEventListener('resize', scheduleGutter);
window.addEventListener('beforeprint', function() {
    printable.textContent = textarea.value;
});
var copyAllButton = document.getElementById('copy-all');
copyAllButton.onclick = function() {
    copyBlock(textarea.value, copyAllButton);
};

function formatDate(seconds) {
    return new Date(seconds * 1000).toLocaleString('en-GB', {weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit'});
}
<?php if ($secret): ?>
// Secret page: opening it (POST) deletes it from the server and returns its text and files.
var SECRET_ID = '<?php print $secret['id']; ?>';
var SECRET_KEY = window.location.hash.slice(1);
var openButton = document.getElementById('open-secret');
var secretInfo = document.getElementById('secret-info');

function showGateError(message) {
    var error = document.getElementById('gate-error');
    error.textContent = message;
    error.hidden = false;
}

// Files are decrypted by the server with the key sent in the body, then saved from a Blob.
function downloadSecretFile(token, index, name) {
    var request = new XMLHttpRequest();
    request.open('POST', window.location.pathname + '?s=' + encodeURIComponent(token) + '&file=' + index, true);
    request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
    request.responseType = 'blob';
    request.onload = function() {
        if (request.status !== 200) {
            alert('Could not download ' + name + ': the time to download the files is over.');
            return;
        }
        var link = document.createElement('a');
        link.href = URL.createObjectURL(new Blob([request.response], {type: 'application/octet-stream'}));
        link.download = name;
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(function() {
            URL.revokeObjectURL(link.href);
        }, 10000);
    };
    request.send('key=' + encodeURIComponent(SECRET_KEY));
}

if (openButton && !SECRET_KEY) {
    showGateError('This link is not complete: check that it was copied whole, including the part after the "#".');
    openButton.parentNode.style.display = 'none';
}
if (openButton) {
    document.getElementById('gate-expires').textContent = 'Expires on ' + formatDate(<?php print $secret['expires']; ?>) + ' if not opened.';
    openButton.onclick = function() {
        openButton.disabled = true;
        var request = new XMLHttpRequest();
        request.open('POST', window.location.pathname + '?s=' + encodeURIComponent(SECRET_ID) + '&open', true);
        request.onload = function() {
            if (request.status !== 200) {
                showGateError(request.responseText || 'Could not open the secret.');
                openButton.parentNode.style.display = 'none';
                return;
            }
            var data = JSON.parse(request.responseText);
            textarea.value = data.text;
            document.body.classList.add('opened');
            document.body.classList.toggle('has-files', data.files.length > 0);
            secretInfo.textContent = 'Secret opened and deleted from the server'
                + (data.files.length ? ': download the files before '
                + new Date(data.until * 1000).toLocaleTimeString('en-GB', {hour: '2-digit', minute: '2-digit'}) : '');
            document.getElementById('files-count').textContent = data.files.length;
            var list = document.getElementById('files-list');
            data.files.forEach(function(file, index) {
                var row = fileRow(file.name, formatSize(file.size), '#');
                var download = document.createElement('button');
                download.type = 'button';
                download.className = 'file-action';
                download.title = 'Download';
                download.innerHTML = DOWNLOAD_ICON;
                row.querySelector('.details').appendChild(download);
                row.querySelector('.name').onclick = download.onclick = function(event) {
                    event.preventDefault();
                    downloadSecretFile(data.token, index, file.name);
                };
                list.appendChild(row);
            });
            updateGutter();
        };
        request.onerror = function() {
            openButton.disabled = false;
        };
        request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        request.send('key=' + encodeURIComponent(SECRET_KEY));
    };
}
<?php else: ?>
var modifiedLabel = document.getElementById('modified-time');
var statusDot = document.getElementById('status');
var noteUrl = window.location.origin + window.location.pathname;
var cli = document.getElementById('cli');
var cliToggle = document.getElementById('cli-toggle');
var cliList = document.getElementById('cli-list');
// [label, shown command, copied text if different: the placeholder is left out so it can follow a command]
[
    ['Upload text', '<command> | curl --data-binary @- ' + noteUrl, ' | curl --data-binary @- ' + noteUrl],
    ['Upload file', 'curl -T <file> ' + noteUrl + '/'],
    ['Download file', 'curl -O ' + noteUrl + '/<file>']
].forEach(function(item) {
    var label = document.createElement('span');
    label.textContent = item[0];
    var code = document.createElement('code');
    code.textContent = item[1];
    code.title = item[1];
    var copy = document.createElement('button');
    copy.type = 'button';
    copy.className = 'file-action';
    copy.title = 'Copy command';
    copy.innerHTML = COPY_ICONS;
    // The whole row copies; the button shows the feedback
    label.onclick = code.onclick = copy.onclick = function() {
        copyBlock(item[2] || item[1], copy);
    };
    cliList.appendChild(label);
    cliList.appendChild(code);
    cliList.appendChild(copy);
});

function setCliOpen(open) {
    cli.classList.toggle('open', open);
    cliToggle.setAttribute('aria-expanded', open);
}

cliToggle.addEventListener('click', function() {
    setCliOpen(!cli.classList.contains('open'));
});
document.addEventListener('click', function(event) {
    if (!cli.contains(event.target)) {
        setCliOpen(false);
    }
});
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        setCliOpen(false);
    }
});
var mtime = <?php print $initial_mtime; ?>;

var refreshButton = document.getElementById('refresh');
var content = textarea.value;
var version = '<?php print $initial_version; ?>';
var conflict = false;
var saving = false;
var checking = false;
var MAX_FILE_SIZE = <?php print $max_file_size; ?>;
var MAX_NOTE_SIZE = <?php print $max_note_size; ?>;
var saveError = '';
var files = <?php print json_encode($initial_files, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var filesVersion = '<?php print files_version($initial_files); ?>';
var filesChanged = <?php print files_changed($files_dir); ?>;
var NOTE_TTL = <?php print $note_ttl; ?>;
var serverOffset = <?php print time(); ?> - Date.now() / 1000;
var expires = document.getElementById('expires');
var expiresFill = document.getElementById('expires-fill');
var expiresLabel = document.getElementById('expires-label');
var filesList = document.getElementById('files-list');
var filesCount = document.getElementById('files-count');
var emptyButton = document.getElementById('empty');
var fileInput = document.getElementById('file-input');
var uploads = [];
var checkingFiles = false;

textarea.focus();
setInterval(uploadContent, 1000);
setInterval(checkRemote, 2000);
setInterval(checkFiles, 2000);
setInterval(showModified, 15000);
document.addEventListener('visibilitychange', checkRemote);
document.addEventListener('visibilitychange', checkFiles);
renderFiles();

// Dragging files anywhere shows a full-page drop zone; text drags inside the editor are left alone.
document.addEventListener('dragenter', function(e) {
    if (isFileDrag(e)) {
        document.body.classList.add('dragging');
    }
});
document.addEventListener('dragover', function(e) {
    if (isFileDrag(e)) {
        e.preventDefault();
    }
});
document.getElementById('attach').onclick = function() {
    fileInput.click();
};
fileInput.onchange = function() {
    uploadFiles(fileInput.files);
    fileInput.value = '';
};
emptyButton.onclick = function() {
    fileRequest('POST', '?empty');
};
document.getElementById('drop').addEventListener('dragleave', function() {
    document.body.classList.remove('dragging');
});
document.addEventListener('drop', function(e) {
    if (isFileDrag(e)) {
        e.preventDefault();
        document.body.classList.remove('dragging');
        uploadFiles(e.dataTransfer.files);
    }
});
showModified();
updateStatus();
textarea.addEventListener('input', updateStatus);

// Only visible while in conflict.
refreshButton.onclick = function() {
    if (!confirm('This note changed on another device and your latest changes here have NOT been saved.\n\nOK: discard them and load the server version.\nCancel: keep them so you can copy them first.')) {
        return;
    }
    refreshButton.disabled = true;

    // Load the server copy, discarding the local changes
    var request = new XMLHttpRequest();
    request.open('GET', window.location.pathname + '?raw', true);
    request.onload = function() {
        if (request.status === 200 || request.status === 404) {
            applyRemote(request);
            setConflict(false);
        }
    };
    request.onloadend = function() {
        refreshButton.disabled = false;
    };
    request.send();
};

// Renew the note so the idle deletion starts over.
expires.onclick = function() {
    var request = new XMLHttpRequest();
    request.open('POST', window.location.pathname + '?renew', true);
    request.onload = function() {
        if (request.status === 200) {
            var data = JSON.parse(request.responseText);
            filesChanged = data.changed;
            setMtime(data.mtime);
        }
    };
    request.send();
};

var clearButton = document.getElementById('clear');
clearButton.onclick = function() {
    if (conflict) {
        alert('This note changed on another device. Click refresh before clearing it.');
        return;
    }

    // An empty text makes the autosave loop delete the note on the server
    textarea.value = '';
    updateStatus();
    updateGutter();
};
// Make it a secret: once everything typed here is saved, the server moves the note to a one-time link.
var secretDialog = document.getElementById('secret-dialog');
var secretAsk = document.getElementById('secret-ask');
var secretDone = document.getElementById('secret-done');
var secretError = document.getElementById('secret-error');
var secretChoices = secretAsk.querySelectorAll('[data-hours]');
var secretCopy = document.getElementById('secret-copy');
secretCopy.innerHTML = COPY_ICONS;

function setSecretBusy(busy) {
    Array.prototype.forEach.call(secretChoices, function(button) {
        button.disabled = busy;
    });
}

function showSecretError(message) {
    secretError.textContent = message;
    secretError.hidden = false;
    setSecretBusy(false);
}

// Call back when the text is saved and no upload is pending; gives up after 10 seconds.
function whenSaved(callback, tries) {
    if (conflict || saveError) {
        showSecretError(conflict ? 'This note changed on another device: click refresh first.' : 'Not saved: ' + saveError);
    } else if (!saving && !uploads.length && content === textarea.value) {
        callback();
    } else if ((tries || 0) < 40) {
        setTimeout(function() {
            whenSaved(callback, (tries || 0) + 1);
        }, 250);
    } else {
        showSecretError('The note is still saving, try again.');
    }
}

function makeSecret(hours) {
    var request = new XMLHttpRequest();
    request.open('POST', window.location.pathname + '?secret=' + hours, true);
    request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
    request.onload = function() {
        if (request.status !== 200) {
            if (request.status === 409) {
                setConflict(true);
            }
            showSecretError(request.responseText || 'Could not make the secret.');
            return;
        }
        var data = JSON.parse(request.responseText);
        var link = window.location.origin + window.location.pathname.replace(/[^\/]*$/, '') + '?s=' + data.id + '#' + data.key;
        document.getElementById('secret-link').textContent = link;
        document.getElementById('secret-expires').textContent = 'If nobody opens it, it expires on ' + formatDate(data.expires) + '.';
        secretCopy.onclick = function() {
            copyBlock(link, secretCopy);
        };
        secretAsk.hidden = true;
        secretDone.hidden = false;

        // The note no longer exists on the server: start over with an empty one.
        textarea.value = content = version = filesVersion = '';
        files = [];
        filesChanged = 0;
        setMtime(0);
        renderFiles();
        updateStatus();
        updateGutter();
    };
    request.onerror = function() {
        showSecretError('Network error, try again.');
    };
    request.send('base=' + encodeURIComponent(version));
}

document.getElementById('make-secret').onclick = function() {
    if (!textarea.value.trim() && !files.length && !uploads.length) {
        alert('Write something or attach a file first.');
        return;
    }
    secretAsk.hidden = false;
    secretDone.hidden = true;
    secretError.hidden = true;
    setSecretBusy(false);
    secretDialog.showModal();
};
Array.prototype.forEach.call(secretChoices, function(button) {
    button.onclick = function() {
        setSecretBusy(true);
        secretError.hidden = true;
        whenSaved(function() {
            makeSecret(button.dataset.hours);
        });
    };
});
Array.prototype.forEach.call(secretDialog.querySelectorAll('.secret-close'), function(button) {
    button.onclick = function() {
        secretDialog.close();
    };
});
secretDialog.addEventListener('close', function() {
    textarea.focus();
});
<?php endif; ?>
</script>
</body>
</html>
