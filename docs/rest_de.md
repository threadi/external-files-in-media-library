# WordPress REST API

Mithilfe der WordPress REST API kann man externe Dateien in der Mediathek
auch aus der Ferne verwalten. Dazu stellt das Plugin einen Endpunkt zur Verfügung
der mit verschiedenen Methoden angesprochen werden kann.

Der Zugriff ist nur mit gültiger Autorisierung möglich. Das kann z.B. mit dem
von WordPress bereitgestellten Anwendungspasswort erfolgen.

## Der Endpunkt

`/wp-json/efml/v1/file/`

## Methoden

### POST

* Erstellt einen neuen Eintrag zu einer externen URL in der Mediathek
* Parameter:
  * "url" mit der externen URL
  * "login" mit einem Login (optional)
  * "password" mit dem dazugehörigen Passwort (optional)

### GET

* Gibt zurück, ob eine angegebene externe URL in der Mediathek existiert
* Parameter:
  * "url" mit der externen URL

### DELETE

* Löscht eine angegeben externe URL
* Parameter:
    * "url" mit der externen URL

## Hinweise

* Es werden alle vom Plugin unterstützten Protokolle auf diesem Weg unterstützt.
* Es ist nicht möglich auf diesem Weg automatisch synchronisierte Dateien zu löschen.
