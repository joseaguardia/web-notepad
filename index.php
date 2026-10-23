<?php

// Path to the directory to save the notes in, without trailing slash.
// Should be outside the document root, if possible.
$save_path = '/var/lib/notas';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $text = isset($_POST['text']) ? $_POST['text'] : file_get_contents("php://input");

    // Serialize the version check and the write across concurrent requests.
    $lock = fopen($save_path . '/.lock', 'c');
    flock($lock, LOCK_EX);

    // Reject the save if the note changed since the client loaded it.
    // Clients that send no base version (e.g. curl) skip the check.
    if (isset($_POST['base']) && $_POST['base'] !== note_version($path)) {
        header('HTTP/1.0 409 Conflict');
        die;
    }

    // Update file.
    file_put_contents($path, $text);

    // If provided input is empty, delete file.
    if (!strlen($text) && is_file($path)) {
        unlink($path);
    }

    // Return the new version so the client can send it as base next time.
    header('X-Note-Mtime: ' . note_mtime($path));
    print note_version($path);
    die;
}

// Print raw file when explicitly requested, or if the client is curl or wget.
if (isset($_GET['raw']) || strpos($_SERVER['HTTP_USER_AGENT'], 'curl') === 0 || strpos($_SERVER['HTTP_USER_AGENT'], 'Wget') === 0) {
    header('X-Note-Version: ' . note_version($path));
    header('X-Note-Mtime: ' . note_mtime($path));
    if (is_file($path)) {
        header('Content-type: text/plain');
        readfile($path);
    } else {
        header('HTTP/1.0 404 Not Found');
    }
    die;
}

// Read the note once so the shown text and its version always match.
$initial_text = is_file($path) ? file_get_contents($path) : false;
$initial_version = $initial_text === false ? '' : md5($initial_text);
$initial_mtime = note_mtime($path);
?><!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php print $_GET['note']; ?></title>
<link rel="icon" href="favicon.ico" sizes="any">
<style>
html,
body {
    height: 100%;
}
body {
    margin: 0;
    background: #004254 linear-gradient(160deg, #004d61 0%, #002f3d 100%) fixed;
}
.container {
    position: absolute;
    top: 20px;
    right: 20px;
    bottom: 48px;
    left: 20px;
}
#modified {
    position: absolute;
    right: 20px;
    bottom: 14px;
    left: 20px;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    font: 18px/24px monospace;
    color: #E3E2DA;
    opacity: 0.6;
}
#modified svg {
    flex-shrink: 0;
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
    color: #6ee7b7;
}
#status.saving {
    color: #f5a524;
    animation: pulse 1s ease-in-out infinite alternate;
}
#status.conflict {
    color: #f28b96;
}
@keyframes pulse {
    to {
        opacity: 0.3;
    }
}
#tip {
    margin-right: auto;
    min-width: 0;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}
#tip code {
    padding: 1px 6px;
    font: inherit;
    background: rgba(0, 0, 0, 0.25);
    border-radius: 4px;
}
@media (max-width: 800px) {
    #tip {
        display: none;
    }
}
#content {
    margin: 0;
    padding: 20px;
    /* Keep text clear of the action buttons (right offset 30px + width 90px + gap) */
    padding-right: 140px;
    /* Room for the copy-block gutter */
    padding-left: 56px;
    overflow-y: auto;
    resize: none;
    width: 100%;
    height: 100%;
    box-sizing: border-box;
    background: #001923;
    color: #E3E2DA;
    caret-color: #4fd1e8;
    border: 1px solid rgba(79, 209, 232, 0.15);
    border-radius: 12px;
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.35);
    outline: none;
    font: 20px/1.5 monospace;
    transition: border-color 0.2s, box-shadow 0.2s;
}
#content:focus {
    border-color: rgba(79, 209, 232, 0.3);
}
#content::selection {
    background: #0a6f88;
    color: #fff;
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
    padding: 20px 140px 20px 56px;
    font: 20px/1.5 monospace;
    white-space: pre-wrap;
    overflow-wrap: break-word;
    word-wrap: break-word;
}
#mirror .marker {
    display: inline-block;
    width: 0;
    height: 1.5em;
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
    background: rgba(227, 226, 218, 0.08);
}
#gutter-inner {
    position: relative;
}
.block-bar {
    position: absolute;
    left: 4px;
    width: 3px;
    border-radius: 2px;
    background: #4fd1e8;
    opacity: 0;
    transition: opacity 0.15s;
}
.copy-btn:hover + .block-bar {
    opacity: 0.8;
}
.copy-btn.copied + .block-bar {
    background: #6ee7b7;
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
    color: #E3E2DA;
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
    background: #003040;
}
.icon-check,
.copied .icon-copy {
    display: none;
}
.copied .icon-check {
    display: block;
}
.copy-btn.copied {
    color: #6ee7b7;
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
    width: 90px;
    height: 90px;
    padding: 0;
    font-size: 54px;
    line-height: 1;
    cursor: pointer;
    --accent: 79, 209, 232;
    color: rgb(var(--accent));
    background: rgba(var(--accent), 0.08);
    border: 1px solid rgba(var(--accent), 0.35);
    border-radius: 14px;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.25);
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
    max-height: 0;
    margin-top: 0;
    overflow: hidden;
    font-size: 16px;
    line-height: 20px;
    opacity: 0;
    transition: max-height 0.15s, margin-top 0.15s, opacity 0.15s;
}
.action:hover small {
    max-height: 20px;
    margin-top: 4px;
    opacity: 1;
}
#copy-all {
    top: 10px;
    --accent: 181, 224, 122;
}
#refresh {
    top: 110px;
}
#clear {
    bottom: 10px;
    --accent: 242, 139, 150;
}
#refresh.conflict {
    --accent: 245, 165, 36;
    background: rgba(var(--accent), 0.25);
    border-color: rgb(var(--accent));
}
@media print {
    .container,
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
<button id="copy-all" class="action" type="button" title="Copy all"><svg class="icon-copy" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg><svg class="icon-check" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg><small>copy all</small></button>
<button id="refresh" class="action" type="button" title="Refresh"><span id="refresh-icon">⟳</span><small>refresh</small></button>
<button id="clear" class="action" type="button" title="Clear note"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m16 22-1-4"/><path d="M19 14a1 1 0 0 0 1-1v-1a2 2 0 0 0-2-2h-3a1 1 0 0 1-1-1V4a2 2 0 0 0-4 0v5a1 1 0 0 1-1 1H6a2 2 0 0 0-2 2v1a1 1 0 0 0 1 1"/><path d="M5 14h14l1.973 6.767A1 1 0 0 1 20 22H4a1 1 0 0 1-.973-1.233z"/><path d="m8 22 1-4"/></svg><small>clear</small></button>
</div>
<div id="modified"><span id="tip">To upload: <code id="tip-command"></code></span><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M12 7v5l3 2"/></svg><span id="modified-time" title="Last modified"></span><span id="status"></span></div>
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
    refreshButton.title = value ? 'This note changed on another device: click to load the server version' : 'Refresh';
}

function uploadContent() {

    // While in conflict, autosave is paused so the server copy is not overwritten.
    if (!conflict && content !== textarea.value) {
        var temp = textarea.value;
        var request = new XMLHttpRequest();
        request.open('POST', window.location.href, true);
        request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        request.onload = function() {
            if (request.status === 200) {
                content = temp;
                version = request.responseText;
                setMtime(request.getResponseHeader('X-Note-Mtime'));
                updateStatus();
            } else if (request.status === 409) {

                // The note changed on another device since it was loaded here.
                setConflict(true);
            }

            // Check again after 1 second.
            setTimeout(uploadContent, 1000);
        }
        request.onerror = function() {

            // Try again after 1 second.
            setTimeout(uploadContent, 1000);
        }
        request.send('text=' + encodeURIComponent(temp) + '&base=' + encodeURIComponent(version));

        // Update the printable contents.
        printable.removeChild(printable.firstChild);
        printable.appendChild(document.createTextNode(temp));
    }
    else {

        // If the content has not changed, check again after 1 second.
        setTimeout(uploadContent, 1000);
    }
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

function showModified() {
    modifiedLabel.textContent = mtime
        ? new Date(mtime * 1000).toLocaleTimeString('en-GB', {hour: '2-digit', minute: '2-digit'})
        : 'Unsaved note';
}

function setMtime(header) {
    mtime = parseInt(header, 10) || 0;
    showModified();
}

var textarea = document.getElementById('content');
var modifiedLabel = document.getElementById('modified-time');
var statusDot = document.getElementById('status');
document.getElementById('tip-command').textContent = '<command> | curl --data-binary @- ' + window.location.origin + window.location.pathname;
var mtime = <?php print $initial_mtime; ?>;
var gutterInner = document.getElementById('gutter-inner');
var mirror = document.getElementById('mirror');
var printable = document.getElementById('printable');
var refreshButton = document.getElementById('refresh');
var refreshIcon = document.getElementById('refresh-icon');
var content = textarea.value;
var version = '<?php print $initial_version; ?>';
var conflict = false;

// Initialize the printable contents with the initial value of the textarea.
printable.appendChild(document.createTextNode(content));

textarea.focus();
uploadContent();
showModified();
updateStatus();

updateGutter();
textarea.addEventListener('input', scheduleGutter);
textarea.addEventListener('input', updateStatus);
textarea.addEventListener('scroll', syncGutterScroll);
window.addEventListener('resize', scheduleGutter);

refreshButton.onclick = function() {
    if (conflict && !confirm('This note changed on another device and your latest changes here have NOT been saved.\n\nOK: discard them and load the server version.\nCancel: keep them so you can copy them first.')) {
        return;
    }
    refreshButton.disabled = true;
    refreshIcon.textContent = '…';

    function done() {
        refreshButton.disabled = false;
        refreshIcon.textContent = '⟳';
    }

    // Wait until pending changes are saved, then load the latest copy from the server
    (function waitAndRefresh() {
        if (!conflict && content !== textarea.value) {
            setTimeout(waitAndRefresh, 200);
            return;
        }
        var request = new XMLHttpRequest();
        request.open('GET', window.location.pathname + '?raw&t=' + Date.now(), true);
        request.onload = function() {
            if (request.status === 200 || request.status === 404) {
                var text = request.status === 200 ? request.responseText : '';

                // Skip the update if the user typed something in the meantime
                // (in conflict, the user already chose to discard local changes)
                if (conflict || content === textarea.value) {
                    textarea.value = text;
                    content = text;
                    version = request.getResponseHeader('X-Note-Version') || '';
                    setMtime(request.getResponseHeader('X-Note-Mtime'));
                    printable.textContent = text;
                    setConflict(false);
                    updateGutter();
                }
            }
            done();
        };
        request.onerror = done;
        request.send();
    })();
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
    clearButton.disabled = true;

    // An empty text makes the autosave loop delete the note on the server
    textarea.value = '';
    printable.textContent = '';
    updateStatus();
    updateGutter();
    (function waitForClear() {
        if (content !== '') {
            setTimeout(waitForClear, 200);
            return;
        }
        clearButton.disabled = false;
    })();
};
</script>
</body>
</html>
