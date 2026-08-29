<?php

namespace NovinVision\DataTable\Engine;

use App\Variables\Status;
use Illuminate\Support\Facades\Lang;

class Html
{
    public static function buildHtmlAttributes($attrs): string
    {
        $items = [];

        foreach ($attrs as $name => $value) {
            if (is_array($value)) {
                $value = implode(' ', $value);
            }
            $items[] = "{$name}=\"{$value}\"";
        }

        return implode(' ', $items);
    }

    public static function createLink($text, $url, $attrs = []): string
    {
        return '<a href="' . $url . '"' . self::buildHtmlAttributes($attrs) . '>' . $text . '</a>';
    }

    public static function createBtnPrimaryLink($text, $url, $attrs = []): string
    {
        return self::createLink($text, $url, array_merge([
            'class' => 'btn btn-primary btn-sm',
        ], $attrs));
    }

    public static function createBtnDangerLink($text, $url, $attrs = []): string
    {
        return self::createLink($text, $url, array_merge([
            'class' => 'btn btn-danger btn-sm',
        ], $attrs));
    }

    public static function createBtnSecondaryLink($text, $url, $attrs = []): string
    {
        return self::createLink($text, $url, array_merge([
            'class' => 'btn btn-secondary btn-sm',
        ], $attrs));
    }

    public static function coloredText($text, $color): string
    {
        return sprintf('<span class="text-%s">%s</span>', $color, $text);
    }

    public static function clearStyleTags($html): string
    {
        return preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
    }

    public static function clearHtmlTagAttributes($html): string
    {
        return preg_replace('/<(\w+)(\s+[^>]*)?>/i', '<$1>', $html);
    }
}
