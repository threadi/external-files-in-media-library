---
name: external-files-in-media-library
description: Help someone who runs WordPress with the plugin "External files in Media Library" add external files, connect services, offload (export) their media, deliver files privately, restore exported files and find out why a file is missing, not exported or not shown. Not for developing the plugin.
---

# External files in Media Library (site operators)

Guidance for AI assistants helping **someone who runs WordPress with the plugin "External files in Media Library"** (configuration, daily use, troubleshooting).
This is **not** for developing the plugin. Developers who extend it use the skill `external-files-in-media-library-developer`.

Official docs: https://plugins.thomaszwirner.de/en/external-files-in-the-media-library/
Documentation of all features: the `docs/` folder of the plugin on GitHub (https://github.com/threadi/external-files-in-media-library/tree/master/docs), e.g. `features.md` ("What is possible?").

## What the plugin does

1. **Use external files in the media library.** Files are added by URL or chosen from a service (HTTP, FTP, SFTP, a local directory of the server, Dropbox, YouTube, Vimeo, Imgur, another WordPress website, ZIP archives, lists of URLs). With free extensions from GitHub also AWS S3 and S3-compatible storages (Hetzner, IONOS, Wasabi, Cloudflare R2, …), Google Cloud Storage, Google Drive and WebDAV (e.g. Nextcloud). The files stay where they are and are used like local files.
2. **External sources.** A directory of a service saved with its access data. It can be synchronized on a schedule (new, changed and deleted files) and used as target for the export.
3. **Export (offloading).** New uploads and existing files are copied to an external source and then used from there, to save space on the hosting. Images are exported with their sizes and their original image if the service supports it. Contents which still contain the old local URLs keep working.
4. **Restore.** Exported files can be brought back as normal local files at their original path at any time.
5. **Migration** from WP Offload Media, Advanced Media Offloader, Stowaway and Offload Media – Cloud Storage without uploading the files again.
6. **Private files**, access data as constants in `wp-config.php`, protection of copies of the website (staging).

## Mental model

```
Service (e.g. FTP, Dropbox, S3)
   └── External source (a directory + access data)
          ├── import / synchronization ─► external files in the media library
          └── export target ◄──────────── uploads and existing local files
                                           (then used as external files)
Delivery to visitors:  via the proxy of the website (default)  or  directly from the external source
```

## Where things are in the backend

- **Media → "Add external files"**: tiles of all services, the quickstart, the list of external sources ("Your external sources").
- **Quickstart** (tile on the same page): guides through the common goals and configures everything, incl. the installation of a missing extension.
- **Settings → External files in Media Library**, tabs:
  - *General Settings* – import and dialog
  - *Services* – all services and their settings, which constants are used
  - *Delivery* – per file type (images, videos, PDF …) and the proxy
  - *Export* – "Enable export", "Choose your primary external source", "Do not delete local files", "Export in background", "Use folders for exported files", buttons "Export existing files", "Export sizes of exported images", "Restore exported files"
  - *Synchronization*
  - *Advanced* – files, plugin, abilities, miscellaneous, permissions
  - *Status* – **Logs**, queue, statistics
  - *Integrations* – only if WooCommerce, Polylang, Instant Images … are active
- **Command palette** (`Ctrl + K` / `Cmd + K`, WordPress 6.9+): finds services, external sources and single settings.

The export of an external source is switched on in the list of external sources (Media → "Add external files" → "Your external sources"), the global switch is in the tab *Export*.

## Delivery and privacy

- By default files are delivered via the **proxy** of the website (`/emlproxy/…`). Visitors and search engines only see the URL of the website. This matters for the GDPR: no external URL is embedded in the pages.
- The proxy can be disabled per file type (*Delivery*). Only then are files and image sizes loaded directly from the external source.
- **Private files** (option "Save files privately" of an external source) are always delivered via the proxy or via links with limited validity, never with a public URL. Their image sizes are delivered via the proxy, too.

## Export: what to expect

- The **primary external source** is used first. If it cannot be used (e.g. its extension is deactivated, export switched off for it, access data defined for another server), nothing is exported and the files stay local; a hint in the backend names the reason.
- Uploads are exported right away, or with "Export in background" via the queue.
- "Do not delete local files" keeps local copies after the export.
- Images get a `srcset` with their exported sizes. WordPress does not create sizes larger than the original, so a small image has only a few sizes.
- On a **copy of the website** (staging) nothing is exported or deleted on external sources. A hint offers to use the copy as the main website.
- Before deactivating the plugin, a dialog offers to restore the exported files. On uninstall and reset, exported files are restored and never deleted.

## When helping the user

**Do**

- Ask whether the file was **imported** from an external source or **exported** from the media library; the answers differ.
- Look into the log first (*Status → Logs*). For more details set *Advanced → "Log-mode"* to a more detailed level, repeat the action and read the log again.
- Check whether the user looks at the backend or at the public page, and whether a page cache or CDN shows an old version.
- Explain that the proxy is intended (privacy), before suggesting to disable it.

**Don't**

- Suggest disabling the proxy only to "see the real URL" – it changes what visitors receive and has privacy consequences.
- Delete files, attachments, external sources, logs or queue entries, switch off the export or restore files without asking first. Name what would change and wait for the answer in this conversation. Deleting an exported attachment deletes the file on the external sources.
- Edit the PHP files of the plugin (`wp-content/plugins/external-files-in-media-library/`); updates overwrite such changes.
- Invent setting names; use the labels of the backend or the documentation.

## Quick troubleshooting

**"The file is not shown / broken image"**
1. Is the file available? (media library: column / info box of the file, *Status → Logs*, `wp eml check`)
2. Is the file type allowed in the settings (*Advanced → Files*)?
3. Proxy: is the proxy cache writable? Does the proxy URL (`/emlproxy/…`) return the file?
4. Access data of the external source still valid (e.g. expired Dropbox token)?

**"My upload is not exported"**
1. Export enabled globally and for the external source?
2. Primary external source usable? (hint in the backend, log)
3. Copy of the website detected? (hint in the backend, `wp eml site`)
4. "Export in background": the file waits in the queue (*Status → Queue*).
5. File type or size excluded by the export settings?

**"No srcset / no sizes"**
1. Image smaller than the sizes? (only smaller sizes exist)
2. Service supports sizes? (local directory, FTP, Dropbox, S3, GCS, WebDAV, Google Drive)
3. Exported before sizes were supported → *Export → "Export sizes of exported images"*.

**"I want to stop offloading"**
→ *Export → "Restore exported files"*: files come back as local files at their original path, the export is switched off; the copies on the external sources are kept or deleted as chosen.

## AI / automation (if available)

The plugin registers **abilities** with the prefix `external-files-in-media-library/` (category `efml`), usable via the MCP Adapter. Settings: *Advanced → Abilities* ("Enable abilities", mode **read only** by default; changing abilities only with mode "all").

| Ability | Use it to |
|---------|-----------|
| `list-files`, `get-file` | Find external files and their data |
| `get-log` | Find the reason of a failure (needs `manage_options`) |
| `get-statistics`, `list-services`, `list-sources`, `get-sync-status`, `queue-status` | Look up the state |
| `check-availability` | Check whether files are reachable |
| `add`, `import`, `queue-add`, `queue-process` | Add files (mode "all") |
| `export-file`, `set-export-state` | Export a file, switch the export of a source (mode "all") |
| `switch-to-local`, `switch-to-external`, `reset-thumbnails`, `reset-proxy-cache` | Change the hosting or caches (mode "all") |
| `add-source`, `delete-source`, `list-directory`, `sync-source`, `delete-synced-files` | Manage external sources (mode "all") |
| `delete`, `queue-cleanup` | Delete (ask first, mode "all") |

Access data are never returned. Save them once as external source and use its `source_id`, so they never pass the conversation.

WP-CLI: `wp eml` lists the commands (import, delete, check, export, export_sizes, localize, reset_plugin, site, migration …), see `docs/cli.md`.
