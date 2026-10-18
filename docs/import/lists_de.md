# Import von URLs aus CSV- und XML-Dateien

Hier wird beschrieben wie du die URLs aus einer CSV- oder XML-Datei in deine Mediathek importieren kannst.

## Unterstützte Dateien

* CSV-Dateien mit beliebigem Trennzeichen (Komma, Semikolon, Tabulator oder senkrechter Strich). Die erste Zeile wird als Kopfzeile verwendet, wenn sie keine URL enthält. Werte in UTF-8, UTF-16 oder Windows-1252 (z. B. aus Excel) werden unterstützt. Eine Zelle darf mehrere URLs enthalten, getrennt durch Komma, Semikolon, senkrechten Strich oder Leerzeichen.
* XML-Dateien wie Sitemaps (auch mit Bildern und Videos), RSS- und Atom-Feeds, Produkt-Feeds (z. B. für Google Shopping), WordPress-Exporte und jede andere XML-Datei mit URLs. XML-Dateien mit DOCTYPE werden aus Sicherheitsgründen nicht unterstützt.

## Import über die Services

1. Gehe auf Medien > Externe Dateien hinzufügen und klicke auf den Service "CSV" oder "XML".
2. Lade eine Datei hoch oder gib die URL einer Datei an. Es ist nur eines von beiden möglich.
   * Eine hochgeladene Datei wird in deiner Mediathek gespeichert.
   * Eine Datei von einer URL wird in deine Mediathek heruntergeladen. Ist die URL per Auth Basic geschützt, gib Login und Passwort an. Rufst du die URL erneut auf, wird sie nur dann neu heruntergeladen, wenn sie sich geändert hat.
3. Jedes Feld mit URLs (eine Spalte der CSV oder ein Knoten der XML) wird als Verzeichnis angezeigt. Felder mit URLs von Dateien stehen oben.
4. Importiere einzelne URLs, ein ganzes Verzeichnis oder alle URLs über den Button zum Import des aktiven Verzeichnisses im ersten Verzeichnis.
5. Im Import-Dialog wählst du, welche Felder die zu importierenden URLs enthalten und welche Felder für Alternativtext, Bildunterschrift, Beschreibung und Datum der importierten Dateien verwendet werden. Das Plugin erkennt sie automatisch. Deine Auswahl wird für die nächsten Importe an der Datei gespeichert.

Die Dateien in deiner Mediathek werden durch das Plugin nie verändert. Dateien, die für die Services hochgeladen oder heruntergeladen wurden, werden einmal täglich automatisch gelöscht, wenn sie 7 Tage nicht verwendet wurden (einstellbar in den Einstellungen des jeweiligen Services). Die zuletzt heruntergeladene Version einer als externe Quelle gespeicherten URL bleibt erhalten. Dateien, die du selbst in deine Mediathek legst, werden nie gelöscht. Diese Dateien zeigen in ihren Details einen Hinweis auf das automatische Löschen. Du kannst sie auch per WP CLI mit `wp eml cleanup_lists` löschen (siehe [WP CLI](../cli_de.md)).

## Import über die Mediathek

Jede CSV- und XML-Datei in deiner Mediathek hat in der Listenansicht und in ihren Details die Option "URLs importieren". Sie öffnet den Import-Dialog für alle URLs dieser Datei. Du kannst diese Option in den Einstellungen des jeweiligen Services deaktivieren.

## Synchronisation

Eine Datei von einer URL kann als deine externe Quelle gespeichert werden. Sie kann dann automatisch synchronisiert werden. Bei jeder Synchronisation wird die URL auf Änderungen geprüft. Hat sie sich geändert, wird die neue Version in deine Mediathek heruntergeladen und verwendet.

## Zugangsdaten

Login und Passwort werden mit der externen Quelle gespeichert. Sie werden nur an den Host der Datei mit den URLs gesendet, nie an andere Hosts (auch nicht nach Weiterleitungen). Dateien aus der Liste auf demselben Host werden mit den Zugangsdaten geladen und lokal gespeichert.
