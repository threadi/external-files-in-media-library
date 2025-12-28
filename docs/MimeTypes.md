## Mime Types

## Background

WordPress automatically allows a large number of files types be uploaded to the Media Library. Among them are
file types that are difficult to process as external files. Therefore, this plugin manages the mime types itself and
releases the most important ones to the user.

Only released mime types in the plugin can be added to the Media Library as external files.

# The list

The following mime types are always available for selection:

* AVIF
* GIF
* JPEG
* MP4
* PDF
* PNG
* SVG
* WebP
* ZIP

# Add mime type

To add a mime type to the list, you need to add the following PHP code to your project:

```
add_filter( 'efml_supported_mime_types', function( $list ) {
  $list['your/mime'] = array(
      'label' => 'Title of your mime',
      'ext' => 'yourmimeextension'
  );
  return $list;
 } );
```

Customize the names in it according to your requirements.
