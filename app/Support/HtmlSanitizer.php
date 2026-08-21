<?php

namespace App\Support;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Minimal allowlist-based HTML sanitizer for rich-text (CKEditor) input.
 *
 * Post content is rendered unescaped with {!! !!} so that editor formatting
 * survives. That makes the stored HTML a stored-XSS sink, so everything that
 * can execute script is stripped here, on the way in.
 *
 * Only the tags/attributes below are kept; anything else is unwrapped
 * (children preserved) or dropped entirely for tags that carry code.
 */
class HtmlSanitizer
{
    /**
     * Tags allowed in post content, mapped to their allowed attributes.
     *
     * @var array<string, array<int, string>>
     */
    protected const ALLOWED = [
        'p' => [], 'br' => [], 'hr' => [],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [],
        'sub' => [], 'sup' => [],
        'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
        'ul' => [], 'ol' => [], 'li' => [],
        'blockquote' => [], 'pre' => [], 'code' => [],
        'a' => ['href', 'title'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tfoot' => [],
        'tr' => [], 'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
        'figure' => [], 'figcaption' => [], 'span' => [], 'div' => [],
    ];

    /**
     * Tags removed together with their contents.
     *
     * @var array<int, string>
     */
    protected const STRIP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'object', 'embed', 'applet',
        'form', 'input', 'button', 'select', 'textarea', 'option',
        'link', 'meta', 'base', 'svg', 'math', 'template', 'noscript',
    ];

    /**
     * URL schemes permitted in href/src.
     *
     * @var array<int, string>
     */
    protected const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * Sanitize a rich-text HTML fragment.
     */
    public static function clean(?string $html): string
    {
        $html = (string) $html;

        if (trim($html) === '') {
            return '';
        }

        $doc = new DOMDocument();

        // Wrap so DOMDocument keeps the fragment intact, and force UTF-8 so
        // multi-byte content is not mangled or used to smuggle markup.
        $wrapped = '<?xml encoding="UTF-8"?><div id="__root__">'.$html.'</div>';

        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadHTML($wrapped, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded === false) {
            // Unparseable markup: fall back to plain text.
            return e(strip_tags($html));
        }

        $xpath = new DOMXPath($doc);

        // Comments can hide conditional-comment script payloads.
        foreach (iterator_to_array($xpath->query('//comment()')) as $comment) {
            $comment->parentNode?->removeChild($comment);
        }

        $root = $doc->getElementById('__root__');

        if (! $root instanceof DOMElement) {
            return '';
        }

        static::sanitizeNode($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    /**
     * Recursively sanitize the children of a node.
     */
    protected static function sanitizeNode(DOMNode $node): void
    {
        // Snapshot: the live NodeList shifts as we mutate the tree.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->nodeName);

            if (in_array($tag, static::STRIP_WITH_CONTENT, true)) {
                $node->removeChild($child);

                continue;
            }

            if (! array_key_exists($tag, static::ALLOWED)) {
                // Unknown tag: keep the text, drop the wrapper.
                static::sanitizeNode($child);
                static::unwrap($child);

                continue;
            }

            static::sanitizeAttributes($child, $tag);
            static::sanitizeNode($child);
        }
    }

    /**
     * Drop every attribute that is not explicitly allowed for this tag.
     */
    protected static function sanitizeAttributes(DOMElement $el, string $tag): void
    {
        $allowed = static::ALLOWED[$tag];

        foreach (iterator_to_array($el->attributes ?? []) as $attr) {
            if (! $attr instanceof DOMAttr) {
                continue;
            }

            $name = strtolower($attr->nodeName);

            // Blocks on* handlers, style, and anything else unlisted.
            if (! in_array($name, $allowed, true)) {
                $el->removeAttribute($attr->nodeName);

                continue;
            }

            if (in_array($name, ['href', 'src'], true)
                && ! static::isSafeUrl($attr->nodeValue)) {
                $el->removeAttribute($attr->nodeName);
            }
        }

        // Outbound links should not hand the opener to the target page.
        if ($tag === 'a' && $el->hasAttribute('href')) {
            $el->setAttribute('rel', 'nofollow noopener noreferrer');
        }
    }

    /**
     * Reject javascript:, data:, and other script-capable URLs.
     */
    protected static function isSafeUrl(?string $url): bool
    {
        $url = trim((string) $url);

        if ($url === '') {
            return false;
        }

        // Strip characters used to hide a scheme (e.g. "java\0script:", tabs).
        $probe = strtolower(preg_replace('/[\s\x00-\x1F\x7F]+/', '', $url) ?? '');

        // Relative URLs and anchors are fine.
        if (str_starts_with($probe, '/') || str_starts_with($probe, '#')) {
            return true;
        }

        if (! str_contains($probe, ':')) {
            return true;
        }

        // Protocol-relative //host is http(s) in practice.
        if (str_starts_with($probe, '//')) {
            return true;
        }

        $scheme = strstr($probe, ':', true);

        return in_array($scheme, static::ALLOWED_SCHEMES, true);
    }

    /**
     * Replace an element with its own children.
     */
    protected static function unwrap(DOMElement $el): void
    {
        $parent = $el->parentNode;

        if ($parent === null) {
            return;
        }

        while ($el->firstChild !== null) {
            $parent->insertBefore($el->firstChild, $el);
        }

        $parent->removeChild($el);
    }
}
