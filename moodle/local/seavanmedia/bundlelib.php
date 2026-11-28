<?php
// Seavan Academy archive downloads.
defined('MOODLE_INTERNAL') || die();

const SEAVAN_BUNDLE_PART_BYTES = 2 * 1024 * 1024 * 1024;
const SEAVAN_BUNDLE_MAX_BYTES = 20 * 1024 * 1024 * 1024;
const SEAVAN_BUNDLE_RESERVE_BYTES = 1024 * 1024 * 1024;

/**
 * Keep files whole and in course order. Each part is independently extractable.
 * A file above the ZIP limit stays available as an individual download.
 */
function sam_bundle_parts(array $files, int $target = SEAVAN_BUNDLE_PART_BYTES, int $maximum = SEAVAN_BUNDLE_MAX_BYTES): array {
    $parts = [];
    $filesinpart = [];
    $bytes = 0;
    foreach ($files as $key => $file) {
        $size = $file['size'];
        if ($filesinpart && $bytes + $size > $target) {
            $parts[] = ['files' => $filesinpart, 'size' => $bytes, 'direct' => false];
            $filesinpart = [];
            $bytes = 0;
        }
        if ($size >= $target) {
            $parts[] = ['files' => [$key => $file], 'size' => $size, 'direct' => $size > $maximum];
            continue;
        }
        $filesinpart[$key] = $file;
        $bytes += $size;
    }
    if ($filesinpart) {
        $parts[] = ['files' => $filesinpart, 'size' => $bytes, 'direct' => false];
    }
    return $parts;
}

/** Reject stale numbered parts when the archive composition has changed. */
function sam_bundle_revision(array $files): string {
    $contents = [];
    foreach ($files as $key => $file) {
        $contents[] = $key . ':' . ($file['fingerprint'] ?? $key);
    }
    return hash('sha256', implode("\n", $contents));
}
