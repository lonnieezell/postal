<?php
/**
 * @var string      $slot
 * @var string|null $color
 */
$color ??= '#eff6ff';

// This lands in a CSS property value, where escaping cannot help: any encoding
// that neutralises a payload also stops the colour being a colour. So the value
// is validated instead, and anything that isn't a hex colour or a bare keyword
// falls back to the default rather than reaching the style attribute.
if (preg_match('/^(#[0-9a-f]{3,8}|[a-z]+)$/i', $color) !== 1) {
    $color = '#eff6ff';
}
?>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width: 100%; margin: 0 0 16px;">
    <tr>
        <td style="padding: 16px; background-color: <?= esc($color, 'html') ?>; border-radius: 4px; border: 1px solid #dbeafe; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 15px; line-height: 1.5; color: #1e3a8a;">
            <?= $slot ?>
        </td>
    </tr>
</table>
