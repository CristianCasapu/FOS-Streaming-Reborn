#!/usr/bin/env php
<?php
/**
 * Organize Documentation Files
 *
 * Moves all markdown files from the project root to the /docs/guides directory
 * except for README.md and CLAUDE.md which must remain in the root.
 *
 * Usage: composer organize-docs
 * Or: php scripts/organize-docs.php
 */

echo "\n=== Organizing Documentation Files ===\n\n";

// Files that should stay in the root directory
$excludeFiles = ['README.md', 'CLAUDE.md'];

// Get all markdown files in the root directory
$files = glob(__DIR__ . '/../*.md');

if (empty($files)) {
    echo "✓ No markdown files found in project root\n";
    echo "\n";
    exit(0);
}

$moved = 0;
$skipped = 0;
$failed = 0;

// Ensure the docs/guides directory exists
$docsDir = __DIR__ . '/../docs/guides';
if (!is_dir($docsDir)) {
    if (!mkdir($docsDir, 0755, true)) {
        echo "✗ Failed to create directory: docs/guides\n";
        exit(1);
    }
    echo "✓ Created directory: docs/guides\n\n";
}

// Process each markdown file
foreach ($files as $filePath) {
    $filename = basename($filePath);

    // Skip excluded files (README.md and CLAUDE.md)
    if (in_array($filename, $excludeFiles)) {
        echo "→ Skipped: {$filename} (must stay in root)\n";
        $skipped++;
        continue;
    }

    // Move the file to docs/guides
    $destination = $docsDir . '/' . $filename;

    // Check if file already exists in destination
    if (file_exists($destination)) {
        echo "! Warning: {$filename} already exists in docs/guides/ - skipping\n";
        $skipped++;
        continue;
    }

    if (rename($filePath, $destination)) {
        echo "✓ Moved: {$filename} -> docs/guides/{$filename}\n";
        $moved++;
    } else {
        echo "✗ Failed to move: {$filename}\n";
        $failed++;
    }
}

// Summary
echo "\n=== Summary ===\n";
echo "Moved: {$moved} file(s)\n";
echo "Skipped: {$skipped} file(s)\n";
if ($failed > 0) {
    echo "Failed: {$failed} file(s)\n";
}

if ($moved === 0 && $failed === 0 && $skipped === count($excludeFiles)) {
    echo "\n✓ All documentation is properly organized!\n";
    echo "  - README.md and CLAUDE.md are in the root (correct)\n";
    echo "  - No other .md files found in root\n";
}

echo "\n";

exit($failed > 0 ? 1 : 0);
