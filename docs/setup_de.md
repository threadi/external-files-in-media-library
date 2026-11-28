# Schnellstart

Der Schnellstart führt dich Schritt für Schritt durch die häufigsten Aufgaben dieses Plugins. Sie legt die externe Quelle an, prüft die Verbindung und setzt die nötigen Einstellungen. Alles, was sie einrichtet, kannst du danach jederzeit in den Einstellungen und in der Liste deiner externen Quellen ändern.

Du findest ihn unter Medien → „Externe Dateien hinzufügen“ als zweite Kachel „Schnellstart“, außerdem in der Befehlspalette (`Strg + K`, Suche nach „Schnellstart“) und im Hilfe-Reiter „Was ist möglich?“. Der Schnellstart steht Benutzern zur Verfügung, die die Einstellungen verwalten dürfen.

## Ablauf

1. **Ziel wählen:** Was möchtest du tun? Die Ziele sind unten beschrieben.
2. **Dienst wählen:** Es erscheinen nur die Dienste, die das gewählte Ziel unterstützen, z. B. für das Auslagern nur Dienste mit Export. Bei manchen Zielen steht der Dienst fest, dann entfällt dieser Schritt. Dienste aus Erweiterungen, die noch nicht installiert sind (z. B. AWS S3 oder Cloudflare R2), erscheinen mit dem Hinweis auf die Erweiterung, sofern du Plugins installieren darfst.
3. **Erweiterung installieren (falls nötig):** Braucht der gewählte Dienst eine Erweiterung, erklärt der Schnellstart, welche und warum (siehe [Erweiterungen](extensions_de.md)). Bestätigst du die Installation, lädt er die neueste Version von GitHub, installiert und aktiviert sie und zeigt das Ergebnis. Schlägt das fehl, siehst du den Grund und kannst es erneut versuchen.
4. **Zugangsdaten eingeben:** Der Schnellstart zeigt die Felder des Dienstes. Die aufklappbare Hilfe „Wo finde ich diese Angaben?“ erklärt, wo du die Zugangsdaten bekommst, welche Berechtigungen sie brauchen und worauf du achten musst, bei AWS S3 mit fertiger IAM-Richtlinie zum Kopieren. Dieselbe Hilfe steht im Formular des Dienstes. Werte, die bereits als Konstante (z. B. in der `wp-config.php`) oder in den Einstellungen hinterlegt sind, fragt er nicht noch einmal ab. Dropbox und Google Drive verbindest du über den Button „Dropbox verbinden“ bzw. „Google Drive verbinden“, danach kehrst du zum Schnellstart zurück. Google Drive braucht dafür seine Erweiterung ab Version 1.4.0.
5. **Verbindung prüfen und Optionen wählen:** Der Schnellstart prüft die Verbindung mit deinen Angaben. Klappt das nicht, siehst du den Grund und gehst zurück, um die Angaben zu korrigieren. Klappt es, wählst du die Optionen des Ziels. Sie sind passend vorbelegt.
6. **Einrichten:** Der Schnellstart legt die externe Quelle an, setzt die Einstellungen und zeigt dir das Ergebnis jedes Schritts.

Jeder Schritt zeigt direkt über seinen Buttons einen Hinweis auf das [Supportforum](https://wordpress.org/support/plugin/external-files-in-media-library/).

## Ziele

Die Ziele erscheinen in dieser Reihenfolge:

| Ziel | Dienst | Was der Schnellstart einrichtet |
|---|---|---|
| Externe Dateien einbinden und synchron halten | alle Dienste mit Synchronisierung | Synchronisierung aktivieren, Intervall, erste Synchronisierung |
| Lokale Dateien importieren | Lokales Verzeichnis deines Hostings | Import des Verzeichnisses, auf Wunsch als echte Dateien in die Mediathek kopiert |
| Daten von FTP synchronisieren | FTP, FTPS und SFTP | Synchronisierung aktivieren, Intervall, erste Synchronisierung |
| Mediathek auslagern | alle Dienste mit Export | Export aktivieren, externe Quelle als Hauptexport, Ordner nach Jahr und Monat, optional „Dateien privat speichern“ und der Export der Dateien, die schon in deiner Mediathek liegen, Verbindungstest |
| Geschützte Downloads | alle Dienste mit privaten Dateien | wie beim Auslagern, „Dateien privat speichern“ ist vorgewählt, auf Wunsch die Downloads von WooCommerce |
| Zentrale Mediathek für dein Netzwerk | keiner | eine Website als zentrale Mediathek für alle anderen Websites der Multisite, siehe unten. Erscheint nur in einer Multisite mit mindestens zwei Websites, für Benutzer, die das Netzwerk verwalten dürfen, und solange das Netzwerk keine zentrale Mediathek nutzt. |
| Von einem anderen Offload-Plugin umsteigen | der Dienst des gefundenen Buckets | Dateien des anderen Plugins ohne erneuten Upload übernehmen, siehe [Umzug](migration_de.md). Erscheint nur, wenn solche Dateien gefunden werden. |

Große Verzeichnisse werden wie gewohnt im Hintergrund importiert bzw. synchronisiert, den Fortschritt siehst du in der Admin-Leiste.

Beim Auslagern und bei geschützten Downloads bietet der Schnellstart an, auch die Dateien zu exportieren, die schon in deiner Mediathek liegen. Die Option ist standardmäßig aus und nennt die Anzahl dieser Dateien. Wählst du sie, werden die Dateien am Ende des Schnellstarts nacheinander im Hintergrund exportiert, den Fortschritt siehst du in der Admin-Leiste. Sonst werden nur neue Dateien exportiert: Die vorhandenen Dateien kannst du später über Einstellungen → „Export“ → „Vorhandene Dateien exportieren“ exportieren oder in der Listenansicht der Mediathek mit der Massenaktion „Zu externer Quelle exportieren“.

## Zentrale Mediathek für dein Netzwerk

In einer Multisite richtet dieses Ziel eine [zentrale Mediathek](multisite_de.md) mit den minimal nötigen Angaben ein. Es braucht keinen Dienst und keine Zugangsdaten und hat daher nach dem Ziel nur einen Schritt:

* **Website mit der zentralen Mediathek:** Die Hauptwebsite ist vorgewählt. Alle anderen Websites des Netzwerks nutzen ihre Dateien, ihre neuen Uploads werden dort gespeichert.
* **Dateien übertragen, die die anderen Websites schon haben** (standardmäßig aus): Ihre lokalen Dateien werden im Hintergrund in die zentrale Mediathek übertragen. Sonst bleiben sie lokale Dateien und können später in der Übersicht des Netzwerks übertragen werden.
* **Optionen dieses Plugins in den anderen Websites ausblenden** (standardmäßig aus).
* **Bestätigung**, dass du eine Sicherung deines Netzwerks erstellt hast.

Der Schnellstart legt eine Gruppe mit allen Websites an, wie es die Netzwerkverwaltung tut. Alle weiteren Einstellungen (weitere Gruppen, Rechte zum Löschen, Verfügbarkeit neuer Uploads, …) behalten ihre Standardwerte und lassen sich jederzeit in der Netzwerkverwaltung unter Einstellungen → „External files“ ändern. Am Ende leitet der Schnellstart zur Übersicht des Netzwerks weiter. Nutzt das Netzwerk bereits eine zentrale Mediathek, wird das Ziel nicht angeboten und der erste Schritt verweist auf die Netzwerkverwaltung.

## Zugangsdaten

Die Werte, die du im Schnellstart eingibst, werden nur für die Dauer der Einrichtung bei deinem Benutzer gespeichert, Passwörter verschlüsselt. Sie werden nie an den Browser zurückgeschickt. Nach dem Einrichten, beim erneuten Öffnen des Schnellstarts und bei der Deinstallation des Plugins werden sie gelöscht. Die angelegte externe Quelle speichert ihre Zugangsdaten wie jede andere externe Quelle.

## Für Entwickler

Der Schnellstart nutzt die Bibliothek [easy-setup-for-wordpress](https://github.com/threadi/easy-setup-for-wordpress) ab Version 1.6.0. Mit diesen Hooks passt du sie an:

* `efml_setup_goals`: Ziele ändern oder ergänzen. Jedes Ziel hat die Schlüssel `label`, `description`, `icon`, `features` (Funktionen, die ein Dienst unterstützen muss, z. B. `export`, `private`, `sync`), `service` (fester Dienst oder leer), `options`, `defaults` und `action` (`export`, `sync`, `import`, `network` oder leer).
* `efml_setup_option_definitions`: Optionen ändern oder ergänzen, die die Ziele anbieten können.
* `efml_setup_option_available`: Festlegen, ob eine Option für einen Dienst angeboten wird.
* `efml_setup_apply_option`: Den gewählten Wert einer eigenen Option speichern.
* `efml_setup_run_action`: Weitere Aufgaben am Ende des Schnellstarts ausführen.
* `efml_setup_config` und `efml_setup_process_completed_text`: Konfiguration und Abschlusstext anpassen.
* `efml_service_oauth_return_url`: Adresse, zu der ein Dienst nach der Verbindung per OAuth zurückkehrt.

Dienste, die per OAuth verbunden werden, geben über `Service_Base::get_setup_connect_dialog()` ihren Dialog zum Verbinden zurück, solange sie nicht verbunden sind, und leiten nach der Verbindung zu `Service_Base::get_oauth_return_url()` weiter.

Beispiel für ein eigenes Ziel:

```php
add_filter( 'efml_setup_goals', function( array $goals ): array {
    $goals['my-sync'] = array(
        'label'       => 'Bilder von meinem Server abgleichen',
        'description' => 'Bindet die Bilder eines FTP-Verzeichnisses ein und gleicht sie täglich ab.',
        'features'    => array( 'sync' ),
        'service'     => 'ftp',
        'options'     => array( 'sync', 'sync_interval' ),
        'defaults'    => array( 'sync' => 1, 'sync_interval' => 'efml_24hourly' ),
        'action'      => 'sync',
    );
    return $goals;
} );
```
