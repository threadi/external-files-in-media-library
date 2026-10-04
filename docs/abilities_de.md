# Abilities API (KI / MCP)

Das Plugin stellt seine Funktionen als Abilities über die Abilities API von WordPress bereit
(verfügbar seit WordPress 6.9). KI-Agenten können damit externe Dateien in der Mediathek
verwalten, z.B. über den MCP Adapter von WordPress.

Alle Abilities

* verwenden den Präfix `external-files-in-media-library/`
* sind der Kategorie `efml` zugeordnet
* sind über die REST API verfügbar (`show_in_rest`) und für den MCP Adapter als öffentlich markiert
* geben einen `WP_Error` mit einem lesbaren Grund zurück, wenn etwas fehlschlägt

## Abilities

### Dateien

| Ability | Beschreibung | Eingabe |
|---|---|---|
| `add` | Eine einzelne URL hinzufügen, gibt die Attachment-ID zurück | `url`, `login`, `password` |
| `import` | Mehrere URLs oder Verzeichnisse importieren, gibt die Dateien und den Grund für jede fehlgeschlagene URL zurück | `urls`, `source_id`, `login`, `password`, `queue`, `real_import`, `use_dates` |
| `get-file` | Daten einer externen Datei | `attachment_id` oder `url` |
| `list-files` | Paginierte Liste der externen Dateien | `source_id`, `mime_type`, `hosting`, `availability`, `search`, `url_contains`, `page`, `per_page` |
| `delete` | Eine externe Datei aus der Mediathek löschen | `attachment_id` oder `url` |
| `check-availability` | Prüfen, ob Dateien noch erreichbar sind; ohne Datei werden alle Dateien in Teilen geprüft | `attachment_id` oder `url`, `limit`, `offset` |
| `switch-to-local` | Eine externe Datei lokal speichern | `attachment_id` oder `url` |
| `switch-to-external` | Eine lokal gespeicherte Datei wieder über ihre externe URL verwenden | `attachment_id` oder `url` |
| `reset-thumbnails` | Die erzeugten Vorschaubilder einer Datei löschen | `attachment_id` oder `url` |
| `reset-proxy-cache` | Den Proxy-Cache löschen | - |

### Informationen

| Ability | Beschreibung | Eingabe |
|---|---|---|
| `get-statistics` | Anzahl und Größe der Dateien nach Hosting, Dienst und Dateityp | - |
| `get-log` | Einträge aus dem Log, z.B. um den Grund für einen fehlgeschlagenen Import zu finden | `url`, `search`, `state`, `limit`, `offset` |
| `list-services` | Nutzbare Dienste mit den von ihnen benötigten Feldern | - |

### Externe Quellen und Synchronisation

| Ability | Beschreibung | Eingabe |
|---|---|---|
| `list-sources` | Gespeicherte externe Quellen des aktuellen Benutzers | `service` |
| `add-source` | Ein Verzeichnis mit seinen Zugangsdaten als externe Quelle speichern, die Verbindung wird vorher getestet | `service`, `directory`, `fields` |
| `delete-source` | Eine externe Quelle löschen | `source_id` |
| `list-directory` | Dateien und Unterverzeichnisse einer externen Quelle, ohne sie zu importieren | `source_id`, `directory` |
| `sync-source` | Die Synchronisation einer externen Quelle jetzt ausführen | `source_id` |
| `get-sync-status` | Status der Synchronisation, letzter und nächster Lauf der externen Quellen | `source_id` |
| `delete-synced-files` | Alle synchronisierten Dateien einer externen Quelle aus der Mediathek löschen | `source_id` |

### Warteschlange

| Ability | Beschreibung | Eingabe |
|---|---|---|
| `queue-add` | URLs in die Warteschlange stellen, statt sie direkt zu importieren | `urls`, `source_id`, `login`, `password`, `real_import`, `use_dates` |
| `queue-status` | Anzahl wartender und fehlgeschlagener URLs sowie die neuesten Einträge | - |
| `queue-process` | Die nächsten URLs aus der Warteschlange jetzt importieren | - |
| `queue-cleanup` | Fehlgeschlagene URLs (`errors`) oder alle Einträge (`all`) entfernen | `mode` |

### Export

| Ability | Beschreibung | Eingabe |
|---|---|---|
| `export-file` | Eine lokale Datei in die für den Export aktivierten externen Quellen exportieren | `attachment_id` |
| `set-export-state` | Eine externe Quelle als Exportziel aktivieren oder deaktivieren | `source_id`, `enabled` |

## Berechtigungen

Jede Ability prüft die Berechtigungen, die auch im Backend verwendet werden:

* Alle Abilities erfordern die Berechtigung zum Verwalten externer Dateien (`eml_manage_files`).
* `get-log` erfordert zusätzlich `manage_options`.
* `sync-source` und `delete-synced-files` erfordern die Berechtigung für die Synchronisation.
* `export-file` und `set-export-state` erfordern die Berechtigung für den Export.
* `add-source` und `delete-source` erfordern die Berechtigungen zum Verwalten bzw. Löschen externer Quellen.
* `import` mit `real_import` erfordert die Berechtigung für den echten Import.
* `delete` und `export-file` prüfen zusätzlich, ob der Benutzer den Anhang löschen bzw. bearbeiten darf.
* Externe Quellen anderer Benutzer können nur von Administratoren verwendet werden (oder wenn die Einstellung zum Anzeigen aller Quellen aktiv ist).
* Der Dienst einer externen Quelle muss für den aktuellen Benutzer erlaubt sein.

## Zugangsdaten

Zugangsdaten werden von keiner Ability zurückgegeben.

Damit Zugangsdaten nicht Teil der Unterhaltung mit einem KI-Agenten werden, speichert man sie einmalig
als externe Quelle (im Backend oder mit `add-source`) und verwendet deren `source_id` für `import`,
`queue-add`, `list-directory` und `sync-source`. Die Zugangsdaten werden verschlüsselt gespeichert.

## Nutzung über die REST API

Die Abilities können über die REST API von WordPress ausgeführt werden:

`/wp-json/wp-abilities/v1/abilities/external-files-in-media-library/<name>/run`

* Nur lesende Abilities erfordern `GET`, die Eingabe wird als Query-Parameter `input` gesendet.
* `delete`, `delete-source`, `delete-synced-files` und `queue-cleanup` erfordern `DELETE`.
* Alle anderen Abilities erfordern `POST` mit der Eingabe als JSON: `{"input":{"url":"https://example.com/file.pdf"}}`

## Abilities anpassen

Über den Hook `efml_abilities` lassen sich Abilities vor ihrer Registrierung entfernen oder ändern:

```
add_filter( 'efml_abilities', function( $abilities ) {
    unset( $abilities['delete-source'], $abilities['delete-synced-files'] );
    return $abilities;
});
```

## Hinweise

* Mit `source_id` werden die von `list-directory` als `url` gelieferten Werte unverändert für `import` und `queue-add`
  verwendet, auch wenn es keine echten URLs sind (z.B. `DropBox/meine datei.pdf`). Pfade relativ zum Verzeichnis der
  externen Quelle sind ebenfalls möglich.
* Import und Synchronisation von Dateien aus ZIP-Archiven hängen vom Formular im Backend ab und sind über
  Abilities nicht möglich. Die Dienste YouTube und WordPress REST API lesen den verwendeten Dienst ebenfalls aus
  diesem Formular, ihre Importe funktionieren über Abilities daher möglicherweise nicht.
* Die Dienste werden vor dem Ausführen einer Ability für den aktuellen Benutzer initialisiert. Dadurch sind Dienste
  wie DropBox auch nutzbar, wenn der Benutzer beim Initialisieren von WordPress noch nicht bekannt ist (z.B. über
  einen MCP-Server).
* Dienste, die über OAuth verbunden werden (z.B. DropBox), müssen im Backend verbunden werden.
* Synchronisation und Export müssen in den Plugin-Einstellungen aktiviert sein, um deren Abilities zu verwenden.
* Automatisch synchronisierte Dateien können nicht mit `delete` gelöscht werden. Dafür gibt es `delete-synced-files`.
