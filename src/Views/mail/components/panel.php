<?php
/**
 * @var string      $slot
 * @var string|null $color
 */
$color ??= '#eff6ff';
?>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width: 100%; margin: 0 0 16px;">
    <tr>
        <td style="padding: 16px; background-color: <?= esc($color, 'css') ?>; border-radius: 4px; border: 1px solid #dbeafe; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 15px; line-height: 1.5; color: #1e3a8a;">
            <?= $slot ?>
        </td>
    </tr>
</table>
