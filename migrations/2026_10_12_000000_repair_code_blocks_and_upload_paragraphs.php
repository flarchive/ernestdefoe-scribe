<?php

/*
 * Repair posts saved before Scribe 1.4.7 fixed two formatting bugs.
 *
 * 1. Code blocks written as <pre><code>…</code></pre> (how the editor writes
 *    them) kept the inner <code> as text, so every such block showed a literal
 *    "<code>…</code>" around its contents.
 * 2. A FoF Upload file, image card or text preview inside a paragraph left the
 *    paragraph's own </p> printed after it (Wil Vincent).
 *
 * The formatter now fixes both while parsing; this reparses the posts and
 * private messages that were saved before it did. Only content that shows one
 * of the two symptoms is touched, and only content Scribe wrote (its HTML
 * markers in the stored XML), so a Markdown post is never reparsed.
 */

use Flarum\Formatter\Formatter;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        /** @var Formatter $formatter */
        $formatter = resolve(Formatter::class);

        // The cached parser predates this release.
        $formatter->flush();

        $db = $schema->getConnection();
        $scribeMarkup = '/<s>&lt;(?:p|h[1-6]|li|ul|ol|strong|em|code|pre|blockquote|a)[ &]/';
        $broken = '~<CODE\b[^>]*>(?:<s>[^<]*</s>)?&lt;code\b|</UPL-(?:FILE|IMAGE|TEXT-PREVIEW)>&lt;/p&gt;~';

        $repair = function (string $table, string $modelClass) use ($formatter, $db, $scribeMarkup, $broken, $schema) {
            if (! $schema->hasTable($table) || ! class_exists($modelClass)) {
                return;
            }

            $query = $db->table($table)
                ->where(fn ($q) => $q->where('content', 'like', '%&lt;code%')->orWhere('content', 'like', '%&lt;/p&gt;%'))
                ->select(['id', 'content']);

            if ($table === 'posts') {
                $query->where('type', 'comment');
            }

            $query->chunkById(200, function ($rows) use ($formatter, $db, $scribeMarkup, $broken, $table, $modelClass) {
                foreach ($rows as $row) {
                    $xml = (string) $row->content;

                    if (! preg_match($scribeMarkup, $xml) || ! preg_match($broken, $xml)) {
                        continue;
                    }

                    $model = (new $modelClass())->newFromBuilder(['id' => $row->id]);
                    $reparsed = $formatter->parse((string) $formatter->unparse($xml, $model), $model);

                    if ($reparsed !== $xml) {
                        // The query builder, not save(): no edit stamp, no events, nothing re-notified.
                        $db->table($table)->where('id', $row->id)->update(['content' => $reparsed]);
                    }
                }
            });
        };

        $repair('posts', Flarum\Post\CommentPost::class);
        $repair('dialog_messages', 'Flarum\\Messages\\DialogMessage');
    },

    'down' => function () {
        // Nothing to undo: the reparsed content is what its authors wrote.
    },
];
