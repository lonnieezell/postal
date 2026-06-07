<?php

/**
 * @var list<array{class: class-string, subject: string|null, error: string|null}> $mailables
 * @var string                                                                     $previewPath
 */

helper('url');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mailable Preview</title>
    <style>
        :root { color-scheme: light dark; }
        .postal-preview { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; max-width: 900px; margin: 2rem auto; padding: 0 1rem; line-height: 1.5; }
        .postal-preview h1 { font-size: 1.5rem; margin-bottom: 0.25rem; }
        .postal-preview p.lead { color: #6b7280; margin-top: 0; }
        .postal-preview ul { list-style: none; padding: 0; margin: 1.5rem 0 0; }
        .postal-preview li { border: 1px solid #e5e7eb; border-radius: 8px; padding: 0.85rem 1rem; margin-bottom: 0.6rem; display: flex; justify-content: space-between; align-items: center; gap: 1rem; }
        .postal-preview a { color: #2563eb; text-decoration: none; font-weight: 600; }
        .postal-preview a:hover { text-decoration: underline; }
        .postal-preview .subject { color: #6b7280; font-size: 0.9rem; }
        .postal-preview .class { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.8rem; color: #9ca3af; display: block; }
        .postal-preview .badge-error { background: #fee2e2; color: #b91c1c; border-radius: 6px; padding: 0.15rem 0.5rem; font-size: 0.8rem; font-weight: 600; }
        .postal-preview .empty { color: #6b7280; font-style: italic; }
    </style>
</head>
<body>
<div class="postal-preview">
    <h1>Mailable Preview</h1>
    <p class="lead">Previewable Mailables discovered in this application.</p>

    <?php if ($mailables === []): ?>
        <p class="empty">No previewable Mailables found.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($mailables as $mailable): ?>
                <li>
                    <span>
                        <a href="<?= esc(site_url($previewPath . '/' . rawurlencode($mailable['class'])), 'attr') ?>">
                            <?= esc(substr((string) strrchr('\\' . $mailable['class'], '\\'), 1)) ?>
                        </a>
                        <span class="class"><?= esc($mailable['class']) ?></span>
                    </span>
                    <?php if ($mailable['error'] !== null): ?>
                        <span class="badge-error" title="<?= esc($mailable['error'], 'attr') ?>">error: <?= esc($mailable['error']) ?></span>
                    <?php else: ?>
                        <span class="subject"><?= esc((string) $mailable['subject']) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
</body>
</html>
