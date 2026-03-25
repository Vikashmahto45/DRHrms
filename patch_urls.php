<?php
// PHP Script to bulk replace all absolute paths safely.
$dir = new RecursiveDirectoryIterator(__DIR__);
$iterator = new RecursiveIteratorIterator($dir);

$phpOpen = '<?' . '= ';
$phpClose = ' ?' . '>';

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php' && $file->getFilename() !== 'patch_urls.php' && $file->getFilename() !== 'database.php') {
        $path = $file->getRealPath();
        $content = file_get_contents($path);
        
        $original = $content;
        
        // Fix header redirects
        $content = preg_replace('/header\s*\(\s*"Location:\s*\/DR Hrms\/(.*?)"\s*\);/is', 'header("Location: " . BASE_URL . "$1");', $content);
        $content = preg_replace('/header\s*\(\s*\'Location:\s*\/DR Hrms\/(.*?)\'\s*\);/is', 'header("Location: " . BASE_URL . "$1");', $content);
        
        // Fix HTML href
        $content = str_replace('href="/DR Hrms/', 'href="' . $phpOpen . 'BASE_URL' . $phpClose, $content);
        $content = str_replace("href='/DR Hrms/", "href='" . $phpOpen . 'BASE_URL' . $phpClose, $content);
        
        // Fix HTML action
        $content = str_replace('action="/DR Hrms/', 'action="' . $phpOpen . 'BASE_URL' . $phpClose, $content);
        $content = str_replace("action='/DR Hrms/", "action='" . $phpOpen . 'BASE_URL' . $phpClose, $content);
        
        // Fix HTML src
        $content = str_replace('src="/DR Hrms/', 'src="' . $phpOpen . 'BASE_URL' . $phpClose, $content);
        $content = str_replace("src='/DR Hrms/", "src='" . $phpOpen . 'BASE_URL' . $phpClose, $content);
        
        // General replacements
        $content = str_replace('"/DR Hrms/', '"' . $phpOpen . 'BASE_URL' . $phpClose, $content);
        $content = str_replace("'/DR Hrms/", "'" . $phpOpen . 'BASE_URL' . $phpClose, $content);
        
        if ($content !== $original) {
            file_put_contents($path, $content);
            echo "Patched URL paths in: " . basename($path) . "\n";
        }
    }
}
echo "ALL REPLACEMENTS COMPLETE.\n";
