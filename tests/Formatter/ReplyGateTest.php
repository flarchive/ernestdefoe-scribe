<?php

namespace ErnestDefoe\Scribe\Tests\Formatter;

use ErnestDefoe\Scribe\Formatter\Configure;
use ErnestDefoe\Scribe\Formatter\ReplyGate;
use PHPUnit\Framework\TestCase;
use s9e\TextFormatter\Configurator;

/**
 * "Reply to view" through the real formatter: what a viewer who has not
 * replied receives must not contain the gated text at all.
 */
class ReplyGateTest extends TestCase
{
    private static $parser;
    private static $renderer;

    public static function setUpBeforeClass(): void
    {
        $config = new Configurator();
        $config->plugins->load('Autolink');
        $config->plugins->load('HTMLEntities');
        (new Configure())($config);

        $built = $config->finalize();
        self::$parser = $built['parser'];
        self::$renderer = $built['renderer'];
    }

    private function xml(string $html): string
    {
        return self::$parser->parse($html);
    }

    public function test_withheld_render_carries_none_of_the_gated_text(): void
    {
        $xml = $this->xml('<p>Before.</p><section class="Scribe-replyGate"><p>The code is <strong>4471</strong>, see <a href="https://example.com/secret">the file</a></p></section><p>After.</p>');

        $open = self::$renderer->render($xml);
        $this->assertStringContainsString('4471', $open, 'an entitled viewer still gets the content');
        $this->assertStringNotContainsString('data-withheld', $open);

        $closed = self::$renderer->render(ReplyGate::withhold($xml));
        $this->assertStringNotContainsString('4471', $closed);
        $this->assertStringNotContainsString('example.com/secret', $closed);
        $this->assertStringContainsString('data-withheld="1"', $closed);
        $this->assertStringContainsString('Scribe-replyGateLocked', $closed, 'the "reply to see this" message stays');
        $this->assertStringContainsString('Before.', $closed);
        $this->assertStringContainsString('After.', $closed);
    }

    public function test_every_gate_and_a_nested_one_are_emptied(): void
    {
        $xml = $this->xml('<section class="Scribe-replyGate"><p>one</p><section class="Scribe-replyGate"><p>inner</p></section></section><p>between</p><section class="Scribe-replyGate"><p>two</p></section>');

        $closed = self::$renderer->render(ReplyGate::withhold($xml));

        foreach (['one', 'inner', 'two'] as $secret) {
            $this->assertStringNotContainsString('<p>' . $secret . '</p>', $closed);
        }
        $this->assertStringContainsString('between', $closed);
        $this->assertSame(2, substr_count($closed, 'data-withheld'));
    }

    public function test_a_post_without_a_gate_is_untouched(): void
    {
        $xml = $this->xml('<p>Nothing <em>gated</em> here.</p>');

        $this->assertSame($xml, (new ReplyGate())(self::$renderer, null, $xml, null));
    }
}
