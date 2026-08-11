<?php
/**
 * @var string $url
 * @var string $slot
 */

// The href is double-quoted below, so the "html" context is the right one: it
// neutralises the characters that could end the attribute while leaving the URL
// itself intact. The "attr" context is meant for unquoted values and encodes
// every non-alphanumeric character, which turns "https://" into
// "https&#x3A;&#x2F;&#x2F;" and hides the link from anything matching hrefs.
?>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 16px;">
    <tr>
        <td style="border-radius: 4px; background-color: #2563eb;" align="center">
            <a href="<?= esc($url, 'html') ?>" target="_blank" style="display: inline-block; padding: 12px 24px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 16px; font-weight: 600; line-height: 1.2; color: #ffffff; text-decoration: none; border-radius: 4px;"><?= $slot ?></a>
        </td>
    </tr>
</table>
