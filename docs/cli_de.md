# WP CLI Kommandos

Das Plugin stellt eine Reihe von WP CLI Kommandos bereit.

## Liste aufrufen

`wp eml`

## Import

`wp eml import [URL] [--login=<login> --password=<password>] [--queue] [--real_import] [--use_dates] [--use_specific_date=<value>]`

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

Abhängig von deinen Einstellungen im Plugin werden dabei ggfs. vorhandene Dateien aus deiner Mediendatenbank entfernt.

`wp eml reset_plugin`

Diese Funktion kann auch im Backend unter Einstellungen > Externe Dateien in der Mediathek > Erweitert ausgeführt werden.
