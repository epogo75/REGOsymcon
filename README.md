# REGOvisu

Symcon-Modul mit den Visu-Elementen von **REGObaseX1** — dieselben Kacheln,
dieselben Farben, dieselbe Bedienlogik, nur auf Symcon-Variablen statt auf einem
Gira X1.

Jede Kachel ist eine eigene Instanz, die auf bereits vorhandene Symcon-Variablen
zeigt. Das Modul legt keine eigenen Variablen an und schreibt nichts, was nicht
über einen Knopf ausgelöst wurde — es ist reine Darstellung und Bedienung.

## Module

| Modul | Kachel | Bedienung |
|---|---|---|
| REGOvisu Schalten | Ein AN/AUS-Knopf | AN ist rot, AUS neutral, unbekannter Zustand blass |
| REGOvisu Dimmen | AN/AUS-Knopf + Prozent-Slider | Slider sendet beim Loslassen, nicht während des Ziehens |
| REGOvisu Jalousie | Auf / Stopp / Ab, optional Positions-Slider | Fahrbefehl invertierbar |
| REGOvisu Klima | „Ist 21.5 °C" + Stepper − / Soll / + | Schrittweite und Grenzen einstellbar, Standard 0,5 K |
| REGOvisu Szene | Eine Knopfreihe, ein Knopf je Szene | Schreibt die Szenennummer |

## Installation

Symcon-Konsole → **Modules** (Kern-Instanzen → Modules) → **+** → URL eintragen:

```
https://github.com/BENUTZER/SymconREGOvisu
```

Danach unter *Instanz hinzufügen* nach `REGOvisu` suchen.

## Konfiguration

Alle Module haben dieselben zwei Kopfzeilen-Einstellungen:

- **Bezeichnung** — überschreibt den Instanznamen in der Kachel; leer lassen,
  dann steht dort der Instanzname.
- **Kopfzeile anzeigen** — Symbol und Name ein- oder ausblenden.

Darunter folgen je Modul die Variablen. Felder, die auf eine andere Variable
zurückfallen können, sind im Formular entsprechend beschriftet (z. B. „Schalten
(leer = Status-Variable)").

Geschrieben wird immer über die Aktion der Zielvariable — bei KNX-Variablen geht
also ein echtes Telegramm auf den Bus. Nur wenn eine Variable gar keine Aktion
hat, setzt das Modul den Wert direkt, damit auch reine Merker funktionieren.

## Design

Farben, Radien, Abstände und Zustandslogik stammen 1:1 aus REGObaseX1:

| Token | Dunkel | Hell |
|---|---|---|
| Hintergrund Kachel | `#131316` | `#ffffff` |
| Rahmen | `rgba(255,255,255,.06)` | `rgba(0,0,0,.08)` |
| Text | `#f2f2f4` | `#1a1a1c` |
| Akzent | `#b9ff5c` | `#4d7616` |
| AN-Zustand | `#f85149` | `#cf222e` |

Hell oder dunkel entscheidet die Kachel selbst: die Kachel-Visualisierung hängt
ihre Farben als Query-Parameter an das iframe, aus der Helligkeit von
`cardcolor` leitet das Modul das Theme ab. Die Kachel folgt damit dem
Symcon-Theme und nicht dem Betriebssystem.

Schriftart ist Inter, mit System-Fallback wo Inter nicht vorhanden ist.

## Voraussetzungen

Symcon ab 7.1 (HTML-SDK), entwickelt und getestet auf 9.1.

## Lizenz

MIT
