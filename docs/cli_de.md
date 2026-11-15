# WP CLI Kommandos

Das Plugin stellt eine Reihe von WP CLI Kommandos bereit.

## Liste aufrufen

`wp eml`

## Import

`wp eml import <URL> [--login=<value>] [--password=<value>] [--queue] [--real_import] [--use_dates] [--use_specific_date=<value>]`

Parameter:

* <URL> zum Importieren in die Mediathek.
* [--login=<Wert>] - Legt das Login fest, die für alle hinzugefügten URLs verwendet werden soll.
* [--password=<Wert>] - Legt das Passwort fest, das für alle hinzugefügten URLs verwendet werden soll.
* [--queue] - Fügt die angegebenen URLs zur Warteschlange hinzu.
* [--real_import] – Importiert Dateien von URLs als echte Dateien, die nicht mit einer externen URL verknüpft sind.
* [--use_dates] – Verwendet die Daten der externen Quelle.
* [--use_specific_date=<Wert>] – Verwendet ein bestimmtes Datum für jede Datei.

## Löschen

`wp eml delete [<URLs>]`

Löscht die angegebenen URLs aus der Mediathek.

## Log bereinigen

`wp eml clear_log`

## Verfügbarkeit prüfen

`wp eml check`

## Plugin zurücksetzen

Das folgende Kommando führt zuerst die Kommandos beim Löschen des Plugins aus. Danach wird eine Installation ausgeführt.

Abhängig von deinen Einstellungen im Plugin werden dabei ggfs. vorhandene Dateien aus deiner Mediendatenbank entfernt. Exportierte Dateien werden vorher als lokale Dateien an ihren ursprünglichen Pfad zurückgeholt und nie gelöscht. Lässt sich eine davon nicht zurückholen, wird das Plugin nicht zurückgesetzt. Mit `--force` wird es trotzdem zurückgesetzt, die nicht zurückgeholten Dateien bleiben dann exportierte Dateien.

`wp eml reset_plugin [--force]`

Diese Funktion kann auch im Backend unter Einstellungen > Externe Dateien in der Mediathek > Erweitert ausgeführt werden.

## Externe Quelle hinzufügen

`wp eml add_external_source <Name> --type=<value> --fields=<value>`

Parameter:
* <Name> – Der zu verwendende Name.
* [--type=<value>] – Legt den Typ fest, z. B. „ftp“.
* [--fields=<value>] – Legt die Konfiguration als JSON-Zeichenfolge in einer Zeile fest. Das Format hängt vom verwendeten Typ ab.

## Externe Quelle löschen

`wp eml delete_external_source <Namen>`

Parameter
* <Namen> – Die Namen der zu löschenden externen Quellen.

## Exportstatus ändern

`wp eml change_export_state <Names> [--enable] [--disable]`

Parameter:
* <Names> – Liste der Namen der zu ändernden externen Quellen.
* [--enable] – Markierung, um die angegebenen Namen für den Export zu aktivieren.
* [--disable] – Markierung, um die angegebenen Namen für den Export zu deaktivieren.

## Warteschlange bereinigen

Dadurch werden fehlerhafte URLs aus der Warteschlange entfernt.

`wp eml cleanup_queue`

## Listen mit URLs aufräumen

Dadurch werden die CSV- und XML-Dateien gelöscht, die über den CSV- oder XML-Service hochgeladen oder heruntergeladen und seit der eingestellten Anzahl an Tagen nicht mehr verwendet wurden. Die zuletzt heruntergeladene Version einer als externe Quelle gespeicherten URL bleibt immer erhalten.

`wp eml cleanup_lists`

Optionale Parameter:

* `--service=csv` oder `--service=xml`, um nur die Dateien eines Services aufzuräumen
* `--days=3`, um Dateien zu löschen, die seit 3 Tagen nicht verwendet wurden (statt des Werts aus den Einstellungen)
* `--force`, um das Aufräumen auch auszuführen, wenn es in den Einstellungen deaktiviert ist
* `--dry-run`, um nur anzuzeigen, welche Dateien gelöscht würden

## Warteschlange löschen

Dadurch werden alle Einträge in der Warteschlange gelöscht.

`wp eml clear_queue`

## Mediendateien exportieren

Dadurch werden alle lokalen Dateien der Mediathek, die noch nicht exportiert sind und deren Dateityp in den Plugin-Einstellungen erlaubt ist, in die externen Quellen mit aktiviertem Export exportiert. Das ist derselbe Export wie über den Button „Vorhandene Dateien exportieren“ in den Export-Einstellungen, nur werden alle Dateien direkt exportiert. Dateien, die nicht exportiert werden konnten, bleiben lokal, sind in der Mediathek markiert und stehen als Fehler in der Warteschlange.

`wp eml export`

## Größen exportierter Bilder exportieren

Dadurch werden die Größen von Bildern, die ohne sie exportiert wurden, in ihre externen Quellen exportiert, sofern deren Dienste das unterstützen. Die Größen werden aus der lokalen Kopie eines Bildes oder aus dem aus seiner externen Quelle heruntergeladenen Bild erzeugt. Das ist dasselbe wie über den Button „Größen jetzt exportieren“ in den Export-Einstellungen, nur werden alle Bilder direkt verarbeitet.

`wp eml export_sizes`

## Exportierte Dateien zurückholen

Dadurch werden alle exportierten Dateien als lokale Dateien an ihren ursprünglichen Pfad in der Mediathek zurückgeholt, z. B. bevor du das Plugin deaktivierst. Die Dateien werden aus ihren externen Quellen heruntergeladen, ihre Größen werden neu erzeugt, und danach sind sie normale lokale Dateien. Deine Inhalte funktionieren so auch ohne das Plugin. Der Export wird dafür ausgeschaltet. Das ist dasselbe Zurückholen wie über den Button „Exportierte Dateien zurückholen“ in den Export-Einstellungen, nur werden alle Dateien direkt zurückgeholt. Dateien, die nicht zurückgeholt werden konnten, bleiben exportierte Dateien und stehen als Fehler in der Warteschlange.

`wp eml localize [--delete-on-target]`

* [--delete-on-target] - Löscht danach die Kopien in den externen Quellen. Ohne diese Angabe bleiben sie erhalten.

## Hauptseite oder Kopie

Zeigt, ob diese Website die Hauptseite oder eine Kopie davon ist (z. B. eine Staging-Seite). Auf einer Kopie werden keine Dateien in externe Quellen exportiert oder dort gelöscht, und zeitgesteuerte Synchronisierungen pausieren.

`wp eml site status`

Diese Website als Hauptseite festlegen, z. B. nach dem Umzug der Live-Seite auf eine neue Domain oder im Deployment:

`wp eml site main`

Diese Website als Kopie bestätigen (der Schutz bleibt aktiv, der Hinweis im Backend wird ausgeblendet):

`wp eml site copy`

## Umzug von anderen Offload-Plugins

Zeigt den Stand des Umzugs von Dateien anderer Offload-Plugins (siehe [Umzug](migration_de.md)):

`wp eml migration status`

Einen laufenden Umzug ohne Zeitlimit fortsetzen, z. B. wenn WP-Cron abgeschaltet ist:

`wp eml migration run`

Den Umzug aller Dateien oder eines einzelnen Anhangs rückgängig machen. Die Dateien werden dann wieder mit ihren lokalen Dateien und den Daten des anderen Plugins genutzt, aktiviere das andere Plugin danach wieder:

`wp eml migration revert`

`wp eml migration revert --attachment=42`
