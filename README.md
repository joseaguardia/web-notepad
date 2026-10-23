# Web Notepad

A web notepad in a single PHP file: open a URL, type, and it saves itself.
Fork of [pereorga/minimalist-web-notepad](https://github.com/pereorga/minimalist-web-notepad).

![Screenshot](screenshot.png)

## What this fork adds

- **Live sync across devices**: changes made on another device (or uploaded with `curl`) show up within a couple of seconds, keeping your cursor in place. Polling pauses while the tab is hidden.
- **Safe saving across devices**: if you edit on two devices at the same time, the note is not overwritten; autosave pauses and an amber refresh button appears so you can load the server version.
- **Save status indicator**: a dot next to the time shows whether the note is saved (green), pending (amber) or in conflict (red).
- **Action buttons** on the right side of the editor (labels appear on hover):
  - **copy all**: copies the whole note to the clipboard.
  - **clear**: empties the note (and deletes it on the server) without asking.
- **Copy by block**: every block of text separated by a blank line gets an icon on the left that copies it to the clipboard; hovering it highlights the block.
- **Footer** with the last saved time and a ready-to-copy `curl` command to upload to the current note.
- **Dark theme** and a larger font.

## Installation

1. Copy `index.php`, `.htaccess` and `favicon.ico` to your server (PHP 7+).
2. Make the notes directory writable by the web server. It is `/var/lib/notas` by default; change it with `$save_path` at the top of `index.php` (preferably outside the document root).
3. Set up URL rewriting so that `/<note>` reaches `index.php?note=<note>`:

**Apache**: with `mod_rewrite` enabled and `AllowOverride` allowed, the included `.htaccess` is enough.

**Nginx** (project under `/notes/`):

```
location ~* ^/notes/([a-zA-Z0-9_-]+)$ {
    try_files $uri /notes/index.php?note=$1&$args;
}
```

## Command-line usage

```
# Read a note (curl and wget get plain text)
curl https://example.com/notes/test

# Save the output of a command to a note
ls -la | curl --data-binary @- https://example.com/notes/test

# Save a file to a note
curl --data-binary @/etc/hosts https://example.com/notes/hosts
```

Use `--data-binary` rather than `--data`, which strips newlines. Saving from the command line always overwrites the note, and sending an empty body deletes it.

When reading with `?raw`, curl or wget, the response includes the `X-Note-Version` (content hash) and `X-Note-Mtime` (last modified, Unix timestamp) headers.

## License

Copyright 2012 Pere Orga <pere@orga.cat>. Changes in this fork are released under the same license.

Licensed under the [Apache License 2.0](http://www.apache.org/licenses/LICENSE-2.0).
