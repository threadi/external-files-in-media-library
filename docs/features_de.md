# Was ist möglich?

External Files in Media Library kann mehr, als einzelne URLs in deine Mediathek aufzunehmen. Diese Seite zeigt die Funktionen nach Zielen geordnet und führt dich zu den Einstellungen und zur ausführlichen Dokumentation.

## Ich möchte …

### … Dateien von anderen Orten in meiner Mediathek nutzen

Füge Dateien über ihre URL hinzu oder wähle einen Dienst, z. B. FTP, Dropbox, YouTube, eine andere WordPress-Website oder mit Erweiterungen AWS S3, Google Drive oder WebDAV. Die Dateien nutzt du wie lokale Dateien, sie bleiben aber an ihrer externen Quelle.

* Im Backend: Medien → „Externe Dateien hinzufügen“ oder „Neue Mediendatei hinzufügen“
* Dokumentation: [Schnellstart](quickstart_de.md), [Protokolle](protocols_de.md), [Import einzelner Dateien und Verzeichnisse](import/directories_de.md)

### … Verzeichnisse automatisch abgleichen

Speichere ein Verzeichnis als externe Quelle und lass neue, geänderte und gelöschte Dateien in einem Intervall deiner Wahl abgleichen. Große Verzeichnisse mit tausenden Dateien werden im Hintergrund importiert.

* Im Backend: Medien → „Deine externen Quellen“, Einstellungen → „Synchronisierung“
* Dokumentation: [Verzeichnisse](import/directories_de.md)

### … meine Medien auslagern und Speicherplatz sparen

Exportiere neue Uploads und vorhandene Dateien zu einer externen Quelle, z. B. zu AWS S3 und S3-kompatiblen Speichern (Hetzner, IONOS, Wasabi …), Google Cloud Storage, FTP, Dropbox oder WebDAV. Danach werden sie als externe Dateien genutzt und belegen keinen Platz mehr auf deinem Hosting. Auf Wunsch werden die Dateien nach Jahr und Monat in Ordner sortiert.

* Im Backend: Einstellungen → „Export“, danach den Export für eine externe Quelle aktivieren
* Dokumentation: [Verzeichnisse](import/directories_de.md) für die externen Quellen, die Dokumentationen der Erweiterungen für ihre Dienste

### … Dateien privat ausliefern

Speichere exportierte Dateien in Speichern, die nicht öffentlich sind. Sie werden über den Proxy deiner Website oder über zeitlich begrenzte Links ausgeliefert. Das funktioniert auch für Downloads in WooCommerce.

* Im Backend: „Dateien privat speichern“ in den Export-Einstellungen einer externen Quelle, Einstellungen → „Auslieferung“ → „Proxy“
* Dokumentation: [Private Dateien](private-files_de.md)

### … Zugangsdaten aus der Datenbank heraushalten

Lege die Zugangsdaten der Dienste als Konstanten in deiner `wp-config.php` fest, auch für mehrere Verbindungen je Dienst (z. B. zwei FTP-Server).

* Im Backend: Einstellungen → „Dienste“ zeigt, welche Konstanten genutzt werden
* Dokumentation: [Zugangsdaten über Konstanten](constants_de.md)

### … sicher mit Kopien meiner Website arbeiten

Auf Kopien deiner Website (z. B. einer Staging-Website) werden keine Dateien zu externen Quellen exportiert oder dort gelöscht, geplante Synchronisierungen pausieren. Das passiert automatisch. Lesen und Ausliefern von Dateien ist davon nicht betroffen.

* Im Backend: Auf einer Kopie erscheint ein Hinweis, über den du sie auch als Hauptseite festlegen kannst
* Dokumentation: [WP CLI](cli_de.md) (`wp eml site`)

### … Aufgaben automatisieren

* [WP CLI](cli_de.md) für Importe, Synchronisierungen und Wartung
* [REST API](rest_de.md), um externe Dateien aus anderen Systemen zu verwalten
* [Abilities](abilities_de.md) für KI-Agenten und den MCP-Adapter

## Schnell finden

* **Dienst wählen:** Medien → „Externe Dateien hinzufügen“ zeigt alle Dienste als Kacheln. Icons zeigen, welche Funktionen ein Dienst bietet (Synchronisierung, Export, private Dateien, Zugangsdaten per Konstante). Mit dem Filter „Funktion“ über den Kacheln zeigst du nur Dienste mit einer bestimmten Funktion. Dienste aus Erweiterungen, die nicht installiert sind, sind als „Erweiterung“ markiert und lassen sich dort installieren.
* **Befehlspalette:** Drücke auf jeder Seite des Backends `Strg + K` (`Cmd + K` unter macOS) und gib z. B. „FTP“, „S3“, „Export“ oder den Namen einer deiner externen Quellen ein. Dienste werden auch unter anderen Namen gefunden, z. B. „Nextcloud“ für WebDAV oder „Hetzner“ für die S3-Erweiterung. Die Befehlspalette gibt es seit WordPress 6.9 auf allen Seiten des Backends.
* **Hilfe:** Der Reiter „Hilfe“ oben rechts auf den Seiten dieses Plugins beginnt mit „Was ist möglich?“.

## Aufbau der Einstellungen

| Reiter | Enthält |
|---|---|
| Allgemeine Einstellungen | Grundeinstellungen für den Import und den Dialog |
| Dienste | Übersicht aller Dienste und ihre Einstellungen |
| Auslieferung | Einstellungen je Dateityp (Bilder, Videos, PDF …) und für den Proxy |
| Export | Export von Dateien zu externen Quellen |
| Synchronisierung | Synchronisierung externer Quellen |
| Erweitert | Dateien, Plugin, Abilities, Verschiedenes, Berechtigungen |
| Status | Protokoll, Warteschlange, Statistik |
| Integrationen | Einstellungen für andere Plugins wie WooCommerce, Polylang oder Instant Images (nur wenn eines davon aktiv ist) |
