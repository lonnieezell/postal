<?php
/**
 * @var string $url
 * @var string $slot
 */
?>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 16px;">
    <tr>
        <td style="border-radius: 4px; background-color: #2563eb;" align="center">
            <a href="<?= esc($url, 'attr') ?>" target="_blank" style="display: inline-block; padding: 12px 24px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 16px; font-weight: 600; line-height: 1.2; color: #ffffff; text-decoration: none; border-radius: 4px;"><?= $slot ?></a>
        </td>
    </tr>
</table>
