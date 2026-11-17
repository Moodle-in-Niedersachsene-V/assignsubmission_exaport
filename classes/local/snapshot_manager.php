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

namespace assignsubmission_exaport\local;

use context_module;
use core_text;
use moodle_exception;
use stdClass;
use stored_file;

/**
 * Stores, copies and removes the PDF snapshots of submitted ePortfolio views.
 *
 * One snapshot exists per submission: a record in assignsubmission_exaport
 * and one PDF file in the plugin's file area of the assignment context.
 *
 * @package    assignsubmission_exaport
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class snapshot_manager {
    /** @var string Frankenstyle name of the plugin. */
    public const COMPONENT = 'assignsubmission_exaport';

    /** @var string File area holding the PDF snapshots. */
    public const FILEAREA = 'snapshot';

    /** @var string Database table of the plugin. */
    public const TABLE = 'assignsubmission_exaport';

    /** @var context_module Context of the assignment. */
    private context_module $context;

    /** @var int Id of the assign instance. */
    private int $assignmentid;

    /** @var view_repository Read access to block_exaport. */
    private view_repository $repository;

    /**
     * Constructor.
     *
     * @param context_module $context Context of the assignment.
     * @param int $assignmentid Id of the assign instance.
     * @param view_repository|null $repository Read access to block_exaport.
     */
    public function __construct(context_module $context, int $assignmentid, ?view_repository $repository = null) {
        $this->context = $context;
        $this->assignmentid = $assignmentid;
        $this->repository = $repository ?? new view_repository();
    }

    /**
     * Create or replace the snapshot of a submission.
     *
     * @param int $submissionid Id in assign_submission.
     * @param int $viewid Chosen view.
     * @param int $ownerid User who must own the view.
     * @return int Id of the assignsubmission_exaport record.
     * @throws moodle_exception If the view is not available to the owner or the PDF cannot be built.
     */
    public function store_snapshot(int $submissionid, int $viewid, int $ownerid): int {
        global $DB;

        $view = $this->repository->get_owned_view($viewid, $ownerid);
        $owner = \core_user::get_user($ownerid, '*', MUST_EXIST);
        $timesnapshot = time();
        $viewmodified = $this->repository->get_last_modified($view);

        $renderer = new snapshot_renderer($this->repository);
        $html = $renderer->render($view, $owner, $timesnapshot);
        $pdf = $this->build_pdf($html, $view->name, fullname($owner));

        $fs = get_file_storage();
        $fs->delete_area_files($this->context->id, self::COMPONENT, self::FILEAREA, $submissionid);
        $fs->create_file_from_string([
            'contextid' => $this->context->id,
            'component' => self::COMPONENT,
            'filearea' => self::FILEAREA,
            'itemid' => $submissionid,
            'filepath' => '/',
            'filename' => $this->filename($view->name),
            'userid' => $ownerid,
        ], $pdf);

        $record = (object)[
            'assignment' => $this->assignmentid,
            'submission' => $submissionid,
            'userid' => $ownerid,
            'viewid' => $viewid,
            'viewname' => core_text::substr((string)$view->name, 0, 1333),
            'viewmodified' => $viewmodified,
            'timesnapshot' => $timesnapshot,
        ];
        $existing = $this->get_record($submissionid);
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record(self::TABLE, $record);
            return (int)$existing->id;
        }
        return (int)$DB->insert_record(self::TABLE, $record);
    }

    /**
     * Return the snapshot record of a submission.
     *
     * @param int $submissionid Id in assign_submission.
     * @return stdClass|null
     */
    public function get_record(int $submissionid): ?stdClass {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['assignment' => $this->assignmentid, 'submission' => $submissionid]);
        return $record ?: null;
    }

    /**
     * Return the PDF file of a submission.
     *
     * @param int $submissionid Id in assign_submission.
     * @return stored_file|null
     */
    public function get_file(int $submissionid): ?stored_file {
        $files = get_file_storage()->get_area_files(
            $this->context->id,
            self::COMPONENT,
            self::FILEAREA,
            $submissionid,
            'id ASC',
            false
        );
        return $files ? reset($files) : null;
    }

    /**
     * Tell whether the view changed after the snapshot was taken.
     *
     * @param stdClass $record Snapshot record.
     * @return bool|null True if changed, false if unchanged, null if the view no longer belongs to the owner.
     */
    public function is_view_changed(stdClass $record): ?bool {
        if (!$this->repository->is_owned_view((int)$record->viewid, (int)$record->userid)) {
            return null;
        }
        $view = $this->repository->get_owned_view((int)$record->viewid, (int)$record->userid);
        return $this->repository->get_last_modified($view) > (int)$record->viewmodified;
    }

    /**
     * Copy the snapshot of one submission to another (new attempt based on the previous one).
     *
     * @param int $sourceid Source submission id.
     * @param int $destid Destination submission id.
     */
    public function copy(int $sourceid, int $destid): void {
        global $DB;
        $fs = get_file_storage();
        $file = $this->get_file($sourceid);
        if ($file) {
            $fs->create_file_from_storedfile(['itemid' => $destid], $file);
        }
        $record = $this->get_record($sourceid);
        if ($record) {
            unset($record->id);
            $record->submission = $destid;
            $DB->insert_record(self::TABLE, $record);
        }
    }

    /**
     * Remove the snapshot of one submission.
     *
     * @param int $submissionid Id in assign_submission.
     */
    public function delete_for_submission(int $submissionid): void {
        global $DB;
        get_file_storage()->delete_area_files($this->context->id, self::COMPONENT, self::FILEAREA, $submissionid);
        $DB->delete_records(self::TABLE, ['assignment' => $this->assignmentid, 'submission' => $submissionid]);
    }

    /**
     * Remove all snapshots of the assignment.
     */
    public function delete_for_assignment(): void {
        global $DB;
        get_file_storage()->delete_area_files($this->context->id, self::COMPONENT, self::FILEAREA);
        $DB->delete_records(self::TABLE, ['assignment' => $this->assignmentid]);
    }

    /**
     * Turn the rendered HTML into a PDF document.
     *
     * @param string $html HTML from snapshot_renderer.
     * @param string $title Document title (view name).
     * @param string $author Full name of the owner.
     * @return string PDF content.
     * @throws moodle_exception If TCPDF fails.
     */
    private function build_pdf(string $html, string $title, string $author): string {
        global $CFG;
        require_once($CFG->libdir . '/pdflib.php');

        try {
            $pdf = new class ('P', 'mm', 'A4') extends \pdf {
                /**
                 * Footer with document title and page numbers; overrides TCPDF::Footer() (PHP method names ignore case).
                 */
                public function footer() {
                    $this->SetY(-12);
                    $this->SetFont('freesans', '', 8);
                    $this->SetTextColor(128, 128, 128);
                    $this->Cell(0, 6, core_text::substr($this->title, 0, 90), 0, 0, 'L');
                    $this->SetX($this->getMargins()['left']);
                    $this->Cell(0, 6, $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'R');
                }
            };
            $pdf->SetCreator('Moodle ' . self::COMPONENT);
            $pdf->SetAuthor($author);
            $pdf->SetTitle($title);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(true);
            $pdf->SetMargins(15, 15, 15);
            $pdf->SetAutoPageBreak(true, 18);
            $pdf->SetFont('freesans', '', 10);
            $pdf->AddPage();
            $pdf->writeHTML($html, true, false, true, false, '');
            return $pdf->Output('', 'S');
        } catch (\Exception $e) {
            throw new moodle_exception('error_snapshotfailed', self::COMPONENT, '', null, $e->getMessage());
        }
    }

    /**
     * File name of the PDF, derived from the view name.
     *
     * @param string $viewname Name of the view.
     * @return string
     */
    private function filename(string $viewname): string {
        $name = clean_filename(core_text::substr(trim($viewname), 0, 80));
        if ($name === '' || $name === '_') {
            $name = 'view';
        }
        return 'ePortfolio_' . $name . '.pdf';
    }
}
