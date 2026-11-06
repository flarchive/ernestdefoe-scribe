<?php

namespace ErnestDefoe\Scribe\Tests\Formatter;

use ErnestDefoe\Scribe\Formatter\CodeBlocksWithoutInnerCode;
use PHPUnit\Framework\TestCase;
use s9e\TextFormatter\Configurator;
use s9e\TextFormatter\Parser;

/**
 * Code blocks written as <pre><code> showed a literal "<code>" — see the class.
 */
class CodeBlocksWithoutInnerCodeTest extends TestCase
{
    private function fix(string $html): string
    {
        $parser = (new \ReflectionClass(Parser::class))->newInstanceWithoutConstructor();

        return (new CodeBlocksWithoutInnerCode())($parser, null, $html);
    }

    /** A code block tag whose contents are not parsed, mapped from <pre>, as on a forum. */
    private function render(string $html): string
    {
        $c = new Configurator();
        $c->tags->add('CODE')->template = '<pre><code><xsl:if test="@lang"><xsl:attribute name="class">language-<xsl:value-of select="@lang"/></xsl:attribute></xsl:if><xsl:apply-templates/></code></pre>';
        $c->tags['CODE']->attributes->add('lang')->required = false;
        $c->tags['CODE']->rules->ignoreTags();
        $c->tags->add('C')->template = '<code><xsl:apply-templates/></code>';
        $h = $c->plugins->load('HTMLElements');
        $h->aliasElement('pre', 'CODE');
        $h->aliasAttribute('pre', 'data-lang', 'lang');
        $h->aliasElement('code', 'C');
        extract($c->finalize());

        return $renderer->render($parser->parse($html));
    }

    public function test_an_editor_code_block_renders_without_a_literal_code_tag(): void
    {
        $post = "<pre><code>composer update ernestdefoe/scribe\nphp flarum cache:clear</code></pre>";

        // The bug: the inner <code> printed as text.
        $this->assertStringContainsString('&lt;code&gt;', $this->render($post));

        $html = $this->render($this->fix($post));
        $this->assertStringNotContainsString('&lt;code&gt;', $html);
        $this->assertSame("<pre><code>composer update ernestdefoe/scribe\nphp flarum cache:clear</code></pre>", $html);
    }

    public function test_the_language_carries_over_for_highlighting(): void
    {
        $this->assertSame('<pre data-lang="php">echo 1;</pre>', $this->fix('<pre><code class="language-php">echo 1;</code></pre>'));
        $this->assertSame('<pre><code class="language-php">echo 1;</code></pre>', $this->render($this->fix('<pre><code class="language-php">echo 1;</code></pre>')));
    }

    public function test_an_existing_data_lang_wins(): void
    {
        $this->assertSame('<pre data-lang="js">x</pre>', $this->fix('<pre data-lang="js"><code class="language-php">x</code></pre>'));
    }

    public function test_inline_code_and_plain_pre_are_untouched(): void
    {
        foreach (['<p>Use <code>php flarum</code> here.</p>', '<pre>plain block</pre>', '<p>No code.</p>'] as $post) {
            $this->assertSame($post, $this->fix($post));
        }
    }

    public function test_two_blocks_are_both_fixed(): void
    {
        $this->assertSame('<pre>a</pre><p>x</p><pre>b</pre>', $this->fix('<pre><code>a</code></pre><p>x</p><pre><code>b</code></pre>'));
    }
}
