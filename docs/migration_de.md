# Umzug von anderen Offload-Plugins

Hast du deine Mediathek bisher mit einem anderen Plugin ausgelagert, kannst du diese Dateien als externe Dateien weiterverwenden – ohne sie erneut hochzuladen. Der Umzug läuft über den [Schnellstart](setup_de.md) mit dem Ziel „Von einem anderen Offload-Plugin umsteigen“. Das Ziel erscheint nur, wenn Dateien eines der unterstützten Plugins gefunden werden. Ist eines der Plugins aktiv, hat aber noch keine Dateien ausgelagert, weist dich der Schnellstart darauf hin.

## Unterstützte Plugins

| Plugin | Anbieter | Benötigte Erweiterung |
|---|---|---|
| WP Offload Media (auch Pro) | AWS S3, DigitalOcean Spaces, Google Cloud Storage | AWS S3 bzw. Google Cloud Storage |
| Advanced Media Offloader | AWS S3, Cloudflare R2, Backblaze B2, DigitalOcean Spaces, Wasabi, S3-kompatibel (z. B. MinIO) | AWS S3 |
| Stowaway | AWS S3 | AWS S3 |
| Offload Media – Cloud Storage | AWS S3, DigitalOcean Spaces, Cloudflare R2, MinIO, Google Cloud Storage | AWS S3 bzw. Google Cloud Storage |

Die Daten werden direkt aus der Datenbank gelesen. Das andere Plugin muss dafür nicht aktiv sein. Fehlt die benötigte Erweiterung, installiert der Schnellstart sie nach deiner Bestätigung (siehe [Erweiterungen](extensions_de.md)).

## Ablauf

1. **Dateien wählen:** Der Schnellstart zeigt je Plugin, Anbieter und Bucket eine Kachel mit der Zahl der Dateien.
2. **Zugangsdaten prüfen:** Bucket, Region, Endpunkt, Domain und Zugangsdaten werden aus den Einstellungen des anderen Plugins übernommen, auch aus seinen Konstanten in der `wp-config.php`. Du kannst sie ändern, danach wird die Verbindung geprüft.
3. **Optionen wählen:** Wie beim Auslagern legst du fest, ob neue Dateien weiterhin in diesen Bucket exportiert werden. Ist das andere Plugin noch aktiv, musst du bestätigen, dass es am Anfang des Umzugs deaktiviert wird. Beide Plugins dürfen die URLs deiner Dateien nicht gleichzeitig ändern. Möchtest du das nicht, brich den Schnellstart ab.
4. **Umzug:** Die externe Quelle wird angelegt, das andere Plugin deaktiviert und die Dateien werden übernommen. Bei vielen Dateien geht es im Hintergrund weiter, den Fortschritt siehst du in der Admin-Leiste. Am Ende erscheint ein Hinweis mit dem Ergebnis. Konnten Dateien nicht übernommen werden oder wurde der Umzug abgebrochen, verweist er auf das Protokoll, in dem für jede Datei der Grund steht.

## Was mit jeder Datei passiert

* Es wird geprüft, ob das Objekt im Bucket existiert. Fehlt es, bleibt der Anhang unverändert und der Grund steht im Protokoll.
* Der Anhang wird zur externen Datei mit der URL des Objekts, so wie nach einem Export. Private Dateien des anderen Plugins bleiben privat und werden über deine Website ausgeliefert.
* Im Bucket wird nichts hochgeladen, verschoben oder gelöscht. Die Vorschaubilder des anderen Plugins bleiben dort liegen, dieses Plugin erzeugt seine Vorschaubilder selbst.
* Lokale Kopien der Dateien und die Daten des anderen Plugins bleiben erhalten, damit du zurückwechseln kannst.
* Bereits übernommene Dateien werden übersprungen. Du kannst den Umzug also wiederholen, z. B. nach einem Fehler.

## Alte URLs in Inhalten

Die anderen Plugins lassen in Beiträgen und Seiten die lokalen URLs stehen (z. B. `/wp-content/uploads/2026/11/bild.jpg`) und ersetzen sie erst bei der Ausgabe. Dieses Plugin macht das für übernommene Dateien genauso: In der Ausgabe werden ihre lokalen URLs und die ihrer Vorschaubilder durch die aktuellen URLs ersetzt. Die Inhalte selbst werden nicht geändert.

Ersetzt wird in den Ausgaben von `the_content`, `the_excerpt`, `widget_text_content` und `widget_block_content`, im Header- und Hintergrundbild des Themes (`theme_mod_header_image`, `theme_mod_background_image`) und im zusätzlichen CSS (`wp_get_custom_css`). Mit dem Hook `efml_local_url_hooks` ergänzt du weitere. URLs mit maskierten Schrägstrichen (z. B. `https:\/\/…` in JSON) und ohne Domain (z. B. `/wp-content/uploads/…`) werden ebenfalls ersetzt, jeweils in ihrer Schreibweise.

Gibt es eine lokale Datei nicht mehr und kommt die Anfrage danach bei WordPress an (z. B. über eine alte URL im Block-Editor oder in einer CSS-Datei), wird sie auf die aktuelle URL der Datei weitergeleitet. Unter Werkzeuge → „Website-Zustand“ siehst du, ob dein Webserver solche Anfragen an WordPress weitergibt und wie du das änderst (z. B. für Locations von Bildern in nginx). Unabhängig davon zeigen der Block-Editor und der klassische Editor Bilder, die sich über ihre alte URL nicht laden lassen, mit ihrer aktuellen URL an; geändert wird nur die Anzeige, nicht der Inhalt.

## Zurückwechseln

Mit `wp eml migration revert` machst du den Umzug rückgängig (siehe [WP CLI](cli_de.md)). Die Anhänge nutzen dann wieder ihre lokalen Dateien und die Daten des anderen Plugins. Aktiviere das andere Plugin danach wieder.

## Für Entwickler

* `efml_migrations`: Liste der Klassen, die die Daten anderer Plugins lesen (Unterklassen von `ExternalFilesInMediaLibrary\ExternalFiles\Migrations\Migration_Base`).
* `efml_migration_batch_size`: Anzahl der Dateien, die auf einmal gelesen werden (Standard 50).
* `efml_local_url_hooks`: Hooks, in deren Ausgabe lokale URLs übernommener und exportierter Dateien ersetzt werden.
* `efml_redirect_missing_uploads`: Gib `false` zurück, um die Weiterleitung von Anfragen nach fehlenden lokalen Dateien abzuschalten.

Dienste unterstützen den Umzug über `Service_Base::get_offloaded_object_meta_key()` und `Service_Base::get_url_of_offloaded_object()`. Die Erweiterungen für AWS S3 und Google Cloud Storage bringen das mit.
