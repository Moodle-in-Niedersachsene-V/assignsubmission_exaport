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

use context_user;
use DOMDocument;
use DOMNode;
use DOMXPath;
use html_writer;
use stdClass;
use stored_file;

/**
 * Builds a print-ready HTML document of an ePortfolio view for the PDF snapshot.
 *
 * The output is meant for TCPDF's writeHTML(): the blocks of all columns are
 * written one below the other, images of the owner's portfolio files are
 * embedded as data streams, and anything TCPDF would have to fetch from the
 * network (external images, embedded media) is replaced by a short note.
 * All user content passes through format_text() before it is used.
 *
 * @package    assignsubmission_exaport
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class snapshot_renderer {
    /** @var string Placeholder URL for embedded images; never requested because the host is reserved (RFC 2606). */
    private const IMAGE_TOKEN = 'https://assignsubmission-exaport.invalid/image/';

    /** @var int Images larger than this (pixels per side) are scaled down before they go into the PDF. */
    private const MAX_IMAGE_PIXELS = 1200;

    /** @var int Maximum display width of an image in the PDF, in TCPDF pixels (about 176 mm). */
    private const MAX_DISPLAY_WIDTH = 500;

    /** @var string Component of the source files. */
    private const SOURCE_COMPONENT = 'block_exaport';

    /** @var array<int, array{data: string, width: int}> Embedded images indexed by token number. */
    private array $images = [];

    /** @var context_user|null User context of the view owner while rendering. */
    private ?context_user $usercontext = null;

    /** @var view_repository Read access to the view data. */
    private view_repository $repository;

    /**
     * Constructor.
     *
     * @param view_repository $repository Read access to the view data.
     */
    public function __construct(view_repository $repository) {
        $this->repository = $repository;
    }

    /**
     * Render a view as HTML for TCPDF.
     *
     * @param stdClass $view The block_exaportview record; the caller has checked the owner.
     * @param stdClass $owner User record of the view owner.
     * @param int $timesnapshot Time stamp printed as snapshot time.
     * @return string HTML document fragment.
     */
    public function render(stdClass $view, stdClass $owner, int $timesnapshot): string {
        $this->images = [];
        $this->usercontext = context_user::instance((int)$view->userid);

        $html = $this->styles();
        $html .= html_writer::tag('h1', format_string($view->name, true, ['context' => $this->usercontext]));
        $meta = get_string('pdfowner', 'assignsubmission_exaport', fullname($owner)) . ' · ' .
            get_string('pdfsnapshot', 'assignsubmission_exaport', userdate($timesnapshot));
        $html .= html_writer::tag('p', s($meta), ['class' => 'meta']);
        $html .= $this->render_text($view->description ?? '', FORMAT_HTML, 'view', (int)$view->id);
        $html .= html_writer::empty_tag('hr');

        foreach ($this->repository->get_blocks($view) as $block) {
            $html .= $this->render_block($block, $view);
        }
        return $this->finalise($html);
    }

    /**
     * Basic styles understood by TCPDF.
     *
     * @return string
     */
    private function styles(): string {
        return '<style>
            h1 { font-size: 18pt; color: #1f3864; }
            h2 { font-size: 14pt; color: #1f3864; }
            h3 { font-size: 12pt; color: #1f3864; }
            p.meta { font-size: 9pt; color: #595959; }
            p.note { font-size: 9pt; color: #595959; font-style: italic; }
            div.contentblock { margin-left: 10px; }
        </style>';
    }

    /**
     * Render one block of the view.
     *
     * @param stdClass $block block_exaportviewblock record.
     * @param stdClass $view The view.
     * @return string
     */
    private function render_block(stdClass $block, stdClass $view): string {
        $title = trim((string)($block->block_title ?? ''));
        $heading = $title === '' ? '' : html_writer::tag('h3', s($title));

        switch ($block->type) {
            case view_repository::BLOCK_ITEM:
                return $this->render_item($block->item);
            case 'headline':
                return html_writer::tag('h2', nl2br(s((string)$block->text)));
            case 'personal_information':
                $name = trim(($block->firstname ?? '') . ' ' . ($block->lastname ?? ''));
                $html = $heading;
                if ($name !== '') {
                    $html .= html_writer::tag('p', html_writer::tag('strong', s($name)));
                }
                if (!empty($block->email)) {
                    $html .= html_writer::tag('p', s($block->email));
                }
                return $html . $this->render_text($block->text ?? '', FORMAT_HTML, 'view_content', (int)$view->id);
            case 'media':
                return $heading . $this->note(get_string('pdfmedia', 'assignsubmission_exaport'));
            case 'badge':
                $badge = $this->repository->get_issued_badge((int)$block->itemid, (int)$view->userid);
                if (!$badge) {
                    return '';
                }
                return html_writer::tag('h3', s(get_string('pdfbadge', 'assignsubmission_exaport', $badge->name))) .
                    html_writer::tag('p', s($badge->description));
            case 'cv_group':
            case 'cv_information':
                return $heading . $this->note(get_string('pdfresume', 'assignsubmission_exaport'));
            default:
                return $heading . $this->render_text($block->text ?? '', FORMAT_HTML, 'view_content', (int)$view->id);
        }
    }

    /**
     * Render a portfolio item with its description, content blocks and files.
     *
     * @param stdClass $item block_exaportitem record of the view owner.
     * @return string
     */
    private function render_item(stdClass $item): string {
        $html = html_writer::tag('h3', format_string($item->name, true, ['context' => $this->usercontext]));
        if ($item->type === 'link' && !empty($item->url)) {
            $html .= html_writer::tag('p', $this->link($item->url));
        }
        $html .= $this->render_text($item->intro ?? '', FORMAT_HTML, 'item_content', (int)$item->id);

        foreach ($this->repository->get_item_content_blocks((int)$item->id) as $contentblock) {
            $html .= $this->render_content_block($contentblock);
        }

        $html .= $this->render_files('item_file', (int)$item->id);
        return $html;
    }

    /**
     * Render a structured content block of an item.
     *
     * @param stdClass $contentblock block_exaportitemblock record.
     * @return string
     */
    private function render_content_block(stdClass $contentblock): string {
        $html = '';
        if (trim((string)$contentblock->title) !== '') {
            $html .= html_writer::tag('p', html_writer::tag('strong', s($contentblock->title)));
        }
        switch ($contentblock->type) {
            case 'text':
                $html .= $this->render_text(
                    $contentblock->content ?? '',
                    (int)$contentblock->contentformat,
                    'item_content_text',
                    (int)$contentblock->id
                );
                break;
            case 'link':
                if (!empty($contentblock->url)) {
                    $html .= html_writer::tag('p', $this->link($contentblock->url));
                }
                break;
            case 'file':
                $html .= $this->render_files('item_content_file', (int)$contentblock->id);
                break;
            default:
                // Linked content (for example competencies) only exists online; the title above names it.
                break;
        }
        if ($html === '') {
            return '';
        }
        // The parts of $html come from s(), format_string() and format_text() above, so it is clean HTML.
        // phpcs:ignore moodle.Commenting.InlineComment
        return html_writer::div($html, 'contentblock'); // nosemgrep: moodle-html-writer-inhalt
    }

    /**
     * Render the files of a file area: images inline, other files as a list.
     *
     * @param string $filearea File area of block_exaport in the owner's user context.
     * @param int $itemid Item id of the file area.
     * @return string
     */
    private function render_files(string $filearea, int $itemid): string {
        $files = get_file_storage()->get_area_files(
            $this->usercontext->id,
            self::SOURCE_COMPONENT,
            $filearea,
            $itemid,
            'filepath ASC, filename ASC, id ASC',
            false
        );
        $images = '';
        $others = [];
        foreach ($files as $file) {
            $token = $this->register_image($file);
            if ($token !== null) {
                $images .= html_writer::tag('p', html_writer::empty_tag('img', ['src' => $token, 'alt' => $file->get_filename()]));
            } else {
                $others[] = s($file->get_filename()) . ' (' . display_size($file->get_filesize()) . ')';
            }
        }
        $html = $images;
        if ($others) {
            $html .= html_writer::tag('p', html_writer::tag('strong', get_string('pdffiles', 'assignsubmission_exaport')));
            $html .= html_writer::alist($others);
        }
        return $html;
    }

    /**
     * Clean a user text and prepare its embedded files.
     *
     * References to files of the given area (@@PLUGINFILE@@) are resolved before
     * format_text() runs: images become placeholder URLs, other file references
     * are emptied and later unwrapped.
     *
     * @param string $text Raw text from block_exaport.
     * @param int $format Text format.
     * @param string $filearea File area of embedded files.
     * @param int $itemid Item id of the file area.
     * @return string Clean HTML.
     */
    private function render_text(string $text, int $format, string $filearea, int $itemid): string {
        if (trim($text) === '') {
            return '';
        }
        $text = preg_replace_callback(
            '~\b(src|href)(\s*=\s*)(["\'])@@PLUGINFILE@@/([^"\']*)\3~i',
            function (array $match) use ($filearea, $itemid): string {
                $token = null;
                if (strtolower($match[1]) === 'src') {
                    $file = $this->find_file($filearea, $itemid, $match[4]);
                    $token = $file ? $this->register_image($file) : null;
                }
                return $match[1] . $match[2] . $match[3] . ($token ?? '') . $match[3];
            },
            $text
        );
        return format_text($text, $format, ['context' => $this->usercontext, 'filter' => false]);
    }

    /**
     * Look up a file referenced as @@PLUGINFILE@@/path/name in the owner's area.
     *
     * @param string $filearea File area.
     * @param int $itemid Item id of the file area.
     * @param string $relativepath URL-encoded path after @@PLUGINFILE@@/.
     * @return stored_file|null
     */
    private function find_file(string $filearea, int $itemid, string $relativepath): ?stored_file {
        $relativepath = rawurldecode(strtok($relativepath, '?#'));
        $parts = explode('/', $relativepath);
        $filename = array_pop($parts);
        $filepath = $parts ? '/' . implode('/', $parts) . '/' : '/';
        if ($filename === '' || $filename === '.') {
            return null;
        }
        $file = get_file_storage()->get_file(
            $this->usercontext->id,
            self::SOURCE_COMPONENT,
            $filearea,
            $itemid,
            $filepath,
            $filename
        );
        return $file ?: null;
    }

    /**
     * Prepare an image for embedding and return its placeholder URL.
     *
     * @param stored_file $file A stored file.
     * @return string|null Placeholder URL, or null if the file is no web image (JPEG, PNG, GIF).
     */
    private function register_image(stored_file $file): ?string {
        if (!$file->is_valid_image()) {
            return null;
        }
        $info = $file->get_imageinfo();
        $width = (int)$info['width'];
        $height = (int)$info['height'];
        if (max($width, $height) > self::MAX_IMAGE_PIXELS) {
            $content = $file->resize_image(self::MAX_IMAGE_PIXELS, self::MAX_IMAGE_PIXELS);
            if ($content === false) {
                return null;
            }
            $width = (int)round($width * self::MAX_IMAGE_PIXELS / max($width, $height));
        } else {
            $content = $file->get_content();
        }
        $index = count($this->images);
        $this->images[$index] = [
            'data' => base64_encode($content),
            'width' => min($width, self::MAX_DISPLAY_WIDTH),
        ];
        return self::IMAGE_TOKEN . $index;
    }

    /**
     * A safe external link showing its own address.
     *
     * @param string $url Address entered by the user.
     * @return string
     */
    private function link(string $url): string {
        $clean = clean_param($url, PARAM_URL);
        if ($clean === '' || !preg_match('~^https?://~i', $clean)) {
            return s($url);
        }
        return html_writer::link($clean, s($clean));
    }

    /**
     * A short italic note.
     *
     * @param string $text Plain text.
     * @return string
     */
    private function note(string $text): string {
        return html_writer::tag('p', s($text), ['class' => 'note']);
    }

    /**
     * Replace image placeholders by data streams and remove what TCPDF must not fetch.
     *
     * Images whose source is not one of our placeholders are replaced by a note,
     * so TCPDF never loads anything from the network or the file system. Links
     * other than http(s) are unwrapped; embedded media is replaced by a note.
     *
     * @param string $html Rendered HTML.
     * @return string HTML for TCPDF.
     */
    private function finalise(string $html): string {
        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadHTML(
            '<?xml encoding="UTF-8"?><html><body><div id="snapshotroot">' . $html . '</div></body></html>',
            LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($doc);

        foreach (iterator_to_array($xpath->query('//img')) as $img) {
            $src = $img->getAttribute('src');
            $index = str_starts_with($src, self::IMAGE_TOKEN) ? (int)substr($src, strlen(self::IMAGE_TOKEN)) : -1;
            if (isset($this->images[$index])) {
                $replacement = $doc->createElement('img');
                $replacement->setAttribute('src', '@' . $this->images[$index]['data']);
                $replacement->setAttribute('width', (string)$this->images[$index]['width']);
                $img->parentNode->replaceChild($replacement, $img);
            } else {
                $alt = trim($img->getAttribute('alt'));
                $text = get_string('pdfimageomitted', 'assignsubmission_exaport', $alt === '' ? '-' : $alt);
                $img->parentNode->replaceChild($doc->createTextNode('[' . $text . ']'), $img);
            }
        }

        foreach (iterator_to_array($xpath->query('//iframe|//video|//audio|//object|//embed')) as $media) {
            $note = $doc->createElement('em');
            $note->appendChild($doc->createTextNode('[' . get_string('pdfmedia', 'assignsubmission_exaport') . ']'));
            $media->parentNode->replaceChild($note, $media);
        }

        foreach (iterator_to_array($xpath->query('//a')) as $anchor) {
            if (!preg_match('~^https?://~i', $anchor->getAttribute('href'))) {
                $this->unwrap($anchor);
            }
        }

        $root = $doc->getElementById('snapshotroot');
        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $doc->saveHTML($child);
        }
        return $result;
    }

    /**
     * Replace an element by its children.
     *
     * @param DOMNode $node Element to unwrap.
     */
    private function unwrap(DOMNode $node): void {
        $parent = $node->parentNode;
        while ($node->firstChild) {
            $parent->insertBefore($node->firstChild, $node);
        }
        $parent->removeChild($node);
    }
}
