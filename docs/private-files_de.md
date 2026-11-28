# Direktzugriff beim Anbieter verhindern

Exportierte Dateien müssen nicht öffentlich auf der externen Quelle liegen. Mit der Option **„Direktzugriff beim Anbieter verhindern“** in den Export-Einstellungen einer externen Quelle bleiben z. B. ein Bucket oder ein Ordner geschlossen. Niemand kann die Dateien dann direkt beim Anbieter laden. Die Website liefert sie immer über ihre eigene URL aus.

Erfordert _External Files in Media Library_ 6.0.0 oder neuer. Bis Version 6.0 hieß die Option „Dateien privat speichern“. In Hooks und im Code des Plugins heißt sie weiterhin „private“.

## Was die Option regelt und was nicht

Die Option regelt, **wo** eine Datei geladen werden kann: nur über deine Website, nicht beim Anbieter. Sie regelt nicht, **wer** sie über deine Website laden darf. Ohne weitere Regeln kann jeder die Datei laden, der die URL deiner Website kennt.

* **WooCommerce:** Dateien von herunterladbaren Produkten bekommen nur Käufer, siehe [WooCommerce](#woocommerce) unten.
* **Plugins zum Schutz von Dateien** wie Prevent Direct Access schützen nur Dateien im Upload-Verzeichnis, keine externen Dateien (siehe [unterstützte Plugins](plugins_de.md)).
* **Eigene Regeln:** über den Hook `efml_proxy_allow_delivery`, z. B. um Dateien nur an angemeldete Benutzer auszuliefern.

## Unterstützte Dienste

| Dienst | Auslieferung |
|---|---|
| AWS S3 und alle S3-kompatiblen Plattformen der Erweiterung | Weiterleitung auf einen signierten Link oder Proxy-Cache |
| Google Cloud Storage | Weiterleitung auf einen signierten Link oder Proxy-Cache |
| DropBox | Weiterleitung auf einen temporären Link oder Proxy-Cache (temporäre Links von DropBox gelten 4 Stunden, daher werden sie nur bei einer Gültigkeit von bis zu 119 Minuten genutzt) |
| Google Drive (nur mit Zugangsdaten für die ganze Website) | Proxy-Cache |
| WebDAV | Proxy-Cache |

Dateien ohne Direktzugriff auf FTP, SFTP und in lokalen Verzeichnissen werden immer über den Proxy ausgeliefert. Andere Dateien dort werden über die öffentliche URL der externen Quelle ausgeliefert (die URL für exportierte Dateien in ihren Export-Einstellungen), wenn der Proxy für ihren Dateityp ausgeschaltet ist, sonst ebenfalls über den Proxy.

## Wie die Dateien ausgeliefert werden

In der Mediathek und im Beitragsinhalt steht immer die feste URL der Website, z. B. `https://example.com/emlproxy/datei.pdf`. Suchmaschinen und Besucher sehen nur diese URL.

* **Proxy für den Dateityp aktiviert** (Einstellungen → Auslieferung): Die Website lädt die Datei einmal in ihren Cache und liefert sie selbst aus. Für Suchmaschinen ist das der sicherste Weg.
* **Proxy für den Dateityp deaktiviert:** Die Website leitet auf einen Link mit begrenzter Gültigkeit weiter. Die Datei kommt direkt vom Anbieter, die Website überträgt sie nicht selbst. Kann der Dienst keine solchen Links erzeugen, wird der Cache genutzt.
* Bestimmte Bildgrößen (z. B. `-150x150`) kommen immer aus dem Cache. Wurden die Größen zusammen mit dem Bild exportiert, lädt die Website die angefragte Größe vom Anbieter in ihren Cache, sonst erstellt sie die Größe aus dem Bild. Bilder bekommen ein `srcset` mit diesen Größen, es enthält nur URLs deiner Website.

## Gültigkeit der Links

Unter Einstellungen → Export → **„Gültigkeit von Links zu Dateien ohne Direktzugriff beim Anbieter (in Minuten)“**, Standard 60 Minuten, höchstens 5040 Minuten (3,5 Tage, da signierte Links von S3 und Google Cloud Storage höchstens 7 Tage gültig sind).

Die Links werden für feste Zeitfenster dieser Länge erzeugt. Innerhalb eines Zeitfensters ist der Link immer gleich, damit Browser und Caches die Datei wiederverwenden können. Jeder Link ist für zwei Zeitfenster gültig, also nach jeder Weiterleitung mindestens noch die eingestellte Zeit. Die Weiterleitung selbst darf der Browser bis zum Ende des Zeitfensters zwischenspeichern.

## Auslieferung prüfen

Proxy-URLs werden nicht auf eine Variante mit Schrägstrich am Ende umgeleitet und beantworten auch HEAD-Anfragen mit den Headern der Datei (z. B. für Link-Checker und CDNs). Eine Datei ohne Direktzugriff und ohne Proxy antwortet mit `302` und dem signierten Link im Header `Location`, prüfbar z. B. mit `curl -I https://example.com/emlproxy/datei.pdf` oder im Netzwerk-Tab der Entwicklertools des Browsers.

## WooCommerce

Externe Dateien aus der Mediathek können wie gewohnt als Dateien von herunterladbaren Produkten ausgewählt werden. Nach den Prüfungen von WooCommerce (Bestellung, Anzahl, Ablauf) wird die Datei so ausgeliefert:

* **Dateien ohne Direktzugriff beim Anbieter:** über einen signierten Link mit 5 Minuten Gültigkeit oder, wenn der Dienst das nicht kann, aus dem Cache. Die Proxy-URL liefert diese Dateien nicht mehr an Besucher aus, nur an Benutzer, die die Datei bearbeiten dürfen. Damit lassen sie sich nur über WooCommerce herunterladen.
* **Andere externe Dateien:** von ihrer externen URL. Wer diese URL kennt, kann die Datei weiterhin abrufen.

Der Infodialog der Datei in der Mediathek zeigt, in welchen Produkten sie als Download genutzt wird. Die Unterstützung hängt an der Einstellung „Unterstützung für WooCommerce aktivieren“ im Reiter WooCommerce.

Hinweis: WooCommerce prüft die „Genehmigten Download-Verzeichnisse“. Speichert ein Administrator das Produkt, wird das Verzeichnis der Proxy-URL automatisch genehmigt.

## Hooks

* `efml_export_private` – ob der Direktzugriff bei einer externen Quelle verhindert wird.
* `efml_private_files_lifetime` – Gültigkeit der Links in Sekunden.
* `efml_private_file_signed_url` – der signierte Link für eine Datei.
* `efml_proxy_allow_delivery` – ob der Proxy eine Datei ausliefern darf.
* `efml_woocommerce_download_lifetime` – Gültigkeit der Links für Downloads in WooCommerce (Standard 300 Sekunden).
