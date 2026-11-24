# Changelog

All notable changes to this project are documented here, following [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]
### Changed

- Add PHPUnit tests that assert generated CSP for fixture directories

### Added

- Add --json flag that outputs discovered resources instead of CSP string
- Detect inline <script> and <style> tags and conditionally add 'unsafe-inline'
