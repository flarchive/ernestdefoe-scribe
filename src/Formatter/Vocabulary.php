<?php

namespace ErnestDefoe\Scribe\Formatter;

/**
 * The single source of truth for how rich text is stored.
 *
 * 🚨 These tag names are NOT ours to invent. They are the vocabulary s9e's
 * Litedown plugin has always produced, which means every post written while
 * flarum/markdown was installed is already stored using them. Scribe emits the
 * same names from HTML, so a Scribe post and a legacy Markdown post are the
 * same shape in the database and share one render path. That is what makes
 * flarum/markdown uninstallable without rewriting a single row.
 *
 * The templates below were extracted from s9e/text-formatter 2.19.3's Litedown
 * defaults rather than written by hand, so legacy posts render byte-identically
 * to how they did before. LegacyParityTest asserts they still match, so an s9e
 * upgrade that changes them fails the suite instead of silently changing how
 * years of posts look.
 */
abstract class Vocabulary
{
    /**
     * HTML element => tag name. This is what TipTap is allowed to emit, and it
     * is deliberately a small, closed list: anything not named here is stripped
     * at parse time rather than stored, so a paste from Word cannot smuggle
     * arbitrary markup into a post.
     */
    public const ELEMENTS = [
        'strong'     => 'STRONG',
        'b'          => 'STRONG',
        'em'         => 'EM',
        'i'          => 'EM',
        'del'        => 'DEL',
        's'          => 'DEL',
        'strike'     => 'DEL',
        'code'       => 'C',
        'sup'        => 'SUP',
        'sub'        => 'SUB',
        'h1'         => 'H1',
        'h2'         => 'H2',
        'h3'         => 'H3',
        'h4'         => 'H4',
        'h5'         => 'H5',
        'h6'         => 'H6',
        'hr'         => 'HR',
        'ul'         => 'LIST',
        'ol'         => 'LIST',
        'li'         => 'LI',
        'blockquote' => 'QUOTE',
        'pre'        => 'CODE',
        'a'          => 'URL',
        'img'        => 'IMG',
    ];

    /**
     * Structural elements kept as themselves. s9e renders a lowercase element
     * literally, so these need no template — and legacy posts already contain
     * bare <p>/<br/>, which is why they survive markdown being removed.
     */
    public const PASSTHROUGH = ['p', 'br'];

    /**
     * Canonical templates, verbatim from Litedown. Only the tags nothing else
     * has claimed get registered — flarum/bbcode owns CODE, DEL, EMAIL, IMG,
     * LI, LIST, QUOTE and URL when it is enabled, and its versions carry extras
     * ours must not clobber (syntax highlighting on CODE, the `uncited` class
     * on QUOTE). See Configure::class.
     */
    public const TEMPLATES = [
        'C'      => '<code><xsl:apply-templates/></code>',
        'CODE'   => '<pre><code><xsl:if test="@lang"><xsl:attribute name="class"><xsl:text>language-</xsl:text><xsl:value-of select="@lang"/></xsl:attribute></xsl:if><xsl:apply-templates/></code></pre>',
        'DEL'    => '<del><xsl:apply-templates/></del>',
        'EM'     => '<em><xsl:apply-templates/></em>',
        'EMAIL'  => '<a href="mailto:{@email}"><xsl:apply-templates/></a>',
        'H1'     => '<h1><xsl:apply-templates/></h1>',
        'H2'     => '<h2><xsl:apply-templates/></h2>',
        'H3'     => '<h3><xsl:apply-templates/></h3>',
        'H4'     => '<h4><xsl:apply-templates/></h4>',
        'H5'     => '<h5><xsl:apply-templates/></h5>',
        'H6'     => '<h6><xsl:apply-templates/></h6>',
        'HR'     => '<hr/>',
        'IMG'    => '<img src="{@src}"><xsl:copy-of select="@alt"/><xsl:copy-of select="@title"/></img>',
        'LI'     => '<li><xsl:apply-templates/></li>',
        'LIST'   => '<xsl:choose><xsl:when test="not(@type)"><ul><xsl:apply-templates/></ul></xsl:when><xsl:otherwise><ol><xsl:copy-of select="@start"/><xsl:apply-templates/></ol></xsl:otherwise></xsl:choose>',
        'QUOTE'  => '<blockquote><div><xsl:apply-templates/></div></blockquote>',
        'STRONG' => '<strong><xsl:apply-templates/></strong>',
        'SUB'    => '<sub><xsl:apply-templates/></sub>',
        'SUP'    => '<sup><xsl:apply-templates/></sup>',
        'URL'    => '<a href="{@url}"><xsl:copy-of select="@title"/><xsl:apply-templates/></a>',
    ];

    /**
     * Everything above exists to stay bug-compatible with Markdown. Everything
     * below is what Scribe adds because it no longer has to be expressible in
     * Markdown at all.
     *
     * 🚨 Tables are the headline: Litedown has NO table syntax, so on a
     * Markdown forum a pasted table renders as a paragraph full of pipes. That
     * is not a bug we are fixing, it is a feature Flarum never had. No existing
     * post can contain these tags, so we own them outright.
     */
    public const EXTRA_ELEMENTS = [
        'u'     => 'U',
        'mark'  => 'MARK',
        'table' => 'TABLE',
        'thead' => 'THEAD',
        'tbody' => 'TBODY',
        'tr'    => 'TR',
        'th'    => 'TH',
        'td'    => 'TD',
        'span'  => 'SPAN',
    ];

    public const EXTRA_TEMPLATES = [
        'U'     => '<u><xsl:apply-templates/></u>',
        'MARK'  => '<mark><xsl:apply-templates/></mark>',
        'TABLE' => '<div class="Scribe-tableWrap"><table><xsl:apply-templates/></table></div>',
        'THEAD' => '<thead><xsl:apply-templates/></thead>',
        'TBODY' => '<tbody><xsl:apply-templates/></tbody>',
        'TR'    => '<tr><xsl:apply-templates/></tr>',
        'TH'    => '<th><xsl:copy-of select="@colspan"/><xsl:copy-of select="@rowspan"/><xsl:if test="@align"><xsl:attribute name="style"><xsl:text>text-align:</xsl:text><xsl:value-of select="@align"/></xsl:attribute></xsl:if><xsl:apply-templates/></th>',
        'TD'    => '<td><xsl:copy-of select="@colspan"/><xsl:copy-of select="@rowspan"/><xsl:if test="@align"><xsl:attribute name="style"><xsl:text>text-align:</xsl:text><xsl:value-of select="@align"/></xsl:attribute></xsl:if><xsl:apply-templates/></td>',
        /*
         * 🚨 The colour lives in an attribute filtered by s9e's #color, never in
         * a style string we assemble from user input. A span whose style we
         * concatenated by hand is a stored-XSS hole: "red;background:url(...)"
         * is a perfectly ordinary-looking colour until it isn't.
         */
        'SPAN'  => '<span><xsl:if test="@color"><xsl:attribute name="style"><xsl:text>color:</xsl:text><xsl:value-of select="@color"/></xsl:attribute></xsl:if><xsl:apply-templates/></span>',
    ];

    /**
     * tag => [ attribute => s9e filter ]. The filter is the security boundary:
     * a value that fails it is dropped, so the template can interpolate the
     * attribute without ever trusting what the client sent.
     */
    public const ATTRIBUTES = [
        'CODE'  => ['lang' => '#simpletext'],
        'EMAIL' => ['email' => '#email'],
        'IMG'   => ['src' => '#url', 'alt' => '#simpletext', 'title' => '#simpletext'],
        'LIST'  => ['type' => '#simpletext', 'start' => '#uint'],
        'URL'   => ['url' => '#url', 'title' => '#simpletext'],
        'TH'    => ['colspan' => '#uint', 'rowspan' => '#uint', 'align' => '#simpletext'],
        'TD'    => ['colspan' => '#uint', 'rowspan' => '#uint', 'align' => '#simpletext'],
        'SPAN'  => ['color' => '#color'],
    ];

    /**
     * Where an attribute is named differently in the HTML the editor emits.
     *
     * 🚨 `type` on LIST is not cosmetic. Both <ul> and <ol> alias to the single
     * LIST tag, and the template picks <ul> vs <ol> purely on whether @type is
     * present — so an ordered list that forgets to emit data-type silently
     * renders as a bulleted one. The editor's ordered-list node MUST set it.
     */
    public const SOURCE_ATTRIBUTES = [
        'url'   => 'href',
        'src'   => 'src',
        'color' => 'data-color',
        'lang'  => 'data-lang',
        'type'  => 'data-type',
        'align' => 'data-align',
    ];
}
