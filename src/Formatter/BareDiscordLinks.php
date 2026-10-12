<?php

namespace ErnestDefoe\Scribe\Formatter;

use Flarum\Extension\ExtensionManager;
use s9e\TextFormatter\Parser;

/**
 * Hands pasted Discord links to fof/discord-autolink as bare URLs.
 *
 * 🚨 Scribe stores every link as an <a> element — the editor turns a pasted URL
 * into a link as it lands. fof/discord-autolink recognises Discord URLs in
 * TEXT, at parse time, so behind an <a> it never sees one: invites stayed
 * ordinary links and the cards never appeared on a Scribe forum.
 *
 * A link whose visible text IS its own address is what a paste produces, so
 * that exact shape is unwrapped back to the bare URL, which the plugin then
 * claims (and renders as a link anyway). A link with its own wording is the
 * writer's choice and is left alone.
 *
 * 🚨 Discord URLs ONLY, and only while fof/discord-autolink is enabled. Unwrapping
 * links in general is not safe here: core's Autolink reads Scribe's HTML as
 * text, so a bare URL ending a paragraph swallowed the "</p" into its address
 * (measured on dev.ernestdefoe.online: url="…dQw4w9WgXcQ%3C/p"). Discord's own
 * plugins match a tighter pattern and stop where they should.
 */
class BareDiscordLinks
{
    /*
     * 🚨 The plugin's OWN patterns, anchored to the whole URL. A Discord-looking
     * address the plugin would NOT claim (a malformed id, say) must stay an <a>:
     * unwrapped, nothing claims it but core's Autolink, which swallows the
     * "</p" after it. Copied from fof/discord-autolink 2.0.0's Configurators.
     */
    private const PATTERNS = [
        // DiscordInvite
        '~^https?://(?:www\.)?(?:discord\.gg|discord(?:app)?\.com/invite)/[A-Za-z0-9-]{2,32}(?:/?\?event=\d{17,20})?$~i',
        // DiscordChannel (channel, message, @me DMs)
        '~^https?://(?:(?:www|ptb|canary)\.)?discord(?:app)?\.com/channels/(?:\d{17,20}|@me)/\d{17,20}(?:/\d{17,20})?$~i',
        // DiscordEvent
        '~^https?://(?:(?:www|ptb|canary)\.)?discord(?:app)?\.com/events/\d{17,20}/\d{17,20}$~i',
    ];

    public function __construct(protected ExtensionManager $extensions)
    {
    }

    public function __invoke(Parser $parser, mixed $context, string $text): string
    {
        if (! str_contains($text, 'discord') || ! $this->extensions->isEnabled('fof-discord-autolink')) {
            return $text;
        }

        return preg_replace_callback('~<a\b([^>]*)>([^<]*)</a>~i', function (array $m) {
            if (! preg_match('~\bhref\s*=\s*"([^"]*)"~i', $m[1], $href)) {
                return $m[0];
            }

            $url = html_entity_decode($href[1], ENT_QUOTES | ENT_HTML5);
            $label = html_entity_decode(trim($m[2]), ENT_QUOTES | ENT_HTML5);

            if ($url !== $label || ! $this->claimed($url)) {
                return $m[0];
            }

            // Raw, not re-escaped: this parser takes text literally, so "&amp;"
            // would reach the page as written. The pattern above already
            // excludes < > and ", the characters that could start markup.
            return $url;
        }, $text) ?? $text;
    }

    private function claimed(string $url): bool
    {
        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }

        return false;
    }
}
