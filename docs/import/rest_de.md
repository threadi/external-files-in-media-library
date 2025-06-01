# Import von WordPress REST API

Hier wird beschrieben wie du Dateien von einem anderen WordPress-Projekt per REST API importieren kannst.

## Voraussetzungen

Das andere WordPress-Projekt muss die REST API öffentlich erreichbar haben. Das kannst du prüfen indem du dessen URL
aufrufst. Beispiel:

https://example.com/wp-json/wp/v2/media

## Dateien einzeln importieren

Um Dateien von einer externen REST API einzeln zu importieren, gehe in deinem WordPress-Backend
auf Medien > Externe Dateien hinzufügen und klicke dort auf den Service "WordPress REST API". Gib in dem Feld die Domain
oder den Pfad zur REST API des anderen Projektes an. Warte bis die Ansicht geladen hat. Anschließend kannst du die
gewünschten Dateien einzeln für deine eigene Mediathek auswählen.

### Hinweis

Der Ladevorgang kann ggfs. abbrechen falls die externe Mediathek sehr groß ist. Hier greifen dann Timeouts deines Hostings,
die das Plugin nicht umgehen kann.

## Alle Dateien importieren

### Im Backend #1

Gehe wie oben für einzelne Dateien beschrieben vor und klicke im Service auf den Button zum Import aller Dateien.

### Im Backend #2

1. Gehe auf Medien > Mediendatei hinzufügen.
2. Klicke auf "Externe Dateien hinzufügen".
3. Gib hier die URL des anderen WordPress-Projektes an.
4. Klicke auf "URLs hinzufügen" und warte dann bis der Vorgang abgeschlossen ist.

### Per WP CLI

Rufe folgendes Kommando auf:

`wp eml import https://example.com/wp-json/wp/v2/media`

Hinweis: hier ist es wichtig den kompletten Pfad zur REST API anzugeben da das Plugin ansonsten nur die
Startseite scannt und versucht zu importieren.
