<?php

// Settings can be overridden with environment variables (Docker/Kubernetes); the values here are the defaults.

// Path to the directory to save the notes in, without trailing slash.
// Should be outside the document root, if possible.
$save_path = rtrim(getenv('NOTES_PATH') ?: '/var/lib/notas', '/');

// Shown in the footer next to the GitHub link. Bump it on each release.
$app_version = '2.5.2';
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

// UI texts. The language comes from the "lang" cookie (set by the footer switcher), else from Accept-Language
// (curl sends none, so it gets English). The same table goes to the page script, which switches it live.
$i18n = [
    'en' => [
        'err_too_many_notes' => 'Too many notes (max {n} at the same time)',
        'err_secret_gone' => 'This secret does not exist: it was already opened or it expired.',
        'err_link_incomplete' => 'This link is not complete: check that it was copied whole, including the part after the "#".',
        'err_file_name' => 'Invalid file name',
        'err_file_large' => 'File too large (max {n} MB)',
        'err_attach_dir' => 'Cannot create attachments directory',
        'err_empty_file' => 'Empty file',
        'err_incomplete_upload' => 'Incomplete upload',
        'err_storage_full' => 'Attachment storage full (max {n} MB in total)',
        'err_note_changed' => 'This note changed on another device',
        'err_note_empty' => 'The note is empty',
        'err_too_many_secrets' => 'Too many secrets (max {n} at the same time)',
        'err_secrets_dir' => 'Cannot create secrets directory',
        'err_note_large' => 'Note too large (max {n} MB)',
        'secret' => 'Secret',
        'copy_all' => 'copy all',
        'copy_all_title' => 'Copy all',
        'refresh' => 'refresh',
        'refresh_title' => 'This note changed on another device: click to load the server version',
        'clear' => 'clear',
        'clear_title' => 'Clear note',
        'attach' => 'attach file',
        'attach_title' => 'Attach file (or drop files anywhere)',
        'make_secret' => 'make it a secret',
        'make_secret_title' => 'Make it a secret: turn this note into a one-time link',
        'make_secret_title_short' => 'Make it a secret',
        'files' => 'Files',
        'empty' => 'empty',
        'empty_title' => 'Delete all files (the note text is kept)',
        'gate_title' => 'Someone shared a secret with you',
        'once_title' => 'It can be opened only once',
        'once_text' => 'Opening it deletes it from the server, so copy or download what you need right away.',
        'open_secret' => 'open secret',
        'gate_note' => 'Encrypted with AES-256. The key travels only in your link and is never stored on the server.',
        'gone_title' => 'Secret not available',
        'gone_text' => 'It was already opened or it expired.',
        'no_text' => 'No text, only files',
        'drop' => 'Drop files to attach them (max {n} MB each)',
        'dialog_text' => 'The text and files of this note become a link that can be opened only once, and the note is deleted.',
        'dialog_expires' => 'If nobody opens it, it expires in:',
        'expiry_1' => '1 hour',
        'expiry_24' => '1 day',
        'expiry_168' => '1 week',
        'cancel' => 'cancel',
        'close' => 'close',
        'ready_title' => 'Secret ready',
        'ready_text' => 'Share this link. It can be opened only once.',
        'copy_link' => 'copy link',
        'copied' => 'copied',
        'generate_qr' => 'generate QR',
        'cli' => 'commands help',
        'cli_title' => 'Command-line usage',
        'theme_title' => 'Switch light/dark theme',
        'repo_title' => 'Source code on GitHub',
        'modified_title' => 'Last modified',
        'saved' => 'Saved',
        'saving' => 'Saving…',
        'not_saved_conflict' => 'Not saved: changed on another device',
        'not_saved' => 'Not saved: {reason}',
        'copy_host' => 'Copy the host',
        'copy_user' => 'Copy the user',
        'copy_password' => 'Copy the password',
        'copy_token' => 'Copy the token',
        'copy_ssh' => 'Copy the SSH key',
        'copy_block' => 'Copy this block',
        'unsaved_note' => 'Unsaved note',
        'expires_title' => 'Auto-delete in {time}. Click to renew it (or edit the note).',
        'less_minute' => 'less than a minute',
        'one_minute' => '1 minute',
        'minutes' => '{n} minutes',
        'download' => 'Download',
        'download_name' => 'Download {name}',
        'downloads_count' => 'Downloaded {n} times',
        'copy_download_command' => 'Copy download command (curl)',
        'upload_failed' => 'Could not upload {name}: {reason}',
        'network_error' => 'network error',
        'file_too_large' => '{name} is too large (max {size}).',
        'download_over' => 'Could not download {name}: the time to download the files is over.',
        'gate_expires' => 'Expires on {date}h',
        'open_failed' => 'Could not open the secret.',
        'opened_info' => 'Secret opened and deleted from the server',
        'opened_info_files' => 'Secret opened and deleted from the server: download the files before {time}',
        'cli_upload_text' => 'Upload text',
        'cli_upload_file' => 'Upload file',
        'cli_download_file' => 'Download file',
        'placeholder_command' => '<command>',
        'placeholder_file' => '<file>',
        'copy_command' => 'Copy command',
        'confirm_refresh' => "This note changed on another device and your latest changes here have NOT been saved.\n\nOK: discard them and load the server version.\nCancel: keep them so you can copy them first.",
        'clear_conflict' => 'This note changed on another device. Click refresh before clearing it.',
        'secret_conflict' => 'This note changed on another device: click refresh first.',
        'still_saving' => 'The note is still saving, try again.',
        'make_failed' => 'Could not make the secret.',
        'secret_expires_on' => 'If nobody opens it, it expires on {date}.',
        'network_retry' => 'Network error, try again.',
        'write_first' => 'Write something or attach a file first.',
        'language' => 'Language',
    ],
    'es' => [
        'err_too_many_notes' => 'Demasiadas notas (máximo {n} a la vez)',
        'err_secret_gone' => 'Este secreto no existe: ya se abrió o caducó.',
        'err_link_incomplete' => 'Este enlace no está completo: comprueba que se copió entero, incluida la parte tras el «#».',
        'err_file_name' => 'Nombre de archivo no válido',
        'err_file_large' => 'Archivo demasiado grande (máximo {n} MB)',
        'err_attach_dir' => 'No se puede crear el directorio de adjuntos',
        'err_empty_file' => 'Archivo vacío',
        'err_incomplete_upload' => 'Subida incompleta',
        'err_storage_full' => 'Almacenamiento de adjuntos lleno (máximo {n} MB en total)',
        'err_note_changed' => 'Esta nota cambió en otro dispositivo',
        'err_note_empty' => 'La nota está vacía',
        'err_too_many_secrets' => 'Demasiados secretos (máximo {n} a la vez)',
        'err_secrets_dir' => 'No se puede crear el directorio de secretos',
        'err_note_large' => 'Nota demasiado grande (máximo {n} MB)',
        'secret' => 'Secreto',
        'copy_all' => 'copiar todo',
        'copy_all_title' => 'Copiar todo',
        'refresh' => 'recargar',
        'refresh_title' => 'Esta nota cambió en otro dispositivo: haz clic para cargar la versión del servidor',
        'clear' => 'vaciar',
        'clear_title' => 'Vaciar la nota',
        'attach' => 'adjuntar archivo',
        'attach_title' => 'Adjuntar archivo (o suelta archivos en cualquier parte)',
        'make_secret' => 'convertir en secreto',
        'make_secret_title' => 'Convertir en secreto: transforma esta nota en un enlace de un solo uso',
        'make_secret_title_short' => 'Convertir en secreto',
        'files' => 'Archivos',
        'empty' => 'vaciar',
        'empty_title' => 'Borrar todos los archivos (el texto de la nota se mantiene)',
        'gate_title' => 'Alguien ha compartido un secreto contigo',
        'once_title' => 'Solo se puede abrir una vez',
        'once_text' => 'Al abrirlo se borra del servidor, así que copia o descarga enseguida lo que necesites.',
        'open_secret' => 'abrir secreto',
        'gate_note' => 'Cifrado con AES-256. La clave solo viaja en tu enlace y nunca se guarda en el servidor.',
        'gone_title' => 'Secreto no disponible',
        'gone_text' => 'Ya se abrió o caducó.',
        'no_text' => 'Sin texto, solo archivos',
        'drop' => 'Suelta archivos para adjuntarlos (máximo {n} MB cada uno)',
        'dialog_text' => 'El texto y los archivos de esta nota se convierten en un enlace que solo se puede abrir una vez, y la nota se borra.',
        'dialog_expires' => 'Si nadie lo abre, caduca en:',
        'expiry_1' => '1 hora',
        'expiry_24' => '1 día',
        'expiry_168' => '1 semana',
        'cancel' => 'cancelar',
        'close' => 'cerrar',
        'ready_title' => 'Secreto listo',
        'ready_text' => 'Comparte este enlace. Solo se puede abrir una vez.',
        'copy_link' => 'copiar enlace',
        'copied' => 'copiado',
        'generate_qr' => 'generar QR',
        'cli' => 'ayuda de comandos',
        'cli_title' => 'Uso desde la línea de comandos',
        'theme_title' => 'Cambiar entre tema claro y oscuro',
        'repo_title' => 'Código fuente en GitHub',
        'modified_title' => 'Última modificación',
        'saved' => 'Guardado',
        'saving' => 'Guardando…',
        'not_saved_conflict' => 'Sin guardar: cambió en otro dispositivo',
        'not_saved' => 'Sin guardar: {reason}',
        'copy_host' => 'Copiar el host',
        'copy_user' => 'Copiar el usuario',
        'copy_password' => 'Copiar la contraseña',
        'copy_token' => 'Copiar el token',
        'copy_ssh' => 'Copiar la clave SSH',
        'copy_block' => 'Copiar este bloque',
        'unsaved_note' => 'Nota sin guardar',
        'expires_title' => 'Se borrará en {time}. Haz clic para renovarla (o edita la nota).',
        'less_minute' => 'menos de un minuto',
        'one_minute' => '1 minuto',
        'minutes' => '{n} minutos',
        'download' => 'Descargar',
        'download_name' => 'Descargar {name}',
        'downloads_count' => 'Descargado {n} veces',
        'copy_download_command' => 'Copiar el comando de descarga (curl)',
        'upload_failed' => 'No se pudo subir {name}: {reason}',
        'network_error' => 'error de red',
        'file_too_large' => '{name} es demasiado grande (máximo {size}).',
        'download_over' => 'No se pudo descargar {name}: se acabó el tiempo para descargar los archivos.',
        'gate_expires' => 'Caduca el {date}h',
        'open_failed' => 'No se pudo abrir el secreto.',
        'opened_info' => 'Secreto abierto y borrado del servidor',
        'opened_info_files' => 'Secreto abierto y borrado del servidor: descarga los archivos antes de las {time}',
        'cli_upload_text' => 'Subir texto',
        'cli_upload_file' => 'Subir archivo',
        'cli_download_file' => 'Descargar archivo',
        'placeholder_command' => '<comando>',
        'placeholder_file' => '<archivo>',
        'copy_command' => 'Copiar comando',
        'confirm_refresh' => "Esta nota cambió en otro dispositivo y tus últimos cambios aquí NO se han guardado.\n\nAceptar: descartarlos y cargar la versión del servidor.\nCancelar: conservarlos para poder copiarlos antes.",
        'clear_conflict' => 'Esta nota cambió en otro dispositivo. Pulsa recargar antes de vaciarla.',
        'secret_conflict' => 'Esta nota cambió en otro dispositivo: pulsa recargar primero.',
        'still_saving' => 'La nota aún se está guardando, inténtalo de nuevo.',
        'make_failed' => 'No se pudo crear el secreto.',
        'secret_expires_on' => 'Si nadie lo abre, caduca el {date}.',
        'network_retry' => 'Error de red, inténtalo de nuevo.',
        'write_first' => 'Escribe algo o adjunta un archivo primero.',
        'language' => 'Idioma',
    ],
    'pt' => [
        'err_too_many_notes' => 'Notas demais (máximo de {n} ao mesmo tempo)',
        'err_secret_gone' => 'Este segredo não existe: já foi aberto ou expirou.',
        'err_link_incomplete' => 'Este link não está completo: verifique se foi copiado inteiro, incluindo a parte depois do "#".',
        'err_file_name' => 'Nome de arquivo inválido',
        'err_file_large' => 'Arquivo grande demais (máximo de {n} MB)',
        'err_attach_dir' => 'Não foi possível criar o diretório de anexos',
        'err_empty_file' => 'Arquivo vazio',
        'err_incomplete_upload' => 'Envio incompleto',
        'err_storage_full' => 'Armazenamento de anexos cheio (máximo de {n} MB no total)',
        'err_note_changed' => 'Esta nota mudou em outro dispositivo',
        'err_note_empty' => 'A nota está vazia',
        'err_too_many_secrets' => 'Segredos demais (máximo de {n} ao mesmo tempo)',
        'err_secrets_dir' => 'Não foi possível criar o diretório de segredos',
        'err_note_large' => 'Nota grande demais (máximo de {n} MB)',
        'secret' => 'Segredo',
        'copy_all' => 'copiar tudo',
        'copy_all_title' => 'Copiar tudo',
        'refresh' => 'recarregar',
        'refresh_title' => 'Esta nota mudou em outro dispositivo: clique para carregar a versão do servidor',
        'clear' => 'limpar',
        'clear_title' => 'Limpar a nota',
        'attach' => 'anexar arquivo',
        'attach_title' => 'Anexar arquivo (ou solte arquivos em qualquer lugar)',
        'make_secret' => 'transformar em segredo',
        'make_secret_title' => 'Transformar em segredo: converte esta nota em um link de uso único',
        'make_secret_title_short' => 'Transformar em segredo',
        'files' => 'Arquivos',
        'empty' => 'esvaziar',
        'empty_title' => 'Apagar todos os arquivos (o texto da nota é mantido)',
        'gate_title' => 'Alguém compartilhou um segredo com você',
        'once_title' => 'Só pode ser aberto uma vez',
        'once_text' => 'Ao abri-lo, ele é apagado do servidor, então copie ou baixe na hora o que precisar.',
        'open_secret' => 'abrir segredo',
        'gate_note' => 'Criptografado com AES-256. A chave vai apenas no seu link e nunca é armazenada no servidor.',
        'gone_title' => 'Segredo não disponível',
        'gone_text' => 'Já foi aberto ou expirou.',
        'no_text' => 'Sem texto, só arquivos',
        'drop' => 'Solte arquivos para anexá-los (máximo de {n} MB cada)',
        'dialog_text' => 'O texto e os arquivos desta nota viram um link que só pode ser aberto uma vez, e a nota é apagada.',
        'dialog_expires' => 'Se ninguém abrir, expira em:',
        'expiry_1' => '1 hora',
        'expiry_24' => '1 dia',
        'expiry_168' => '1 semana',
        'cancel' => 'cancelar',
        'close' => 'fechar',
        'ready_title' => 'Segredo pronto',
        'ready_text' => 'Compartilhe este link. Ele só pode ser aberto uma vez.',
        'copy_link' => 'copiar link',
        'copied' => 'copiado',
        'generate_qr' => 'gerar QR',
        'cli' => 'ajuda de comandos',
        'cli_title' => 'Uso pela linha de comando',
        'theme_title' => 'Alternar entre tema claro e escuro',
        'repo_title' => 'Código-fonte no GitHub',
        'modified_title' => 'Última modificação',
        'saved' => 'Salvo',
        'saving' => 'Salvando…',
        'not_saved_conflict' => 'Não salvo: mudou em outro dispositivo',
        'not_saved' => 'Não salvo: {reason}',
        'copy_host' => 'Copiar o host',
        'copy_user' => 'Copiar o usuário',
        'copy_password' => 'Copiar a senha',
        'copy_token' => 'Copiar o token',
        'copy_ssh' => 'Copiar a chave SSH',
        'copy_block' => 'Copiar este bloco',
        'unsaved_note' => 'Nota não salva',
        'expires_title' => 'Será apagada em {time}. Clique para renová-la (ou edite a nota).',
        'less_minute' => 'menos de um minuto',
        'one_minute' => '1 minuto',
        'minutes' => '{n} minutos',
        'download' => 'Baixar',
        'download_name' => 'Baixar {name}',
        'downloads_count' => 'Baixado {n} vezes',
        'copy_download_command' => 'Copiar o comando de download (curl)',
        'upload_failed' => 'Não foi possível enviar {name}: {reason}',
        'network_error' => 'erro de rede',
        'file_too_large' => '{name} é grande demais (máximo de {size}).',
        'download_over' => 'Não foi possível baixar {name}: o tempo para baixar os arquivos acabou.',
        'gate_expires' => 'Expira em {date}h',
        'open_failed' => 'Não foi possível abrir o segredo.',
        'opened_info' => 'Segredo aberto e apagado do servidor',
        'opened_info_files' => 'Segredo aberto e apagado do servidor: baixe os arquivos antes das {time}',
        'cli_upload_text' => 'Enviar texto',
        'cli_upload_file' => 'Enviar arquivo',
        'cli_download_file' => 'Baixar arquivo',
        'placeholder_command' => '<comando>',
        'placeholder_file' => '<arquivo>',
        'copy_command' => 'Copiar comando',
        'confirm_refresh' => "Esta nota mudou em outro dispositivo e suas últimas alterações aqui NÃO foram salvas.\n\nOK: descartá-las e carregar a versão do servidor.\nCancelar: mantê-las para poder copiá-las antes.",
        'clear_conflict' => 'Esta nota mudou em outro dispositivo. Clique em recarregar antes de limpá-la.',
        'secret_conflict' => 'Esta nota mudou em outro dispositivo: clique em recarregar primeiro.',
        'still_saving' => 'A nota ainda está sendo salva, tente de novo.',
        'make_failed' => 'Não foi possível criar o segredo.',
        'secret_expires_on' => 'Se ninguém abrir, expira em {date}.',
        'network_retry' => 'Erro de rede, tente de novo.',
        'write_first' => 'Escreva algo ou anexe um arquivo primeiro.',
        'language' => 'Idioma',
    ],
];

$lang = $_COOKIE['lang'] ?? '';
if (!is_string($lang) || !isset($i18n[$lang])) {
    $lang = 'en';
    foreach (explode(',', (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) as $accepted) {
        $code = strtolower(substr(trim($accepted), 0, 2));
        if (isset($i18n[$code])) {
            $lang = $code;
            break;
        }
    }
}

function t($key, $args = []) {
    global $i18n, $lang;
    $text = $i18n[$lang][$key] ?? $i18n['en'][$key] ?? $key;
    foreach ($args as $name => $value) {
        $text = str_replace('{' . $name . '}', $value, $text);
    }
    return $text;
}

function i18n_args($args) {
    return $args ? ' data-i18n-args="' . htmlspecialchars(json_encode($args), ENT_QUOTES, 'UTF-8') . '"' : '';
}

// Translated element content (it also closes the opening tag), marked so the page script can switch it.
function tt($key, $args = []) {
    print ' data-i18n="' . $key . '"' . i18n_args($args) . '>' . htmlspecialchars(t($key, $args), ENT_QUOTES, 'UTF-8');
}

// Translated attribute, marked the same way.
function ta($attr, $key, $args = []) {
    print ' data-i18n-' . $attr . '="' . $key . '"' . i18n_args($args) . ' ' . $attr . '="' . htmlspecialchars(t($key, $args), ENT_QUOTES, 'UTF-8') . '"';
}

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
    $downloads = read_downloads($dir);
    foreach (is_dir($dir) ? scandir($dir) : [] as $name) {
        if (valid_file_name($name) && is_file("$dir/$name")) {
            $files[] = ['name' => $name, 'size' => filesize("$dir/$name"), 'mtime' => filemtime("$dir/$name"),
                'downloads' => $downloads[$name] ?? 0];
        }
    }
    return $files;
}

// Download counts per attachment, kept in the hidden "<note>.files/.downloads" (JSON {name: count}).
function read_downloads($dir) {
    $data = is_file("$dir/.downloads") ? json_decode(file_get_contents("$dir/.downloads"), true) : null;
    return is_array($data) ? $data : [];
}

// Set (or with null, forget) the count of one file; call it under the lock. The file is rewritten in place
// and the directory mtime restored, since a download must not count as a change for the note expiry.
function set_downloads($dir, $name, $count) {
    $downloads = read_downloads($dir);
    if ($count === null) {
        if (!isset($downloads[$name])) {
            return;
        }
        unset($downloads[$name]);
    } else {
        $downloads[$name] = $count;
    }
    clearstatcache(true, $dir);
    $changed = filemtime($dir);
    if ($downloads) {
        $handle = fopen("$dir/.downloads", 'c');
        ftruncate($handle, 0);
        fwrite($handle, json_encode($downloads));
        fclose($handle);
    } elseif (is_file("$dir/.downloads")) {
        unlink("$dir/.downloads");
    }
    touch($dir, $changed);
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
        fail(507, t('err_too_many_notes', ['n' => $max_notes]));
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
    if (is_file("$dir/.downloads")) {
        unlink("$dir/.downloads");
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
            fail(404, t('err_secret_gone'));
        }
        $data = is_file("$dir/data") ? file_get_contents("$dir/data") : '';
        if (!($key = posted_key()) || ($data = unseal($data, $key)) === false) {
            fail(403, t('err_link_incomplete'));
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
        fail(400, t('err_file_name'));
    }
    $length = $_SERVER['CONTENT_LENGTH'] ?? '';
    if ($length !== '' && (int) $length > $max_file_size) {
        fail(413, t('err_file_large', ['n' => $max_file_size >> 20]));
    }
    // Check the note limit and create the directory under the lock, then release it.
    $lock = fopen($save_path . '/.lock', 'c');
    flock($lock, LOCK_EX);
    check_note_limit($save_path, $_GET['note'], $max_notes);
    if (!is_dir($files_dir) && !@mkdir($files_dir, 0755) && !is_dir($files_dir)) {
        fail(500, t('err_attach_dir'));
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
        fail(413, t('err_file_large', ['n' => $max_file_size >> 20]));
    }
    if ($copied === 0 || $copied === false) {
        fail(400, t('err_empty_file'));
    }
    if ($length !== '' && $copied !== (int) $length) {
        fail(400, t('err_incomplete_upload'));
    }

    $lock = fopen($save_path . '/.lock', 'c');
    flock($lock, LOCK_EX);
    $target = "$files_dir/$name";
    if (attachments_size($save_path, $target) + $copied > $max_total_size) {
        fail(507, t('err_storage_full', ['n' => $max_total_size >> 20]));
    }
    rename($temp, $target);
    set_downloads($files_dir, $name, null);
    send_files($files_dir);
}

// Delete one attachment, or all of them with ?empty.
if ((isset($_GET['delete']) || isset($_GET['empty'])) && $method === 'POST') {
    $lock = fopen($save_path . '/.lock', 'c');
    flock($lock, LOCK_EX);
    if (isset($_GET['empty'])) {
        delete_note('', $files_dir);
    } elseif (valid_file_name($_GET['delete']) && is_file("$files_dir/{$_GET['delete']}")) {
        unlink("$files_dir/{$_GET['delete']}");
        set_downloads($files_dir, $_GET['delete'], null);
        list_files($files_dir) ?: delete_note('', $files_dir);
    }
    send_files($files_dir);
}

// Download an attachment.
if (isset($_GET['file'])) {
    if (!valid_file_name($_GET['file']) || !is_file($file = "$files_dir/{$_GET['file']}")) {
        fail(404, 'Not found');
    }
    if ($method === 'GET') {
        $lock = fopen($save_path . '/.lock', 'c');
        flock($lock, LOCK_EX);
        set_downloads($files_dir, $_GET['file'], (read_downloads($files_dir)[$_GET['file']] ?? 0) + 1);
        flock($lock, LOCK_UN);
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
        fail(409, t('err_note_changed'));
    }
    if (!is_file($path) && !list_files($files_dir)) {
        fail(404, t('err_note_empty'));
    }
    if (count(secret_ids($secrets_path)) >= $max_secrets) {
        fail(507, t('err_too_many_secrets', ['n' => $max_secrets]));
    }
    if (!is_dir($secrets_path) && !@mkdir($secrets_path, 0755) && !is_dir($secrets_path)) {
        fail(500, t('err_secrets_dir'));
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
        fail(413, t('err_note_large', ['n' => $max_note_size >> 20]));
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
<html lang="<?php print $lang === 'pt' ? 'pt-BR' : $lang; ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php print $secret ? t('secret') : htmlspecialchars($_GET['note'], ENT_QUOTES, 'UTF-8'); ?></title>
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
    --shadow-strong: rgba(0, 0, 0, 0.5);
    --on-primary: 0, 25, 35;
    --qr-dark: 0, 25, 35;
    --qr-light: 255, 255, 255;
    --secret-shade-from: rgba(0, 25, 35, 0);
    --secret-shade-to: rgba(0, 25, 35, 0.55);
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
    --shadow-strong: rgba(0, 25, 35, 0.4);
    --on-primary: 0, 25, 35;
    --qr-dark: 0, 25, 35;
    --qr-light: 255, 255, 255;
    --secret-shade-from: rgba(0, 25, 35, 0);
    --secret-shade-to: rgba(0, 25, 35, 0.55);
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
#modified > :not(#cli):not(#saved):not(#secret-info):not(#lang),
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
/* Language switcher, centred in the footer (in the flow on narrow screens) */
#lang {
    position: absolute;
    left: 50%;
    display: flex;
    align-items: center;
    gap: 2px;
    transform: translateX(-50%);
}
#lang svg {
    margin-right: 4px;
    opacity: 0.6;
}
#lang button {
    padding: 0 6px;
    font: 14px/22px var(--mono);
    color: inherit;
    background: transparent;
    border: 0;
    border-radius: 999px;
    opacity: 0.6;
    cursor: pointer;
    transition: opacity 0.15s, background 0.15s;
}
#lang button:hover {
    opacity: 1;
}
#lang button.active {
    background: rgba(var(--footer-text), 0.15);
    opacity: 1;
}
/* Narrow screens: no room for everything in one line. The curl help goes away (no use on a phone),
   the GitHub link keeps only its icon and the save time drops its icon and fixed width. */
@media (max-width: 800px) {
    #lang {
        position: static;
        margin-right: auto;
        transform: none;
    }
    #lang svg,
    #cli,
    #repo .version,
    #saved > svg {
        display: none;
    }
    #lang button {
        padding: 0 4px;
    }
    #modified,
    #saved {
        gap: 6px;
    }
    #modified {
        right: 12px;
        left: 12px;
    }
    #repo,
    #theme {
        margin-right: 0;
    }
    #saved {
        min-width: 0;
    }
    #secret-info {
        display: none;
    }
}
@media (max-width: 359px) {
    #repo {
        display: none;
    }
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
/* Secret view: instead of the block copy buttons, an icon per detected credential line
   (one line tall, so consecutive lines don't overlap) that copies only its value */
.copy-btn.sensitive {
    --accent: var(--primary);
    height: 24px;
    margin-top: 1px;
    color: rgb(var(--accent));
    opacity: 0.85;
}
.copy-btn.sensitive:hover {
    background: rgba(var(--accent), 0.15);
}
.copy-btn.sensitive.copied {
    color: rgb(var(--ok));
}
/* User and host stay in --primary; actual secrets (password, token, SSH key) in --danger */
.sensitive.password,
.sensitive.token,
.sensitive.ssh {
    --accent: var(--danger);
}
.copy-btn.sensitive + .block-bar {
    background: rgb(var(--accent));
}
.copy-btn.sensitive:hover + .block-bar {
    opacity: 0.8;
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
    /* Springy timing, so the press bounces back on release */
    transition: background 0.15s, border-color 0.15s, box-shadow 0.2s, transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.action:hover {
    background: rgba(var(--accent), 0.2);
    border-color: rgba(var(--accent), 0.7);
    box-shadow: 0 6px 18px var(--shadow-soft), 0 0 0 3px rgba(var(--accent), 0.05), 0 0 16px rgba(var(--accent), 0.18);
    transform: translateY(-1px);
}
.action:active:not(:disabled) {
    transform: scale(0.95);
    transition-duration: 0.15s, 0.15s, 0.2s, 0.08s;
}
/* Each icon plays its own short animation once on hover */
.action:hover:not(:disabled) > svg {
    animation: 0.5s ease-in-out;
}
#copy-all:hover > svg { animation-name: icon-lift; }
#refresh:hover:not(:disabled) > svg { animation-name: icon-spin; animation-duration: 0.6s; }
#clear:hover:not(:disabled) > svg { animation-name: icon-sweep; transform-origin: 50% 10%; }
#attach:hover > svg { animation-name: icon-tilt; }
#make-secret:hover > svg { animation-name: icon-turn; }
@keyframes icon-lift {
    50% { transform: translate(-1px, -1px); }
}
@keyframes icon-spin {
    to { transform: rotate(360deg); }
}
@keyframes icon-sweep {
    25% { transform: rotate(-7deg); }
    75% { transform: rotate(7deg); }
}
@keyframes icon-tilt {
    40% { transform: rotate(-9deg); }
    70% { transform: rotate(3deg); }
}
@keyframes icon-turn {
    50% { transform: rotate(-18deg); }
}
@media (prefers-reduced-motion: reduce) {
    .action > svg {
        animation: none !important;
    }
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
    opacity: 0;
    pointer-events: none;
    transform: translate(4px, -50%);
    transition: opacity 0.15s, transform 0.15s;
}
/* Keyboard shortcut keys inside a label */
.action small kbd {
    display: inline-block;
    min-width: 20px;
    margin-left: 4px;
    padding: 0 5px;
    font-family: var(--mono);
    font-size: 11px;
    line-height: 18px;
    text-align: center;
    background: rgba(var(--accent), 0.1);
    border: 1px solid rgba(var(--accent), 0.35);
    border-bottom-width: 2px;
    border-radius: 4px;
}
.action small kbd:first-of-type {
    margin-left: 10px;
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
/* The secret ready screen is centred */
#secret-done {
    text-align: center;
}
#secret-done h2,
#secret-done .choices.end {
    justify-content: center;
}
#secret-copy-row {
    margin-top: 12px;
}
/* The copy link button says "copied" for a moment after copying */
#secret-copy .copied-label,
#secret-copy.copied .copy-label {
    display: none;
}
#secret-copy.copied .copied-label {
    display: inline;
}
#secret-dialog {
    margin: auto;
}
#secret-expires {
    margin: 12px 0 0;
}
#secret-qr-row {
    margin-top: 16px;
}
#secret-qr-button {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
/* QR codes need dark modules on a light background in both themes */
#secret-qr svg {
    display: block;
    width: 200px;
    height: 200px;
    margin: 16px auto 0;
    background: rgb(var(--qr-light));
    border-radius: 8px;
}
#secret-qr path {
    fill: rgb(var(--qr-dark));
}
#secret-dialog::backdrop {
    background: var(--backdrop);
}
/* Secret page: only the card until it is opened, then the usual editor (read-only) and files panel */
/* The top padding leaves the background's lock and password field (its top 22%) above the card */
#gate {
    position: absolute;
    top: 0;
    right: 0;
    bottom: var(--footer-height);
    left: 0;
    display: flex;
    box-sizing: border-box;
    padding-top: calc(max(56.3vw, 100vh) * 0.23);
    overflow-y: auto;
}
#gate .card {
    margin: auto;
}
body.secret-view:not(.opened) .container,
body.secret-view:not(.opened) #files,
body.opened #gate {
    display: none;
}
/* Secret page background: an illustration (embedded, so no third party sees the visit) under a brand shade */
body.secret-view {
    background: linear-gradient(160deg, var(--secret-shade-from), var(--secret-shade-to)), var(--bg) url("data:image/jpeg;base64,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BAAAD+LjrxKIClSValVFaslVlVsURSwoAURSylJRFBSwUFSUAoAAVIASVEVElZuWmUmpEmkJUolAABQFSUAooBAAAAABSKJQKSKJSiiKQoigpJQFJSgAQAAFiyApLJUsBAFSyAVLAtJu9riem+rp5XoduvkmrdcooiwAAASiSpZWyei+3l6M+56fP9N0t5etSaAAAAlgAASwAAA/i468SkFJVpZUVq5VUFsURSgSgWVFKUShKCixRBaFSKAEoiwWAFRIRGpElkZmmbJqEmgAACgAEopZQEFIoihKAAQUiiVSKqKQUigUikKIoChUigAAACLABLFCEsWASxUsgFAFRu9rh6Nenr5nodenkaXXIVIoiwAASliwLuHe+3l6c+2+jz/TdF5etSaAAASwqAAAQssBCwAP4yOvAWhUFFWxVZpbFlQKoQCgVUFsFFlRSwUBKKWVAAABAJUQRlpGZUkmkSaQlgUAUAKSKJSlEFABSLEFJQAFIqwUigAolEKqUCkigAAoiiKQCKIpYBASxRIEUQBYIBRpJ0d9Yem+rp5Z6HXp5Jq3XOKQABLAogAC7lei+zl6nsvfzfSdF5+sJoAAAQAAAIABAAAD+Ml7cBUKFWxVZUsoFECllBUAostlRRFlKLFlBUFoELAABLBElRFZSambmaSSasJUpUoAFSUpQlAVJQBCgAAAqhUlApIoFqKgKKQUikFIoiqioiiUAEsAEsAWAgVCVASlksgFGkdHfXN63q6eSeh16+WWrzKQCKIoiiBQC7iel7eXqnsvfzfUu15+oJoAAACAAAQAIAAQWAg/jg7eelFWxRKWwEoKlQKFFlRZUFFlsURZSixZRZUAosAAIUiEZVm5mpm5mmUmglBQARQFqUQolAEKApQBABSUFEAUopIolUiklUlKKSUAAAAEoiwBYshLAFgISURQEFCC0nV6dcs+rXp6+WeidenlmlvMVIoiiFIAACW7lejXs5euex3830215+oJoAAAAlIABKIACAELAQAP46rt51lspUURZbFgUFlQBZaWVFlRYLZbKlSgosoFhKBYoJAVEERWUmmWZpEmkJQoIAoBaBFApJQAFqUQAUABFAoCxQUBUigWyKBSKJRAAABFASwBUISxUBCVBYAFghWrHS+jXKep6unldm+nlaW4BKAEABUoiiW9Ino17OXrnsno831ZuufpCaAA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") center top / cover no-repeat fixed;
}
#gate .card {
    width: min(440px, calc(100vw - 32px));
    padding: 32px 28px 22px;
    text-align: center;
    background: rgba(var(--surface), 0.86);
    -webkit-backdrop-filter: blur(14px) saturate(140%);
    backdrop-filter: blur(14px) saturate(140%);
    border-radius: 16px;
    box-shadow: 0 24px 64px var(--shadow-strong);
    animation: gate-in 0.4s ease-out;
}
@keyframes gate-in {
    from {
        opacity: 0;
        transform: translateY(12px) scale(0.98);
    }
}
#gate h2 {
    justify-content: center;
    margin-bottom: 20px;
    font-size: 19px;
    color: rgb(var(--text));
}
.gate-badge {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 64px;
    height: 64px;
    margin: 0 auto 16px;
    color: rgb(var(--primary));
    background: rgba(var(--primary), 0.14);
    border-radius: 50%;
    box-shadow: 0 0 0 8px rgba(var(--primary), 0.06);
}
.gate-badge.gone {
    color: rgb(var(--danger));
    background: rgba(var(--danger), 0.14);
    box-shadow: 0 0 0 8px rgba(var(--danger), 0.06);
}
#gate .once {
    text-align: left;
}
.gate-meta {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    font-size: 13px;
    opacity: 0.7;
}
.gate-meta svg,
.gate-note svg {
    flex-shrink: 0;
}
.card-button.solid {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    height: 46px;
    font-size: 15px;
    font-weight: 500;
    color: rgb(var(--on-primary));
    background: rgb(var(--primary));
    border-color: transparent;
    transition: filter 0.15s, box-shadow 0.15s, transform 0.15s;
}
.card-button.solid:not(:disabled):hover {
    background: rgb(var(--primary));
    border-color: transparent;
    filter: brightness(1.1);
    box-shadow: 0 6px 18px rgba(var(--primary), 0.35);
    transform: translateY(-1px);
}
.gate-note {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin: 20px 0 0 !important;
    padding-top: 16px;
    font-size: 12px;
    line-height: 18px;
    text-align: left;
    opacity: 0.65;
    border-top: 1px solid rgba(var(--text), 0.12);
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
    max-width: calc(50% - 130px);
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
/* Download and copy the curl command, on the right of the card */
.file .actions {
    display: flex;
    flex-shrink: 0;
    gap: 2px;
}
.file .actions:empty {
    display: none;
}
.file .actions .file-action {
    width: 32px;
    height: 32px;
    border-radius: 6px;
}
.file .actions svg {
    width: 20px;
    height: 20px;
}
.file .size small {
    font-size: 9px;
}
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
/* Fixed width too, so the size and buttons stay aligned */
.file .downloads {
    min-width: 4ch;
    margin-right: 4px;
    font-size: 11px;
    line-height: 18px;
    opacity: 0.5;
}
.file-action.copied {
    color: rgb(var(--ok));
    opacity: 1;
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
<textarea id="content"<?php if ($secret) { print ' readonly'; ta('placeholder', 'no_text'); } ?>><?php
if ($initial_text !== false) {
    print htmlspecialchars($initial_text, ENT_QUOTES, 'UTF-8');
}
?></textarea>
<div id="glow"><div id="glow-inner"></div></div>
<div id="gutter"><div id="gutter-inner"></div></div>
<div id="mirror" aria-hidden="true"></div>
<button id="copy-all" class="action" type="button"<?php ta('title', 'copy_all_title'); ?>><svg class="icon-copy" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg><svg class="icon-check" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg><small<?php tt('copy_all'); ?></small></button>
<?php if (!$secret): ?>
<button id="refresh" class="action" type="button"<?php ta('title', 'refresh_title'); ?>><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg><small<?php tt('refresh'); ?></small></button>
<button id="clear" class="action" type="button"<?php ta('title', 'clear_title'); ?>><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m16 22-1-4"/><path d="M19 14a1 1 0 0 0 1-1v-1a2 2 0 0 0-2-2h-3a1 1 0 0 1-1-1V4a2 2 0 0 0-4 0v5a1 1 0 0 1-1 1H6a2 2 0 0 0-2 2v1a1 1 0 0 0 1 1"/><path d="M5 14h14l1.973 6.767A1 1 0 0 1 20 22H4a1 1 0 0 1-.973-1.233z"/><path d="m8 22 1-4"/></svg><small<?php tt('clear'); ?></small></button>
<button id="attach" class="action" type="button"<?php ta('title', 'attach_title'); ?>><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg><small><span<?php tt('attach'); ?></span><kbd class="mod-key">Ctrl</kbd><kbd>V</kbd></small></button>
<button id="make-secret" class="action" type="button"<?php ta('title', 'make_secret_title'); ?>><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4z"/><circle cx="16.5" cy="7.5" r=".5" fill="currentColor"/></svg><small<?php tt('make_secret'); ?></small></button>
<input id="file-input" type="file" multiple hidden>
<?php endif; ?>
</div>
<div id="files">
<div id="files-title"><span<?php tt('files'); ?></span> <span id="files-count">0</span></div>
<div id="files-list"></div>
<?php if (!$secret): ?>
<button id="empty" type="button"<?php ta('title', 'empty_title'); ?>><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg><span<?php tt('empty'); ?></span></button>
<?php endif; ?>
</div>
<?php if ($secret): ?>
<div id="gate"><div class="card">
<?php if ($secret['id']): ?>
<div class="gate-badge"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4z"/><circle cx="16.5" cy="7.5" r=".5" fill="currentColor"/></svg></div>
<h2<?php tt('gate_title'); ?></h2>
<div class="once"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg><div><strong<?php tt('once_title'); ?></strong><span<?php tt('once_text'); ?></span></div></div>
<p class="gate-meta"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg><span id="gate-expires"></span></p>
<p id="gate-error" class="error" hidden></p>
<div class="choices"><button id="open-secret" class="card-button solid" type="button"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg><span<?php tt('open_secret'); ?></span></button></div>
<p class="gate-note"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg><span<?php tt('gate_note'); ?></span></p>
<?php else: ?>
<div class="gate-badge gone"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg></div>
<h2<?php tt('gone_title'); ?></h2>
<p class="muted"<?php tt('gone_text'); ?></p>
<?php endif; ?>
</div></div>
<?php else: ?>
<div id="drop"<?php tt('drop', ['n' => $max_file_size >> 20]); ?></div>
<dialog id="secret-dialog" class="card">
<div id="secret-ask">
<h2><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg><span<?php tt('make_secret_title_short'); ?></span></h2>
<p<?php tt('dialog_text'); ?></p>
<p class="muted"<?php tt('dialog_expires'); ?></p>
<div class="choices"><?php foreach ($secret_expiries as $hours): ?><button class="card-button" type="button" data-hours="<?php print $hours; ?>"<?php tt("expiry_$hours"); ?></button><?php endforeach; ?></div>
<p id="secret-error" class="error" hidden></p>
<div class="choices end"><button class="card-button secondary secret-close" type="button"<?php tt('cancel'); ?></button></div>
</div>
<div id="secret-done" hidden>
<h2><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg><span<?php tt('ready_title'); ?></span></h2>
<p<?php tt('ready_text'); ?></p>
<div class="link"><code id="secret-link"></code></div>
<div id="secret-copy-row" class="choices"><button id="secret-copy" class="card-button solid" type="button"></button></div>
<p id="secret-expires" class="muted"></p>
<div id="secret-qr-row" class="choices"><button id="secret-qr-button" class="card-button" type="button"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="5" height="5" x="3" y="3" rx="1"/><rect width="5" height="5" x="16" y="3" rx="1"/><rect width="5" height="5" x="3" y="16" rx="1"/><path d="M21 16h-3a2 2 0 0 0-2 2v3"/><path d="M21 21v.01"/><path d="M12 7v3a2 2 0 0 1-2 2H7"/><path d="M3 12h.01"/><path d="M12 3h.01"/><path d="M12 16v.01"/><path d="M16 12h1"/><path d="M21 12v.01"/><path d="M12 21v-1"/></svg><span<?php tt('generate_qr'); ?></span></button></div>
<div id="secret-qr" hidden></div>
<div class="choices end"><button class="card-button secondary secret-close" type="button"<?php tt('close'); ?></button></div>
</div>
</dialog>
<?php endif; ?>
<div id="modified"><?php if ($secret): ?><span id="secret-info"></span><?php else: ?><div id="cli"><div id="cli-panel"><div id="cli-list"></div></div><button id="cli-toggle" type="button" aria-expanded="false"<?php ta('title', 'cli_title'); ?>><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 17 10 11 4 5"/><line x1="12" x2="20" y1="19" y2="19"/></svg><span<?php tt('cli'); ?></span></button></div><?php endif; ?><div id="lang"<?php ta('title', 'language'); ?>><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg><?php foreach (['es' => 'Español', 'en' => 'English', 'pt' => 'Português (Brasil)'] as $code => $name): ?><button type="button" data-lang="<?php print $code; ?>" title="<?php print $name; ?>"<?php if ($code === $lang) { print ' class="active"'; } ?>><?php print strtoupper($code); ?></button><?php endforeach; ?></div><button id="theme" type="button"<?php ta('title', 'theme_title'); ?>><svg class="icon-sun" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg><svg class="icon-moon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg></button><a id="repo" href="<?php print $repo_url; ?>" target="_blank" rel="noopener noreferrer"<?php ta('title', 'repo_title'); ?>><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.93.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.22.165.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg><span class="version">v<?php print $app_version; ?></span></a><?php if (!$secret): ?><span id="saved"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M12 7v5l3 2"/></svg><span id="modified-time"<?php ta('title', 'modified_title'); ?>></span><button id="expires" type="button" hidden><span id="expires-fill"></span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg><span id="expires-label"></span></button></span><span id="status"></span><?php endif; ?></div>
<pre id="printable"></pre>
<script>
// Translations (the $i18n table). Texts marked with data-i18n, data-i18n-title or data-i18n-placeholder
// (plus data-i18n-args) are switched by setLang(); computed ones are rebuilt by the onLang() callbacks.
var I18N = <?php print json_encode($i18n, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
var LANG = '<?php print $lang; ?>';
var LOCALES = {en: 'en-GB', es: 'es-ES', pt: 'pt-BR'};
var langCallbacks = [];

function t(key, args) {
    var text = I18N[LANG][key] || I18N.en[key] || key;
    for (var name in args || {}) {
        text = text.split('{' + name + '}').join(args[name]);
    }
    return text;
}

// Sets a translated text (or attribute) and marks it so switching the language translates it again.
function tr(el, key, args, attr) {
    el.dataset[attr ? 'i18n' + attr.charAt(0).toUpperCase() + attr.slice(1) : 'i18n'] = key;
    if (args) {
        el.dataset.i18nArgs = JSON.stringify(args);
    }
    if (attr) {
        el.setAttribute(attr, t(key, args));
    } else {
        el.textContent = t(key, args);
    }
}

// Runs now and again after every language switch.
function onLang(callback) {
    langCallbacks.push(callback);
    callback();
}

function setLang(lang) {
    LANG = lang;
    document.documentElement.lang = lang === 'pt' ? 'pt-BR' : lang;
    document.cookie = 'lang=' + lang + '; path=' + window.location.pathname.replace(/[^\/]*$/, '')
        + '; max-age=31536000; SameSite=Lax' + (window.location.protocol === 'https:' ? '; Secure' : '');
    Array.prototype.forEach.call(document.querySelectorAll('[data-i18n], [data-i18n-title], [data-i18n-placeholder]'), function(el) {
        var args = el.dataset.i18nArgs ? JSON.parse(el.dataset.i18nArgs) : null;
        if (el.dataset.i18n) {
            el.textContent = t(el.dataset.i18n, args);
        }
        if (el.dataset.i18nTitle) {
            el.title = t(el.dataset.i18nTitle, args);
        }
        if (el.dataset.i18nPlaceholder) {
            el.placeholder = t(el.dataset.i18nPlaceholder, args);
        }
    });
    Array.prototype.forEach.call(document.querySelectorAll('#lang button'), function(button) {
        button.classList.toggle('active', button.dataset.lang === lang);
    });
    langCallbacks.forEach(function(callback) {
        callback();
    });
}

Array.prototype.forEach.call(document.querySelectorAll('#lang button'), function(button) {
    button.onclick = function() {
        setLang(button.dataset.lang);
    };
});

function updateStatus() {
    var state = conflict ? 'conflict' : (saveError ? 'error' : (content !== textarea.value ? 'saving' : 'saved'));
    statusDot.className = state;
    statusDot.title = {
        saved: t('saved'),
        saving: t('saving'),
        conflict: t('not_saved_conflict'),
        error: t('not_saved', {reason: saveError})
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
        setSaveError(t('err_note_large', {n: MAX_NOTE_SIZE >> 20}));
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

// Credential lines in a secret: "<label>: value" (or "=") lines whose label, up to 30 chars,
// contains one of these words (the first matching type wins, so "Mail password" is a password and "SSH clave" an SSH key),
// SSH keys and well-known token formats. Each one is { start, end, type, value } (offsets of its lines).
var SENSITIVE_LABELS = [
    ['ssh', /(?:^|[^\p{L}\d])(?:ssh|private[ _-]?key|clave privada|chave privada)(?![\p{L}\d])/iu],
    ['password', /(?:^|[^\p{L}\d])(?:pass(?:word|wd)?|pwd|contraseña|clave|senha|chave)(?![\p{L}\d])/iu],
    ['token', /(?:^|[^\p{L}\d])(?:token|api[ _-]?key|secret)(?![\p{L}\d])/iu],
    ['user', /(?:^|[^\p{L}\d])(?:user(?:name)?|usuario|usuário|login|e-?mail|mail|correo)(?![\p{L}\d])/iu],
    ['host', /(?:^|[^\p{L}\d])(?:host(?:name)?|server|servidor|ip|url|uri|endpoint|domain|dominio)(?![\p{L}\d])/iu],
    // "API" alone last, so "API URL" stays a host
    ['token', /(?:^|[^\p{L}\d])api(?![\p{L}\d])/iu]
];
var TOKEN_PATTERN = /(?:gh[pousr]_[A-Za-z0-9]{36,}|github_pat_\w{20,}|glpat-[\w-]{20,}|AKIA[0-9A-Z]{16}|xox[abprs]-[\w-]{10,}|sk-[\w-]{20,}|eyJ[\w-]{10,}\.[\w-]{10,}\.[\w-]+)/;
var SENSITIVE_ICONS = {
    host: '<rect x="3" y="4" width="18" height="7" rx="2"/><rect x="3" y="13" width="18" height="7" rx="2"/><line x1="7" y1="7.5" x2="7.01" y2="7.5"/><line x1="7" y1="16.5" x2="7.01" y2="16.5"/>',
    user: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    password: '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
    token: '<path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6z"/>',
    ssh: '<polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/>'
};

function findSensitive(text) {
    var lines = text.split('\n');
    var items = [];
    var pos = 0;
    var keyStart = -1;
    for (var i = 0; i < lines.length; i++) {
        var line = lines[i];
        var end = pos + line.length;
        var match;
        if (keyStart >= 0) {
            if (/^-----END [A-Z ]*PRIVATE KEY-----/.test(line.trim())) {
                items.push({start: keyStart, end: end, type: 'ssh', value: text.slice(keyStart, end) + '\n'});
                keyStart = -1;
            }
        } else if (/^-----BEGIN [A-Z ]*PRIVATE KEY-----/.test(line.trim())) {
            keyStart = pos;
        } else if (/^\s*(?:ssh-(?:rsa|ed25519|dss)|ecdsa-sha2-\S+) [A-Za-z0-9+\/=]{20,}/.test(line)) {
            items.push({start: pos, end: end, type: 'ssh', value: line.trim()});
        } else {
            var label = /^\s*(?:[-*•]\s*)?([^:=\n]{1,30}?)\s*[:=]\s*(\S.*)$/.exec(line);
            var found = label && SENSITIVE_LABELS.some(function(type) {
                if (type[1].test(label[1])) {
                    var value = label[2].trim().replace(/^(["'`])(.*)\1$/, '$2');
                    items.push({start: pos, end: end, type: type[0], value: value});
                    return true;
                }
            });
            if (!found && (match = TOKEN_PATTERN.exec(line))) {
                items.push({start: pos, end: end, type: 'token', value: match[0]});
            }
        }
        pos = end + 1;
    }
    return items;
}

function syncGutterScroll() {
    gutterInner.style.transform = glowInner.style.transform = 'translateY(' + (-textarea.scrollTop) + 'px)';
}

// Rebuild the copy buttons, placing each one at the first line of its block.
// In the secret view the buttons go on the detected credential lines instead, and copy only the value.
function updateGutter() {
    var text = textarea.value;
    var blocks = SECRET_VIEW ? findSensitive(text) : findBlocks(text);

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
        if (SECRET_VIEW) {
            button.className = 'copy-btn sensitive ' + block.type;
            button.title = t('copy_' + block.type);
            button.innerHTML = '<svg class="icon-copy" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
                + SENSITIVE_ICONS[block.type] + '</svg>' + COPY_ICONS.slice(COPY_ICONS.indexOf('<svg class="icon-check"'));
        } else {
            button.className = 'copy-btn';
            button.title = t('copy_block');
            button.innerHTML = COPY_ICONS;
        }
        button.style.top = markers[i].offsetTop + 'px';

        // Keep the focus (and cursor) in the editor.
        button.onmousedown = function(e) {
            e.preventDefault();
        };
        button.onclick = function() {
            copyBlock(SECRET_VIEW ? block.value : text.slice(block.start, block.end), button);
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
        ? formatTime(latest)
        : t('unsaved_note');
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
    expires.title = t('expires_title', {time: seconds < 60 ? t('less_minute') : left === 1 ? t('one_minute') : t('minutes', {n: left})});
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
        tr(label, 'download_name', {name: name}, 'title');
    }
    meta.appendChild(label);
    var details = document.createElement('div');
    details.className = 'details';
    meta.appendChild(details);
    var size = document.createElement('span');
    size.className = 'size';
    // The unit ("KB", "MB"…) goes smaller and right after the number.
    var parts = detail.match(/^([\d.]+) (\S+)$/);
    size.textContent = parts ? parts[1] : detail;
    if (parts) {
        var unit = document.createElement('small');
        unit.textContent = parts[2];
        size.appendChild(unit);
    }
    details.appendChild(size);
    var actions = document.createElement('div');
    actions.className = 'actions';
    row.appendChild(actions);
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
        var actions = chip.querySelector('.actions');
        var count = document.createElement('span');
        count.className = 'downloads';
        count.textContent = '↓' + (file.downloads || 0);
        count.title = t('downloads_count', {n: file.downloads || 0});
        details.insertBefore(count, details.firstChild);
        var download = document.createElement('a');
        download.className = 'file-action';
        download.href = href;
        download.title = t('download');
        download.innerHTML = DOWNLOAD_ICON;
        actions.appendChild(download);
        var command = document.createElement('button');
        command.type = 'button';
        command.className = 'file-action';
        command.title = t('copy_download_command');
        command.innerHTML = COMMAND_ICONS;
        command.onclick = function() {
            copyBlock('curl -sO ' + noteUrl + '/' + file.name, command);
        };
        actions.appendChild(command);
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
            alert(t('upload_failed', {name: upload.file.name, reason: request.responseText || t('network_error')}));
        }
        nextUpload();
    };
    request.send(upload.file);
}

function uploadFiles(list) {
    Array.prototype.forEach.call(list, function(file) {
        if (file.size > MAX_FILE_SIZE) {
            alert(t('file_too_large', {name: file.name, size: formatSize(MAX_FILE_SIZE)}));
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
var SECRET_VIEW = document.body.classList.contains('secret-view');
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
langCallbacks.push(scheduleGutter);
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
    return new Date(seconds * 1000).toLocaleString(LOCALES[LANG], {weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit'});
}

function formatTime(seconds) {
    return new Date(seconds * 1000).toLocaleTimeString(LOCALES[LANG], {hour: '2-digit', minute: '2-digit'});
}
<?php if ($secret): ?>
// Secret page: opening it (POST) deletes it from the server and returns its text and files.
var SECRET_ID = '<?php print $secret['id']; ?>';
var SECRET_KEY = window.location.hash.slice(1);
var openButton = document.getElementById('open-secret');
var secretInfo = document.getElementById('secret-info');

// A key is translated (and switched with the language); a server message is shown as is (already translated).
function showGateError(message, key) {
    var error = document.getElementById('gate-error');
    if (key) {
        tr(error, key);
    } else {
        delete error.dataset.i18n;
        error.textContent = message;
    }
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
            alert(t('download_over', {name: name}));
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
    showGateError('', 'err_link_incomplete');
    openButton.parentNode.style.display = 'none';
}
if (openButton) {
    onLang(function() {
        document.getElementById('gate-expires').textContent = t('gate_expires', {date: formatDate(<?php print $secret['expires']; ?>)});
    });
    openButton.onclick = function() {
        openButton.disabled = true;
        var request = new XMLHttpRequest();
        request.open('POST', window.location.pathname + '?s=' + encodeURIComponent(SECRET_ID) + '&open', true);
        request.onload = function() {
            if (request.status !== 200) {
                showGateError(request.responseText, request.responseText ? '' : 'open_failed');
                openButton.parentNode.style.display = 'none';
                return;
            }
            var data = JSON.parse(request.responseText);
            textarea.value = data.text;
            document.body.classList.add('opened');
            document.body.classList.toggle('has-files', data.files.length > 0);
            onLang(function() {
                secretInfo.textContent = data.files.length ? t('opened_info_files', {time: formatTime(data.until)}) : t('opened_info');
            });
            document.getElementById('files-count').textContent = data.files.length;
            var list = document.getElementById('files-list');
            data.files.forEach(function(file, index) {
                var row = fileRow(file.name, formatSize(file.size), '#');
                var download = document.createElement('button');
                download.type = 'button';
                download.className = 'file-action';
                tr(download, 'download', null, 'title');
                download.innerHTML = DOWNLOAD_ICON;
                row.querySelector('.actions').appendChild(download);
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
onLang(function() {
    cliList.textContent = '';
    [
        [t('cli_upload_text'), t('placeholder_command') + ' | curl --data-binary @- ' + noteUrl, ' | curl --data-binary @- ' + noteUrl],
        [t('cli_upload_file'), 'curl -T ' + t('placeholder_file') + ' ' + noteUrl + '/'],
        [t('cli_download_file'), 'curl -O ' + noteUrl + '/' + t('placeholder_file')]
    ].forEach(function(item) {
        var label = document.createElement('span');
        label.textContent = item[0];
        var code = document.createElement('code');
        code.textContent = item[1];
        code.title = item[1];
        var copy = document.createElement('button');
        copy.type = 'button';
        copy.className = 'file-action';
        copy.title = t('copy_command');
        copy.innerHTML = COPY_ICONS;
        // The whole row copies; the button shows the feedback
        label.onclick = code.onclick = copy.onclick = function() {
            copyBlock(item[2] || item[1], copy);
        };
        cliList.appendChild(label);
        cliList.appendChild(code);
        cliList.appendChild(copy);
    });
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
if (/Mac|iPhone|iPad/.test(navigator.platform)) {
    document.querySelector('#attach .mod-key').textContent = '⌘';
}
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
// Files pasted with Ctrl+V become attachments; plain text pastes are left alone.
document.addEventListener('paste', function(e) {
    var data = e.clipboardData;
    if (!data || !data.files.length || data.types.indexOf('text/plain') !== -1) {
        return;
    }
    e.preventDefault();
    var stamp = new Date().toISOString().replace(/\D/g, '').slice(0, 14);
    uploadFiles(Array.prototype.map.call(data.files, function(file, i) {
        // Screenshots arrive as "image.png": give them a unique name so they don't overwrite each other.
        if (!/^image\.\w+$/.test(file.name)) {
            return file;
        }
        var ext = file.name.split('.').pop();
        return new File([file], 'pasted-' + stamp + (i ? '-' + i : '') + '.' + ext, {type: file.type});
    }));
});
showModified();
updateStatus();
langCallbacks.push(updateStatus, renderFiles);
textarea.addEventListener('input', updateStatus);

// Only visible while in conflict.
refreshButton.onclick = function() {
    if (!confirm(t('confirm_refresh'))) {
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
        alert(t('clear_conflict'));
        return;
    }

    // An empty text makes the autosave loop delete the note on the server
    textarea.value = '';
    updateStatus();
    updateGutter();
};
// Minimal QR encoder for the secret link: byte mode, error correction level M, versions 1-9 (up to 180 bytes)
// and a fixed mask 0 (no penalty scoring, scanners read any mask). Returns rows of booleans (true = dark),
// or null if the text does not fit.
function qrMatrix(text) {
    // Per version: total codewords, error correction codewords per block, blocks, alignment pattern centres
    var TOTAL = [0, 26, 44, 70, 100, 134, 172, 196, 242, 292];
    var EC = [0, 10, 16, 26, 18, 24, 16, 18, 22, 22];
    var BLOCKS = [0, 1, 1, 1, 2, 2, 4, 4, 4, 5];
    var ALIGN = [0, [], [6, 18], [6, 22], [6, 26], [6, 30], [6, 34], [6, 22, 38], [6, 24, 42], [6, 26, 46]];
    var bytes = Array.from(new TextEncoder().encode(text));
    var version = 1;
    while (version <= 9 && bytes.length + 2 > TOTAL[version] - EC[version] * BLOCKS[version]) {
        version++;
    }
    if (version > 9) {
        return null;
    }
    var i, j, total = TOTAL[version], ecLen = EC[version], numBlocks = BLOCKS[version];
    var dataLen = total - ecLen * numBlocks;

    // Data codewords: mode, length, bytes, terminator, then the standard padding
    var bits = [];
    function push(value, length) {
        for (var n = length - 1; n >= 0; n--) {
            bits.push((value >>> n) & 1);
        }
    }
    push(4, 4);
    push(bytes.length, 8);
    bytes.forEach(function(b) { push(b, 8); });
    push(0, Math.min(4, dataLen * 8 - bits.length));
    push(0, (8 - bits.length % 8) % 8);
    var data = [];
    for (i = 0; i < bits.length; i += 8) {
        data.push(parseInt(bits.slice(i, i + 8).join(''), 2));
    }
    for (var pad = 0xEC; data.length < dataLen; pad ^= 0xEC ^ 0x11) {
        data.push(pad);
    }

    // Reed-Solomon error correction per block over GF(256), then interleave the blocks
    function mul(x, y) {
        var z = 0;
        for (var n = 7; n >= 0; n--) {
            z = (z << 1) ^ ((z >>> 7) * 0x11D);
            z ^= ((y >>> n) & 1) * x;
        }
        return z;
    }
    var divisor = [], root = 1;
    for (i = 0; i < ecLen - 1; i++) {
        divisor.push(0);
    }
    divisor.push(1);
    for (i = 0; i < ecLen; i++) {
        for (j = 0; j < ecLen; j++) {
            divisor[j] = mul(divisor[j], root);
            if (j + 1 < ecLen) {
                divisor[j] ^= divisor[j + 1];
            }
        }
        root = mul(root, 2);
    }
    var numShort = numBlocks - total % numBlocks, shortLen = Math.floor(total / numBlocks);
    var blocks = [], start = 0;
    for (i = 0; i < numBlocks; i++) {
        var end = start + shortLen - ecLen + (i < numShort ? 0 : 1);
        var block = data.slice(start, end);
        start = end;
        var rem = divisor.map(function() { return 0; });
        block.forEach(function(b) {
            var factor = b ^ rem.shift();
            rem.push(0);
            divisor.forEach(function(c, n) { rem[n] ^= mul(c, factor); });
        });
        if (i < numShort) {
            block.push(0);
        }
        blocks.push(block.concat(rem));
    }
    var codewords = [];
    for (i = 0; i < blocks[0].length; i++) {
        for (j = 0; j < numBlocks; j++) {
            if (i !== shortLen - ecLen || j >= numShort) {
                codewords.push(blocks[j][i]);
            }
        }
    }

    // Function patterns: timing, finders, alignment, format and version information
    var size = version * 4 + 17, modules = [], fixed = [];
    for (i = 0; i < size; i++) {
        modules.push(new Array(size).fill(false));
        fixed.push(new Array(size).fill(false));
    }
    function set(x, y, dark) {
        modules[y][x] = dark;
        fixed[y][x] = true;
    }
    for (i = 0; i < size; i++) {
        set(6, i, i % 2 === 0);
        set(i, 6, i % 2 === 0);
    }
    [[3, 3], [size - 4, 3], [3, size - 4]].forEach(function(c) {
        for (var dy = -4; dy <= 4; dy++) {
            for (var dx = -4; dx <= 4; dx++) {
                var d = Math.max(Math.abs(dx), Math.abs(dy)), x = c[0] + dx, y = c[1] + dy;
                if (x >= 0 && x < size && y >= 0 && y < size) {
                    set(x, y, d !== 2 && d !== 4);
                }
            }
        }
    });
    var align = ALIGN[version], last = align.length - 1;
    align.forEach(function(ax, a) {
        align.forEach(function(ay, b) {
            if ((a === 0 && b === 0) || (a === 0 && b === last) || (a === last && b === 0)) {
                return;
            }
            for (var dy = -2; dy <= 2; dy++) {
                for (var dx = -2; dx <= 2; dx++) {
                    set(ax + dx, ay + dy, Math.max(Math.abs(dx), Math.abs(dy)) !== 1);
                }
            }
        });
    });
    // Level M (00) with mask 0 has all-zero format data, so its BCH bits are zero too and only the XOR mask remains
    var format = 0x5412;
    function formatBit(n) {
        return ((format >>> n) & 1) === 1;
    }
    for (i = 0; i <= 5; i++) {
        set(8, i, formatBit(i));
    }
    set(8, 7, formatBit(6));
    set(8, 8, formatBit(7));
    set(7, 8, formatBit(8));
    for (i = 9; i < 15; i++) {
        set(14 - i, 8, formatBit(i));
    }
    for (i = 0; i < 8; i++) {
        set(size - 1 - i, 8, formatBit(i));
    }
    for (i = 8; i < 15; i++) {
        set(8, size - 15 + i, formatBit(i));
    }
    set(8, size - 8, true);
    if (version >= 7) {
        var r = version;
        for (i = 0; i < 12; i++) {
            r = (r << 1) ^ ((r >>> 11) * 0x1F25);
        }
        var info = (version << 12) | r;
        for (i = 0; i < 18; i++) {
            var dark = ((info >>> i) & 1) === 1, a = size - 11 + i % 3, b = Math.floor(i / 3);
            set(a, b, dark);
            set(b, a, dark);
        }
    }

    // Codewords in the zigzag column pairs, masked with (x + y) % 2 === 0
    var n = 0;
    for (var right = size - 1; right >= 1; right -= 2) {
        if (right === 6) {
            right = 5;
        }
        for (var vert = 0; vert < size; vert++) {
            for (j = 0; j < 2; j++) {
                var x = right - j, y = ((right + 1) & 2) === 0 ? size - 1 - vert : vert;
                if (fixed[y][x]) {
                    continue;
                }
                var bit = n < codewords.length * 8 && ((codewords[n >>> 3] >>> (7 - (n & 7))) & 1) === 1;
                n++;
                modules[y][x] = bit !== ((x + y) % 2 === 0);
            }
        }
    }
    return modules;
}

// SVG of a QR matrix with the 4-module quiet zone
function qrSvg(modules) {
    var size = modules.length + 8, path = '';
    modules.forEach(function(row, y) {
        row.forEach(function(dark, x) {
            if (dark) {
                path += 'M' + (x + 4) + ' ' + (y + 4) + 'h1v1h-1z';
            }
        });
    });
    return '<svg viewBox="0 0 ' + size + ' ' + size + '" shape-rendering="crispEdges"><path d="' + path + '"/></svg>';
}

// Make it a secret: once everything typed here is saved, the server moves the note to a one-time link.
var secretQr = document.getElementById('secret-qr');
var secretQrRow = document.getElementById('secret-qr-row');
var secretDialog = document.getElementById('secret-dialog');
var secretAsk = document.getElementById('secret-ask');
var secretDone = document.getElementById('secret-done');
var secretError = document.getElementById('secret-error');
var secretChoices = secretAsk.querySelectorAll('[data-hours]');
var secretCopy = document.getElementById('secret-copy');
secretCopy.innerHTML = COPY_ICONS + '<span class="copy-label" data-i18n="copy_link"></span><span class="copied-label" data-i18n="copied"></span>';
secretCopy.querySelector('.copy-label').textContent = t('copy_link');
secretCopy.querySelector('.copied-label').textContent = t('copied');

var secretExpiresAt = 0;
function showSecretExpires() {
    if (secretExpiresAt) {
        document.getElementById('secret-expires').textContent = t('secret_expires_on', {date: formatDate(secretExpiresAt)});
    }
}
langCallbacks.push(showSecretExpires);

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
        showSecretError(conflict ? t('secret_conflict') : t('not_saved', {reason: saveError}));
    } else if (!saving && !uploads.length && content === textarea.value) {
        callback();
    } else if ((tries || 0) < 40) {
        setTimeout(function() {
            whenSaved(callback, (tries || 0) + 1);
        }, 250);
    } else {
        showSecretError(t('still_saving'));
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
            showSecretError(request.responseText || t('make_failed'));
            return;
        }
        var data = JSON.parse(request.responseText);
        var link = window.location.origin + window.location.pathname.replace(/[^\/]*$/, '') + '?s=' + data.id + '#' + data.key;
        document.getElementById('secret-link').textContent = link;
        secretExpiresAt = data.expires;
        showSecretExpires();
        secretCopy.onclick = function() {
            copyBlock(link, secretCopy);
        };
        var qrMatrixForLink = qrMatrix(link);
        secretQr.hidden = true;
        secretQr.innerHTML = '';
        secretQrRow.style.display = qrMatrixForLink ? '' : 'none';
        document.getElementById('secret-qr-button').onclick = function() {
            secretQr.innerHTML = qrSvg(qrMatrixForLink);
            secretQr.hidden = false;
            secretQrRow.style.display = 'none';
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
        showSecretError(t('network_retry'));
    };
    request.send('base=' + encodeURIComponent(version));
}

document.getElementById('make-secret').onclick = function() {
    if (!textarea.value.trim() && !files.length && !uploads.length) {
        alert(t('write_first'));
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
