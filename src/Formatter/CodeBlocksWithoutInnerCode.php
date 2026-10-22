<?php

namespace ErnestDefoe\Scribe\Formatter;

use s9e\TextFormatter\Parser;

/**
 * Turns `<pre><code class="language-x">…</code></pre>` into `<pre data-lang="x">…</pre>`.
 *
 * 🚨 Every code block arrived like that — it is how the editor (Tiptap's
 * CodeBlock) and HTML in general write one — but Scribe maps <pre> to the code
 * block tag, whose contents are never parsed. The inner <code> was therefore
 * kept as TEXT, and every code block showed a literal "<code>…</code>" around
 * its contents. The language moves to `data-lang`, which is what the code
 * block's `lang` (and its syntax highlighting) is read from.
 */
class CodeBlocksWithoutInnerCode
{
    public function __invoke(Parser $parser, mixed $context, string $text): string
    {
        if (stripos($text, '<pre') === false || stripos($text, '<code') === false) {
            return $text;
        }

        return self::apply($text);
    }

    public static function apply(string $text): string
    {
        return preg_replace_callback('~<pre\b([^>]*)>\s*<code\b([^>]*)>(.*?)</code>\s*</pre>~is', function (array $m) {
            $pre = $m[1];

            if (stripos($pre, 'data-lang') === false
                && preg_match('~\bclass\s*=\s*"[^"]*\blanguage-([A-Za-z0-9_+#.-]+)~i', $m[2], $lang)) {
                $pre .= ' data-lang="' . $lang[1] . '"';
            }

            return "<pre$pre>{$m[3]}</pre>";
        }, $text) ?? $text;
    }
}
