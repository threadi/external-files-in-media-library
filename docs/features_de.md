# Was ist möglich?

External Files in Media Library kann mehr, als einzelne URLs in deine Mediathek aufzunehmen. Diese Seite zeigt die Funktionen nach Zielen geordnet und führt dich zu den Einstellungen und zur ausführlichen Dokumentation.

**Du weißt nicht, wo du anfangen sollst?** Der Schnellstart führt dich Schritt für Schritt durch die häufigsten Aufgaben und richtet alles für dich ein: Medien → „Externe Dateien hinzufügen“ → Kachel „Schnellstart“. Siehe [Schnellstart](setup_de.md).

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

Mit der Option „Export im Hintergrund“ in den Export-Einstellungen werden neue Uploads nicht während des Uploads exportiert, sondern gleich danach im Hintergrund, die ältesten zuerst; bis dahin werden sie als lokale Dateien genutzt. Dateien, die schon in deiner Mediathek liegen, exportierst du mit einem Klick: Einstellungen → „Export“ → „Vorhandene Dateien exportieren“. Sie werden nacheinander im Hintergrund exportiert, den Fortschritt siehst du in der Admin-Leiste. Ein Klick darauf öffnet die Warteschlange, dort siehst du jede Datei und kannst Dateien, die nicht exportiert werden konnten, erneut exportieren. Der Schnellstart bietet das ebenfalls als Option an.

Bilder werden zusammen mit ihren Größen und ihrem Original exportiert, wenn der Dienst der externen Quelle das unterstützt (lokales Verzeichnis, FTP, Dropbox, AWS S3 und S3-kompatible Speicher, Google Cloud Storage, WebDAV, Google Drive). Die Größen werden genau wie die Bilder selbst ausgeliefert: über den Proxy deiner Website, wenn er für Bilder aktiviert ist, sonst aus der externen Quelle. Deine Bilder bekommen ein `srcset` mit ihren Größen. Private Dateien liefern ihre Größen immer über den Proxy aus. Bilder, die schon früher exportiert wurden, bekommen ihre Größen über Einstellungen → „Export“ → „Größen exportierter Bilder exportieren“. Erzeugst du die Größen exportierter Bilder neu, z. B. mit `wp media regenerate` oder dem Plugin „Regenerate Thumbnails“, lädt das Plugin dafür kurz das Originalbild herunter, exportiert die neuen Größen und entfernt die lokalen Dateien wieder. Ersetzte Größen bleiben in der externen Quelle, weil deine Inhalte sie noch nutzen könnten, und werden mit dem Bild gelöscht. Das gilt auch für den Bildeditor von WordPress (Zuschneiden, Drehen, Skalieren und „Bild wiederherstellen“): Das bearbeitete Bild wird unter seinem neuen Namen exportiert, die bisherigen Dateien bleiben als Sicherung in der externen Quelle. Auch der Block-Editor kann exportierte Bilder zuschneiden und drehen. Plugins, die Dateien umbenennen, werden ebenfalls unterstützt: „Media File Renamer“ mit jeder externen Quelle, andere Plugins, wenn sie die Dateien erreichen (z. B. mit dem lokalen Verzeichnis als externer Quelle oder mit nach dem Export behaltenen lokalen Kopien). Die umbenannten Dateien werden exportiert, die bisherigen in der externen Quelle gelöscht. Löschst du eine exportierte Datei, wird sie überall gelöscht: in jeder externen Quelle, in die sie exportiert wurde (auch wenn ihr Export inzwischen ausgeschaltet ist), mit ihren Größen und früheren Dateien, und ihre lokalen Kopien. Dateien, die gerade nicht gelöscht werden können (z. B. weil die Erweiterung des Dienstes nicht aktiv ist), werden später über die Warteschlange gelöscht (Einstellungen → „Status“ → „Warteschlange“). Schlägt der Export eines neuen Uploads fehl (z. B. weil die externe Quelle kurz nicht erreichbar ist oder viele Dateien auf einmal hochgeladen werden), bleibt die Datei lokal und wird über die Warteschlange erneut exportiert; Größen, die nicht exportiert werden konnten, werden dreimal im Abstand von 10 Minuten erneut exportiert. Ist ein Plugin aktiv, das die lokalen Dateien nach dem Upload optimiert oder umbenennt (Imagify, ShortPixel, Smush, EWWW Image Optimizer, TinyPNG, Converter for Media, Media File Renamer), werden neue Uploads 5 Minuten später exportiert, damit die optimierten bzw. umbenannten Dateien exportiert werden. Bildoptimierer, die neben deinen Bildern Varianten in modernen Formaten (WebP, AVIF) anlegen (Imagify, EWWW Image Optimizer, ShortPixel, TinyPNG, Converter for Media, Modern Image Formats), werden ebenfalls unterstützt: Die Varianten werden mit dem Bild und seinen Größen exportiert, mit ihm gelöscht und mit ihm zurückgeholt. Der Proxy liefert sie Browsern, die ihr Format unterstützen, unter derselben URL wie das Bild aus; ohne Proxy werden die Bilder in ihrem eigenen Format ausgeliefert. Bei Imagify und ShortPixel, die über ihre API optimieren, wartet der Export, bis sie das Ende der Optimierung melden. Uploads, deren Export noch wartet, zeigt die Mediathek mit „Wird exportiert“ an. Seit WordPress 7.1 erstellt der Block-Editor die Größen hochgeladener Bilder in deinem Browser: solche Uploads werden exportiert, sobald der Browser ihre Größen gespeichert hat (wird der Browser vorher geschlossen, nach einer Stunde mit auf dem Server erstellten Größen).

Dateien, die du vor ihrem Export in deine Inhalte eingefügt hast, funktionieren weiter: Ihre lokalen URLs (auch die ihrer Größen) werden bei der Ausgabe durch ihre aktuellen URLs ersetzt. Deine Inhalte in der Datenbank werden dabei nicht verändert.

Du willst das Auslagern beenden oder das Plugin deaktivieren? Hol die exportierten Dateien mit einem Klick zurück: Einstellungen → „Export“ → „Exportierte Dateien zurückholen“. Sie werden heruntergeladen und als lokale Dateien an ihrem ursprünglichen Pfad gespeichert, ihre Größen werden neu erzeugt. Danach sind sie normale lokale Dateien, und deine Inhalte funktionieren auch ohne das Plugin. Der Export wird dafür ausgeschaltet, die Kopien in den externen Quellen bleiben erhalten oder werden gelöscht, wie du es wählst. Deaktivierst du das Plugin, während es exportierte Dateien gibt, bietet dir ein Dialog vorher das Zurückholen an. Auch bei der Deinstallation und beim Zurücksetzen des Plugins werden exportierte Dateien zurückgeholt und nie gelöscht.

Kann die primäre externe Quelle für Exporte nicht genutzt werden (z. B. weil die Erweiterung ihres Dienstes deaktiviert wurde), werden keine Dateien exportiert, auch nicht zu den anderen externen Quellen. Sie bleiben lokale Dateien, ein Hinweis im Backend nennt den Grund. Brauchst du die externe Quelle nicht mehr, schalte ihren Export in der Liste deiner externen Quellen aus. Deine Einstellungen bleiben erhalten: Sobald der Grund behoben ist, funktioniert der Export wieder, und „Vorhandene Dateien exportieren“ holt die zwischenzeitlich hochgeladenen Dateien nach.

* Im Backend: Einstellungen → „Export“, danach den Export für eine externe Quelle aktivieren
* Dokumentation: [Verzeichnisse](import/directories_de.md) für die externen Quellen, die Dokumentationen der Erweiterungen für ihre Dienste

### … von einem anderen Offload-Plugin umsteigen

Dateien, die WP Offload Media, Advanced Media Offloader, Stowaway oder Offload Media – Cloud Storage ausgelagert haben, nutzt du ohne erneuten Upload als externe Dateien weiter. Lokale Kopien und die Daten des anderen Plugins bleiben erhalten.

* Im Backend: Schnellstart → „Von einem anderen Offload-Plugin umsteigen“
* Dokumentation: [Umzug](migration_de.md)

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

* **Schnellstart:** Medien → „Externe Dateien hinzufügen“ → Kachel „Schnellstart“ führt dich durch die häufigsten Aufgaben, siehe [Schnellstart](setup_de.md).
* **Dienst wählen:** Medien → „Externe Dateien hinzufügen“ zeigt alle Dienste als Kacheln. Icons zeigen, welche Funktionen ein Dienst bietet (Synchronisierung, Export, private Dateien, Zugangsdaten per Konstante). Mit dem Filter „Funktion“ über den Kacheln zeigst du nur Dienste mit einer bestimmten Funktion. Dienste aus Erweiterungen, die nicht installiert sind, sind als „Erweiterung“ markiert und lassen sich dort installieren. Warum manche Dienste eine Erweiterung brauchen, steht unter [Erweiterungen](extensions_de.md).
* **Befehlspalette:** Drücke auf jeder Seite des Backends `Strg + K` (`Cmd + K` unter macOS) und gib z. B. „FTP“, „S3“, „Export“ oder den Namen einer deiner externen Quellen ein. Dienste werden auch unter anderen Namen gefunden, z. B. „Nextcloud“ für WebDAV oder „Hetzner“ für die S3-Erweiterung. Die Befehlspalette gibt es seit WordPress 6.9 auf allen Seiten des Backends. Sie findet auch einzelne Einstellungen, z. B. „Offload“ oder „Timeout“, und öffnet sie hervorgehoben.
* **Suche in den Einstellungen:** Das Feld oben rechts in den Einstellungen filtert beim Tippen die Einstellungen des aktuellen Reiters. Die Reiter zeigen die Zahl ihrer Treffer, eine Liste unter den Einstellungen die Treffer in anderen Reitern und Seiten des Plugins. Einstellungen, die erst sichtbar sind, wenn eine andere Einstellung aktiviert ist, stehen mit einem Link auf diese Einstellung in der Liste.
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
