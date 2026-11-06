# mod_lernpfad – Lernpfad als Liniennetz

Moodle-Aktivität, die einen Abschnitt oder den ganzen Kurs als Liniennetzplan zeigt.
Die Karte baut sich selbst: keine Orte setzen, kein Hintergrundbild zeichnen.

- **Bereich „Aktivitäten dieses Abschnitts“:** Jede Aktivität des Abschnitts (oder
  Unterabschnitts), in dem der Lernpfad liegt, ist eine Station. Ein Unterabschnitt
  (mod_subsection) darin wird eine Station mit Fortschrittsring. So lässt sich der
  Lernpfad in jedem Abschnitt und Unterabschnitt wieder einsetzen.
- **Bereich „Abschnitte des ganzen Kurses“:** Jeder Abschnitt (außer 0) ist eine
  Station; der Fortschritt ist der Anteil abgeschlossener Aktivitäten.
- **Verbindungen** kommen aus den Voraussetzungen: positive Abschluss- und
  Notenbedingungen (auch „vorherige Aktivität“), an Aktivitäten und Abschnitten.
  Gibt es zwischen den Stationen keine, gilt die Reihenfolge auf der Kursseite.
- **Zustände je Nutzer:** abgeschlossen (Haken), Teilfortschritt (Ring), als Nächstes
  (gefüllt, pulsierend), offen, gesperrt (Schloss), ohne Abschlussverfolgung (kleiner
  Punkt). Stationen sind Links, Namen und Zustand stehen im Tooltip.
- **Layout** ohne externe Werkzeuge: Ränge nach längstem Pfad, Reihenfolge per
  Baryzentrum, Ketten bleiben gerade; waagerecht bis 6 Ränge, sonst senkrecht
  (einstellbar). Beschriftung: gemeinsames erstes Wort entfällt, „Code · Titel“ und
  „Typ: Titel“ werden zu Kürzel + Titel.
- **Darstellung** als SVG auf dem Server, Farben aus den Bootstrap-Variablen des Themes
  (`--bs-primary`, `--bs-success`, …), also passend zu Theme und Dunkelmodus;
  Puls entfällt bei `prefers-reduced-motion`.

Keine Nutzerdaten, Sicherung/Wiederherstellung nur der Einstellungen.
Benötigt Moodle 4.5+ (getestet auf 5.3).

## Entwicklung

Tests mit der Moodle-Testumgebung (Plugin unter `mod/lernpfad` bzw. `public/mod/lernpfad`):

```bash
php admin/tool/phpunit/cli/init.php        # Moodle 5.1+: public/admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --testsuite mod_lernpfad_testsuite
```

Die CI (`.github/workflows/ci.yml`) läuft mit moodle-plugin-ci gegen Moodle 4.5 bis 5.3.

## Lizenz

GPL v3 oder später, © 2026 Malte Zilinski.
