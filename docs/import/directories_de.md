# Import von Verzeichnissen mit vielen Dateien

Dieser Abschnitt beschreibt, wie Verzeichnisse importiert werden, auch wenn sie zehntausende Dateien enthalten.

## Unterstützte Dienste

Dies gilt für Verzeichnisse der folgenden Dienste:

* lokale Verzeichnisse
* FTP, FTPS und SFTP
* DropBox
* die Erweiterungen für AWS S3 und weitere S3-kompatible Plattformen, Google Cloud Storage, Google Drive und WebDAV

Andere Importe (z. B. einzelne URLs, Listen von URLs, YouTube-Kanäle oder ZIP-Dateien) werden wie bisher importiert.

## Ablauf

1. Die Dateien des Verzeichnisses werden seitenweise aufgelistet, ohne sie zu laden. Jede Datei wird der Warteschlange
   hinzugefügt.
2. Die Dateien werden einzeln importiert.

Dateien, die bereits in deiner Mediathek sind, werden nicht in die Warteschlange gelegt.

Im Import-Dialog werden kleine Verzeichnisse vollständig importiert, der Dialog zeigt den Fortschritt. Enthält ein
Verzeichnis mehr als 500 Dateien, werden die ersten 20 Dateien direkt importiert und alle weiteren im Hintergrund. Der
Dialog zeigt dazu einen Hinweis.

Der Import im Hintergrund nutzt WP Cron und ist unabhängig vom Intervall der Warteschlange. Solange Dateien im
Hintergrund importiert werden, zeigt die Admin-Leiste den Fortschritt. Ein Klick darauf zeigt die Warteschlange mit den
wartenden Dateien.

Dateien, die nicht importiert werden konnten, bleiben als Fehler in der Warteschlange. Du kannst sie dort erneut
importieren oder löschen. Wenn du die Warteschlange leerst, werden die laufenden Importe abgebrochen.

Importe per WP CLI importieren alle Dateien direkt.

## Synchronisierung

Synchronisierungen laufen auf demselben Weg. Dateien, die es auf der externen Quelle nicht mehr gibt, werden erst
gelöscht, nachdem alle Dateien des Verzeichnisses aufgelistet und importiert wurden. Konnte die Liste nicht vollständig
geladen werden (z. B. durch einen Fehler der externen Quelle), wird nichts gelöscht.

Große Synchronisierungen werden im Hintergrund fortgesetzt. Der Hook `efml_after_sync` läuft nach jedem Teil davon, der
neue Hook `efml_sync_completed` erst nach dem Ende der Synchronisierung.

## Unterverzeichnisse bei der Synchronisierung

Jeder Dienst hat einen eigenen Standard, ob die Dateien von Unterverzeichnissen synchronisiert werden:

| Dienst | Unterverzeichnisse standardmäßig |
|---|---|
| FTP, FTPS, SFTP, lokale Verzeichnisse | nein |
| WebDAV | nein |
| DropBox | ja |
| ZIP-Dateien | ja |
| AWS S3 und S3-kompatible Plattformen, Google Cloud Storage | ja |
| Google Drive | ja für das Hauptverzeichnis, nein für Ordner |

Deine Mediathek kennt keine Ordner: Alle synchronisierten Dateien liegen auf einer Ebene.

Um das zu ändern, aktiviere die Option „Unterverzeichnisse synchronisieren“ in den Einstellungen unter
„Synchronisierung“. Dann kannst du für jede externe Quelle in der Konfiguration ihrer Synchronisierung wählen:

* **Standard:** die globale Einstellung „Unterverzeichnisse synchronisieren“ oder, wenn sie deaktiviert ist, der
  Standard des Dienstes.
* **Ja:** Die Dateien aller Unterverzeichnisse werden synchronisiert.
* **Nein:** Nur die Dateien direkt im Verzeichnis werden synchronisiert.

Ohne aktivierte Option nutzt jeder Dienst seinen Standard. Externe Quellen von Diensten, die schon vorher
Unterverzeichnisse genutzt haben (z. B. DropBox und ZIP-Dateien), haben mit dem Update auf Version 6.1.0 die Einstellung
„Ja“ bekommen.

Der Import eines Verzeichnisses im Dialog wird durch diese Einstellung nicht verändert. Die Erweiterungen zeigen die
Option, sobald sie sie unterstützen: Bis dahin behalten sie ihren Standard.

Für FTP, FTPS, SFTP, lokale Verzeichnisse, WebDAV und Ordner in Google Drive gilt:

* Unterverzeichnisse werden bis zu einer Tiefe von 10 genutzt (Hook `efml_subdirectories_max_depth`).
* Die Verzeichnisse `node_modules`, `vendor`, `tmp`, `.git` und `.svn` werden nie genutzt (Hook
  `efml_subdirectories_excluded`). Sie werden auch in der Auflistung lokaler Verzeichnisse ausgeblendet.
* Verknüpfungen (Symlinks) auf Verzeichnisse wird nie gefolgt. Verknüpfungen auf Dateien in lokalen Verzeichnissen
  werden wie bisher genutzt.
* Unterverzeichnisse, die nicht gelesen werden konnten, werden übersprungen und protokolliert. Ihre Dateien werden bei
  dieser Synchronisierung nicht gelöscht. Da Dateien in Google Drive keinen Pfad in ihrer URL haben, wird dann keine
  Datei der externen Quelle gelöscht.

DropBox, ZIP-Dateien, AWS S3 und S3-kompatible Plattformen, Google Cloud Storage und das Hauptverzeichnis von Google
Drive nutzen wie bisher alle Unterverzeichnisse, ohne diese Grenzen. Ohne Unterverzeichnisse nutzen sie nur die Dateien
direkt im Verzeichnis (die oberste Ebene des Buckets oder von Google Drive).

### Ordnerstruktur in Ordner-Plugins

Mit der Option „Ordnerstruktur übernehmen“ in der Konfiguration der Synchronisierung wird jede Datei in deinem
Ordner-Plugin dem Ordner ihres Unterverzeichnisses zugeordnet, unterhalb des für die externe Quelle gewählten Ordners
(oder auf der obersten Ebene, wenn keiner gewählt ist). Fehlende Ordner werden angelegt. Die Option erscheint, wenn ein
unterstütztes Ordner-Plugin aktiv ist, und wirkt nur, wenn Unterverzeichnisse synchronisiert werden.

* Unterstützt: FileBird, CatFolders, Folders, Folderly, Media Library Organizer, Media Library Assistant und Enhanced
  Media Library. Real Media Library nur in einer Version mit Unterordnern (die Lite-Version unterstützt sie nicht).
  iFolders wird nicht unterstützt.
* Bei Plugins mit mehreren Kategorien pro externer Quelle (Folderly, Media Library Organizer, Media Library Assistant,
  Enhanced Media Library) wird die Struktur nur genutzt, wenn genau eine Kategorie gewählt ist.
* Die Ordner werden pro externer Quelle gespeichert: Sie werden bei der nächsten Synchronisierung wiedergefunden, auch
  wenn du sie umbenannt hast. Ein gelöschter Ordner wird neu angelegt. Ordner werden nie automatisch gelöscht, auch dann
  nicht, wenn ihr Verzeichnis nicht mehr existiert.
* Jede Synchronisierung ordnet die Dateien erneut dem Ordner ihres Verzeichnisses zu.
* Weitere Ordner-Plugins können über den Hook `efml_folder_structure_integrations` ergänzt werden (Interface
  `ExternalFilesInMediaLibrary\ThirdParty\Folder_Structure_Interface`).

Bitte beachte:

* Wenn du die Unterverzeichnisse deaktivierst oder die Tiefe verringerst, werden die Dateien dieser Unterverzeichnisse
  bei der nächsten Synchronisierung gelöscht, wenn ungenutzte Dateien nach der Synchronisierung gelöscht werden.
* Dateien, die du über die Auflistung einer externen Quelle aus einem Unterverzeichnis importierst, werden dieser
  externen Quelle zugeordnet. Nutzt ihre Synchronisierung keine Unterverzeichnisse, werden diese Dateien bei der nächsten
  Synchronisierung gelöscht, wenn ungenutzte Dateien nach der Synchronisierung gelöscht werden.
* Dateien, die auf die externe Quelle exportiert wurden, werden nie synchronisiert, auch nicht in Unterverzeichnissen und
  auch nicht ihre Bildgrößen.
* Wird eine Datei in ein anderes Unterverzeichnis verschoben, ändert sich ihre URL: Sie wird als neue Datei importiert und
  die bisherige Datei wird gelöscht.

## Hooks

* `efml_background_import_threshold` ändert die Anzahl an Dateien (Standard 500), ab der der Import im Dialog im
  Hintergrund fortgesetzt wird.
* `efml_background_import_direct_count` ändert die Anzahl an Dateien (Standard 20), die im Dialog direkt importiert
  werden, wenn der Import im Hintergrund fortgesetzt wird.
* `efml_import_run_time_limit` ändert die Zeit in Sekunden (Standard 20), die eine Anfrage für den Import nutzen darf.
* `efml_file_list_page_size` ändert die Anzahl an Dateien (Standard 500), die pro Anfrage an die externe Quelle
  aufgelistet werden.
* `efml_import_run_files` filtert die Dateien jeder Seite, bevor sie der Warteschlange hinzugefügt werden.
* `efml_import_run_finished` läuft, nachdem der Import eines Verzeichnisses beendet wurde.
* `efml_sync_completed` läuft nach dem Ende einer Synchronisierung.
* `efml_include_subdirectories` filtert, ob die Dateien von Unterverzeichnissen genutzt werden.
* `efml_subdirectories_max_depth` ändert die maximale Tiefe der Unterverzeichnisse (Standard 10).
* `efml_subdirectories_excluded` filtert die Namen der Verzeichnisse, die nie genutzt werden.
* `efml_subdirectory_skipped` läuft, wenn ein Unterverzeichnis nicht gelesen werden konnte.
* `efml_folder_structure_integrations` filtert die Ordner-Plugins, die die Ordnerstruktur übernehmen können.
* `efml_sync_supports_subdirectories` und `efml_sync_subdirectories_default` filtern die Unterstützung und den Standard
  einer externen Quelle.

## Hinweise

* Die Hooks für den Import von Verzeichnissen der einzelnen Protokolle (z. B. `efml_ftp_directory_import_files`) werden
  für diese Importe nicht mehr genutzt, da jede Datei einzeln importiert wird.
* Für den Import im Hintergrund muss WP Cron regelmäßig laufen. Hat deine Website nur wenige Besucher, nutze einen
  Cronjob deines Hostings.
