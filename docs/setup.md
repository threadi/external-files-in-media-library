# Quickstart

The quickstart guides you step by step through the most common tasks of this plugin. It creates the external source, checks the connection and sets the needed settings. You can change everything it configures at any time afterwards in the settings and in the list of your external sources.

You find it under Media → "Add External Files" as second tile "Quickstart", in the command palette (`Ctrl + K`, search for "quickstart") and in the help tab "What is possible?". The quickstart is available for users, who are allowed to manage the settings.

## Steps

1. **Choose a goal:** What would you like to do? The goals are described below.
2. **Choose a service:** Only the services, which support the chosen goal, are shown, e.g. only services with export for offloading. Some goals define the service, then this step is skipped.
3. **Enter the access data:** The quickstart shows the fields of the service. Values, which are already defined as constant (e.g. in your `wp-config.php`) or saved in the settings, are not asked again. Connect Dropbox via the button "Connect Dropbox", afterwards you return to the quickstart.
4. **Check the connection and choose the options:** The quickstart checks the connection with your details. If it fails, you see the reason and go back to correct your details. If it works, you choose the options of the goal. They are preselected.
5. **Configure:** The quickstart creates the external source, sets the settings and shows the result of each step.

## Goals

| Goal | Service | What the quickstart configures |
|---|---|---|
| Offload your media library | all services with export | enable the export, the external source as main export, folders by year and month, optionally "Save files privately", connection test |
| Protected downloads | all services with private files | like offloading, "Save files privately" is preselected, optionally the downloads of WooCommerce |
| Embed and synchronize external files | all services with synchronization | enable the synchronization, interval, first synchronization |
| Import local files | local directory of your hosting | import of the directory, optionally copied as real files into your media library |
| Synchronize files from FTP | FTP, FTPS and SFTP | enable the synchronization, interval, first synchronization |

Large directories are imported or synchronized in the background as usual, the progress is shown in the admin bar.

For offloading, the quickstart exports new files only. Export files, which are already in your media library, in the list view of your media library with the bulk action "Export to external source". The quickstart links there at its end.

## Access data

The values you enter in the quickstart are saved for your user only for the time of the quickstart, passwords encrypted. They are never sent back to the browser. They are deleted after the quickstart has been run, when the quickstart is opened again and when the plugin is uninstalled. The created external source saves its access data like any other external source.

## For developers

The quickstart uses the library [easy-setup-for-wordpress](https://github.com/threadi/easy-setup-for-wordpress) in version 1.6.0 or newer. Use these hooks to customize it:

* `efml_setup_goals`: change or add goals. Each goal has the keys `label`, `description`, `icon`, `features` (features a service must support, e.g. `export`, `private`, `sync`), `service` (a fixed service or empty), `options`, `defaults` and `action` (`export`, `sync`, `import` or empty).
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
