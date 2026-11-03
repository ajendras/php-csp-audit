#!/usr/bin/env php
<?php
$dir = $argv[1] ?? getcwd();
$exts = ['html','htm','php','js','css'];
$sources = ['script'=>[],'style'=>[],'img'=>[],'font'=>[],'connect'=>[]];
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
foreach ($rii as $file) {
  if ($file->isDir() || !in_array($file->getExtension(),$exts)) continue;
  $content = file_get_contents($file->getPathname());
  if (preg_match_all('/<script[^>]*src=["\']([^"\']+)["\'][^>]*>/i',$content,$m)) $sources['script'] = array_merge($sources['script'],$m[1]);
  if (preg_match_all('/<link[^>]*href=["\']([^"\']+)["\'][^>]*rel=["\']stylesheet["\'][^>]*>/i',$content,$m)) $sources['style'] = array_merge($sources['style'],$m[1]);
  if (preg_match_all('/<img[^>]*src=["\']([^"\']+)["\'][^>]*>/i',$content,$m)) $sources['img'] = array_merge($sources['img'],$m[1]);
  if (preg_match_all('/url\(["\']?([^"\')]+)["\']?\)/i',$content,$m)) $sources['font'] = array_merge($sources['font'],$m[1]);
}
$csp = "default-src 'self'; script-src 'self'".
  (count($sources['script'])?' '.implode(' ',array_unique($sources['script'])):'')."; style-src 'self' 'unsafe-inline'".
  (count($sources['style'])?' '.implode(' ',array_unique($sources['style'])):'')."; img-src 'self' data:".
  (count($sources['img'])?' '.implode(' ',array_unique($sources['img'])):'')."; font-src 'self'".
  (count($sources['font'])?' '.implode(' ',array_unique($sources['font'])):'')."; connect-src 'self';";
echo $csp.PHP_EOL;