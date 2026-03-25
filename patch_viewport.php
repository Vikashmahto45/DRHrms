<?php
function patchFile($filePath) {
    if (!file_exists($filePath)) return;
    $content = file_get_contents($filePath);
    $changed = false;

    // 1. Inject Viewport Tag if completely missing
    if (stripos($content, 'name="viewport"') === false) {
        $insertStr = "\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">";
        if (stripos($content, '<meta charset="UTF-8">') !== false) {
            $content = preg_replace('/(<meta charset="UTF-8">)/i', '$1' . $insertStr, $content);
            $changed = true;
        } elseif (stripos($content, '<head>') !== false) {
            $content = preg_replace('/(<head>)/i', '$1' . $insertStr, $content);
            $changed = true;
        }
    }

    // 2. Cache Busting – match BOTH versioned (?v=xxx) and unversioned CSS hrefs
    $ts = time();
    $newContent = preg_replace_callback(
        '/href="([^"]*\/assets\/css\/[a-z_]+\.css)(\?v=[^"]*)?"/',
        function($matches) use ($ts) {
            return 'href="' . $matches[1] . '?v=' . $ts . '"';
        },
        $content
    );
    if ($newContent !== $content) {
        $content = $newContent;
        $changed = true;
    }

    if ($changed) {
        file_put_contents($filePath, $content);
        echo "Patched: " . basename($filePath) . "\n";
    }
}

// Scan all PHP files
$dirs = ['admin', 'admin/includes', 'superadmin', 'superadmin/includes', '.'];
foreach ($dirs as $d) {
    $files = ($d === '.') ? glob("*.php") : glob("$d/*.php");
    foreach ((array)$files as $f) {
        patchFile($f);
    }
}
echo "Done patching!\n";
