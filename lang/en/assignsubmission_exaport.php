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
 * English strings for the ePortfolio view submission plugin.
 *
 * @package    assignsubmission_exaport
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['chooseview'] = 'Choose a view ...';
$string['default'] = 'Enabled by default';
$string['default_help'] = 'If set, this submission method will be enabled by default for all new assignments.';
$string['enabled'] = 'ePortfolio view';
$string['enabled_help'] = 'If enabled, students choose one of their ePortfolio views (Exabis ePortfolio) as their submission. A PDF of the view is stored with the submission when it is saved and again when it is submitted for grading. Teachers grade and annotate this PDF; later changes to the view do not change the submission.';
$string['error_snapshotfailed'] = 'The PDF of the ePortfolio view could not be created. Please try again or contact the site administrator.';
$string['error_viewnotavailable'] = 'The chosen ePortfolio view does not exist any more or does not belong to you. Please edit your submission and choose another view.';
$string['exaport'] = 'ePortfolio view';
$string['gotoportfolio'] = 'Open my ePortfolio views';
$string['noviews'] = 'You have no ePortfolio views yet. Create a view in your ePortfolio first.';
$string['pdfbadge'] = 'Badge: {$a}';
$string['pdffiles'] = 'Files';
$string['pdfimageomitted'] = 'Image not included: {$a}';
$string['pdfmedia'] = 'Embedded media is only available online in the ePortfolio.';
$string['pdfowner'] = 'Owner: {$a}';
$string['pdfresume'] = 'Résumé sections are not included in the PDF.';
$string['pdfsnapshot'] = 'Snapshot: {$a}';
$string['pluginname'] = 'ePortfolio view submissions';
$string['privacy:metadata:assignment'] = 'The assignment the view was submitted to.';
$string['privacy:metadata:filepurpose'] = 'The PDF snapshot of the submitted ePortfolio view.';
$string['privacy:metadata:submission'] = 'The submission the snapshot belongs to.';
$string['privacy:metadata:tablesummary'] = 'Stores which ePortfolio view was submitted and when its PDF snapshot was taken.';
$string['privacy:metadata:timesnapshot'] = 'The time the PDF snapshot was taken.';
$string['privacy:metadata:userid'] = 'The owner of the submitted ePortfolio view.';
$string['privacy:metadata:viewid'] = 'The ID of the submitted ePortfolio view.';
$string['privacy:metadata:viewmodified'] = 'The latest change of the view when the snapshot was taken.';
$string['privacy:metadata:viewname'] = 'The name of the view when the snapshot was taken.';
$string['privacy:path'] = 'ePortfolio view';
$string['selectview'] = 'ePortfolio view';
$string['selectview_help'] = 'Choose the ePortfolio view you want to hand in. When you save, a PDF of the view is stored with your submission. When you submit for grading, the PDF is created again so that it shows the current state of the view. Changes you make to the view afterwards do not change your submission. Embedded videos and résumé sections are not part of the PDF.';
$string['snapshottime'] = 'PDF from {$a}';
$string['submittedview'] = 'View: {$a}';
$string['viewchanged'] = 'The view has been changed since this PDF was created.';
$string['viewremoved'] = 'The view no longer exists in the ePortfolio; the PDF is kept.';
