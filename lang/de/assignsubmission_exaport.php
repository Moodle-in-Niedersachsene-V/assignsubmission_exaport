<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * German strings for the ePortfolio view submission plugin.
 *
 * @package    assignsubmission_exaport
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['chooseview'] = 'Sammelmappe auswählen ...';
$string['default'] = 'Standardmäßig aktiviert';
$string['default_help'] = 'Wenn aktiviert, ist dieser Abgabetyp bei neuen Aufgaben voreingestellt.';
$string['enabled'] = 'Portfolio-Sammelmappe';
$string['enabled_help'] = 'Wenn aktiviert, wählen Schülerinnen und Schüler eine ihrer Sammelmappen aus dem ePortfolio (Exabis ePortfolio) als Abgabe. Beim Speichern und erneut beim Abgeben wird ein PDF der Sammelmappe in der Abgabe abgelegt. Bewertet und kommentiert wird dieses PDF; spätere Änderungen an der Sammelmappe verändern die Abgabe nicht.';
$string['error_snapshotfailed'] = 'Das PDF der Sammelmappe konnte nicht erstellt werden. Bitte erneut versuchen oder die Administration informieren.';
$string['error_viewnotavailable'] = 'Die gewählte Sammelmappe existiert nicht mehr oder gehört nicht Ihnen. Bitte die Abgabe bearbeiten und eine andere Sammelmappe wählen.';
$string['exaport'] = 'Portfolio-Sammelmappe';
$string['gotoportfolio'] = 'Meine Sammelmappen öffnen';
$string['noviews'] = 'Sie haben noch keine Sammelmappe. Legen Sie zuerst eine Sammelmappe in Ihrem ePortfolio an.';
$string['pdfbadge'] = 'Badge: {$a}';
$string['pdffiles'] = 'Dateien';
$string['pdfimageomitted'] = 'Bild nicht übernommen: {$a}';
$string['pdfmedia'] = 'Eingebettete Medien sind nur online im ePortfolio sichtbar.';
$string['pdfowner'] = 'Erstellt von: {$a}';
$string['pdfresume'] = 'Lebenslauf-Abschnitte sind im PDF nicht enthalten.';
$string['pdfsnapshot'] = 'Stand: {$a}';
$string['pluginname'] = 'Abgabe von Portfolio-Sammelmappen';
$string['privacy:metadata:assignment'] = 'Die Aufgabe, in der die Sammelmappe abgegeben wurde.';
$string['privacy:metadata:filepurpose'] = 'Das PDF der abgegebenen Sammelmappe.';
$string['privacy:metadata:submission'] = 'Die Abgabe, zu der das PDF gehört.';
$string['privacy:metadata:tablesummary'] = 'Speichert, welche Sammelmappe abgegeben wurde und wann das PDF erstellt wurde.';
$string['privacy:metadata:timesnapshot'] = 'Zeitpunkt, zu dem das PDF erstellt wurde.';
$string['privacy:metadata:userid'] = 'Besitzer/in der abgegebenen Sammelmappe.';
$string['privacy:metadata:viewid'] = 'Die ID der abgegebenen Sammelmappe.';
$string['privacy:metadata:viewmodified'] = 'Letzte Änderung der Sammelmappe zum Zeitpunkt des PDFs.';
$string['privacy:metadata:viewname'] = 'Name der Sammelmappe zum Zeitpunkt des PDFs.';
$string['privacy:path'] = 'Portfolio-Sammelmappe';
$string['selectview'] = 'Sammelmappe';
$string['selectview_help'] = 'Wählen Sie die Sammelmappe aus Ihrem ePortfolio, die Sie abgeben möchten. Beim Speichern wird ein PDF der Sammelmappe in Ihrer Abgabe abgelegt. Beim Abgeben wird das PDF neu erstellt, damit es den aktuellen Stand zeigt. Spätere Änderungen an der Sammelmappe verändern Ihre Abgabe nicht. Eingebettete Videos und Lebenslauf-Abschnitte sind nicht Teil des PDFs.';
$string['snapshottime'] = 'PDF vom {$a}';
$string['submittedview'] = 'Sammelmappe: {$a}';
$string['viewchanged'] = 'Die Sammelmappe wurde nach Erstellung dieses PDFs geändert.';
$string['viewremoved'] = 'Die Sammelmappe existiert im ePortfolio nicht mehr; das PDF bleibt erhalten.';
