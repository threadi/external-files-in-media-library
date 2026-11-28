# Central media libraries in a multisite

In a multisite you can use the media library of one website as central media library for other websites of the network. These websites then use the same files. With groups, a network could have several central media libraries, e.g. one per language or per client.

## Set up

Network administration → Settings → "External files". Create a backup of everything beforehand.

For a first central media library for all websites you could also use the [quickstart](setup.md) (goal "Central media library for your network"). It asks only for the website with the central media library and creates one group with all other websites.

* **Groups:** each group has a name and a website with its central media library. Add a group in the last row of the table.
* **Websites:** choose the group of each website. Each website belongs to one group at most. A central media library belongs to its own group only.
* **New websites join:** the first group (default), no group or a chosen group.

Afterward, for the websites of a group:

* Uploads are exported to the central media library of the group. The website saves a reference to it.
* The files of the central media library are available in each website of the group as external files.
* The synchronization runs daily, as changes are passed immediately (see below). The files are copied on the server, not loaded via HTTP. Unchanged files are skipped.

With "Hide the options of this plugin" the users of the websites of the groups do not see any options of this plugin.

The central media library of versions before 6.1.0 is used as first group with all websites.

## Local files of the websites

Files, which a website had before it joined its group, stay local files of the website. The overview shows their number ("Local files"). With "Transfer" (or "Transfer the local files of all websites") they are exported to the central media library in the background. Files with the same content as an existing file are linked to it (see below).

## What arrives in the websites

Changes in a central media library are passed to the websites of its group at the end of the request. What does not fit into the time limit (hook `efml_network_time_limit`, default 10 seconds) is continued via WP Cron.

* **New files** arrive immediately via WP Cron in the websites, which could use them. The regular synchronization is an additional safeguard.
* **Deleted files** are deleted in all websites, also in the website the upload comes from.
* **Caption, description and alternative text** come from the central media library. The title of a file in the websites is its file name, as the proxy uses it in the URL of the file (e.g. `/emlproxy/image.jpg`). If a website changes one of these values, the change applies only to this website and is kept, also if the central file changes. The own changes could be discarded in the dialog of the file ("Discard the changes and use the values of the central media library").
* **Changes of the origin:** with the option "Changes of caption, description and alternative text in the website, from which a file comes, apply to all websites" (disabled by default) the changes of the website, in which a file has been uploaded, are made in the central media library and passed to all websites.
* **Replaced or edited files** (e.g. cropped) are used in all websites. Their cached files and sizes are loaded again.
* **Folders** of plugins for folders are not passed: each website has its own folders.

## Uploads with the same content

If a website uploads a file, which exists already in the central media library with the same content, the existing file is used instead of adding it again (option "Use an existing file of the central media library for an upload with the same content", enabled by default). The website could use the file then. It is not an own upload of the website: deleting it in the website does not delete the existing file.

With this option, the synchronization also links local files of a website with the files of the same content in the central media library, instead of adding these again. This happens e.g. if a website joins a group with files, which the central media library has already (like a former central media library after a move). The attachments keep their IDs, own captions, descriptions and alternative texts are kept.

## Which websites could use a file

In the central media library you choose for each file who could use it ("Available for"):

* all websites,
* only the selected websites,
* all websites except the selected ones.

For multiple files at once use the bulk action "Change availability in the network" in the list view.

The website, from which a file comes, could always use it. If a website could not use a file anymore, it is deleted there, **also if it is used in posts**.

New uploads get a default from the network administration ("New uploads are available for"): all websites or only the website, in which the file has been uploaded.

Super administrators, administrators and the roles chosen in the network administration could change it for their uploads below the upload field and change the availability of the files in the central media library (field, bulk action). The roles are chosen as default and differently for single websites ("Could choose the availability"). The other users do not see these options, their uploads get the default.

## Who could delete files

A file of a central media library is always deleted in all websites of its group. In the network administration you choose what websites could delete, as default and differently for single websites:

* **No file:** no file of the central media library, not even own uploads.
* **Only own uploads:** only files uploaded in this website. This is the default and matches the behaviour before version 6.1.0.
* **All files:** every file of the central media library.

Users of the central media library could delete every file. If the deletion is not allowed, the media library does not show the delete action. Before a deletion in the whole network, the media library asks for confirmation. The confirmation names how many websites have the file and in how many posts it is used.

## Usage in the network

In the dialog of a file "Usage in the network" → "Show usage" shows which websites have the file and in how many posts it is used there. A file counts as used if it is the featured image or if the content contains it with its ID, e.g. in the image, gallery, cover or file block.

The filter of the media library in the websites shows "Own uploads" or the files "From the central media library".

## Unused files and duplicates

The usage of the files of each central media library is checked daily in the background. In its media library, the filter "Unused in the network" shows the files, which no content of the network uses (as of the last check). Nothing is deleted automatically.

Media → "Duplicates in the network" in a central media library shows files with the same content. "Merge" keeps the chosen file: the websites use it instead of the others, also where these are used in contents (their attachments stay). The others are deleted in the central media library afterward. A file, which is used in contents of the central media library itself, is not merged.

## Overview

The tab "Overview" on the page in the network administration shows for each website of each group: the number of files, own uploads, errors of the last 7 days, overdue tasks of WP Cron and the next synchronization. Site Health of each central media library reports websites, whose WP Cron does not run.

## Moving a central media library

In the settings of the network, "Move the central media library to" moves the central media library of a group to a website of the group or to a website without group. In the background:

1. the files are added to the new website, its copies of them become its own files,
2. the new website becomes the central media library, new uploads are exported to it,
3. the copies in the websites use the files of the new central media library (their attachments stay, so their usage in contents is kept).

The files in the former central media library are kept. It does not belong to the group afterward. The groups could not be changed during the move. The overview shows its progress.

## Changed domain

The websites save the addresses of the files of the central media library. A search and replace in the whole database (e.g. `wp search-replace`) changes them with the domain. Site Health of each central media library reports websites, which use other addresses. "Use the actual addresses" in the overview or `wp eml network fix-addresses` creates the addresses from the IDs of the files again.

## Summary via e-mail

With "Send a weekly summary of problems …" (disabled by default) the e-mail address of the network administration gets a summary of errors, websites without running WP Cron and wrong addresses once a week. It is sent only if there are problems.

## Leaving a group

If a website leaves its group (other group, no group or its group is removed), its export and synchronization stop at once. In the background:

* its own uploads and the files used in its contents become normal local files (option "A website leaving its group": "keeps …", default) or are removed, too ("loses all files …"),
* the other files of the central media library are removed from the website.

The files in the central media library are kept. Own uploads belong to the central media library afterward.

## Deleted websites

The uploads of a deleted website are kept and belong to the central media library (default). With "are deleted in all websites" they are deleted, also where they are used. If a central media library is deleted, its group is removed and its websites leave it.

## Upload space

If the upload space of the websites is limited in the settings of the network, the uploads of a website in the central media library count for this website, not for the central media library (option "Count the uploads of a website in the central media library for its upload space", enabled by default). Without this option the uploads do not count anywhere, as before.

## WP-CLI, REST API and Abilities

* `wp eml network status` shows the groups and the state of their websites.
* `wp eml network join <blog_id> <group_id>` moves a website to a group (0 to leave its group).
* `wp eml network visibility <central> <attachment_id> <all|only|except> [--sites=2,3]` changes the availability of a file.
* `wp eml network usage <central> <attachment_id>` shows the usage of a file.
* `wp eml network sync <blog_id>` runs the synchronization of a website.
* `wp eml network transfer-local [<blog_id>...]` transfers the local files of websites to their central media library.
* `wp eml network unused <central> [--check]` shows the unused files.
* `wp eml network duplicates <central> [--merge]` shows or merges files with the same content.
* `wp eml network move <group_id> <blog_id>` moves the central media library of a group.
* `wp eml network fix-addresses [--dry-run]` uses the actual addresses of the files.
* `wp eml network digest` sends the summary now.
* `wp eml network run` runs the tasks in the background now (leaving websites, moves, migrations).

REST API (namespace `efml/v1`): `GET /network`, `POST /network/site/<blog_id>` with `group`, `POST /network/site/<blog_id>/sync`, `POST /network/<central>/file/<attachment_id>/visibility` with `mode` and `sites`, `GET /network/<central>/file/<attachment_id>/usage`. The network functions need the capability `manage_network_options`, the functions for a file alternatively the right to edit it in the central media library.

Abilities: `network-status`, `network-file-usage` and `network-set-visibility` (with the prefix of this plugin).

## Hooks

* `efml_network_delete_right` changes the right of a website to delete files.
* `efml_network_file_visible` changes whether a website could use a file.
* `efml_network_time_limit` changes the time, which passing changes could use in a request.
* `efml_sync_listing_complete` changes whether a synchronization could delete files, which are not in the list of the external source.
