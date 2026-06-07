<?php

/**
 * @var class-string  $class
 * @var string        $previewPath
 * @var string|null   $error
 * @var string        $subject     (absent when $error is set)
 * @var string|null   $htmlBody    (absent when $error is set)
 * @var string        $text        (absent when $error is set)
 * @var bool          $textAuto    (absent when $error is set)
 * @var string        $rawMime     (absent when $error is set)
 * @var list<string>  $attachments (absent when $error is set)
 */

helper('url');
$shortName = substr((string) strrchr('\\' . $class, '\\'), 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview: <?= esc($shortName) ?></title>
    <style>
        :root { color-scheme: light dark; }
        .postal-preview { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; max-width: 960px; margin: 1.5rem auto; padding: 0 1rem; line-height: 1.5; }
        .postal-preview a.back { color: #2563eb; text-decoration: none; font-size: 0.9rem; }
        .postal-preview h1 { font-size: 1.4rem; margin: 0.5rem 0 0.1rem; }
        .postal-preview .class { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.8rem; color: #9ca3af; }
        .postal-preview .subject { color: #374151; margin: 0.4rem 0 1rem; }
        .postal-preview .subject strong { color: #111827; }
        .postal-preview .tabs { display: flex; gap: 0.25rem; border-bottom: 2px solid #e5e7eb; }
        .postal-preview .tab { background: none; border: none; padding: 0.6rem 1rem; font-size: 0.95rem; cursor: pointer; color: #6b7280; border-bottom: 2px solid transparent; margin-bottom: -2px; }
        .postal-preview .tab.active { color: #2563eb; border-bottom-color: #2563eb; font-weight: 600; }
        .postal-preview .panel { display: none; padding-top: 1rem; }
        .postal-preview .panel.active { display: block; }
        .postal-preview iframe { width: 100%; min-height: 480px; border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; }
        .postal-preview pre { background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 8px; padding: 1rem; overflow: auto; font-size: 0.85rem; white-space: pre-wrap; word-break: break-word; }
        .postal-preview .note { color: #6b7280; font-size: 0.85rem; margin: 0 0 0.6rem; }
        .postal-preview .empty { color: #6b7280; font-style: italic; }
        .postal-preview .attachments { color: #374151; font-size: 0.9rem; margin-top: 1.25rem; border-top: 1px solid #e5e7eb; padding-top: 0.75rem; }
        .postal-preview .error { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; border-radius: 8px; padding: 1rem; white-space: pre-wrap; word-break: break-word; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.85rem; }
    </style>
</head>
<body>
<div class="postal-preview">
    <a class="back" href="<?= esc(site_url($previewPath), 'attr') ?>">&larr; All previews</a>
    <h1><?= esc($shortName) ?></h1>
    <div class="class"><?= esc($class) ?></div>

    <?php if ($error !== null): ?>
        <p class="note">This Mailable could not be previewed:</p>
        <div class="error"><?= esc($error) ?></div>
    <?php else: ?>
        <p class="subject">Subject: <strong><?= $subject === '' ? '<span class="empty">(no subject)</span>' : esc($subject) ?></strong></p>

        <div class="tabs" role="tablist">
            <button class="tab active" type="button" data-tab="html">HTML</button>
            <button class="tab" type="button" data-tab="text">Text</button>
            <button class="tab" type="button" data-tab="raw">Raw MIME</button>
        </div>

        <div class="panel active" data-panel="html">
            <?php if ($htmlBody !== null): ?>
                <iframe srcdoc="<?= esc($htmlBody, 'attr') ?>" sandbox title="HTML preview"></iframe>
            <?php else: ?>
                <p class="empty">No HTML body</p>
            <?php endif; ?>
        </div>

        <div class="panel" data-panel="text">
            <?php if ($text !== ''): ?>
                <?php if ($textAuto): ?>
                    <p class="note">auto-generated from HTML</p>
                <?php endif; ?>
                <pre><?= esc($text) ?></pre>
            <?php else: ?>
                <p class="empty">empty message</p>
            <?php endif; ?>
        </div>

        <div class="panel" data-panel="raw">
            <pre><?= esc($rawMime) ?></pre>
        </div>

        <div class="attachments">
            <?php if ($attachments === []): ?>
                No attachments.
            <?php else: ?>
                Attachments (<?= count($attachments) ?>): <?= esc(implode(', ', $attachments)) ?>
            <?php endif; ?>
            <div class="note">Inline <code>cid:</code> images won't render in this preview iframe.</div>
        </div>
    <?php endif; ?>
</div>

<script>
    document.querySelectorAll('.postal-preview .tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            var name = tab.getAttribute('data-tab');
            document.querySelectorAll('.postal-preview .tab').forEach(function (t) {
                t.classList.toggle('active', t === tab);
            });
            document.querySelectorAll('.postal-preview .panel').forEach(function (p) {
                p.classList.toggle('active', p.getAttribute('data-panel') === name);
            });
        });
    });
</script>
</body>
</html>
