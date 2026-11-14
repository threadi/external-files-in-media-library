# Erweiterungen für weitere Dienste

Einige Dienste stellt nicht das Plugin selbst bereit, sondern eine kostenlose Erweiterung. Du installierst sie mit einem Klick im Schnellstart oder in der Liste der Dienste (Medien → „Externe Dateien hinzufügen“).

## Warum Erweiterungen?

Der Hauptgrund ist die Lizenz: Plugins im WordPress-Plugin-Verzeichnis müssen mit der Lizenz von WordPress (GPLv2 oder später) vereinbar sein, auch alle Bibliotheken, die sie mitbringen. Einige Anbieter stellen ihre Bibliotheken für den Zugriff auf ihre Dienste aber nur unter Lizenzen bereit, die damit nicht vereinbar sind. Das AWS-SDK für PHP steht z. B. unter der Apache-Lizenz 2.0, die nicht mit der GPLv2 kompatibel ist. Solche Bibliotheken dürfen nicht Teil dieses Plugins sein, deshalb liegen die betroffenen Dienste in eigenen Erweiterungen auf GitHub.

Dazu kommt die Größe: Mit allen Bibliotheken wäre das Plugin zu groß für das WordPress-Plugin-Verzeichnis. Die Erweiterungen bringen nur die Bibliotheken mit, die du für deinen Dienst wirklich brauchst.

## Die Erweiterungen

| Erweiterung | Dienste | Quelle |
|---|---|---|
| External Files from AWS S3-compatible platforms | AWS S3, Cloudflare R2, Backblaze B2, DigitalOcean Spaces, Hetzner, IONOS, OVHcloud, Scaleway, Wasabi und weitere S3-kompatible Speicher | [GitHub](https://github.com/threadi/external-files-from-aws-s3) |
| External Files from Google Cloud Storage | Google Cloud Storage | [GitHub](https://github.com/threadi/external-files-from-google-cloud-storage) |
| External Files from Google Drive in Media Library | Google Drive | [GitHub](https://github.com/threadi/external-files-from-google-drive) |
| External Files from WebDav | WebDAV, z. B. Nextcloud | [GitHub](https://github.com/threadi/external-files-from-webdav) |

## Installation

* **Im Schnellstart:** Wähle den Dienst, auch wenn seine Erweiterung noch fehlt. Im nächsten Schritt bestätigst du die Installation, der Schnellstart lädt die neueste Version von GitHub, installiert und aktiviert sie und macht danach mit den Zugangsdaten weiter. Das gilt auch für den [Umzug von anderen Offload-Plugins](migration_de.md).
* **In der Liste der Dienste:** Dienste aus Erweiterungen sind dort mit „Erweiterung“ markiert und lassen sich per Klick installieren.
* **Von Hand:** Lade die ZIP-Datei der neuesten Version aus dem jeweiligen GitHub-Repository und installiere sie unter Plugins → Installieren → Plugin hochladen.

Für die Installation brauchst du das Recht, Plugins zu installieren. In einem Multisite-Netzwerk installiert die Administration des Netzwerks die Erweiterungen. Schlägt die Installation fehl, z. B. weil dein Server GitHub nicht erreicht oder das Plugin-Verzeichnis nicht beschreibbar ist, steht der Grund im Protokoll des Plugins.

Du kannst eine Erweiterung jederzeit deaktivieren und löschen. Dabei werden die zugehörigen Dateien aus deiner Mediathek entfernt.
