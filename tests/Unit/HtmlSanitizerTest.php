<?php

namespace Tests\Unit;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_it_strips_script_tags_and_their_contents(): void
    {
        $out = HtmlSanitizer::clean('<p>hi</p><script>alert(1)</script>');

        $this->assertStringNotContainsString('alert(1)', $out);
        $this->assertStringNotContainsString('<script', $out);
        $this->assertStringContainsString('<p>hi</p>', $out);
    }

    public function test_it_removes_event_handler_attributes(): void
    {
        $out = HtmlSanitizer::clean('<p onclick="alert(1)">click</p>');

        $this->assertStringNotContainsString('onclick', $out);
        $this->assertStringContainsString('click', $out);
    }

    public function test_it_removes_image_onerror_payload(): void
    {
        $out = HtmlSanitizer::clean('<img src=x onerror="alert(1)">');

        $this->assertStringNotContainsString('onerror', $out);
    }

    public function test_it_strips_svg_payloads(): void
    {
        $out = HtmlSanitizer::clean('<svg><script>alert(1)</script></svg>');

        $this->assertStringNotContainsString('alert', $out);
        $this->assertStringNotContainsString('svg', $out);
    }

    public function test_it_strips_iframes_and_objects(): void
    {
        $out = HtmlSanitizer::clean('<iframe src="//evil.test"></iframe><object></object>');

        $this->assertStringNotContainsString('iframe', $out);
        $this->assertStringNotContainsString('object', $out);
    }

    public function test_it_drops_style_attributes_and_tags(): void
    {
        $out = HtmlSanitizer::clean('<style>body{}</style><p style="x:expression(alert(1))">t</p>');

        $this->assertStringNotContainsString('style', $out);
        $this->assertStringContainsString('t', $out);
    }

    #[DataProvider('dangerousUrlProvider')]
    public function test_it_rejects_script_capable_link_targets(string $url): void
    {
        $out = HtmlSanitizer::clean('<a href="'.$url.'">x</a>');

        $this->assertStringNotContainsString('href', $out);
    }

    public static function dangerousUrlProvider(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'mixed case' => ['JaVaScRiPt:alert(1)'],
            'data uri' => ['data:text/html;base64,PHNjcmlwdD4='],
            'vbscript' => ['vbscript:msgbox(1)'],
            'file' => ['file:///etc/passwd'],
        ];
    }

    #[DataProvider('safeUrlProvider')]
    public function test_it_keeps_safe_link_targets(string $url): void
    {
        $out = HtmlSanitizer::clean('<a href="'.$url.'">x</a>');

        $this->assertStringContainsString('href', $out);
    }

    public static function safeUrlProvider(): array
    {
        return [
            'https' => ['https://example.com'],
            'relative' => ['/images/a.png'],
            'anchor' => ['#section'],
            'mailto' => ['mailto:a@b.com'],
        ];
    }

    public function test_it_adds_noopener_to_links(): void
    {
        $out = HtmlSanitizer::clean('<a href="https://example.com">x</a>');

        $this->assertStringContainsString('noopener', $out);
    }

    public function test_it_preserves_legitimate_editor_formatting(): void
    {
        $html = '<p><strong>bold</strong> and <em>italic</em></p><ul><li>one</li></ul>';

        $out = HtmlSanitizer::clean($html);

        $this->assertStringContainsString('<strong>bold</strong>', $out);
        $this->assertStringContainsString('<em>italic</em>', $out);
        $this->assertStringContainsString('<li>one</li>', $out);
    }

    public function test_it_handles_empty_and_null_input(): void
    {
        $this->assertSame('', HtmlSanitizer::clean(null));
        $this->assertSame('', HtmlSanitizer::clean(''));
        $this->assertSame('', HtmlSanitizer::clean('   '));
    }

    public function test_it_preserves_utf8_content(): void
    {
        $out = HtmlSanitizer::clean('<p>Halo Dunia — café 日本語</p>');

        $this->assertStringContainsString('café', $out);
        $this->assertStringContainsString('日本語', $out);
    }
}
