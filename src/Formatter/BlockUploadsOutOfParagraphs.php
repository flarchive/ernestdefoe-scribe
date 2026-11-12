<?php

namespace ErnestDefoe\Scribe\Formatter;

use s9e\TextFormatter\Parser;

/**
 * Lifts FoF Upload's block-level tags out of the paragraph Scribe put them in.
 *
 * 🚨 Scribe stores a post as HTML, and the editor's only home for text is a
 * paragraph, so an inserted `[upl-file …]…[/upl-file]` arrives as
 * `<p>[upl-file …]…[/upl-file]</p>`. FoF Upload renders that tag as a <div>.
 * A paragraph cannot hold a <div>, so the formatter closes the <p> just before
 * it — and the post's own `</p>` is then left closing nothing, and is printed
 * as text: a literal "</p>" after the download button. Reported by Wil Vincent.
 *
 * So, before parsing, any paragraph holding one of those tags is split around
 * it: the text either side stays in paragraphs and the tag stands between
 * them, where a block belongs. A paragraph without one is left exactly as it
 * was. `upl-image-preview` renders inline (<a><img>) and is not touched.
 */
class BlockUploadsOutOfParagraphs
{
    /** FoF Upload tags whose template is a block element (<div>). */
    private const BLOCK_TAGS = 'upl-file|upl-image|upl-text-preview';

    public function __invoke(Parser $parser, mixed $context, string $text): string
    {
        if (! str_contains($text, '[upl-')) {
            return $text;
        }

        return preg_replace_callback('~<p\b([^>]*)>(.*?)</p>~is', function (array $m) {
            $attrs = $m[1];
            $parts = preg_split('~(\[(' . self::BLOCK_TAGS . ')\b[^\]]*\].*?\[/\2\])~is', $m[2], -1, PREG_SPLIT_DELIM_CAPTURE);

            if ($parts === false || count($parts) === 1) {
                return $m[0];
            }

            $out = '';

            // preg_split with two capture groups yields: text, whole tag, tag name, text, …
            for ($i = 0, $n = count($parts); $i < $n; $i++) {
                $piece = $parts[$i];

                if ($i % 3 === 0) {
                    if (trim(preg_replace('~<br\s*/?>~i', '', $piece) ?? $piece) !== '') {
                        $out .= "<p$attrs>$piece</p>";
                    }
                } elseif ($i % 3 === 1) {
                    $out .= $piece;
                }
            }

            return $out;
        }, $text) ?? $text;
    }
}
