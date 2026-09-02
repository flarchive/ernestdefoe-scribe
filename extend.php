<?php

/*
 * Scribe — a true WYSIWYG editor for Flarum.
 *
 * Deliberately does NOT depend on flarum/markdown. See src/Formatter/Vocabulary.
 */

use Flarum\Extend;
use ErnestDefoe\Scribe\Formatter\Configure;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        /*
         * 🚨 Without this the editor chunk 404s and the composer stays empty.
         *
         * TipTap is loaded with a dynamic import so it stays out of forum.js —
         * ~430KB that would otherwise be downloaded by every visitor on every
         * page, including guests who cannot post. Flarum does not publish an
         * extension's chunk files unless the directory holding them is declared
         * here; the browser requests the chunk, gets a 404, and the editor never
         * mounts. There is no server-side error to find when that happens.
         */
        ->jsDirectory(__DIR__.'/js/dist/forum')
        ->css(__DIR__.'/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    (new Extend\Formatter)
        ->configure(Configure::class),

    /*
     * Which buttons the toolbar shows, in order, as chosen in the AdminCP.
     *
     * Sent to the forum so the composer can build its toolbar on first paint
     * without an extra request. Decoded here rather than in JS so a corrupt or
     * hand-edited value fails to a null the client reads as "use the defaults",
     * instead of throwing inside the composer and leaving no editor at all.
     */
    (new Extend\Settings)
        ->serializeToForum('scribeToolbar', 'ernestdefoe-scribe.toolbar', function ($value) {
            $decoded = json_decode((string) $value, true);

            return is_array($decoded) && $decoded !== [] ? array_values(array_filter($decoded, 'is_string')) : null;
        }),

    new Extend\Locales(__DIR__.'/locale'),
];
