# Schnellstart

Der Schnellstart führt dich Schritt für Schritt durch die häufigsten Aufgaben dieses Plugins. Sie legt die externe Quelle an, prüft die Verbindung und setzt die nötigen Einstellungen. Alles, was sie einrichtet, kannst du danach jederzeit in den Einstellungen und in der Liste deiner externen Quellen ändern.

Du findest ihn unter Medien → „Externe Dateien hinzufügen“ als zweite Kachel „Schnellstart“, außerdem in der Befehlspalette (`Strg + K`, Suche nach „Schnellstart“) und im Hilfe-Reiter „Was ist möglich?“. Der Schnellstart steht Benutzern zur Verfügung, die die Einstellungen verwalten dürfen.

## Ablauf

1. **Ziel wählen:** Was möchtest du tun? Die Ziele sind unten beschrieben.
2. **Dienst wählen:** Es erscheinen nur die Dienste, die das gewählte Ziel unterstützen, z. B. für das Auslagern nur Dienste mit Export. Bei manchen Zielen steht der Dienst fest, dann entfällt dieser Schritt.
3. **Zugangsdaten eingeben:** Der Schnellstart zeigt die Felder des Dienstes. Werte, die bereits als Konstante (z. B. in der `wp-config.php`) oder in den Einstellungen hinterlegt sind, fragt er nicht noch einmal ab. Dropbox verbindest du über den Button „Dropbox verbinden“, danach kehrst du zum Schnellstart zurück.
4. **Verbindung prüfen und Optionen wählen:** Der Schnellstart prüft die Verbindung mit deinen Angaben. Klappt das nicht, siehst du den Grund und gehst zurück, um die Angaben zu korrigieren. Klappt es, wählst du die Optionen des Ziels. Sie sind passend vorbelegt.
5. **Einrichten:** Der Schnellstart legt die externe Quelle an, setzt die Einstellungen und zeigt dir das Ergebnis jedes Schritts.

## Ziele

| Ziel | Dienst | Was der Schnellstart einrichtet |
|---|---|---|
| Mediathek auslagern | alle Dienste mit Export | Export aktivieren, externe Quelle als Hauptexport, Ordner nach Jahr und Monat, optional „Dateien privat speichern“, Verbindungstest |
| Geschützte Downloads | alle Dienste mit privaten Dateien | wie beim Auslagern, „Dateien privat speichern“ ist vorgewählt, auf Wunsch die Downloads von WooCommerce |
| Externe Dateien einbinden und synchron halten | alle Dienste mit Synchronisierung | Synchronisierung aktivieren, Intervall, erste Synchronisierung |
| Lokale Dateien importieren | Lokales Verzeichnis deines Hostings | Import des Verzeichnisses, auf Wunsch als echte Dateien in die Mediathek kopiert |
| Daten von FTP synchronisieren | FTP, FTPS und SFTP | Synchronisierung aktivieren, Intervall, erste Synchronisierung |

Große Verzeichnisse werden wie gewohnt im Hintergrund importiert bzw. synchronisiert, den Fortschritt siehst du in der Admin-Leiste.

Beim Auslagern exportiert der Schnellstart nur neue Dateien. Dateien, die bereits in deiner Mediathek liegen, exportierst du in der Listenansicht der Mediathek mit der Massenaktion „Zu externer Quelle exportieren“. Der Schnellstart verlinkt am Ende dorthin.

## Zugangsdaten

Die Werte, die du im Schnellstart eingibst, werden nur für die Dauer der Einrichtung bei deinem Benutzer gespeichert, Passwörter verschlüsselt. Sie werden nie an den Browser zurückgeschickt. Nach dem Einrichten, beim erneuten Öffnen des Schnellstarts und bei der Deinstallation des Plugins werden sie gelöscht. Die angelegte externe Quelle speichert ihre Zugangsdaten wie jede andere externe Quelle.

## Für Entwickler

Der Schnellstart nutzt die Bibliothek [easy-setup-for-wordpress](https://github.com/threadi/easy-setup-for-wordpress) ab Version 1.6.0. Mit diesen Hooks passt du sie an:

* `efml_setup_goals`: Ziele ändern oder ergänzen. Jedes Ziel hat die Schlüssel `label`, `description`, `icon`, `features` (Funktionen, die ein Dienst unterstützen muss, z. B. `export`, `private`, `sync`), `service` (fester Dienst oder leer), `options`, `defaults` und `action` (`export`, `sync`, `import` oder leer).
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
