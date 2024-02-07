<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for local:adele, language de
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Required strings.
$string['modulename'] = 'Adele';
$string['modulenameplural'] = 'Adeles';
$string['modulename_help'] = 'Adele';
$string['pluginadministration'] = 'Adele Verwaltung';
$string['pluginname'] = 'Adele';

// Capabilities.
$string['adele:edit'] = 'Adele bearbeiten';
$string['adele:view'] = 'Adele anzeigen';
$string['adele:canmanage'] = 'Darf Adele Plugins managen';

// Role.
$string['adeleroledescription'] = 'Adele Verwalter/in';


// Vue component route not found.
$string['route_not_found_site_name'] = 'Fehler: Seite (Route) nicht gefunden!';
$string['route_not_found'] = 'Seite wurde nicht gefunden. Bitte gehen Sie zurück und versuchen Sie es erneut.';

// Vue component learning goal edit.
$string['learningpaths_edit_site_name'] = 'Adele Lernziele';
$string['learningpaths_edit_site_description'] = 'Sie können einen neuen Lernpfad hinzufügen oder vorhandene Pfade bearbeiten.';
$string['learningpaths_edit_site_no_learningpaths'] = 'Noch keine Lernpfade vorhanden.';
$string['learningpaths_edit_no_learningpaths'] = 'Es gibt keine Ziele zu zeigen.';

// Learning goals overview.
$string['edit'] = 'Bearbeiten';
$string['delete'] = 'Löschen';
$string['deletepromptpre'] = 'Möchten Sie das Lernziel "';
$string['deletepromptpost'] = '" wirklich löschen?';
$string['btnconfirmdelete'] = 'Löschen bestätigen';
$string['duplicate'] = 'Duplizieren';
$string['toclipboard'] = 'In die Zwischenablage kopieren';
$string['goalnameplaceholder'] = 'Name des Lernpfads';
$string['goalsubjectplaceholder'] = 'Beschreibung des Lernpfads';
$string['toclipboarddone'] = 'In die Zwischenablage kopiert';
$string['subject'] = 'Thema';

// Learning goal form.
$string['learningpath'] = 'Ziel';
$string['learningpath_name'] = 'Zielname';
$string['learningpath_description'] = 'Zielbeschreibung';
$string['learningpath_form_title_add'] = 'Einen neuen Lernpfad hinzufügen';
$string['learningpath_form_title_edit'] = 'Einen Lernpfad bearbeiten';
$string['save'] = 'Speichern';
$string['cancel'] = 'Abbrechen';

// Tabs.
$string['thinkingskill'] = 'Denkfähigkeit';
$string['content'] = 'Inhalt';
$string['resources'] = 'Ressourcen';
$string['products'] = 'Produkte';
$string['groups'] = 'Gruppen';

// Words.
$string['prethinkingskill'] = 'Die Schüler werden';
$string['clicktoedit'] = '[zum Bearbeiten klicken]';
$string['preresource'] = 'verwenden';
$string['preproduct'] = 'und erstellen';
$string['pregroup'] = 'in Gruppen von';

// Button strings.
$string['btnadele'] = 'Lernpfade';
$string['btnbacktooverview'] = 'Zurück zur Übersicht';

// From Strings.
$string['fromlearningtitel'] = 'Titel des Lernpfads';
$string['fromlearningdescription'] = 'Beschreibung des Lernpfads';
$string['fromavailablecourses'] = 'Liste der verfügbaren Kurse';
$string['fromlearningtitelplaceholder'] = 'Bitte geben Sie einen Titel an';
$string['fromlearningdescriptionplaceholder'] = 'Bitte geben Sie eine kurze Beschreibung an';

// Overview String.
$string['overviewlearningpaths'] = 'Übersicht aller Lernpfade';
$string['overviewaddingbtn'] = 'Neuen Lernpfad erstellen';

// Adele Settings.
$string['activefilter'] = 'Filter aktivieren';
$string['activefilter_desc'] = 'Die Filter wirken sich auf die verfügbaren Kurse für die Erstellung von Lernpfaden aus';

$string['courselevel'] = 'Kursniveau wählen';
$string['courselevel_desc'] = 'Entscheiden Sie, welches Kursniveau innerhalb der Erstellung von Lernpfaden angezeigt wird';

$string['tagsinclude'] = 'Eingeschlossene Tags definieren';
$string['tagsinclude_desc'] = 'Definieren Sie, welche Kurse nach ihren Tags gefiltert werden sollen. Kurse mit einem dieser Tags werden gefiltert';

$string['tagsexclude'] = 'Ausgeschlossene Tags definieren';
$string['tagsexclude_desc'] = 'Definieren Sie, welche Kurse nach ihren Tags nicht gefiltert werden sollen. Kurse mit einem dieser Tags werden nicht gefiltert';

$string['categories'] = 'Kategorieniveau definieren';
$string['categories_desc'] = 'Definieren Sie, welches Kursniveau einbezogen werden soll';

$string['tag_invalid'] = 'Folgende Tags wurden nicht gefunden: {$a}';
