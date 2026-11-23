# Zentrale Mediatheken in einer Multisite

In einer Multisite kannst du die Mediathek einer Website als zentrale Mediathek für andere Websites des Netzwerks nutzen. Diese Websites verwenden dann dieselben Dateien. Mit Gruppen kann ein Netzwerk mehrere zentrale Mediatheken haben, z. B. eine je Sprache oder je Kunde.

## Einrichten

Netzwerkverwaltung → Einstellungen → „External files“. Erstelle vorher eine Sicherung von allem.

* **Groups:** Jede Gruppe hat einen Namen und eine Website mit ihrer zentralen Mediathek. Eine Gruppe fügst du in der letzten Zeile der Tabelle hinzu.
* **Websites:** Wähle die Gruppe jeder Website. Jede Website gehört höchstens zu einer Gruppe. Eine zentrale Mediathek gehört nur zu ihrer eigenen Gruppe.
* **New websites join:** der ersten Gruppe (Standard), keiner Gruppe oder einer gewählten Gruppe.

Danach gilt für die Websites einer Gruppe:

* Uploads werden in die zentrale Mediathek der Gruppe exportiert. Die Website speichert einen Verweis darauf.
* Die Dateien der zentralen Mediathek sind in jeder Website der Gruppe als externe Dateien verfügbar.
* Die Synchronisierung läuft täglich, da Änderungen sofort weitergegeben werden (siehe unten). Die Dateien werden auf dem Server kopiert, nicht über HTTP geladen. Unveränderte Dateien werden übersprungen.

Mit „Hide the options of this plugin“ sehen die Nutzer der Websites der Gruppen keine Optionen dieses Plugins.

Die zentrale Mediathek von Versionen vor 6.1.0 wird als erste Gruppe mit allen Websites übernommen.

## Lokale Dateien der Websites

Dateien, die eine Website schon vor dem Beitritt zu ihrer Gruppe hatte, bleiben lokale Dateien der Website. Die Übersicht zeigt ihre Anzahl („Local files“). Mit „Transfer“ (oder „Transfer the local files of all websites“) werden sie im Hintergrund in die zentrale Mediathek exportiert. Dateien mit gleichem Inhalt wie eine vorhandene Datei werden mit ihr verknüpft (siehe unten).

## Was in den Websites ankommt

Änderungen in einer zentralen Mediathek werden am Ende der Anfrage an die Websites ihrer Gruppe weitergegeben. Was nicht in das Zeitlimit passt (Hook `efml_network_time_limit`, Standard 10 Sekunden), wird über WP Cron fortgesetzt.

* **Neue Dateien** kommen sofort über WP Cron in den Websites an, die sie nutzen dürfen. Die regelmäßige Synchronisierung ist eine zusätzliche Absicherung.
* **Gelöschte Dateien** werden in allen Websites gelöscht, auch in der Website, aus der der Upload stammt.
* **Beschriftung, Beschreibung und Alternativtext** kommen aus der zentralen Mediathek. Der Titel einer Datei ist in den Websites ihr Dateiname, da der Proxy ihn in der URL der Datei nutzt (z. B. `/emlproxy/bild.jpg`). Ändert eine Website einen dieser Werte, gilt die Änderung nur für diese Website und bleibt erhalten, auch wenn die zentrale Datei sich ändert. Im Dialog der Datei lassen sich die eigenen Änderungen verwerfen („Discard the changes and use the values of the central media library“).
* **Änderungen der Herkunft:** Mit der Option „Changes of caption, description and alternative text in the website, from which a file comes, apply to all websites“ (standardmäßig deaktiviert) werden die Änderungen der Website, in der eine Datei hochgeladen wurde, in der zentralen Mediathek gemacht und an alle Websites weitergegeben.
* **Ersetzte oder bearbeitete Dateien** (z. B. zugeschnitten) werden in allen Websites verwendet. Deren zwischengespeicherte Dateien und Größen werden neu geladen.
* **Ordner** von Ordner-Plugins werden nicht weitergegeben: Jede Website hat ihre eigenen Ordner.

## Uploads mit gleichem Inhalt

Lädt eine Website eine Datei hoch, die mit gleichem Inhalt schon in der zentralen Mediathek existiert, wird die vorhandene Datei verwendet, statt sie erneut hinzuzufügen (Option „Use an existing file of the central media library for an upload with the same content“, standardmäßig aktiviert). Die Website darf die Datei dann nutzen. Sie ist kein eigener Upload der Website: Löschen in der Website löscht die vorhandene Datei nicht.

Mit dieser Option verknüpft auch die Synchronisierung lokale Dateien einer Website mit den Dateien gleichen Inhalts in der zentralen Mediathek, statt diese erneut hinzuzufügen. Das passiert z. B., wenn eine Website einer Gruppe mit Dateien beitritt, die die zentrale Mediathek schon hat (wie eine ehemalige zentrale Mediathek nach einem Umzug). Die Anhänge behalten ihre IDs, eigene Beschriftungen, Beschreibungen und Alternativtexte bleiben erhalten.

## Welche Websites eine Datei nutzen dürfen

In der zentralen Mediathek legst du für jede Datei fest, wer sie nutzen darf („Available for“):

* alle Websites,
* nur ausgewählte Websites,
* alle Websites außer ausgewählten.

Für mehrere Dateien auf einmal gibt es in der Listenansicht die Massenaktion „Change availability in the network“.

Die Website, aus der eine Datei stammt, darf sie immer nutzen. Darf eine Website eine Datei nicht mehr nutzen, wird sie dort gelöscht, **auch wenn sie in Beiträgen verwendet wird**.

Für neue Uploads gibt es eine Voreinstellung in der Netzwerkverwaltung („New uploads are available for“): alle Websites oder nur die Website, in der die Datei hochgeladen wurde.

Super-Admins, Administratoren und die in der Netzwerkverwaltung gewählten Rollen können sie für ihre Uploads unter dem Upload-Feld ändern und die Verfügbarkeit der Dateien in der zentralen Mediathek ändern (Feld, Massenaktion). Die Rollen werden als Standard und abweichend für einzelne Websites gewählt („Could choose the availability“). Die anderen Nutzer sehen diese Optionen nicht, ihre Uploads bekommen die Voreinstellung.

## Wer Dateien löschen darf

Eine Datei einer zentralen Mediathek wird immer in allen Websites ihrer Gruppe gelöscht. In der Netzwerkverwaltung legst du fest, was Websites löschen dürfen, als Standard und abweichend für einzelne Websites:

* **No file:** keine Datei der zentralen Mediathek, auch keine eigenen Uploads.
* **Only own uploads:** nur Dateien, die in dieser Website hochgeladen wurden. Das ist der Standard und entspricht dem Verhalten vor Version 6.1.0.
* **All files:** jede Datei der zentralen Mediathek.

Nutzer der zentralen Mediathek dürfen jede Datei löschen. Ist das Löschen nicht erlaubt, zeigt die Mediathek keine Löschen-Aktion. Vor dem Löschen im ganzen Netzwerk fragt die Mediathek nach. Die Rückfrage nennt, in wie vielen Websites die Datei vorhanden ist und in wie vielen Beiträgen sie verwendet wird.

## Verwendung im Netzwerk

Im Dialog einer Datei zeigt „Usage in the network“ → „Show usage“, welche Websites die Datei haben und in wie vielen Beiträgen sie dort verwendet wird. Als verwendet gilt eine Datei, wenn sie Beitragsbild ist oder im Inhalt mit ihrer ID eingebunden ist, z. B. im Bild-, Galerie-, Cover- oder Datei-Block.

Der Filter der Mediathek in den Websites zeigt „Own uploads“ oder die Dateien „From the central media library“.

## Ungenutzte Dateien und Dubletten

Die Verwendung der Dateien jeder zentralen Mediathek wird täglich im Hintergrund geprüft. In ihrer Mediathek zeigt der Filter „Unused in the network“ die Dateien, die kein Inhalt des Netzwerks verwendet (Stand der letzten Prüfung). Gelöscht wird nichts automatisch.

Medien → „Duplicates in the network“ in einer zentralen Mediathek zeigt Dateien mit gleichem Inhalt. „Merge“ behält die gewählte Datei: Die Websites verwenden sie statt der anderen, auch dort, wo diese in Inhalten verwendet werden (ihre Anhänge bleiben). Die anderen werden danach in der zentralen Mediathek gelöscht. Eine Datei, die in Inhalten der zentralen Mediathek selbst verwendet wird, wird nicht zusammengeführt.

## Übersicht

Der Reiter „Overview“ auf der Seite in der Netzwerkverwaltung zeigt für jede Website jeder Gruppe: die Anzahl der Dateien, eigene Uploads, Fehler der letzten 7 Tage, überfällige Aufgaben von WP Cron und die nächste Synchronisierung. Der Website-Zustand jeder zentralen Mediathek meldet Websites, deren WP Cron nicht läuft.

## Eine zentrale Mediathek umziehen

In den Einstellungen des Netzwerks zieht „Move the central media library to“ die zentrale Mediathek einer Gruppe in eine Website der Gruppe oder eine Website ohne Gruppe um. Im Hintergrund:

1. werden die Dateien in die neue Website übernommen, ihre Kopien davon werden zu ihren eigenen Dateien,
2. wird die neue Website zur zentralen Mediathek, neue Uploads werden dorthin exportiert,
3. verwenden die Kopien in den Websites die Dateien der neuen zentralen Mediathek (ihre Anhänge bleiben, die Verwendung in Inhalten bleibt erhalten).

Die Dateien in der bisherigen zentralen Mediathek bleiben erhalten. Sie gehört danach nicht mehr zur Gruppe. Während des Umzugs lassen sich die Gruppen nicht ändern. Die Übersicht zeigt den Fortschritt.

## Geänderte Domain

Die Websites speichern die Adressen der Dateien der zentralen Mediathek. Ein Suchen und Ersetzen in der ganzen Datenbank (z. B. `wp search-replace`) ändert sie mit der Domain. Der Website-Zustand jeder zentralen Mediathek meldet Websites, die andere Adressen verwenden. „Use the actual addresses“ in der Übersicht oder `wp eml network fix-addresses` erzeugt die Adressen anhand der IDs der Dateien neu.

## Zusammenfassung per E-Mail

Mit „Send a weekly summary of problems …“ (standardmäßig deaktiviert) bekommt die E-Mail-Adresse der Netzwerkverwaltung einmal pro Woche eine Zusammenfassung von Fehlern, Websites ohne laufendes WP Cron und falschen Adressen. Sie wird nur verschickt, wenn es Probleme gibt.

## Eine Gruppe verlassen

Verlässt eine Website ihre Gruppe (andere Gruppe, keine Gruppe oder ihre Gruppe wird entfernt), stoppen ihr Export und ihre Synchronisierung sofort. Im Hintergrund:

* werden ihre eigenen Uploads und die in ihren Inhalten verwendeten Dateien zu normalen lokalen Dateien (Option „A website leaving its group“: „keeps …“, Standard) oder ebenfalls entfernt („loses all files …“),
* werden die anderen Dateien der zentralen Mediathek aus der Website entfernt.

Die Dateien in der zentralen Mediathek bleiben erhalten. Eigene Uploads gehören danach der zentralen Mediathek.

## Gelöschte Websites

Die Uploads einer gelöschten Website bleiben erhalten und gehören der zentralen Mediathek (Standard). Mit „are deleted in all websites“ werden sie gelöscht, auch dort, wo sie verwendet werden. Wird eine zentrale Mediathek gelöscht, wird ihre Gruppe entfernt und ihre Websites verlassen sie.

## Upload-Speicherplatz

Ist der Upload-Speicherplatz der Websites in den Einstellungen des Netzwerks begrenzt, zählen die Uploads einer Website in der zentralen Mediathek für diese Website, nicht für die zentrale Mediathek (Option „Count the uploads of a website in the central media library for its upload space“, standardmäßig aktiviert). Ohne diese Option zählen die Uploads nirgends, wie bisher.

## WP-CLI, REST API und Abilities

* `wp eml network status` zeigt die Gruppen und den Zustand ihrer Websites.
* `wp eml network join <blog_id> <group_id>` verschiebt eine Website in eine Gruppe (0, um ihre Gruppe zu verlassen).
* `wp eml network visibility <central> <attachment_id> <all|only|except> [--sites=2,3]` ändert die Verfügbarkeit einer Datei.
* `wp eml network usage <central> <attachment_id>` zeigt die Verwendung einer Datei.
* `wp eml network sync <blog_id>` führt die Synchronisierung einer Website aus.
* `wp eml network transfer-local [<blog_id>...]` überträgt die lokalen Dateien von Websites in ihre zentrale Mediathek.
* `wp eml network unused <central> [--check]` zeigt die ungenutzten Dateien.
* `wp eml network duplicates <central> [--merge]` zeigt Dateien mit gleichem Inhalt oder führt sie zusammen.
* `wp eml network move <group_id> <blog_id>` zieht die zentrale Mediathek einer Gruppe um.
* `wp eml network fix-addresses [--dry-run]` verwendet die aktuellen Adressen der Dateien.
* `wp eml network digest` verschickt die Zusammenfassung jetzt.
* `wp eml network run` führt die Aufgaben im Hintergrund jetzt aus (verlassende Websites, Umzüge, Migrationen).

REST API (Namespace `efml/v1`): `GET /network`, `POST /network/site/<blog_id>` mit `group`, `POST /network/site/<blog_id>/sync`, `POST /network/<central>/file/<attachment_id>/visibility` mit `mode` und `sites`, `GET /network/<central>/file/<attachment_id>/usage`. Die Netzwerk-Funktionen brauchen die Berechtigung `manage_network_options`, die Funktionen für eine Datei alternativ das Recht, sie in der zentralen Mediathek zu bearbeiten.

Abilities: `network-status`, `network-file-usage` und `network-set-visibility` (mit dem Präfix dieses Plugins).

## Hooks

* `efml_network_delete_right` ändert das Recht einer Website zum Löschen.
* `efml_network_file_visible` ändert, ob eine Website eine Datei nutzen darf.
* `efml_network_time_limit` ändert die Zeit, die das Weitergeben von Änderungen in einer Anfrage nutzen darf.
* `efml_sync_listing_complete` ändert, ob eine Synchronisierung Dateien löschen darf, die nicht in der Liste der externen Quelle sind.
