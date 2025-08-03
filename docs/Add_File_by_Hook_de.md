# Datei per Hook ergänzen

## Zielgruppe

Diese Anleitung richtet sich an Entwickler von Plugins oder Themes, die die Möglichkeiten
von External Files for Media Library für sich selbst nutzen möchten.

## Voraussetzungen

* Ein eigenes WordPress-Plugin oder -Theme.
* Kenntnisse in PHP.

## Hook verwenden

### Struktur

* Typ: Filter
* Name: efml_add_url
* Parameter:
  * ID der Medien-Datei
  * URL die hinzugefügt werden soll
  * Login um auf die URL zuzugreifen (optional).
  * Passwort um auf die URL zuzugreifen (optional).
  * API-Key um auf die URL zuzugreifen (optional).
* Rückgabe:
  * gleich "0" wenn die URL nicht hinzugefügt wurde und auch nicht in der Mediathek existiert.
  * größer "0" wenn die URL hinzugefügt wurde oder bereits in der Mediathek existiert

### Beispiel:

`apply_filters( 'efml_add_url', 0, 'https://example.com/sample.pdf', 'login', 'password' );`
