<?php

/**
 * Builds the PDF versions of Martin's HVAC manuals from their Markdown source.
 *
 *   php docs/pdf/build.php
 *
 * Markdown → HTML (league/commonmark, shipped with Laravel) → PDF through a
 * locally installed headless Chrome or Edge. No extra dependency, nothing is
 * downloaded. The Markdown files stay the single source of truth: edit those
 * and rebuild, never edit the PDFs.
 */

use Illuminate\Support\Str;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

$documents = [
    'martin-hvac-praktijkhandleiding' => [
        'kicker'   => 'Praktische handleiding voor dagelijks gebruik',
        'audience' => 'Voor Martin — zaakvoerder en installateur',
    ],
    'martin-hvac-technische-bijlage' => [
        'kicker'   => 'Bijlage bij "Mastechnics — Van aanvraag tot offerte"',
        'audience' => 'Formules, rekenregels en hun status',
    ],
    'martin-hvac-snelstart' => [
        'kicker'   => 'Eén blad om naast de computer te leggen',
        'audience' => null, // one-pager: no title page, no table of contents
    ],
];

$browser = null;
foreach ([
    'C:\Program Files\Google\Chrome\Application\chrome.exe',
    'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
    'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
    'C:\Program Files\Microsoft\Edge\Application\msedge.exe',
    '/usr/bin/google-chrome',
    '/usr/bin/chromium',
] as $candidate) {
    if (is_file($candidate)) {
        $browser = $candidate;
        break;
    }
}
if ($browser === null) {
    fwrite(STDERR, "Geen Chrome of Edge gevonden — PDF's niet gebouwd. De Markdown-bronnen blijven geldig.\n");
    exit(1);
}

$logo = $root . '/public/assets/images/logoMetTekst.webp';
$logoData = is_file($logo) ? 'data:image/webp;base64,' . base64_encode(file_get_contents($logo)) : null;
$css = file_get_contents(__DIR__ . '/manual.css');
$workDir = sys_get_temp_dir() . '/mastechnics-manual-' . getmypid();
@mkdir($workDir, 0777, true);

foreach ($documents as $name => $meta) {
    $markdown = file_get_contents("{$root}/docs/{$name}.md");

    // Title = first H1; it moves to the title page / header.
    preg_match('/^# (.+)$/m', $markdown, $titleMatch);
    $title = trim($titleMatch[1] ?? $name);
    $markdown = preg_replace('/^# .+$/m', '', $markdown, 1);

    $html = Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]);

    // Callouts: a blockquote whose first words are bold gets a type.
    $html = preg_replace_callback('/<blockquote>\s*<p><strong>([^<]+)<\/strong>/u', function (array $m) {
        $label = mb_strtolower($m[1]);
        $type = match (true) {
            str_starts_with($label, 'let op')           => 'warn',
            str_starts_with($label, 'nooit automatisch') => 'never',
            str_starts_with($label, 'voorbeeld')        => 'example',
            str_starts_with($label, 'tip')              => 'tip',
            default                                     => 'note',
        };

        return "<blockquote class=\"callout callout--{$type}\"><p><strong>{$m[1]}</strong>";
    }, $html);

    // Heading ids + table of contents from the H2 chapters.
    $toc = [];
    $html = preg_replace_callback('/<h2>(.+?)<\/h2>/u', function (array $m) use (&$toc) {
        $id = 'h-' . Str::slug(strip_tags($m[1]));
        $toc[] = ['id' => $id, 'text' => strip_tags($m[1])];

        return "<h2 id=\"{$id}\">{$m[1]}</h2>";
    }, $html);

    $withCover = $meta['audience'] !== null;
    $logoTag = $logoData ? "<img class=\"logo\" src=\"{$logoData}\" alt=\"Mastechnics\">" : '<strong>Mastechnics</strong>';

    $cover = '';
    $tocHtml = '';
    if ($withCover) {
        $cover = "<section class=\"cover\">{$logoTag}<div class=\"cover__body\"><p class=\"cover__kicker\">"
            . e($meta['kicker']) . "</p><h1>" . e($title) . "</h1><p class=\"cover__audience\">" . e($meta['audience'])
            . "</p></div><p class=\"cover__foot\">Versie september 2026 · mastechnics.be</p></section>";

        $items = implode('', array_map(
            fn (array $t) => "<li><a href=\"#{$t['id']}\">" . e($t['text']) . '</a></li>',
            $toc
        ));
        $tocHtml = "<section class=\"toc\"><h2 class=\"toc__title\">Inhoud</h2><ol>{$items}</ol></section>";
    } else {
        $cover = "<header class=\"sheet-head\">{$logoTag}<div><h1>" . e($title) . '</h1><p>' . e($meta['kicker']) . '</p></div></header>';
    }

    $page = '<!doctype html><html lang="nl"><head><meta charset="utf-8"><title>' . e($title) . '</title><style>'
        . $css . '</style></head><body class="' . ($withCover ? 'manual' : 'sheet') . '" data-title="' . e($title) . '">'
        . $cover . $tocHtml . '<main>' . $html . '</main></body></html>';

    $htmlFile = "{$workDir}/{$name}.html";
    file_put_contents($htmlFile, $page);
    $pdfFile = __DIR__ . "/{$name}.pdf";

    // Own profile directory: never attaches to a browser the user has open.
    $command = sprintf(
        '"%s" --headless=new --disable-gpu --no-first-run --user-data-dir="%s" --no-pdf-header-footer --print-to-pdf="%s" "file:///%s" 2>&1',
        $browser,
        $workDir . '/profile',
        $pdfFile,
        str_replace('\\', '/', $htmlFile)
    );
    exec($command, $output, $exitCode);

    if (! is_file($pdfFile) || filesize($pdfFile) < 1000) {
        fwrite(STDERR, "Mislukt: {$name} (exit {$exitCode})\n" . implode("\n", $output) . "\n");
        exit(1);
    }
    echo str_pad($name . '.pdf', 44) . number_format(filesize($pdfFile) / 1024, 0) . " kB\n";
}
