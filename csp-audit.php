#!/usr/bin/env php
<?php
function extractHostname($src) {
  if (preg_match('~^((https?:)?//[^/]+)~i', $src, $m)) {
    return $m[1];
  }
  return $src;
}

function shouldIgnore($pathname, $ignores) {
  $path = str_replace('\\', '/', $pathname);
  foreach ($ignores as $dir) {
    if ($dir && (strpos($path, '/' . $dir . '/') !== false || strpos($path, $dir . '/') === 0)) {
      return true;
    }
  }
  return false;
}

function stripComments($content, $ext) {
  // Remove HTML comments to prevent matching resources or inline tags inside them
  $content = preg_replace('~<!--[\s\S]*?-->~', '', $content);
  // Remove CSS comments
  $content = preg_replace('~/\*[\s\S]*?\*/~', '', $content);
  if (in_array($ext, ['js', 'php'])) {
    // Remove single-line JS/PHP comments (approximate - may affect // inside string literals on same line)
    $content = preg_replace('~//.*$~m', '', $content);
  }
  return $content;
}

$dir = getcwd();
$json = false;
$nonce = false;
$ignores = ['node_modules', 'vendor', 'dist'];
if (count($argv) > 1) {
  foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--json') {
      $json = true;
    } elseif ($arg === '--nonce') {
      $nonce = true;
    } elseif (str_starts_with($arg, '--ignore=')) {
      $val = substr($arg, 9);
      $ignores = array_filter(array_map('trim', explode(',', $val)));
    } elseif (is_dir($arg)) {
      $dir = $arg;
    }
  }
}
$exts = ['html','htm','php','js','css'];
$sources = ['script'=>[],'style'=>[],'img'=>[],'font'=>[],'connect'=>[]];
$hasInlineScript = false;
$hasInlineStyle = false;
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
foreach ($rii as $file) {
  if ($file->isDir() || !in_array($file->getExtension(),$exts)) continue;
  if (shouldIgnore($file->getPathname(), $ignores)) continue;
  $content = file_get_contents($file->getPathname());
  $ext = $file->getExtension();
  $clean = stripComments($content, $ext);
  if (preg_match('/<script\b(?![^>]*\s+src\s*=)[^>]*>.*?<\/script>/is', $clean)) $hasInlineScript = true;
  if (preg_match('/<style\b[^>]*>.*?<\/style>/is', $clean)) $hasInlineStyle = true;
  // Remove inline <script> blocks (but not external script tags or styles) so JS strings cannot trigger false resource matches
  $clean = preg_replace('/<script\b(?![^>]*\s+src\s*=)[^>]*>.*?<\/script>/is', '', $clean);
  if (preg_match_all('/<script[^>]*src=["\']([^"\']+)["\'][^>]*>/i',$clean,$m)) $sources['script'] = array_merge($sources['script'],$m[1]);
  if (preg_match_all('/<link[^>]*href=["\']([^"\']+)["\'][^>]*rel=["\']stylesheet["\'][^>]*>/i',$clean,$m)) $sources['style'] = array_merge($sources['style'],$m[1]);
  if (preg_match_all('/<img[^>]*src=["\']([^"\']+)["\'][^>]*>/i',$clean,$m)) $sources['img'] = array_merge($sources['img'],$m[1]);
  if (preg_match_all('/url\(["\']?([^"\')]+)["\']?\)/i',$clean,$m)) $sources['font'] = array_merge($sources['font'],$m[1]);
}
$sources = array_map('array_unique', $sources);
$sources = array_map(function($list) {
  return array_unique(array_map('extractHostname', $list));
}, $sources);
if ($nonce) {
  $nonceValue = rtrim(base64_encode(random_bytes(16)), '=');
  echo 'Generated nonce: ' . $nonceValue . PHP_EOL . PHP_EOL;
  $csp = "default-src 'self'; script-src 'self'".
    ($hasInlineScript ? " 'nonce-$nonceValue'" : "").
    (count($sources['script'])?' '.implode(' ',array_unique($sources['script'])):'').
    "; style-src 'self'".
    ($hasInlineStyle ? " 'nonce-$nonceValue'" : "").
    (count($sources['style'])?' '.implode(' ',array_unique($sources['style'])):'').
    "; img-src 'self' data:".
    (count($sources['img'])?' '.implode(' ',array_unique($sources['img'])):'').
    "; font-src 'self'".
    (count($sources['font'])?' '.implode(' ',array_unique($sources['font'])):'').
    "; connect-src 'self';";
  echo "Example meta tag:" . PHP_EOL;
  echo '<meta http-equiv="Content-Security-Policy" content="' . $csp . '">' . PHP_EOL . PHP_EOL;
  echo "Add the nonce attribute to your inline <script> and <style> tags:" . PHP_EOL;
  echo '  <script nonce="' . $nonceValue . '">...</script>' . PHP_EOL;
  echo '  <style nonce="' . $nonceValue . '">...</style>' . PHP_EOL;
} elseif ($json) {
  $data = [
    'sources' => $sources,
    'hasInlineScript' => $hasInlineScript,
    'hasInlineStyle' => $hasInlineStyle
  ];
  echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} else {
  $csp = "default-src 'self'; script-src 'self'".
    ($hasInlineScript ? " 'unsafe-inline'" : "").
    (count($sources['script'])?' '.implode(' ',array_unique($sources['script'])):'').
    "; style-src 'self'".
    ($hasInlineStyle ? " 'unsafe-inline'" : "").
    (count($sources['style'])?' '.implode(' ',array_unique($sources['style'])):'').
    "; img-src 'self' data:".
    (count($sources['img'])?' '.implode(' ',array_unique($sources['img'])):'').
    "; font-src 'self'".
    (count($sources['font'])?' '.implode(' ',array_unique($sources['font'])):'').
    "; connect-src 'self';";
  echo $csp.PHP_EOL;
}
