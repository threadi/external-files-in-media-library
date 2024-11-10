# Import von SFTP/SSH

Hier wird beschrieben wie Du Dateien von einem SFTP/SSH-Verzeichnis importieren kannst.

## Voraussetzungen

Das SFTP/SSH-Verzeichnis muss für den Import mit einem der folgenden Protokolle erreichbar sein:

* `sftp://`

Es sind immer Zugangsdaten erforderlich. Der Import von anonymen FTP-Verzeichnissen wird nicht unterstützt.

Das Hosting muss die PHP-Bibliotheken _ssh2_connect_ bereitstellen.

## Hinweis

Die Angabe von einer URL mit sichtbaren Zugangsdaten wird _nicht_ unterstützt.

Beispiel: `sftp://login:password@example.com/example.png`

## Beispiele

### Einzelne Datei

Für den Import einer einzelnen Datei von einem SFTP/SSH-Zugang gibst Du die SFTP/SSH-URL dieser Datei zusammen mit den SFTP/SSH-Zugangsdaten an.

Beispiel für eine URL: `sftp://example.com/example.png`

### Verzeichnis importieren

Für den Import aller Dateien eines SFTP/SSH-Verzeichnisses gibst Du die SFTP/SSH-URL dieses Verzeichnisses zusammen mit den SFTP/SSH-Zugangsdaten an.

Beispiel für eine URL: `sftp://example.com/directory-name/`
