<?php

// Settings can be overridden with environment variables (Docker/Kubernetes); the values here are the defaults.

// Path to the directory to save the notes in, without trailing slash.
// Should be outside the document root, if possible.
$save_path = rtrim(getenv('NOTES_PATH') ?: '/var/lib/notas', '/');

// Shown in the footer next to the GitHub link. Bump it on each release.
$app_version = '1.4';
$repo_url = 'https://github.com/joseaguardia/web-notepad';

// Attachment limits: size of each file, and total size of all attachments of all notes.
$max_file_size = (int) (getenv('MAX_FILE_SIZE_MB') ?: 5) * 1024 * 1024;
$max_total_size = (int) (getenv('MAX_TOTAL_SIZE_MB') ?: 100) * 1024 * 1024;

// Disable caching.
header('Cache-Control: no-store');

// If no note name is provided, or if the name is too long, or if it contains invalid characters.
if (!isset($_GET['note']) || strlen($_GET['note']) > 64 || !preg_match('/^[a-zA-Z0-9_-]+$/', $_GET['note'])) {

    // Generate a name with 5 random unambiguous characters. Redirect to it.
    header("Location: " . substr(str_shuffle('234579abcdefghjkmnpqrstwxyz'), -5));
    die;
}

$path = $save_path . '/' . $_GET['note'];

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
    return is_string($name) && preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,99}$/', $name);
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

function send_files($dir) {
    $files = list_files($dir);
    header('Content-Type: application/json');
    print json_encode(['version' => files_version($files), 'files' => $files]);
    die;
}

function fail($code, $message) {
    http_response_code($code);
    header('Content-Type: text/plain');
    print $message;
    die;
}

$files_dir = $path . '.files';
$method = $_SERVER['REQUEST_METHOD'];

// Upload an attachment: the raw request body is the file, e.g. curl --upload-file photo.png '<url>?upload=photo.png'.
// The rewrite also maps PUT/POST <url>/<file> here, so curl --upload-file photo.png <url>/ names it automatically
// (and GET <url>/<file> to ?file=<file>, the download below).
// file_uploads is disabled in PHP on purpose, so no multipart parsing is involved.
if (isset($_GET['upload']) && ($method === 'POST' || $method === 'PUT')) {
    $length = $_SERVER['CONTENT_LENGTH'] ?? '';
    if ($length !== '' && (int) $length > $max_file_size) {
        fail(413, 'File too large (max ' . ($max_file_size >> 20) . ' MB)');
    }
    if (!is_dir($files_dir) && !@mkdir($files_dir, 0755) && !is_dir($files_dir)) {
        fail(500, 'Cannot create attachments directory');
    }
    $name = sanitize_file_name($_GET['upload']);

    // Stream to a hidden temp file first, without holding the lock, so a slow upload never blocks note saves.
    $temp = $files_dir . '/.' . bin2hex(random_bytes(8)) . '.tmp';
    register_shutdown_function(function() use ($temp) {
        if (is_file($temp)) {
            unlink($temp);
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
    $total = 0;
    foreach (glob($save_path . '/*.files/*') ?: [] as $file) {
        if ($file !== $target) {
            $total += filesize($file);
        }
    }
    if ($total + $copied > $max_total_size) {
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

// Download an attachment. Always as a download and sandboxed, so an uploaded HTML or SVG can never run in this origin.
if (isset($_GET['file'])) {
    if (!valid_file_name($_GET['file']) || !is_file($file = "$files_dir/{$_GET['file']}")) {
        fail(404, 'Not found');
    }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $_GET['file'] . '"');
    header('Content-Length: ' . filesize($file));
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'; sandbox");
    readfile($file);
    die;
}

// List of attachments as JSON. With since=<version> answers 204 when unchanged (used by the browser's polling).
if (isset($_GET['files'])) {
    if (isset($_GET['since']) && $_GET['since'] === files_version(list_files($files_dir))) {
        http_response_code(204);
        die;
    }
    send_files($files_dir);
}

if ($method === 'POST') {
    $text = $_POST['text'] ?? file_get_contents('php://input');

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
if (isset($_GET['raw']) || strpos($agent, 'curl') === 0 || strpos($agent, 'Wget') === 0) {

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
<title><?php print $_GET['note']; ?></title>
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
    --footer-height: 80px;
}
@media (max-width: 800px) {
    :root {
        --editor-font: 15px/1.6 var(--mono);
        --editor-padding: 16px 104px 16px 56px;
        --footer-height: 68px;
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
    --primary: 79, 209, 232;
    --neutral: 229, 231, 235;
    --teal: 45, 212, 191;
    --ok: 110, 231, 183;
    --warn: 245, 165, 36;
    --danger: 242, 139, 150;
    --selection: #0a6f88;
    --selection-text: #fff;
    --code-bg: rgba(0, 0, 0, 0.25);
    --shadow: rgba(0, 0, 0, 0.35);
    --shadow-soft: rgba(0, 0, 0, 0.25);
}
:root[data-theme="light"] {
    color-scheme: light;
    /* Brand palette: Gris Cerámica background, white surfaces, Azul Oscuro text,
       Azul Claro highlights and Azul Amazónico for the attachments accent */
    --bg: #e3e2da;
    --bg-from: #e9e8e1;
    --bg-to: #d9d8cf;
    --surface: 255, 255, 255;
    --text: 0, 25, 35;
    --primary: 0, 176, 189;
    --neutral: 0, 25, 35;
    --teal: 0, 66, 84;
    --ok: 5, 150, 105;
    --warn: 217, 119, 6;
    --danger: 220, 38, 38;
    --selection: #b3e7eb;
    --selection-text: rgb(0, 25, 35);
    --code-bg: rgba(0, 66, 84, 0.08);
    --shadow: rgba(0, 25, 35, 0.12);
    --shadow-soft: rgba(0, 25, 35, 0.10);
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
    color: rgb(var(--text));
    opacity: 0.6;
}
#modified svg {
    flex-shrink: 0;
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
#repo:hover {
    color: rgb(var(--primary));
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
#status.conflict {
    color: rgb(var(--danger));
}
@keyframes pulse {
    to {
        opacity: 0.3;
    }
}
/* Label and command columns, so the commands line up */
#tip {
    display: grid;
    grid-template-columns: max-content minmax(0, 1fr);
    column-gap: 8px;
    margin-right: auto;
    min-width: 0;
    font-size: 14px;
    line-height: 20px;
}
#tip code {
    justify-self: start;
    max-width: 100%;
    box-sizing: border-box;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    padding: 0 6px;
    font: inherit;
    background: var(--code-bg);
    border-radius: 4px;
}
@media (max-width: 800px) {
    #tip {
        display: none;
    }
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
/* Hidden copy of the editor text, used to measure where each block starts */
#mirror {
    position: absolute;
    top: 0;
    left: 0;
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
<body>
<div class="container">
<textarea id="content"><?php
if ($initial_text !== false) {
    print htmlspecialchars($initial_text, ENT_QUOTES, 'UTF-8');
}
?></textarea>
<div id="gutter"><div id="gutter-inner"></div></div>
<div id="mirror" aria-hidden="true"></div>
<button id="copy-all" class="action" type="button" title="Copy all"><svg class="icon-copy" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg><svg class="icon-check" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg><small>copy all</small></button>
<button id="refresh" class="action" type="button" title="This note changed on another device: click to load the server version"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg><small>refresh</small></button>
<button id="clear" class="action" type="button" title="Clear note"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m16 22-1-4"/><path d="M19 14a1 1 0 0 0 1-1v-1a2 2 0 0 0-2-2h-3a1 1 0 0 1-1-1V4a2 2 0 0 0-4 0v5a1 1 0 0 1-1 1H6a2 2 0 0 0-2 2v1a1 1 0 0 0 1 1"/><path d="M5 14h14l1.973 6.767A1 1 0 0 1 20 22H4a1 1 0 0 1-.973-1.233z"/><path d="m8 22 1-4"/></svg><small>clear</small></button>
<button id="attach" class="action" type="button" title="Attach file (or drop files anywhere)"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg><small>attach file</small></button>
<input id="file-input" type="file" multiple hidden>
</div>
<div id="files">
<div id="files-title">Files <span id="files-count">0</span></div>
<div id="files-list"></div>
<button id="empty" type="button" title="Delete all files (the note text is kept)"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>empty</button>
</div>
<div id="drop">Drop files to attach them (max <?php print $max_file_size >> 20; ?> MB each)</div>
<div id="modified"><span id="tip"><span>Upload text:</span><code id="tip-text"></code><span>Upload file:</span><code id="tip-upload"></code><span>Download file:</span><code id="tip-download"></code></span><button id="theme" type="button" title="Switch light/dark theme"><svg class="icon-sun" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg><svg class="icon-moon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg></button><a id="repo" href="<?php print $repo_url; ?>" target="_blank" rel="noopener noreferrer" title="Source code on GitHub"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.93.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.22.165.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>v<?php print $app_version; ?></a><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M12 7v5l3 2"/></svg><span id="modified-time" title="Last modified"></span><span id="status"></span></div>
<pre id="printable"></pre>
<script>
function updateStatus() {
    var state = conflict ? 'conflict' : (content !== textarea.value ? 'saving' : 'saved');
    statusDot.className = state;
    statusDot.title = {saved: 'Saved', saving: 'Saving…', conflict: 'Not saved: changed on another device'}[state];
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
            updateStatus();
        } else if (request.status === 409) {

            // The note changed on another device since it was loaded here.
            setConflict(true);
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
    gutterInner.style.transform = 'translateY(' + (-textarea.scrollTop) + 'px)';
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
    });
    syncGutterScroll();
}

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
var modifiedLabel = document.getElementById('modified-time');
var statusDot = document.getElementById('status');
var noteUrl = window.location.origin + window.location.pathname;
document.getElementById('tip-text').textContent = '<command> | curl --data-binary @- ' + noteUrl;
document.getElementById('tip-upload').textContent = 'curl -T <file> ' + noteUrl + '/';
document.getElementById('tip-download').textContent = 'curl -O ' + noteUrl + '/<file>';
var mtime = <?php print $initial_mtime; ?>;

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

var gutterInner = document.getElementById('gutter-inner');
var mirror = document.getElementById('mirror');
var printable = document.getElementById('printable');
var refreshButton = document.getElementById('refresh');
var content = textarea.value;
var version = '<?php print $initial_version; ?>';
var conflict = false;
var saving = false;
var checking = false;
var MAX_FILE_SIZE = <?php print $max_file_size; ?>;
var files = <?php print json_encode($initial_files, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var filesVersion = '<?php print files_version($initial_files); ?>';
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

updateGutter();
if (document.fonts) {
    document.fonts.ready.then(scheduleGutter);
    document.fonts.addEventListener('loadingdone', scheduleGutter);
}
textarea.addEventListener('input', scheduleGutter);
textarea.addEventListener('input', updateStatus);
textarea.addEventListener('scroll', syncGutterScroll);
window.addEventListener('resize', scheduleGutter);
window.addEventListener('beforeprint', function() {
    printable.textContent = textarea.value;
});

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

var copyAllButton = document.getElementById('copy-all');
copyAllButton.onclick = function() {
    copyBlock(textarea.value, copyAllButton);
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
</script>
</body>
</html>
