# Changelog

All notable changes to this project are documented here, following [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]
### Added

- Add --ignore flag (defaults to node_modules,vendor,dist) to skip irrelevant directories during scan
- extract hostnames only (instead of full paths) for external resources

### Changed

- add stripComments() preprocessing and update resource regex scans to avoid false positives from HTML comments, JS strings and CSS/JS comments

## [0.2.0] - 2026-11-24
### Changed

- Add PHPUnit tests that assert generated CSP for fixture directories

### Added

- Support --nonce flag that prints a random nonce and example meta tags
- Add --json flag that outputs discovered resources instead of CSP string
- Detect inline <script> and <style> tags and conditionally add 'unsafe-inline'
