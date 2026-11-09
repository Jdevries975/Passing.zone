# Passing.zone
Eine Sammlung von Ideen, Fragen, To Dos

## Shortcodes (functions.php)

### `[pz_related_patterns]`
Zeigt unter einem Pattern ähnliche Patterns als Karten – gleiche Optik wie „Newest Patterns“ auf der Startseite:
Bild (oben abgerundet), Titel, Datum, Schwierigkeit und Anzahl Jongleur:innen. Kein Hover-Effekt.

- Eingebunden im Beaver-Themer-Layout „Single Pattern“.
- Auswahl: zufällige Patterns mit gleichem Pattern-Typ oder Tag; reicht das nicht, wird mit anderen zufälligen Patterns aufgefüllt.
- Attribute: `count` (Standard `3`), `heading` (Standard `More patterns to try:`, leer = keine Überschrift).
  Beispiel: `[pz_related_patterns count="6" heading="Similar patterns"]`
- CSS: `style.css`, Klassen `.pz-related-*`.
- Ersetzt das Plugin „Related Posts Thumbnails“ (gelöscht am 2026-11-09).
