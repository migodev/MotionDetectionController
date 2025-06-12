# Bewegungsmelder Zustands Controller
Das Modul ermöglicht es, einen Bewegungsmelder mit einer Zwischenvariable zu aktivieren & deaktivieren.
Darüber hinaus können Bedingungen zum Schalten von Lampen und Dimmern benutzt werden.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [Konfiguration](#6-konfiguration)
7. [Visualisierung](#7-visualisierung)
8. [PHP-Befehlsreferenz](#8-php-befehlsreferenz)


### 1. Funktionsumfang

* Schaltvorgänge einer Variable überwachen, Bedingungen auswerten und bei Bedarf Lichter Schalten

### 2. Voraussetzungen

- IP-Symcon ab Version 8.0

### 3. Software-Installation

* Über den Module Store das 'Bewegungsmelder Zustands Controller'-Modul installieren.
* Alternativ über das Module Control folgende URL hinzufügen: https://github.com/migodev/MotionDetectionController

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'Bewegungsmelder Zustands Controller'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

### 5. Statusvariablen und Profile

Es werden keine Profile angelegt.
Es werden 2 Statusvariablen angelegt:

Name                  | Typ					| Funktion
--------------------- | ------------------- | -------------------
Bewegungsmelder Modus aktiv | Boolean		| Zeigt den Status an, ob das Modul Signale verarbeitet
Bewegungsmelder Zustand 	| Boolean		| Zeigt den gefilterten Zustand an
Eingangswert				| Boolean		| Repräsentiert den Eingangswert unabhängig von Filterregeln

### 6. Konfiguration

| Eigenschaft                                           |   Typ   | Standardwert | Funktion                                                  |
|:------------------------------------------------------|:-------:|:-------------|:----------------------------------------------------------|
| Bewegungsmelder Variable                              | integer | 0            | Die Variable des Bewegungsmelders die überwacht werden soll bzw. auf die reagiert werden soll |
| Bedingungen zum Schalten der Lichter                	| string  | []           | Bedinungsliste die entweder komplett oder mind. eine erfüllt sein muss |
| Ausgabevariablen                                      | string  | []           | Liste der Ausgangsvariablen. Es können Lampen an/aus aber auch dimmbare Lampen geschaltet werden. Bei dimmbaren Lampen hier nur die Intensitäts Variable wählen |
| Aktion beim Deaktivieren                              | integer | 0            | Hier kann gewählt werden, was passiert wenn der Modus der Instanz deaktiviert wird. Entweder bleiben die Ausgangsvariablen geschaltet oder sie werden direkt ausgeschaltet |
| False-Aktion der Eingangsvariable wenn Bedingungen nicht erfüllt sind | integer | 0             | False Meldungen (z.B. der BWM schaltet wieder aus) können hier akzeptiert werden, obwohl die Bedingungen nicht erfüllt sind. In dem Fall wird false immer ausgeführt, das anschalten nur wenn die Bedingungen erfüllt sind |
| Setze Bewegungsmelder Zustand synchon nach Aktivieren des Controllers | boolean | 0            | Ist dieser Schalter aktiviert, wird beim Aktivieren des Modus der aktuelle Wert des BWM übernommen und die Variable aktualisiert. Bei Deaktivierung wird der Wert erst beim nächsten Schalten aktualisiert |
| Helligkeit Dimmer beim Anschalten						| integer | 0			 | Dimmwert Vorgabe für alle dimmbaren Ausgangsvariablen |

### 6. Visualisierung

Das Modul bietet in der Visualisierung die Möglichkeit den Modus an & auszuschalten.

### 7. PHP-Befehlsreferenz

Keine Funktion