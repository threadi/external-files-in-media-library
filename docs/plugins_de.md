# Andere Plugins

External Files in Media Library arbeitet mit vielen Plugins zusammen. Plugins, die es unterstützt, sind in der Liste
deiner Plugins mit „Unterstützt von External Files in Media Library“ markiert. Du kannst diesen Hinweis ausblenden:
Einstellungen → „Erweitert“ → „Plugin“ → „Unterstützte Plugins markieren“.

Entwickler können eigene Plugins über den Hook `efml_supported_plugin_files` markieren (Liste von Plugin-Dateien wie
`my-plugin/my-plugin.php`).

Hier findest du, wie diese Plugins mit externen Dateien arbeiten.

## Umstieg von anderen Offload-Plugins

### Advanced Media Offloader

Mit Advanced Media Offloader ausgelagerte Dateien kannst du als externe Dateien übernehmen, ohne sie erneut hochzuladen
(siehe [Umzug](migration_de.md)). Solange beide Plugins aktiv sind, lagert Advanced Media Offloader externe Dateien
dieses Plugins nicht aus.

### Exmage – WordPress image links

Bilder, die du über Exmage als Link eingefügt hast, lassen sich in externe Dateien dieses Plugins umwandeln. Ein Hinweis
im Backend bietet diesen Umzug an, solange es solche Bilder gibt.

### Media Cloud Sync

Media Cloud Sync verwendet die externen Dateien dieses Plugins nicht für seine eigenen Aufgaben, lagert sie also z. B.
nicht noch einmal aus.

Weitere Offload-Plugins, deren Dateien du übernehmen kannst: WP Offload Media (auch Pro), Stowaway und Offload Media –
Cloud Storage. Siehe [Umzug](migration_de.md).

## Ordner, Kategorien und Galerien

Diese Plugins ordnen deine Mediathek in Ordnern, Kategorien oder Galerien. Wenn du Dateien aus einer externen Quelle
importierst, wählst du den Ordner, die Kategorie oder die Galerie im Dialog des Imports. Für die Synchronisierung eines
externen Verzeichnisses wählst du sie in den Einstellungen der Synchronisierung: Neue Dateien landen dann dort.

| Plugin | Auswahl beim Import | Auswahl für die Synchronisierung |
|---|---|---|
| CatFolders | Ordner | Ordner |
| Download List with Icons | Download-Liste | Download-Liste |
| Enhanced Media Library | Kategorie | Kategorie |
| FileBird Lite | Ordner | Ordner |
| Folderly | Ordner | Ordner |
| Folders | Ordner | Ordner |
| iFolders | Ordner | Ordner |
| Media Library Assistant | Kategorie und Schlagwort | Kategorie und Schlagwort |
| Media Library Organizer | Kategorie | Kategorie |
| NextGEN Gallery | – | Galerie |
| Real Media Library (Lite) | Ordner | Ordner |
| Robo Gallery | – | Galerie |

In den Listen von Download List with Icons bekommen externe Dateien das Attribut `rel="external"`.

## Bildoptimierer und moderne Bildformate

Bildoptimierer arbeiten mit den lokalen Dateien von Uploads. Ist eines dieser Plugins aktiv, wartet der Export eines
neuen Uploads, bis der Bildoptimierer fertig ist. Danach werden die optimierten Dateien exportiert, zusammen mit ihren
Varianten in anderen Formaten (z. B. WebP oder AVIF) und den Sicherungen des Bildoptimierers. Bereits exportierte Bilder
kannst du später über lokale Arbeitskopien optimieren (siehe [Funktionen](features_de.md)).

- **Converter for Media:** Seine WebP- und AVIF-Varianten werden neben dem Bild exportiert. Nach einer späteren
  Optimierung nutze Einstellungen → „Export“ → „Optimierte Bilder exportieren“.
- **EWWW Image Optimizer** (auch die Cloud-Version): Optimierte Dateien, Varianten wie `bild.jpg.webp` und seine
  Sicherungen werden exportiert. „Optimize now!“ und „Re-optimize“ in der Mediathek funktionieren auch bei exportierten
  Bildern.
- **Imagify:** Der Export wartet, bis die Optimierung über seine API fertig ist. Varianten wie `bild.jpg.webp` und
  `bild.jpg.avif` werden exportiert.
- **Modern Image Formats** des WordPress Performance Teams: Größen in WebP oder AVIF werden wie andere Größen exportiert,
  zusätzliche Varianten des Bilds in diesen Formaten werden exportiert und über den Proxy ausgeliefert.
- **ShortPixel Image Optimizer:** Der Export wartet, bis die Optimierung über seine API fertig ist. Varianten wie
  `bild.webp` werden exportiert.
- **Smush** (auch Pro): Optimierte Dateien und seine Sicherung des Originalbilds (z. B. `bild.bak.jpg`) werden
  exportiert.
- **TinyPNG – JPEG, PNG & WebP image compression:** Komprimierte Dateien und Varianten wie `bild.webp` und `bild.avif`
  werden exportiert.

## Bildbearbeitung und Medienverwaltung

### Enable Media Replace

Das Ersetzen von Dateien mit Enable Media Replace funktioniert nur mit lokalen Dateien. Bei externen Dateien sind seine
Box auf der Seite des Anhangs und seine Links in der Mediathek ausgeblendet.

### Export Media Library

Export Media Library packt die Dateien deiner Mediathek in eine ZIP-Datei. Externe Dateien bleiben dabei außen vor, weil
sie keine lokale Datei haben.

### Image Watermark

Neue Uploads bekommen ihr Wasserzeichen, bevor sie exportiert werden. Bei exportierten Bildern kannst du das
Wasserzeichen auch nachträglich hinzufügen oder entfernen, einzeln und über die Massenaktion in der Mediathek: Vorher
wird eine lokale Arbeitskopie gespeichert, danach werden die geänderten Dateien exportiert. Die Sicherung des
Originalbilds, die Image Watermark anlegt, wird neben dem Bild exportiert (als `bild.iw-backup.jpg`), damit du das
Wasserzeichen später wieder entfernen kannst.

Image Watermark liest das Bild des Wasserzeichens nur aus deinem Upload-Verzeichnis. Deshalb wird dieses Bild nicht
exportiert. Wurde es schon exportiert (z. B. direkt nach dem Hochladen in den Einstellungen von Image Watermark), wird es
als lokale Datei zurückgeholt, sobald du es als Wasserzeichen speicherst.

Geänderte Bilder bekommen eine neue Version in den URLs des Proxys, die WordPress für die Ausgabe erzeugt (z. B. im
`srcset`), damit Browser die geänderte Datei sofort laden. Das gilt auch für Bildoptimierer und andere Plugins, die
Dateien ändern.

### Instant Images

Bilder, die du über Instant Images von Unsplash oder Pexels einfügst, kannst du als externe Dateien speichern: Sie
werden dann von ihrer Plattform ausgeliefert statt aus deinem Upload-Verzeichnis, wie es die Richtlinien von Unsplash
verlangen. Bilder von Pixabay bleiben lokale Dateien, weil Pixabay das verbietet. Ein Hinweis im Backend bietet diese
Unterstützung an, solange keine Plattform aktiviert ist.

### Media Bulk Download

Media Bulk Download packt die ausgewählten Dateien deiner Mediathek in eine ZIP-Datei. Externe Dateien kommen ebenfalls
hinein: Während seiner Anfragen werden sie aus dem Cache des Proxys genommen oder vorübergehend von ihrer externen Quelle
geladen. In der ZIP-Datei haben sie ihren echten Namen. Die Grenzen des Plugins (Anzahl der Dateien, Größe) gelten auch
für sie.

### Media Cleaner

Media Cleaner sucht unbenutzte Medien und Dateien. Exportierte Dateien werden über ihre früheren lokalen URLs in deinen
Inhalten geprüft (auch die ihrer Größen), aus externen Quellen importierte Dateien gelten immer als benutzt (Hook
`efml_media_cleaner_check_external_files`), lokale Kopien und Arbeitskopien exportierter Dateien zeigt sein
Dateisystem-Scan nicht an, und externe Quellen wie FTP werden bei seinen Scans nicht angesprochen. Löschst du eine Datei
in Media Cleaner, wird sie auch auf ihren externen Quellen gelöscht.

### Media File Renamer

Media File Renamer kann auch exportierte Bilder umbenennen (auch Pro): Ihre Dateien werden vorher geladen, die
umbenannten Dateien danach exportiert. Der Proxy findet das Bild weiterhin auch über seinen früheren Namen.

### Perfect Images (früher WP Retina 2x)

Seine Retina-Dateien (`image-300x200@2x.jpg`) und seine WebP- und AVIF-Dateien (`image-300x200.jpg.webp`) werden
zusammen mit dem Bild exportiert, mit ihm gelöscht und mit ihm zurückgeholt. Der Proxy liefert die WebP- und
AVIF-Dateien an Browser aus, die sie unterstützen. Die Retina-Dateien nutzt Perfect Images nur, solange das Bild eine
lokale Datei ist.

„Regenerate“ in seinem Dashboard funktioniert auch für exportierte Bilder: Ihre Dateien werden vorher geladen, die neuen
Größen danach exportiert. Seine anderen Funktionen für einzelne Bilder (Bild ersetzen, die optimierte Neuerstellung und
die Neuerstellung per KI, Retina- und WebP-Dateien erstellen, löschen oder optimieren) sind für externe Dateien nicht
möglich, da sie die Dateien direkt in ihrer externen Quelle ändern würden. Perfect Images zeigt dafür einen Hinweis.

### Regenerate Thumbnails, Force Regenerate Thumbnails und Regenerate Thumbnails Advanced

Alle drei erzeugen die Größen exportierter Bilder neu: Ihr Originalbild wird dafür kurz geladen, die neuen Größen werden
exportiert und die lokalen Dateien danach entfernt (siehe [Funktionen](features_de.md)). Das gilt auch für den
WP-CLI-Befehl von Force Regenerate Thumbnails.

Dateien, die du aus einer externen Quelle importiert hast, haben keine lokalen Dateien: Ihre Größen erzeugt der Proxy.
Regenerate Thumbnails zeigt für sie einen Hinweis, Force Regenerate Thumbnails überspringt sie und Regenerate Thumbnails
Advanced markiert sie als ausgelagert. So werden keine Größen in ihrer externen Quelle erzeugt (z. B. in einem lokalen
Verzeichnis) und die externe Quelle wird nicht kontaktiert (z. B. mit einer anonymen Anmeldung an einem FTP-Server).

### WP Extra File Types

Dateitypen, die du über WP Extra File Types erlaubst, kannst du auch für externe Dateien nutzen.

## Schutz von Dateien

### Prevent Direct Access

Prevent Direct Access schützt lokale Dateien im Ordner `_pda` deines Upload-Verzeichnisses. Auf externen Quellen wirkt
dieser Schutz nicht, deshalb:

- Geschützte Dateien werden nicht exportiert, sie bleiben lokale Dateien. Die Mediathek zeigt ein Schloss bei ihnen.
- Exportierte und andere externe Dateien lassen sich mit Prevent Direct Access nicht schützen. Die Option
  „Direktzugriff beim Anbieter verhindern“ (siehe [Direktzugriff beim Anbieter verhindern](private-files_de.md)) ist
  kein Ersatz: Sie verhindert nur, dass die Dateien direkt beim Anbieter geladen werden, nicht über deine Website.

### Prevent Files / Folders Access

Prevent Files / Folders Access erlaubt den Zugriff auf Dateien der eingestellten Typen in deinem Upload-Verzeichnis nur
angemeldeten Benutzern. Auf externen Quellen wirkt dieser Schutz nicht, deshalb werden Dateien dieser Typen nicht
exportiert, solange die Einschränkung aktiv ist. Die Einstellung für den Export nennt diese Typen, die Mediathek zeigt
ein Schloss bei diesen Dateien.

### WP Consent API

External Files in Media Library ist bei der WP Consent API registriert, da es keine Cookies setzt und keine
personenbezogenen Daten erhebt.

## Pagebuilder

### Brizy

Brizy erzeugt die Bilder für seine Elemente selbst. Für externe Bilder bekommt es dafür eine lokale Datei, die am Ende
der Anfrage wieder gelöscht wird.

### Elementor

YouTube-Videos, die du in deine Mediathek aufgenommen hast, kannst du im Video-Widget von Elementor verwenden: Im
Frontend werden sie als YouTube-Videos angezeigt.

## Statische Websites

### Simply Static

Simply Static erstellt eine statische Kopie deiner Website. Dateien, die über den Proxy ausgeliefert werden, kommen mit in
die statische Website, weil ihre URLs die Form einer Datei haben (z. B. `/emlproxy/bild.jpg`). Dateien, die direkt von
ihrer externen Quelle ausgeliefert werden, bleiben dorthin verlinkt.

Das setzt sprechende Permalinks voraus: Ohne sie enthalten die URLs des Proxys einen Query-String
(`?emlproxy=bild.jpg`), den Simply Static nicht als Datei kopiert. Werkzeuge → „Website-Zustand“ zeigt dann einen
Hinweis. Dateien ohne Direktzugriff beim Anbieter sind nicht Teil der statischen Website, weil sie kein PHP zum Ausliefern hat.

## SEO

### Rank Math und Yoast SEO

Beide Plugins verwalten die Anhangseiten deiner Mediathek selbst (z. B. als Weiterleitung auf die Datei). Deshalb ändert
External Files in Media Library die Links zu Anhangseiten nicht, solange eines von ihnen aktiv ist. Seine eigene
Einstellung für Anhangseiten ist dann schreibgeschützt und nennt das SEO-Plugin.

## Mehrsprachige Websites

### Polylang

Neue externe Dateien kannst du auf einen Schlag in allen Sprachen deiner Website speichern (auch mit Polylang Pro), damit
sie in der Mediathek jeder Sprache verfügbar sind. Schalte das in den Einstellungen ein: „Integrationen“ → „Polylang“.

## Shops

### WooCommerce

- **Produktimport per CSV:** Der Importer von WooCommerce bietet an, Produktbilder und Galeriebilder als externe Dateien
  zu importieren: Sie werden nicht in deine Mediathek hochgeladen, sondern von ihrer Quelle ausgeliefert.
- **Herunterladbare Produkte:** Externe Dateien kannst du als Downloads verwenden. Geschützte Downloads liefert
  WooCommerce nur an Käufer aus, auch wenn der Direktzugriff bei ihrer externen Quelle verhindert ist (siehe
  [Direktzugriff beim Anbieter verhindern](private-files_de.md)). CDNs speichern sie nicht zwischen.
