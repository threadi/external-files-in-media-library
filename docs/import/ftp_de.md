# Import von FTP

Hier wird beschrieben wie du Dateien von einem FTP-Verzeichnis importieren kannst.

## Voraussetzungen

Das FTP-Verzeichnis muss für den Import mit einem der folgenden Protokolle erreichbar sein:

* `ftp://`
* `ftps://`

Es sind immer Zugangsdaten erforderlich. Der Import von anonymen FTP-Verzeichnissen wird nicht unterstützt.

Das Hosting muss die PHP-Bibliotheken _ftp_connect_ bzw. _ftp_ssl_connect_ bereitstellen. Bei Fragen dazu wende dich
an den Support deines Hosters.

## Hinweis

Die Angabe von einer URL mit sichtbaren Zugangsdaten wird _nicht_ unterstützt.

Beispiel: `ftp://login:password@example.com/example.png`

## Beispiele

### Einzelne Datei

Für den Import einer einzelnen Datei von einem FTP-Zugang gibst du die FTP-URL dieser Datei zusammen mit den
FTP-Zugangsdaten an.

Beispiel für eine URL: `ftp://example.com/example.png`

### Verzeichnis importieren

Für den Import aller Dateien eines FTP-Verzeichnisses gibst du die FTP-URL dieses Verzeichnisses zusammen mit den
FTP-Zugangsdaten an.

Beispiel für eine URL: `ftp://example.com/directory-name/`
