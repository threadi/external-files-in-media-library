# Zugangsdaten über Konstanten

Die Zugangsdaten der Dienste können als Konstanten festgelegt werden, z. B. in der `wp-config.php`. Sie stehen dann nicht in der Datenbank, werden nie an den Browser geschickt und gelten für jede Verbindung zu diesem Dienst: in der Verzeichnisansicht, beim Import, bei der Synchronisierung, beim Export und bei der Auslieferung über den Proxy.

## Aufbau der Namen

`EFML_` + Name des Dienstes + `_` + Name des Feldes, alles in Großbuchstaben. Bindestriche werden zu Unterstrichen.

Beispiel: das Feld `password` des Dienstes `ftp` wird über `EFML_FTP_PASSWORD` festgelegt.

## Regeln

* **Alle Pflichtfelder:** Die Konstanten eines Dienstes werden nur genutzt, wenn für **alle Pflichtfelder** dieses Dienstes eine Konstante gesetzt ist. Fehlt eine, wird keine einzige Konstante dieses Dienstes genutzt. Die Einstellungsseite des Dienstes zeigt dann, welche Konstanten fehlen.
* **Optionale Felder:** Sie können zusätzlich als Konstante gesetzt werden, z. B. die Auth-Basic-Zugangsdaten bei der REST-API.
* **Konstanten gewinnen:** Ihre Werte ersetzen die Werte aus den Einstellungen, den Benutzerprofilen und den gespeicherten externen Quellen.
* **Nur für den festgelegten Server:** Bei Diensten mit Server-Feld werden die Zugangsdaten nur für Verbindungen zu dem Host genutzt, der in der Konstante für den Server steht. Für andere Server werden sie nie verwendet.
* **Geheime Werte im Browser:** Zugangsdaten werden im Formular nicht angezeigt, dort steht stattdessen der Name der Konstante. Der Server bleibt sichtbar, damit klar ist, welcher genutzt wird.
* **Speicherort fest auf „Eines für die Website“:** Sind die Konstanten eines Dienstes vollständig, steht der Speicherort der Zugangsdaten fest auf „Eines für die Website“ und lässt sich nicht ändern. Der vorher gespeicherte Speicherort bleibt erhalten und gilt wieder, sobald die Konstanten entfernt werden.
* **Einstellungen und Benutzerprofile:** Bei „Eines für die Website“ und „Benutzerspezifisch“ erscheinen statt der Eingabefelder Hinweise auf die Konstanten. Die in der Datenbank gespeicherten Werte bleiben unverändert und gelten wieder, sobald die Konstanten entfernt werden.

## Gespeicherte externe Quellen

* **Quellen auf dem Server der Konstanten** funktionieren weiter. Ihre gespeicherten Zugangsdaten werden ignoriert, es gelten die Konstanten.
* **Quellen auf anderen Servern** sind nicht nutzbar, solange die Konstanten gesetzt sind: Öffnen, Import, Synchronisierung und Export sind gesperrt, auf diesen Servern wird nichts geschrieben oder gelöscht. Jeder Versuch wird protokolliert. In der Liste der externen Quellen steht ein Hinweis, Bearbeiten, Umbenennen und Löschen bleiben möglich.
* **Nichts wird gelöscht:** Die Quellen und ihre Einstellungen bleiben erhalten und funktionieren wieder, sobald die Konstanten entfernt werden.
* **Bereits importierte Dateien** dieser Quellen werden weiterhin mit ihren eigenen Zugangsdaten ausgeliefert.
* **Manuelle Angaben:** Auch über „URL hinzufügen“ lassen sich keine URLs anderer Server dieses Dienstes importieren, selbst mit eigenen Zugangsdaten (z. B. `ftp://` oder `sftp://` bei gesetzten FTP-Konstanten). Normale `http(s)://`-URLs sind davon nicht betroffen, auch nicht durch die Konstanten der REST-API.

## Mehrere Verbindungen

Bei allen Diensten mit Zugangsdaten in ihren Feldern (alle außer Dropbox und der Erweiterung für Google Drive) kannst du mehrere Verbindungen festlegen, z. B. zwei FTP-Server oder zwei Buckets.

1. Liste die Namen der Verbindungen kommagetrennt in `EFML_` + Name des Dienstes + `_CONNECTIONS` auf.
2. Lege die Konstanten jeder Verbindung fest: `EFML_` + Name des Dienstes + `_` + Name der Verbindung + `_` + Name des Feldes.

```php
define( 'EFML_FTP_CONNECTIONS', 'shop,archiv' );

define( 'EFML_FTP_SHOP_SERVER', 'ftps://shop.example.com' );
define( 'EFML_FTP_SHOP_LOGIN', 'shop-login' );
define( 'EFML_FTP_SHOP_PASSWORD', 'shop-passwort' );

define( 'EFML_FTP_ARCHIV_SERVER', 'sftp://archiv.example.com' );
define( 'EFML_FTP_ARCHIV_LOGIN', 'archiv-login' );
define( 'EFML_FTP_ARCHIV_PASSWORD', 'archiv-passwort' );
```

* **Namen:** Buchstaben, Ziffern, Bindestriche und Unterstriche, die Groß- und Kleinschreibung spielt keine Rolle. In den Namen der Konstanten werden Bindestriche zu Unterstrichen (`mein-shop` → `EFML_FTP_MEIN_SHOP_SERVER`). Der Name `default` ist reserviert. Namen, die zu den Namen anderer Konstanten führen würden, lassen sich nicht nutzen. Die Einstellungsseite des Dienstes zeigt, welche Namen nicht nutzbar sind.
* **Standardverbindung:** Die Konstanten ohne Namen einer Verbindung (z. B. `EFML_FTP_SERVER`) funktionieren weiterhin. Sie erscheinen als Verbindung „Standard“ und lassen sich mit benannten Verbindungen kombinieren.
* **Alle Pflichtfelder je Verbindung:** Jede Verbindung braucht Konstanten für alle Pflichtfelder. Unvollständige Verbindungen werden nicht genutzt, die Einstellungsseite zeigt, welche Konstanten fehlen.
* **Verbindung auswählen:** Ist mehr als eine Verbindung vollständig, zeigt das Formular für eine neue externe Quelle das Feld „Verbindung“. Die Werte der Verbindungen erscheinen im Formular nicht. Die gewählte Verbindung wird mit der externen Quelle und mit den daraus importierten Dateien gespeichert. Die Abilities nutzen dafür das Feld `efml_connection`.
* **Ohne gewählte Verbindung** (z. B. bei vorher gespeicherten externen Quellen, manuellen Importen über „URL hinzufügen“ oder vorher importierten Dateien) gilt die erste Verbindung, deren Server (bzw. Bucket usw.) passt, beginnend mit der Standardverbindung.
* **Nur für den eigenen Server:** Jede Verbindung gilt nur für ihren eigenen Server bzw. Bucket. Externe Quellen und URLs, die zu keiner Verbindung passen, sind wie oben beschrieben gesperrt. Eine externe Quelle mit gewählter Verbindung ist auch dann gesperrt, wenn ihr Server nicht zu dieser Verbindung passt.
* **Nur eine vollständige Verbindung:** Sie gilt wie die Konstanten ohne Namen einer Verbindung, eine Auswahl erscheint nicht.

## Konstanten der Dienste

### FTP, FTPS und SFTP

| Konstante | Pflicht | Inhalt |
|---|---|---|
| `EFML_FTP_SERVER` | ja | Server mit Protokoll, z. B. `ftps://ftp.example.com` oder `sftp://example.com:2222` |
| `EFML_FTP_LOGIN` | ja | Benutzername |
| `EFML_FTP_PASSWORD` | ja | Passwort |

```php
define( 'EFML_FTP_SERVER', 'ftps://ftp.example.com' );
define( 'EFML_FTP_LOGIN', 'mein-login' );
define( 'EFML_FTP_PASSWORD', 'mein-passwort' );
```

### WordPress REST API

| Konstante | Pflicht | Inhalt |
|---|---|---|
| `EFML_REST_SERVER` | ja | URL der WordPress-Website, z. B. `https://example.com` |
| `EFML_REST_LOGIN` | nein | Auth-Basic-Login |
| `EFML_REST_PASSWORD` | nein | Auth-Basic-Passwort |

### ZIP

| Konstante | Pflicht | Inhalt |
|---|---|---|
| `EFML_ZIP_SERVER` | ja | URL der ZIP-Datei |
| `EFML_ZIP_LOGIN` | nein | Auth-Basic-Login |
| `EFML_ZIP_PASSWORD` | nein | Auth-Basic-Passwort |
| `EFML_ZIP_ZIP_PASSWORD` | nein | Passwort der ZIP-Datei |

### CSV- und XML-Listen

| Konstante | Pflicht | Inhalt |
|---|---|---|
| `EFML_CSV_LIST_URL` bzw. `EFML_XML_LIST_URL` | ja | URL der Liste |
| `EFML_CSV_LOGIN` bzw. `EFML_XML_LOGIN` | nein | Auth-Basic-Login |
| `EFML_CSV_PASSWORD` bzw. `EFML_XML_PASSWORD` | nein | Auth-Basic-Passwort |

### YouTube

| Konstante | Pflicht | Inhalt |
|---|---|---|
| `EFML_YOUTUBE_CHANNEL_ID` | ja | ID des Kanals |
| `EFML_YOUTUBE_API_KEY` | ja | API-Schlüssel |

### Dropbox

Dropbox nutzt OAuth. Als Konstanten lassen sich nur die Zugangsdaten der Dropbox-App festlegen. Die Verbindung selbst wird weiterhin über „Verbinden“ hergestellt, die Tokens werden in der Datenbank gespeichert, weil Dropbox sie regelmäßig erneuert.

| Konstante | Pflicht | Inhalt |
|---|---|---|
| `EFML_DROPBOX_API_KEY` | ja | App key |
| `EFML_DROPBOX_API_SECRET` | ja | App secret |

### Dienste aus Erweiterungen

Dienste aus Erweiterungen (z. B. AWS S3, Google Drive, WebDAV) nutzen dasselbe Schema. Welche Konstanten es dort gibt, steht in der Dokumentation der jeweiligen Erweiterung.

Ohne Konstanten sind die Dienste Lokal und Multisite, weil sie keine Zugangsdaten nutzen.

## Hooks für Entwickler

* `efml_service_field_constant_name`: Namen einer Konstante ändern. Der Name der Verbindung ist der vierte Parameter (`default` bei den Konstanten ohne Namen einer Verbindung).
* `efml_service_connections_constant_name`: Namen der Konstante mit der Liste der Verbindungen ändern.
* `efml_service_constants_connection`: Entscheiden, welche Verbindung für bestimmte Felder und eine URL gilt (`null` zurückgeben, um keine Konstanten zu nutzen).
* `efml_service_required_fields_for_constants`: Liste der Felder ändern, die alle als Konstante gesetzt sein müssen.
* `efml_service_url_matching_constants`: Entscheiden, ob eine URL zum Server der Konstanten passt.
* `efml_service_names_for_constants`: Festlegen, welche Dienste ihre Konstanten für ein Protokoll bereitstellen (z. B. `sftp` → `ftp`, `http` → `rest`).
* `efml_service_fields`: Felder eines Dienstes samt Werten ändern.
* `efml_service_names_for_blocking`: Festlegen, zu welchen Diensten die URLs eines Protokolls gehören und damit bei gesetzten Konstanten auf deren Server beschränkt sind (z. B. `sftp` → `ftp`).
* `efml_service_fields_matching_constants`: Entscheiden, ob Felder zum Ziel der Konstanten einer Verbindung passen (z. B. derselbe Bucket). Der Name der Verbindung ist der vierte Parameter.
* `efml_source_blocked_by_constants`: Entscheiden, ob eine gespeicherte externe Quelle wegen der Konstanten gesperrt ist.

Für einen eigenen Dienst ohne Zugangsdaten `protected bool $fields_via_constants = false;` setzen.

Weitere Möglichkeiten für Dienste aus Erweiterungen:

* `protected array $constants_target_fields = array( 'bucket' );`: Felder, die das Ziel neben dem Server bestimmen. Gespeicherte Quellen und manuelle Angaben mit anderen Werten sind gesperrt, die Konstanten werden für sie nie genutzt.
* `protected bool $constants_in_fields = false;`: Die Konstanten sind keine Werte der Felder (z. B. API-Zugangsdaten für OAuth), dazu `get_constant_field_definitions()` überschreiben. Solche Dienste unterstützen nur die Konstanten ohne Namen einer Verbindung (siehe `supports_constant_connections()`).
* `is_fields_matching_connection()`: Überschreiben, um die Zielfelder einer Verbindung anders zu vergleichen (z. B. URLs in normalisierter Form).
* `get_mode_for_constants()`: Speicherort, der bei gesetzten Konstanten gilt (Standard `global`).
* `get_required_fields_for_constants()`: Liste der Pflichtfelder ändern.
* `add_constant_hint_setting()`: Hinweis statt einer Einstellung anzeigen, wenn der Wert per Konstante gesetzt ist. Hat der Dienst ein Feld mit der URL des Servers, `protected string $constants_host_field = '<feldname>';` setzen, damit die Zugangsdaten nur für diesen Server genutzt werden.
