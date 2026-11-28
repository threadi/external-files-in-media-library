# External files in the media dialog

The dialog of WordPress to choose or upload media files (e.g. "Add media", the featured image, the image or gallery block) offers the functions of this plugin, too:

* **Filter for external files** beside the filters of WordPress: all files, only external URLs, no external URLs, the state of the export, your external sources and the further options of other features (e.g. in a multisite). The options, which need the database, are loaded when you point to the filter for the first time.
* **Mark on the files:** external files show the logo of their service (e.g. Dropbox, FTP) at the top left of their preview, exported files with a cloud and files without direct access at the source with a shield. Its label names the service. Services without own logo show a link.
* **Tab "Insert from URL":** enter one or more URLs (one per line). The files are added to your media library and selected in the dialog. URLs, which are already in your media library, are not added again: their file is selected.
* **Tab "From external source":** shows your saved external sources as cards with the logo of their service and their directory listing, like Media → "Your external sources". "Import" adds a file and selects it in the dialog. If the dialog allows only one file, it shows the media library with the file afterward, otherwise you could add further files.

## Who could use it

Settings → "Permissions" → "Permissions for tools" → "External files in the media dialog": choose the roles, which get these functions (by default administrators and editors). Without a role nobody gets them, the dialog is unchanged then.

The permissions of the plugin apply as everywhere: the user needs the permission to add external files, the tab "From external source" shows only the external sources the user could use in the backend (own ones, all for administrators or if "Show all external sources" is enabled) and only of the services the user is allowed to use. Each import checks these permissions again on the server. To prevent a role from importing files of a service in the dialog, remove its permission for this service.

In the tab "From external source" files are only imported. Directories are imported, saved and tested in the list of external sources.

## Performance

Opening the dialog does not cause any further request or database query: the plugin adds only a small script. The further options of the filter, the external sources and the page of the tab "From external source" are loaded on their first use. The marks are part of the data, which the dialog loads for its files anyway.

## The tab "From external source" and security headers

The tab shows a page of your backend in an iframe. This works, if the dialog runs on the same domain as your backend, which is the default. The tab opens the external sources in a new window instead, if

* the dialog runs on another domain or protocol than your backend (e.g. in page builders in the frontend with a mapped domain or with `FORCE_SSL_ADMIN` and a frontend via http), or
* your website prevents showing its backend in an iframe, e.g. with the header `X-Frame-Options: DENY` or `Content-Security-Policy: frame-ancestors 'none'` (set by some security plugins or hosters). WordPress itself sends `X-Frame-Options: SAMEORIGIN`, which allows it.

Files, which you add in the new window, are selected in the dialog as well. Both pages accept messages only from the expected domain; they contain only the IDs of the added files.

## For developers: shared components

The logos of the services and the cards are shared components in `admin/components.css` (loaded on the pages of this plugin and in the media dialog):

* `Helper::get_service_icon( $name )` returns the logo of a service as icon in the size of the variable `--efml-service-icon-size` (16px by default). It uses the rules of the logos of the services: `.efml-<name of the service>:before` or `.efml-<name> > *:before`. Their size is replaced by the size of the icon. Services without logo show a link.
* Extensions load the styles with their logos via the action `efml_enqueue_service_logos`, where this plugin shows them outside of its pages (media dialog, tab "From external source"), with `efml-components` as dependency. For a wide logo they could add a square variant for the icon with the rule `span.efml-service-icon.efml-<name>:before`.
* `Helper::get_card( $url, $icon, $label, $description, $args )` returns a card, `Directory_Listing::get_source_card()` the card of a saved external source. Show cards in an element with the class `efml-cards` and output them with `wp_kses( $card, Helper::get_allowed_html_for_cards() )`.
