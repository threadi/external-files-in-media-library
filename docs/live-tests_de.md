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
- die Synchronisierung des Verzeichnisses ohne seine Unterverzeichnisse, wenn es höchstens `EFML_LIVE_MAX_SYNC_FILES`
  Dateien (Standard 20) enthält,
- mit `EFML_LIVE_SYNC_SUBDIRECTORIES=1`: die Synchronisierung mit den Dateien der Unterverzeichnisse und deren Löschen,
  wenn die Unterverzeichnisse danach deaktiviert werden (nur für Verzeichnisse mit Unterverzeichnissen; diese können
  viele Dateien enthalten),
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

### Einrichten

1. Installiere auf deiner Entwicklungsseite das Plugin **EFML Dev Credentials** (siehe unten). Es liest dieselbe
   `.env.live` und stellt dort die Zugangsdaten der Dienste bereit.
2. Kopiere `.env.live.example` nach `.env.live` (falls nicht schon für die PHPUnit-Tests geschehen) und setze im
   Abschnitt „Playwright live tests“ (entferne das `#` am Zeilenanfang):

   | Einstellung | Bedeutung |
   |---|---|
   | `EFML_LIVE_SITE_URL` | **Pflicht**: die Adresse deiner Entwicklungsseite, z. B. `https://dev.example.com`, mit `http://` oder `https://`, ohne `/wp-admin`. Ersetze den Beispielwert `https://efml.local`. |
   | `EFML_LIVE_SITE_ADMIN_USER`, `EFML_LIVE_SITE_ADMIN_PASSWORD` | **Pflicht**: Benutzername und Passwort eines Administrators |
   | `EFML_LIVE_SITE_EDITOR_USER`, `EFML_LIVE_SITE_EDITOR_PASSWORD` | optional: ein Redakteur für den Export seiner Uploads (am besten ohne die Rechte für die Dienste) |
   | `EFML_LIVE_BROWSER_CHANNEL` | optional: `chrome` für dein installiertes Google Chrome, sonst der Browser von Playwright |

3. Installiere einmalig die Abhängigkeiten und den Browser: `npm install` und `npx playwright install chromium`
   (nicht nötig mit `EFML_LIVE_BROWSER_CHANNEL=chrome`). Auf einem Linux-Server ohne Desktop installiert
   `npx playwright install --with-deps chromium` auch die nötigen Systembibliotheken.
4. Starte die Tests:

```bash
composer test-live-e2e
```

Die Tests laufen auf dem Rechner, auf dem du sie startest: Die Adresse in `EFML_LIVE_SITE_URL` muss von dort erreichbar
sein (echte Domain oder Eintrag in der hosts-Datei dieses Rechners). Die Benutzer müssen sich über `wp-login.php` ohne
Zwei-Faktor-Anmeldung oder Captcha anmelden können.

### Was passiert

Vor den Tests prüft Playwright die Konfiguration und ob die Seite erreichbar ist, und meldet sich einmal mit jedem
Benutzer an. Die Sitzungen werden automatisch in `tests/Live/Playwright/.auth/admin.json` und `editor.json`
gespeichert (die legst du nie selbst an; das Verzeichnis ignoriert Git). Ist die Seite nicht konfiguriert, werden
leere Sitzungen gespeichert und alle Tests mit dem Grund übersprungen.

Die Tests überspringen Dienste, die in EFML Dev Credentials ausgeschaltet oder unvollständig sind oder deren
Erweiterung nicht aktiv ist. Die Liste in der Konsole zeigt übersprungene Tests ohne Grund; den Grund jedes Tests, bei
fehlgeschlagenen Tests auch die Fehlermeldung und einen Screenshot, zeigt der Bericht in `playwright-report/live`
(`npx playwright show-report playwright-report/live`).

Die Sprache des Backends spielt keine Rolle, die Tests finden Buttons und Felder unabhängig davon. Zeigt ein Dialog einen
Fehler, schlägt der Test sofort mit dem Text des Dialogs fehl. Ein kompletter Lauf dauert einige Minuten; Composer bricht
ihn nicht nach 300 Sekunden ab.

### Typische Fehler

| Meldung | Lösung |
|---|---|
| `These values are missing in …/.env.live: …` | Setze die genannten Werte und entferne das `#` am Zeilenanfang. |
| `The name "…" could not be resolved` | Die Adresse ist auf diesem Rechner unbekannt: Nutze die echte Domain oder trage sie in die hosts-Datei ein. Steht noch der Beispielwert `https://efml.local` drin? |
| `Nothing answers at "…"` | Der Webserver ist nicht erreichbar: Prüfe Adresse und Port. |
| `… shows no login form of WordPress` | Die URL enthält `/wp-admin`, oder ein Plugin versteckt oder schützt die Anmeldeseite. |
| `The login of "…" failed: …` | Prüfe Benutzername und Passwort. Zwei-Faktor-Anmeldung oder Captcha müssen für diesen Benutzer aus sein. |
| `ENOENT … .auth/admin.json` | Kommt von älteren Ständen dieser Tests: Aktualisiere die Tests. |
| `The process … exceeded the timeout of 300 seconds` | Kommt von älteren Ständen dieser Tests: Aktualisiere die `composer.json`. |

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
