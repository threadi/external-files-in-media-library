# Quickstart

The quickstart guides you step by step through the most common tasks of this plugin. It creates the external source, checks the connection and sets the needed settings. You can change everything it configures at any time afterwards in the settings and in the list of your external sources.

You find it under Media → "Add External Files" as second tile "Quickstart", in the command palette (`Ctrl + K`, search for "quickstart") and in the help tab "What is possible?". The quickstart is available for users, who are allowed to manage the settings.

## Steps

1. **Choose a goal:** What would you like to do? The goals are described below.
2. **Choose a service:** Only the services, which support the chosen goal, are shown, e.g. only services with export for offloading. Some goals define the service, then this step is skipped. Services from extensions, which are not installed yet (e.g. AWS S3 or Cloudflare R2), are shown with a hint about the extension, if you are allowed to install plugins.
3. **Install the extension (if needed):** If the chosen service needs an extension, the quickstart explains which one and why (see [extensions](extensions.md)). If you confirm the installation, it loads the newest release from GitHub, installs and activates it and shows the result. If this fails, you see the reason and could try it again.
4. **Enter the access data:** The quickstart shows the fields of the service. The expandable help "Where do I find these details?" explains where you get the access data, which permissions they need and what to watch out for, for AWS S3 with a ready-made IAM policy to copy. The same help is shown in the form of the service. Values, which are already defined as constant (e.g. in your `wp-config.php`) or saved in the settings, are not asked again. Connect Dropbox or Google Drive via the button "Connect Dropbox" or "Connect Google Drive", afterwards you return to the quickstart. Google Drive requires its extension in version 1.4.0 or newer for this.
5. **Check the connection and choose the options:** The quickstart checks the connection with your details. If it fails, you see the reason and go back to correct your details. If it works, you choose the options of the goal. They are preselected.
6. **Configure:** The quickstart creates the external source, sets the settings and shows the result of each step.

Each step shows a hint for the [support forum](https://wordpress.org/support/plugin/external-files-in-media-library/) directly above its buttons.

## Goals

The goals are shown in this order:

| Goal | Service | What the quickstart configures |
|---|---|---|
| Embed and synchronize external files | all services with synchronization | enable the synchronization, interval, first synchronization |
| Import local files | local directory of your hosting | import of the directory, optionally copied as real files into your media library |
| Synchronize files from FTP | FTP, FTPS and SFTP | enable the synchronization, interval, first synchronization |
| Offload your media library | all services with export | enable the export, the external source as main export, folders by year and month, optionally "Prevent direct access at the source" and the export of the files already in your media library, connection test |
| Protected downloads | all services, which could prevent direct access | like offloading, "Prevent direct access at the source" is preselected, optionally the downloads of WooCommerce |
| Central media library for your network | none | one website as central media library for all other websites of the multisite, see below. Only shown in a multisite with at least two websites, for users who could manage the network and as long as the network uses no central media library. |
| Switch from another offload plugin | the service of the found bucket | take over the files of the other plugin without uploading them again, see [migration](migration.md). Only shown if such files have been found. |

Large directories are imported or synchronized in the background as usual, the progress is shown in the admin bar.

For offloading and protected downloads, the quickstart offers the option to export the files, which are already in your media library, too. It is disabled by default and shows the number of these files. If you choose it, the files are exported one by one in the background at the end of the quickstart, the admin bar shows the progress. Otherwise only new files are exported: you could export the existing files later via settings → "Export" → "Export existing files" or in the list view of your media library with the bulk action "Export to external source".

## Central media library for your network

In a multisite this goal sets up a [central media library](multisite.md) with the minimal details. It needs no service and no access data, so it has one step after the goal:

* **Website with the central media library:** the main website is preselected. All other websites of the network use its files, their new uploads are saved there.
* **Transfer the files, which the other websites already have** (disabled by default): their local files are transferred to the central media library in the background. Otherwise they stay local files and could be transferred later in the overview of the network.
* **Hide the options of this plugin in the other websites** (disabled by default).
* **Confirmation**, that you have created a backup of your network.

The quickstart creates one group with all websites, like the network administration does it. All further settings (more groups, rights to delete, availability of new uploads, …) use their defaults and could be changed at any time in the network administration under Settings → "External files". At the end the quickstart forwards to the overview of the network. If the network already uses a central media library, the goal is not offered and the first step links to the network administration.

## Access data

The values you enter in the quickstart are saved for your user only for the time of the quickstart, passwords encrypted. They are never sent back to the browser. They are deleted after the quickstart has been run, when the quickstart is opened again and when the plugin is uninstalled. The created external source saves its access data like any other external source.

## For developers

The quickstart uses the library [easy-setup-for-wordpress](https://github.com/threadi/easy-setup-for-wordpress) in version 1.6.0 or newer. Use these hooks to customize it:

* `efml_setup_goals`: change or add goals. Each goal has the keys `label`, `description`, `icon`, `features` (features a service must support, e.g. `export`, `private`, `sync`), `service` (a fixed service or empty), `options`, `defaults` and `action` (`export`, `sync`, `import`, `network` or empty).
* `efml_setup_option_definitions`: change or add options, which the goals could offer.
* `efml_setup_option_available`: define whether an option is offered for a service.
* `efml_setup_apply_option`: save the chosen value of an own option.
* `efml_setup_run_action`: run further tasks at the end of the quickstart.
* `efml_setup_config` and `efml_setup_process_completed_text`: change the configuration and the text at the end.
* `efml_service_oauth_return_url`: the address a service returns to after it has been connected via OAuth.

Services, which are connected via OAuth, return their dialog to connect via `Service_Base::get_setup_connect_dialog()` as long as they are not connected, and forward to `Service_Base::get_oauth_return_url()` after the connection.

Example for an own goal:

```php
add_filter( 'efml_setup_goals', function( array $goals ): array {
    $goals['my-sync'] = array(
        'label'       => 'Synchronize images from my server',
        'description' => 'Embeds the images of an FTP directory and synchronizes them daily.',
        'features'    => array( 'sync' ),
        'service'     => 'ftp',
        'options'     => array( 'sync', 'sync_interval' ),
        'defaults'    => array( 'sync' => 1, 'sync_interval' => 'efml_24hourly' ),
        'action'      => 'sync',
    );
    return $goals;
} );
```
