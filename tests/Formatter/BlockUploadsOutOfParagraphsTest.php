<?php

namespace ErnestDefoe\Scribe\Tests\Formatter;

use ErnestDefoe\Scribe\Formatter\BlockUploadsOutOfParagraphs;
use PHPUnit\Framework\TestCase;
use s9e\TextFormatter\Configurator;
use s9e\TextFormatter\Parser;

/**
 * A FoF Upload file inside a Scribe paragraph rendered a literal "</p>" after
 * its download button — see the class for why.
 */
class BlockUploadsOutOfParagraphsTest extends TestCase
{
    private function lift(string $html): string
    {
        $parser = (new \ReflectionClass(Parser::class))->newInstanceWithoutConstructor();

        return (new BlockUploadsOutOfParagraphs())($parser, null, $html);
    }

    /** Parse and render the way a forum with Scribe and FoF Upload does. */
    private function render(string $html): string
    {
        $c = new Configurator();
        $c->plugins->load('HTMLElements')->allowElement('p');
        $c->BBCodes->addCustom(
            '[upl-file uuid={IDENTIFIER} size={SIMPLETEXT2}]{TEXT1}[/upl-file]',
            '<div class="ButtonGroup" data-fof-upload-download-uuid="{@uuid}"><div class="Button">{TEXT1}</div><div class="Button"><xsl:value-of select="@size"/></div></div>'
        );
        $c->BBCodes->addCustom(
            '[upl-image-preview uuid={IDENTIFIER} url={URL?}]',
            '<a href="{@url}"><img src="{@url}" data-id="{@uuid}"/></a>'
        );
        extract($c->finalize());

        return $renderer->render($parser->parse($html));
    }

    public function test_a_file_on_its_own_line_renders_without_a_stray_closing_tag(): void
    {
        $post = '<p>Here is the list.</p><p>[upl-file uuid=abc size=13kB]FBS Team W-L Records W6[/upl-file]</p>';

        // The bug, as Wil saw it: the paragraph's own </p> printed as text.
        $this->assertStringContainsString('&lt;/p&gt;', $this->render($post));

        $html = $this->render($this->lift($post));
        $this->assertStringNotContainsString('&lt;/p&gt;', $html);
        $this->assertStringContainsString('<p>Here is the list.</p><div class="ButtonGroup" data-fof-upload-download-uuid="abc">', $html);
    }

    public function test_text_either_side_of_a_file_stays_in_paragraphs(): void
    {
        $this->assertSame(
            '<p class="x">Before </p>[upl-file uuid=abc size=1kB]a.xlsx[/upl-file]<p class="x"> after</p>',
            $this->lift('<p class="x">Before [upl-file uuid=abc size=1kB]a.xlsx[/upl-file] after</p>')
        );
    }

    public function test_two_files_in_one_paragraph_both_come_out(): void
    {
        $this->assertSame(
            '[upl-file uuid=a size=1kB]a[/upl-file][upl-file uuid=b size=2kB]b[/upl-file]',
            $this->lift('<p>[upl-file uuid=a size=1kB]a[/upl-file]<br>[upl-file uuid=b size=2kB]b[/upl-file]</p>')
        );
    }

    public function test_paragraphs_without_a_block_upload_are_untouched(): void
    {
        foreach ([
            '<p>Plain text with [brackets] and a [b]bold[/b] word.</p>',
            '<p>An inline image [upl-image-preview uuid=abc url=https://example.com/a.png] stays put.</p>',
            '<p>No uploads at all.</p><p>Second paragraph.</p>',
        ] as $post) {
            $this->assertSame($post, $this->lift($post));
        }
    }

    public function test_a_post_with_no_uploads_is_returned_as_is(): void
    {
        $post = '<p>Hello</p>';
        $this->assertSame($post, $this->lift($post));
    }
}
