## Mime Types

# Hintergrund

WordPress lässt von sich aus eine Vielzahl an Dateitypen für den Upload in die Media Library zu. Darunter sind
jedoch auch Datei-Arten die als externe Dateien schwierig zu verarbeiten sind. Daher verwaltet dieses Plugin
die MIME-Types selbst und gibt dem Nutzer die wichtigsten frei.

Nur im Plugin freigegebene MIME-Types können auch als externe Dateien in die Media Library eingefügt werden.

# Die Liste

Möglich zur Auswahl sind immer folgende MIME-Types:

* AVIF
* GIF
* JPEG
* MP4
* PDF
* PNG
* SVG
* WebP
* ZIP

# Mime Type ergänzen

Um einen MIME-Typ in der Liste zu ergänzen, musst Du folgenden PHP-Code in Deinem Projekt hinterlegen:

```
add_filter( 'efml_supported_mime_types', function( $list ) {
  $list['your/mime'] = array(
      'label' => 'Title of your mime',
      'ext' => 'yourmimeextension'
  );
  return $list;
 } );
```

Passe die Bezeichnungen darin entsprechend deinen Anforderungen an.
