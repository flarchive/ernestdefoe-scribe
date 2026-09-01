<?php

namespace ErnestDefoe\Scribe\Formatter;

use s9e\TextFormatter\Configurator;

/**
 * Teaches the formatter to read the HTML the editor produces, and to keep
 * rendering the posts written before it existed.
 *
 * The whole design rests on one property of s9e: the *tag* vocabulary is
 * independent of the *syntax* that produced it. Markdown was only ever one
 * parser feeding those tags. Swap the parser for an HTML one and every post,
 * old and new, still renders through a single set of templates.
 */
class Configure
{
    public function __invoke(Configurator $config): void
    {
        $this->registerTags($config, Vocabulary::TEMPLATES, Vocabulary::ATTRIBUTES);
        $this->registerTags($config, Vocabulary::EXTRA_TEMPLATES, Vocabulary::ATTRIBUTES);

        $plugin = $config->plugins->load('HTMLElements');

        foreach (Vocabulary::ELEMENTS + Vocabulary::EXTRA_ELEMENTS as $element => $tag) {
            // A tag we skipped because nothing registered it (and nobody else
            // did either) must not be aliased, or the parser produces a tag the
            // renderer has no template for — which renders as nothing at all.
            if (! $config->tags->exists($tag)) {
                continue;
            }

            $plugin->aliasElement($element, $tag);

            foreach (Vocabulary::ATTRIBUTES[$tag] ?? [] as $attribute => $filter) {
                $plugin->aliasAttribute($element, Vocabulary::SOURCE_ATTRIBUTES[$attribute] ?? $attribute, $attribute);
            }
        }

        /*
         * <p> and <br> stay themselves. s9e renders a lowercase element in the
         * stored XML literally, which is exactly why paragraphs in existing
         * posts survive flarum/markdown being removed while <STRONG> would not.
         */
        foreach (Vocabulary::PASSTHROUGH as $element) {
            $plugin->allowElement($element);
        }
    }

    /**
     * Registers a tag only if nothing has claimed the name yet.
     *
     * 🚨 Never overwrite an existing template. flarum/bbcode owns CODE, DEL,
     * EMAIL, IMG, LI, LIST, QUOTE and URL when enabled, and its versions carry
     * things ours do not — syntax highlighting on CODE, the `uncited` class and
     * author citation on QUOTE. Clobbering those would silently strip
     * highlighting from every code block on the forum, which is precisely the
     * class of failure this extension exists to avoid.
     */
    private function registerTags(Configurator $config, array $templates, array $attributes): void
    {
        foreach ($templates as $name => $template) {
            if ($config->tags->exists($name)) {
                continue;
            }

            $tag = $config->tags->add($name);

            foreach ($attributes[$name] ?? [] as $attribute => $filter) {
                $attr = $tag->attributes->add($attribute);
                $attr->required = false;
                // The filter is the security boundary, not the template. An
                // unfiltered value interpolated into a style or href is stored
                // XSS; #color/#url/#uint reject the value outright instead.
                $attr->filterChain->append($filter);
            }

            $tag->template = $template;
        }
    }
}
