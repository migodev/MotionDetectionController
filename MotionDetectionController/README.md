# Bewegungsmelder Zustands Controller
Das Modul ermöglicht es, einen Bewegungsmelder mit einer Zwischenvariable zu aktivieren & deaktivieren.
Darüber hinaus können Bedingungen zum Schalten von Lampen und Dimmern benutzt werden.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [Visualisierung](#6-visualisierung)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)


### 1. Funktionsumfang

* Schaltvorgänge einer Variable überwachen, Bedingungen auswerten und bei Bedarf Lichter Schalten

### 2. Voraussetzungen

- IP-Symcon ab Version 7.1

### 3. Software-Installation

* Über den Module Store das 'Bewegungsmelder Zustands Controller'-Modul installieren.
* Alternativ über das Module Control folgende URL hinzufügen: https://github.com/migodev/MotionDetectionController

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'Bewegungsmelder Zustands Controller'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

### 5. Statusvariablen und Profile

Es werden keine Profile angelegt.
Es werden 2 Statusvariablen angelegt:

Name                  | Typ
--------------------- | -------------------
Bewegungsmelder Modus aktiv | Boolean
Bewegungsmelder Zustand | Boolean


### 6. Visualisierung

Das Modul bietet in der Visualisierung die Möglichkeit den Modus an & auszuschalten.

### 7. PHP-Befehlsreferenz

Keine Funktion