# Web Notepad

A web notepad in a single PHP file: open a URL, type, and it saves itself. Drop files on it to share them between devices.
Fork of [pereorga/minimalist-web-notepad](https://github.com/pereorga/minimalist-web-notepad).

<picture>
  <source media="(prefers-color-scheme: light)" srcset="screenshot-light.png">
  <img alt="Screenshot" src="screenshot.png">
</picture>

## What this fork adds

- **File attachments**: drag files anywhere onto the page or use the **attach file** button (up to 5 MB each). The first file splits the screen and opens a **Files** panel on the right (below the editor on narrow screens) to download or delete each file, plus an **empty** button that deletes all of them and keeps the text.
- **Live sync across devices**: text and file changes made on another device (or uploaded with `curl`) show up within a couple of seconds, keeping your cursor in place. Polling pauses while the tab is hidden.
- **Safe saving across devices**: if you edit on two devices at the same time, the note is not overwritten; autosave pauses and an amber refresh button appears so you can load the server version.
- **Save status indicator**: a dot next to the time shows whether the note is saved (green), pending (amber) or in conflict (red).
- **Action buttons** on the right side of the editor (labels appear on hover):
  - **attach file**: picks files to upload (also works on mobile, where there is no drag and drop).
  - **copy all**: copies the whole note to the clipboard.
  - **clear**: empties the note (and deletes it on the server) without asking.
- **Copy by block**: every block of text separated by a blank line gets an icon on the left that copies it to the clipboard; hovering it highlights the block.
- **Footer** with ready-to-copy `curl` commands to upload text, upload a file and download a file for the current note, a link to this repository with the app version, and the last saved time.
- **Per-file buttons** in the Files panel: download the file, or copy its `curl` download command.
- **Light and dark themes** based on the brand palette: follows the browser preference, with a sun/moon button in the footer to switch it (remembered per browser). Also a larger font.
- **Docker image and Kubernetes manifests**: non-root, read-only container, configured with environment variables.

## Installation

### Docker

The image (`Dockerfile`) is based on the official `php:8.5.12-apache` image, pinned to a release that fixes the php2shell vulnerabilities (CVE-2026-109618, CVE-2026-109619). Apache runs as `www-data` on port 8080 and the container works with a read-only root filesystem. Notes and attachments are stored in the `/var/lib/notas` volume.

1. Clone the repository and enter it: `git clone https://github.com/joseaguardia/web-notepad.git && cd web-notepad`.
2. Build and start it with Compose (read-only container, `notes` volume, port 8080; set `PORT` to change it):

   ```
   docker compose up --detach --build
   ```

   Or without Compose:

   ```
   docker build --pull --tag web-notepad . && docker run --detach --name web-notepad --publish 8080:8080 --volume notes:/var/lib/notas --restart unless-stopped web-notepad
   ```

3. Open `http://localhost:8080/`, which redirects to a new note with a random name.

With Podman, replace `docker` with `podman` in the commands above.

Settings are environment variables (in `docker-compose.yml`, or `--env` with `docker run`):

| Variable | Default | Description |
|---|---|---|
| `MAX_FILE_SIZE_MB` | `5` | Maximum size of each attachment |
| `MAX_TOTAL_SIZE_MB` | `100` | Maximum size of all attachments of all notes together |
| `PHP_POST_MAX_SIZE` | `8M` | PHP request body limit: keep it a bit larger than `MAX_FILE_SIZE_MB` |
| `NOTES_PATH` | `/var/lib/notas` | Directory for notes and attachments |

Useful commands:

- Logs: `docker logs --follow web-notepad`
- Update after pulling changes: `docker compose up --detach --build`
- Stop (notes are kept in the volume): `docker compose down`; add `--volumes` to delete the notes too.

Nothing is deleted automatically. For temporary notes, add a cron job on the host, e.g. `0 19 * * * docker exec web-notepad find /var/lib/notas -mindepth 1 -delete`.

Behind a reverse proxy, allow request bodies a bit larger than `MAX_FILE_SIZE_MB` (e.g. `client_max_body_size 8m;` in Nginx).

### Kubernetes

The manifests in `deploy/k8s/` use kustomize (built into `kubectl`) and target ingress-nginx:

| File | Resource |
|---|---|
| `namespace.yaml` | `web-notepad` namespace |
| `pvc.yaml` | 1 GiB `ReadWriteOnce` volume for notes and attachments |
| `deployment.yaml` | Single replica, non-root, read-only root filesystem, probes and resource limits |
| `service.yaml` | ClusterIP service on port 80 |
| `ingress.yaml` | Ingress with `proxy-body-size: 8m` (ingress-nginx allows only 1 MB by default) |
| `cronjob.yaml` | Empties the volume every day at 19:00 Europe/Madrid (remove it from `kustomization.yaml` to keep notes) |

1. Build the image and push it to your registry:

   ```
   docker build --pull --tag registry.example.com/web-notepad:1.4 . && docker push registry.example.com/web-notepad:1.4
   ```

2. Set the image in `kustomization.yaml` (`images`), the host in `ingress.yaml` (plus TLS if needed) and, optionally, the storage size in `pvc.yaml` and the limits in the `env` of `deployment.yaml`.
3. Apply and check:

   ```
   kubectl apply --kustomize deploy/k8s && kubectl --namespace web-notepad rollout status deployment/web-notepad
   ```

Keep a single replica: saves are serialized with a file lock in the volume, which is `ReadWriteOnce`. The cleanup CronJob runs on the same node as the app pod for the same reason.

### Manual

1. Copy `index.php`, `.htaccess` and `favicon.ico` to your server (PHP 7+).
2. Make the notes directory writable by the web server. It is `/var/lib/notas` by default; change it with `$save_path` at the top of `index.php` (preferably outside the document root). Attachments are stored there too, in a `<note>.files/` directory per note.
3. Set up URL rewriting so that `/<note>` reaches `index.php?note=<note>`, keeping the query string:

**Apache**: with `mod_rewrite` enabled and `AllowOverride` allowed, the included `.htaccess` is enough.

**Nginx** (project under `/notes/`):

```
location ~* ^/notes/([a-zA-Z0-9_-]+)$ {
    try_files $uri /notes/index.php?note=$1&$args;
}

# PUT /notes/<note>/<file> uploads an attachment (curl --upload-file <file> https://example.com/notes/<note>/),
# GET downloads it (curl --remote-name https://example.com/notes/<note>/<file>)
location ~* ^/notes/(?<note>[a-zA-Z0-9_-]+)/(?<file>[^/]+)$ {
    if ($request_method ~ ^(PUT|POST)$) {
        rewrite ^ /notes/index.php?note=$note&upload=$file last;
    }
    try_files $uri /notes/index.php?note=$note&file=$file&$args;
}
```

### Attachment settings

The limits are at the top of `index.php`: `$max_file_size` (5 MB per file) and `$max_total_size` (100 MB for all the attachments of all notes together).

Files are sent as the raw request body, so PHP's `file_uploads` can stay disabled. The web server and PHP must still accept request bodies a bit larger than `$max_file_size`:

- PHP: `post_max_size` (8 MB by default, enough for 5 MB files).
- Nginx: `client_max_body_size` (1 MB by default, so it must be raised).

### Security notes

- Attachments are only served through `index.php`, always as a download (`Content-Disposition: attachment`) with `X-Content-Type-Options: nosniff` and a sandboxing `Content-Security-Policy`, so an uploaded HTML or SVG file can never run scripts on your domain.
- File names are reduced to `[a-zA-Z0-9._-]`, so paths like `../../etc/passwd` end up as a plain file name inside the note's directory.
- There is no authentication: anyone who knows a note's URL can read it and upload files to it. Keep `$max_total_size` low and consider deleting notes periodically, e.g. with a daily cron job: `find /var/lib/notas -mindepth 1 -delete`.

## Command-line usage

```
# Read a note (curl and wget get plain text)
curl https://example.com/notes/test

# Save the output of a command to a note
ls -la | curl --data-binary @- https://example.com/notes/test

# Save a file's contents as the note text
curl --data-binary @/etc/hosts https://example.com/notes/hosts

# Attach a file to a note (with a trailing slash curl appends the local file name)
curl --upload-file report.pdf https://example.com/notes/test/

# Attach a file under a different name
curl --upload-file report.pdf 'https://example.com/notes/test?upload=2026-report.pdf'

# Download an attachment (saved as report.pdf in the current directory)
curl --remote-name https://example.com/notes/test/report.pdf

# List the attachments of a note (JSON)
curl 'https://example.com/notes/test?files'
```

Use `--data-binary` rather than `--data`, which strips newlines. Saving text from the command line always overwrites the note, and sending an empty body deletes it. Uploading a file with the same name as an existing attachment replaces it.

When reading with `?raw`, curl or wget, the response includes the `X-Note-Version` (content hash) and `X-Note-Mtime` (last modified, Unix timestamp) headers.

## License

Copyright 2012 Pere Orga <pere@orga.cat>. Changes in this fork are released under the same license.

Licensed under the [Apache License 2.0](http://www.apache.org/licenses/LICENSE-2.0).
