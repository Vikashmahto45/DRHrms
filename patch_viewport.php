<?php
function patchFile($filePath) {
    if (!file_exists($filePath)) return;
    $content = file_get_contents($filePath);
    $changed = false;

    // 1. Inject Viewport Tag if completely missing
    if (stripos($content, 'name="viewport"') === false) {
        // Find <head> or <meta charset="UTF-8"> and insert it right after
        $insertStr = "\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">";
        
        if (stripos($content, '<meta charset="UTF-8">') !== false) {
            $content = preg_replace('/(<meta charset="UTF-8">)/i', '$1' . $insertStr, $content);
            $changed = true;
        } elseif (stripos($content, '<head>') !== false) {
            $content = preg_replace('/(<head>)/i', '$1' . $insertStr, $content);
            $changed = true;
        }
    }

    // 2. Cache Busting for CSS
    if (preg_match('/href="(.*?\/assets\/css\/[a-z_]+\.css)"/i', $content)) {
        // Change any style.css or admin.css to style.css?v=2.0
        $newContent = preg_replace_callback('/href="(.*?\/assets\/css\/([a-z_]+\.css))"/i', function($matches) {
            return 'href="' . $matches[1] . '?v=' . time() . '"';
        }, $content);
        
        if ($newContent !== $content) {
            $content = $newContent;
            $changed = true;
        }
    }

    if ($changed) {
        file_put_contents($filePath, $content);
        echo "Patched: " . basename($filePath) . "\n";
    }
}

// recursively search
$dirs = ['admin', 'superadmin', '.'];
foreach ($dirs as $d) {
    if ($d === '.') {
        $files = glob("*.php");
    } else {
        $files = glob("$d/*.php");
    }
    
    foreach ($files as $f) {
        patchFile($f);
    }
}
echo "Done patching!\n";
