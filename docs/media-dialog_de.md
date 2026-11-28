# Externe Dateien im Medien-Dialog

Der Dialog von WordPress zum Auswählen oder Hochladen von Mediendateien (z. B. „Dateien hinzufügen“, das Beitragsbild, der Bild- oder Galerie-Block) bietet auch die Funktionen dieses Plugins:

* **Filter für externe Dateien** neben den Filtern von WordPress: alle Dateien, nur externe URLs, keine externen URLs, der Stand des Exports, deine externen Quellen und die weiteren Optionen anderer Funktionen (z. B. in einer Multisite). Die Optionen, die die Datenbank brauchen, werden geladen, wenn du zum ersten Mal auf den Filter zeigst.
* **Markierung an den Dateien:** Externe Dateien zeigen oben links in ihrer Vorschau das Logo ihres Dienstes (z. B. Dropbox, FTP), exportierte zusätzlich mit einer Wolke und Dateien ohne Direktzugriff beim Anbieter mit einem Schild. Die Beschriftung nennt den Dienst. Dienste ohne eigenes Logo zeigen einen Link.
* **Reiter „Von URL einfügen“:** Gib eine oder mehrere URLs ein (eine pro Zeile). Die Dateien werden in deine Mediathek aufgenommen und im Dialog ausgewählt. URLs, die schon in deiner Mediathek sind, werden nicht erneut aufgenommen: Ihre Datei wird ausgewählt.
* **Reiter „Aus externer Quelle“:** zeigt deine gespeicherten externen Quellen als Karten mit dem Logo ihres Dienstes und ihre Verzeichnisansicht wie unter Medien → „Deine externen Quellen“. „Importieren“ nimmt eine Datei auf und wählt sie im Dialog aus. Erlaubt der Dialog nur eine Datei, zeigt er danach die Mediathek mit der Datei, sonst kannst du weitere Dateien hinzufügen.

## Wer es nutzen kann

Einstellungen → „Berechtigungen“ → „Berechtigungen für Werkzeuge“ → „Externe Dateien im Medien-Dialog“: Wähle die Rollen, die diese Funktionen bekommen (standardmäßig Administratoren und Redakteure). Ohne Rolle bekommt sie niemand, der Dialog bleibt dann unverändert.

Die Berechtigungen des Plugins gelten wie überall: Der Benutzer braucht die Berechtigung, externe Dateien hinzuzufügen, der Reiter „Aus externer Quelle“ zeigt nur die externen Quellen, die der Benutzer im Backend nutzen kann (eigene, alle für Administratoren oder wenn „Alle externen Quellen anzeigen“ aktiviert ist), und nur von Diensten, die der Benutzer nutzen darf. Jeder Import prüft diese Berechtigungen auf dem Server erneut. Soll eine Rolle im Dialog keine Dateien eines Dienstes importieren, entziehe ihr die Berechtigung für diesen Dienst.

Im Reiter „Aus externer Quelle“ werden nur Dateien importiert. Verzeichnisse werden in der Liste der externen Quellen importiert, gespeichert und getestet.

## Leistung

Das Öffnen des Dialogs löst keine weitere Anfrage und keine Datenbankabfrage aus: Das Plugin fügt nur ein kleines Skript hinzu. Die weiteren Optionen des Filters, die externen Quellen und die Seite des Reiters „Aus externer Quelle“ werden bei ihrer ersten Nutzung geladen. Die Markierungen sind Teil der Daten, die der Dialog für seine Dateien ohnehin lädt.

## Der Reiter „Aus externer Quelle“ und Sicherheits-Header

Der Reiter zeigt eine Seite deines Backends in einem iframe. Das funktioniert, wenn der Dialog auf derselben Domain wie dein Backend läuft, was der Normalfall ist. Der Reiter öffnet die externen Quellen stattdessen in einem neuen Fenster, wenn

* der Dialog auf einer anderen Domain oder mit einem anderen Protokoll als dein Backend läuft (z. B. in Page-Buildern im Frontend mit gemappter Domain oder mit `FORCE_SSL_ADMIN` und einem Frontend über http), oder
* deine Website verhindert, dass ihr Backend in einem iframe gezeigt wird, z. B. mit dem Header `X-Frame-Options: DENY` oder `Content-Security-Policy: frame-ancestors 'none'` (gesetzt von manchen Sicherheits-Plugins oder Hostern). WordPress selbst sendet `X-Frame-Options: SAMEORIGIN`, was es erlaubt.

Dateien, die du im neuen Fenster hinzufügst, werden ebenfalls im Dialog ausgewählt. Beide Seiten nehmen Nachrichten nur von der erwarteten Domain an; sie enthalten nur die IDs der hinzugefügten Dateien.

## Für Entwickler: gemeinsame Bausteine

Die Logos der Dienste und die Karten sind gemeinsame Bausteine in `admin/components.css` (geladen auf den Seiten dieses Plugins und im Medien-Dialog):

* `Helper::get_service_icon( $name )` gibt das Logo eines Dienstes als Icon in der Größe der Variable `--efml-service-icon-size` zurück (standardmäßig 16px). Es nutzt die Regeln der Logos der Dienste: `.efml-<Name des Dienstes>:before` oder `.efml-<Name> > *:before`. Ihre Größe wird durch die Größe des Icons ersetzt. Dienste ohne Logo zeigen einen Link.
* Erweiterungen laden die Styles mit ihren Logos über die Action `efml_enqueue_service_logos`, wo dieses Plugin sie außerhalb seiner Seiten zeigt (Medien-Dialog, Reiter „Aus externer Quelle“), mit `efml-components` als Abhängigkeit. Für ein breites Logo können sie mit der Regel `span.efml-service-icon.efml-<Name>:before` eine quadratische Variante für das Icon ergänzen.
* `Helper::get_card( $url, $icon, $label, $description, $args )` gibt eine Karte zurück, `Directory_Listing::get_source_card()` die Karte einer gespeicherten externen Quelle. Zeige Karten in einem Element mit der Klasse `efml-cards` und gib sie mit `wp_kses( $card, Helper::get_allowed_html_for_cards() )` aus.
