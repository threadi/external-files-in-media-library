# Import von HTTP

Hier wird beschrieben wie Du Dateien von einer HTTP-URL importieren kannst.

## Voraussetzungen

Die URL muss unter einem der folgenden Protokolle erreichbar sein:

* `http://`
* `https://`

Die Datei muss eines der zulässigen Formate haben. Diese kannst Du in den Plugin-Einstellungen festlegen.

Das Hosting muss die WordPress-Funktionen wp_remote_* unterstützen (z.B. mit curl oder alternativen Bibliotheken).

## Hinweis

Wenn die Quell-Datei ohne SSL ausgeliefert wird, deine Website jedoch SSL verwendet, wird die Datei in jedem Fall
lokal gespeichert, um Fehlermeldungen beim Besucher im Frontend zu vermeiden.

## Zugangsdaten

Optional kann man für den Import auch Zugangsdaten für eine AuthBasic-Authentifizierung angeben. So importierte Dateien
werden generell lokal gespeichert, da sie sonst für die Besucher der Website nicht aufrufbar wären.

## Beispiele

### Einzelne Datei

Gib für den Import die URL an unter der deine Datei erreichbar ist, z.B.

`https://example.com/example.png`

### Verzeichnis importieren

Gibt für den Import die URL des Verzeichnisses an, dessen Dateien du komplett importieren möchtest. _Voraussetzung für
diesen Import ist, dass unter der URL das DirectoryListing aktiviert ist._ Sprich dazu ggfs. den Support des Hosters an.

Beispiel: `https://example.com/directory-name/`
