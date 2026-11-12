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

## Hinweise

* Die Hooks für den Import von Verzeichnissen der einzelnen Protokolle (z. B. `efml_ftp_directory_import_files`) werden
  für diese Importe nicht mehr genutzt, da jede Datei einzeln importiert wird.
* Für den Import im Hintergrund muss WP Cron regelmäßig laufen. Hat deine Website nur wenige Besucher, nutze einen
  Cronjob deines Hostings.
