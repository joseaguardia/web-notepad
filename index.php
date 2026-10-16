<?php

// Path to the directory to save the notes in, without trailing slash.
// Should be outside the document root, if possible.
$save_path = '_tmp';

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
<link rel="icon" href="favicon.svg" type="image/svg+xml">
<style>
body {
    margin: 0;
    background: #004254;
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
    font: 14px/20px monospace;
    color: #E3E2DA;
    opacity: 0.6;
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
    border: 1px solid #0a5a6e;
    outline: none;
    font: 20px/1.5 monospace;
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
#gutter-inner {
    position: relative;
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
    border-radius: 4px;
    opacity: 0.4;
    cursor: pointer;
    pointer-events: auto;
}
.copy-btn:hover {
    opacity: 1;
    background: #003040;
}
.copy-btn .icon-check,
.copy-btn.copied .icon-copy {
    display: none;
}
.copy-btn.copied .icon-check {
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
    color: #E3E2DA;
    background: #005a72;
    border: 1px solid #0a7a94;
    border-radius: 4px;
}
.action small {
    margin-top: 4px;
    font-size: 12px;
}
#refresh {
    top: 10px;
}
#delete {
    bottom: 10px;
    color: #f1aeb5;
    background: #6b2c33;
    border-color: #8a3b42;
}
#refresh.conflict {
    color: #fff;
    background: #8a5300;
    border-color: #f59f00;
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
<button id="refresh" class="action" type="button" title="Refrescar"><span id="refresh-icon">⟳</span><small>refresh</small></button>
<button id="delete" class="action" type="button" title="Eliminar nota"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg><small>delete</small></button>
</div>
<div id="modified"></div>
<pre id="printable"></pre>
<script>
function setConflict(value) {
    conflict = value;
    refreshButton.classList.toggle('conflict', value);
    refreshButton.title = value ? 'La nota ha cambiado en otro equipo: pulsa para cargar la versión del servidor' : 'Refrescar';
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
    var last = 0;
    blocks.forEach(function(block) {
        mirror.appendChild(document.createTextNode(text.slice(last, block.start)));
        var marker = document.createElement('span');
        marker.className = 'marker';
        mirror.appendChild(marker);
        markers.push(marker);
        last = block.start;
    });
    mirror.appendChild(document.createTextNode(text.slice(last)));

    gutterInner.textContent = '';
    blocks.forEach(function(block, i) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'copy-btn';
        button.title = 'Copiar este bloque';
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
        ? 'Última modificación: ' + new Date(mtime * 1000).toLocaleString('es-ES')
        : 'Nota sin guardar';
}

function setMtime(header) {
    mtime = parseInt(header, 10) || 0;
    showModified();
}

var textarea = document.getElementById('content');
var modifiedLabel = document.getElementById('modified');
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

updateGutter();
textarea.addEventListener('input', scheduleGutter);
textarea.addEventListener('scroll', syncGutterScroll);
window.addEventListener('resize', scheduleGutter);

refreshButton.onclick = function() {
    if (conflict && !confirm('Esta nota ha cambiado en otro equipo y tus últimos cambios aquí NO se han guardado.\n\nAceptar: descartarlos y cargar la versión del servidor.\nCancelar: mantenerlos para que puedas copiarlos antes.')) {
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

var deleteButton = document.getElementById('delete');
deleteButton.onclick = function() {
    if (conflict) {
        alert('Esta nota ha cambiado en otro equipo. Pulsa refresh antes de eliminarla.');
        return;
    }
    if (!confirm('¿Eliminar esta nota?')) {
        return;
    }
    deleteButton.disabled = true;

    // An empty text makes the autosave loop delete the note on the server
    textarea.value = '';
    printable.textContent = '';
    updateGutter();
    (function waitForDelete() {
        if (content !== '') {
            setTimeout(waitForDelete, 200);
            return;
        }
        deleteButton.disabled = false;
    })();
};
</script>
</body>
</html>
