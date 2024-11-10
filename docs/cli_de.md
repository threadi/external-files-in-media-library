# WP CLI Kommandos

Das Plugin stellt eine Reihe von WP CLI Kommandos bereit.

## Liste aufrufen

`wp eml`

## Import

`wp eml import [URL] [--login=<login> --password=<password>]`

* Du kannst eine URL, deren Protokoll vom Plugin unterstützt wird, hier angeben.
* Die optionalen Zugangsdaten werden bei jeder dieser URLs verwendet.

## Löschen

`wp eml delete`

* Es gibt keine Parameter, um das Kommando zu beeinflussen.

## Log bereinigen

`wp eml clear_log`

## Verfügbarkeit prüfen

`wp eml check`

## Plugin zurücksetzen

Das folgende Kommando führt zuerst die Kommandos beim Löschen des Plugins aus. Danach wird eine Installation ausgeführt.

Abhängig von deinen Einstellungen im Plugin werden dabei ggfs. vorhandene Dateien aus deiner Mediendatenbank entfernt.

`wp eml reset_plugin`
