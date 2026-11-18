# Live-Tests mit echten Konten

Die Live-Tests prüfen das Plugin und seine Erweiterungen mit deinen echten Konten der Dienste: die Verbindung, die
Verzeichnisansicht, den Import, die Synchronisierung, den Export (öffentlich und privat), die Auslieferung an Besucher
und die Berechtigungen. Sie ersetzen die üblichen Tests von Hand nach einer Änderung.

Es gibt zwei Arten von Live-Tests, beide nutzen dieselbe Datei `.env.live`:

| | PHPUnit (`composer test-live`) | Playwright (`composer test-live-e2e`) |
|---|---|---|
| Läuft in | dem WordPress der Unit-Tests | deiner Entwicklungsseite |
| Testet | das Plugin über seine Abilities und Klassen | das Backend im Browser wie ein Benutzer |
| Braucht | einmal `composer test-install-live` | das Plugin [EFML Dev Credentials](#efml-dev-credentials) auf der Seite |

Getestet werden nur Dienste, deren Zugangsdaten in `.env.live` vollständig sind, alle anderen werden übersprungen. Ohne
vollständige Zugangsdaten zeigen die Tests einen Hinweis und machen nichts.

## .env.live

Kopiere `.env.live.example` im Verzeichnis des Plugins nach `.env.live` und trage die Zugangsdaten der Dienste ein, die
du testen möchtest. Die Datei steht in `.gitignore` und ist nie Teil des Release. Du kannst sie auch außerhalb des
Repositorys ablegen und ihren Pfad in der Umgebungsvariable `EFML_LIVE_ENV` angeben.

- Die Schlüssel sind die [Konstanten](constants_de.md) des Plugins und seiner Erweiterungen
  (`EFML_<DIENST>_<FELD>`), dazu einige Einstellungen der Tests (`EFML_LIVE_…`).
- Werte aus der Umgebung haben Vorrang vor den Werten der Datei.
- Schlüssel mit der Endung `_FILE` lesen ihren Wert aus einer Datei, z. B.
  `EFML_GOOGLE_CLOUD_STORAGE_AUTHENTICATION_JSON_FILE=/pfad/zu/service-account.json`.
- Dropbox und Google Drive nutzen OAuth: Verbinde sie einmal auf einer Website und kopiere das Refresh-Token in
  `.env.live` (`EFML_LIVE_DROPBOX_REFRESH_TOKEN`, `EFML_LIVE_GOOGLE_DRIVE_REFRESH_TOKEN`). Die Seite
  „Werkzeuge > EFML Dev Credentials“ des Plugins unten zeigt sie dir an.

Die Tests laden Dateien hoch, deren Namen mit `efml-live-` beginnen, und löschen sie danach wieder, auch auf den
externen Quellen. Nutze Testverzeichnisse, Test-Buckets oder Testkonten.

## PHPUnit

```bash
composer test-install-live   # einmalig: das WordPress der Tests inkl. der Erweiterungen von GitHub
composer test-live           # die Live-Tests ausführen
```

`composer test-install-live` arbeitet wie `composer test-install` und installiert zusätzlich die Erweiterungen in das
WordPress der Tests. Einstellungen in `.env.live` (oder in der Umgebung):

| Einstellung | Bedeutung |
|---|---|
| `EFML_ADDONS_SOURCE` | `github` (Standard von `composer test-install-live`), `none` oder ein Verzeichnis mit deinen Arbeitskopien der Erweiterungen nebeneinander, die verlinkt werden |
| `EFML_ADDONS_REF` | Branch, Tag oder Commit auf GitHub (Standard `master`) |
| `EFML_ADDON_<AWS\|GCS\|DRIVE\|WEBDAV>_SOURCE` / `_REF` | dasselbe für eine einzelne Erweiterung, z. B. der Pfad deiner Arbeitskopie der AWS-Erweiterung |

`composer test-install-addons` installiert nur die Erweiterungen neu (z. B. nach einer Änderung dieser Einstellungen).
Verlinkte Arbeitskopien brauchen ihr Verzeichnis `vendor` (`composer install`).

Was je Dienst getestet wird:

- die Verbindung und die Verzeichnisansicht einer gespeicherten externen Quelle,
- der Import der ersten Datei und ihre Auslieferung an Besucher,
- die Synchronisierung des Verzeichnisses, wenn es höchstens `EFML_LIVE_MAX_SYNC_FILES` Dateien (Standard 20) und keine
  Unterverzeichnisse enthält,
- der Export eines hochgeladenen Bildes inkl. seiner Größen, seine Auslieferung an Besucher und sein Löschen auf der
  externen Quelle,
- dasselbe als private Datei (bei Diensten mit privaten Dateien),
- der Export eines Uploads eines Redakteurs ohne das Recht für den Dienst, der keine Dateien daraus importieren kann,
- die URLs in `EFML_LIVE_URLS` über „URL hinzufügen“.

Erlaubt die externe Quelle eines Dienstes nur private Dateien (z. B. ein Bucket mit „Block public access“), trage den
Dienst in `EFML_LIVE_PRIVATE_ONLY` ein (z. B. `aws-s3,google-cloud-storage`): Der öffentliche Export wird dann
übersprungen, die übrigen Tests exportieren privat.

Für lokale Testserver (z. B. MinIO oder WebDAV in einem Container) setze `EFML_LIVE_ALLOW_LOCAL_HOSTS=1` und bei
selbst signierten Zertifikaten `EFML_LIVE_IGNORE_SSL=1`.

## Playwright

Die Playwright-Live-Tests nutzen deine Entwicklungsseite im Browser so wie du: Sie öffnen die Verzeichnisansicht jedes
Dienstes, importieren eine Datei über den Dialog, laden Bilder über „Mediendatei hinzufügen“ mit eingeschaltetem
Export hoch (öffentlich, privat und als Redakteur), prüfen die Dateien als Besucher und fügen die URLs aus
`EFML_LIVE_URLS` hinzu.

```bash
composer test-live-e2e
```

Einstellungen in `.env.live`:

| Einstellung | Bedeutung |
|---|---|
| `EFML_LIVE_SITE_URL` | die URL deiner Entwicklungsseite |
| `EFML_LIVE_SITE_ADMIN_USER`, `EFML_LIVE_SITE_ADMIN_PASSWORD` | ein Administrator |
| `EFML_LIVE_SITE_EDITOR_USER`, `EFML_LIVE_SITE_EDITOR_PASSWORD` | optional: ein Redakteur für den Export seiner Uploads |
| `EFML_LIVE_BROWSER_CHANNEL` | optional: `chrome` für dein installiertes Google Chrome, sonst der Browser von Playwright (`npx playwright install chromium`) |

Die Seite braucht das Plugin EFML Dev Credentials mit denselben Zugangsdaten. Die Tests überspringen Dienste, die dort
ausgeschaltet oder unvollständig sind oder deren Erweiterung nicht aktiv ist. Den Bericht findest du in
`playwright-report/live`.

## EFML Dev Credentials

Ein kleines Plugin für deine Entwicklungsseiten in einem eigenen Repository: Es liest `.env.live` und definiert die
Zugangsdaten aller vollständigen Dienste als Konstanten, bevor das Plugin geladen wird. So ist jeder Dienst auf jeder
Entwicklungsseite sofort nutzbar, ohne Zugangsdaten einzugeben.

- **Admin-Leiste „EFML Dev“**: zeigt die Zahl der genutzten Dienste und schaltet die Zugangsdaten insgesamt oder je
  Dienst um. Schalte einen Dienst aus, um für ihn die Einstellungen der Website zu nutzen (Konstanten erzwingen den
  globalen Modus und sperren gespeicherte externe Quellen anderer Server).
- **Werkzeuge > EFML Dev Credentials**: der Stand aller Dienste und die Refresh-Tokens der aktuellen Verbindungen zu
  Dropbox und Google Drive zum Kopieren in `.env.live`.
- Stellt die Verbindungen zu Dropbox und Google Drive aus den Refresh-Tokens in `.env.live` her.
- Schaltet alle Abilities des Plugins frei (Filter `efml_abilities_mode`).
- Endpunkte für die Playwright-Tests: `efml-dev/v1/status` und `efml-dev/v1/prepare` (nur für Administratoren).
- Arbeitet nur auf Websites mit dem Umgebungstyp `local` oder `development` (`WP_ENVIRONMENT_TYPE`).

Installation (als mu-plugin, damit es vor allen Plugins geladen wird):

```bash
git clone <repository> ~/repos/efml-dev-credentials
~/repos/efml-dev-credentials/install.sh /pfad/zu/wordpress
```

`.env.live` wird im Verzeichnis des Plugins „External Files in Media Library“ (seiner Arbeitskopie), im Verzeichnis
über WordPress oder unter dem Pfad der Konstante `EFML_LIVE_ENV` in `wp-config.php` gefunden.
