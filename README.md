# ePortfolio view submissions (assignsubmission_exaport)

Submission plugin for the Moodle assignment activity. Students hand in one of
their ePortfolio views ("Sammelmappen") from Exabis ePortfolio (`block_exaport`)
as their submission. The plugin stores a PDF snapshot of the view with the
submission, so teachers grade a fixed state and can annotate it with the
built-in PDF annotation (`assignfeedback_editpdf`).

Maintainer: Moodle in Niedersachsen e. V.

## Requirements

- Moodle 5.1 to 5.3 (requires 2025100600)
- PHP 8.2, 8.3 or 8.4
- MariaDB, MySQL or PostgreSQL
- `block_exaport` 2026111202 or later

## Installation

Unpack the ZIP into `mod/assign/submission/exaport` (in Moodle 5.1+:
`public/mod/assign/submission/exaport`) and run the upgrade.

## How it works

1. The teacher enables the submission type "ePortfolio view" in the
   assignment settings. The site default can be set under
   *Site administration › Plugins › Activity modules › Assignment ›
   Submission plugins › ePortfolio view submissions*.
2. The student chooses one of their own views in the submission form. Only
   views the student owns are offered; the owner is checked again on the
   server when the submission is saved.
3. On saving, the plugin renders the view and stores
   `ePortfolio_<view name>.pdf` in the submission. When the student submits
   for grading, the PDF is created again so it shows the state at hand-in.
4. Teachers see the view name, the time of the PDF and a note if the view has
   been changed or deleted since. The PDF appears in the grader, in
   "Download all submissions" and in the PDF annotation tool.

## What the PDF contains

- View name, owner, snapshot time and view description
- All blocks of all columns, one below the other: headlines, text blocks,
  personal information (name, e-mail, text), badges the owner holds
- Items of the owner: name, link, description, structured content blocks
  (text, link, files) and attached files. JPEG, PNG and GIF images are
  embedded (scaled to max. 1200 px); other files are listed by name and size.

Not included (a note is printed instead): embedded media (videos, iframes),
résumé sections, external images, items of other users that were shared with
the owner. The layout of the view (columns, sizes) is not reproduced.

## Security and privacy

- Only the owner's own views and items are read; nothing is written to
  `block_exaport` tables.
- All user text passes through `format_text()`. TCPDF receives only embedded
  image data; it never loads files or URLs.
- The PDF is served through `assignsubmission_exaport_pluginfile()` with the
  same checks as file submissions (`can_view_submission()` /
  `can_view_group_submission()`) and is always sent as download.
- Privacy provider: export and deletion of the snapshot record and the PDF.
- Events: `submission_created` / `submission_updated` (based on mod_assign).

## Known limitations (prototype)

- The PDF is built by this plugin with Moodle's TCPDF and does not use the PDF
  export of `block_exaport`. A shared rendering class in `block_exaport` would
  be preferable and is to be discussed with GTN.
- Résumé blocks are not rendered.
- `lang/de/` is shipped for the association's sites (deviation from rule N3);
  remove it before a submission to the Moodle plugin directory.

## License

GNU GPL v3 or later, see <https://www.gnu.org/copyleft/gpl.html>.
