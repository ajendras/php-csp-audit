#!/usr/bin/env php
<?php
$dir = getcwd();
$json = false;
$nonce = false;
if (count($argv) > 1) {
  foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--json') {
      $json = true;
    } elseif ($arg === '--nonce') {
      $nonce = true;
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
  $content = file_get_contents($file->getPathname());
  if (preg_match_all('/<script[^>]*src=["\']([^"\']+)["\'][^>]*>/i',$content,$m)) $sources['script'] = array_merge($sources['script'],$m[1]);
  if (preg_match_all('/<link[^>]*href=["\']([^"\']+)["\'][^>]*rel=["\']stylesheet["\'][^>]*>/i',$content,$m)) $sources['style'] = array_merge($sources['style'],$m[1]);
  if (preg_match_all('/<img[^>]*src=["\']([^"\']+)["\'][^>]*>/i',$content,$m)) $sources['img'] = array_merge($sources['img'],$m[1]);
  if (preg_match_all('/url\(["\']?([^"\')]+)["\']?\)/i',$content,$m)) $sources['font'] = array_merge($sources['font'],$m[1]);
  if (preg_match('/<script\b(?![^>]*\s+src\s*=)[^>]*>.*?<\/script>/is', $content)) $hasInlineScript = true;
  if (preg_match('/<style\b[^>]*>.*?<\/style>/is', $content)) $hasInlineStyle = true;
}
$sources = array_map('array_unique', $sources);
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
