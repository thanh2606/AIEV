<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ProjectHelper
{
    /**
     * Đồng bộ brand logo vào assets của project.
     */
    public static function syncBrandLogo(string $projectId, ?array $style): ?string
    {
        $repoRoot = config('aiev.repo_root');
        $destDir = config('aiev.paths.video_projects') . "/{$projectId}/assets";
        $srcRel = $style['logoPath'] ?? null;
        $srcAbs = $srcRel ? "{$repoRoot}/{$srcRel}" : null;
        
        $hasLogo = $srcAbs && File::exists($srcAbs);
        $ext = $srcRel ? strtolower(pathinfo($srcRel, PATHINFO_EXTENSION)) : 'png';
        if (!$ext) $ext = 'png';
        
        $fileName = $hasLogo ? "brand-logo.{$ext}" : "";

        // Dọn dẹp logo cũ
        if (File::isDirectory($destDir)) {
            foreach (scandir($destDir) as $f) {
                if (preg_match('/^brand-logo\./i', $f) && $f !== $fileName) {
                    @unlink("{$destDir}/{$f}");
                    // TODO: remove from assets.json if needed
                }
            }
        }

        if (!$hasLogo || !$srcAbs || !$style) {
            return null;
        }

        if (!File::isDirectory($destDir)) {
            File::makeDirectory($destDir, 0755, true);
        }

        $destAbs = "{$destDir}/{$fileName}";
        try {
            File::copy($srcAbs, $destAbs);
        } catch (\Throwable $e) {
            Log::warning("Cannot copy brand logo: " . $e->getMessage());
            return File::exists($destAbs) ? $fileName : null;
        }

        // Cập nhật assets.json
        self::writeAssetEntry($projectId, $fileName, [
            'description' => "Logo thương hiệu \"{$style['name']}\" - BẮT BUỘC dùng đúng file ảnh này khi video cần logo. Không tự vẽ lại, không thay bằng chữ tên thương hiệu."
        ]);

        return $fileName;
    }

    public static function readAssetEntries(string $projectId): array
    {
        $jsonPath = config('aiev.paths.video_projects') . "/{$projectId}/assets/assets.json";
        if (!File::exists($jsonPath)) return [];
        $data = json_decode(File::get($jsonPath), true);
        return is_array($data) ? $data : [];
    }

    public static function writeAssetEntry(string $projectId, string $fileName, array $patch): void
    {
        $map = self::readAssetEntries($projectId);
        $cur = $map[$fileName] ?? [];
        if (array_key_exists('description', $patch)) $cur['description'] = $patch['description'];
        if (array_key_exists('colorGrade', $patch)) {
            if ($patch['colorGrade'] === null) unset($cur['colorGrade']);
            else $cur['colorGrade'] = $patch['colorGrade'];
        }
        if (array_key_exists('colorAdjust', $patch)) {
            if ($patch['colorAdjust'] === null) unset($cur['colorAdjust']);
            else $cur['colorAdjust'] = $patch['colorAdjust'];
        }
        $map[$fileName] = $cur;

        $destDir = config('aiev.paths.video_projects') . "/{$projectId}/assets";
        if (!File::isDirectory($destDir)) File::makeDirectory($destDir, 0755, true);
        File::put("{$destDir}/assets.json", json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
    }

    public static function listProjectAssets(string $projectId): array
    {
        $dir = config('aiev.paths.video_projects') . "/{$projectId}/assets";
        if (!File::isDirectory($dir)) return [];

        $files = [];
        $entries = self::readAssetEntries($projectId);

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() !== 'assets.json') {
                $relPath = str_replace($dir . '/', '', $file->getPathname());
                // Chuẩn hóa path cho json
                $relPath = str_replace('\\', '/', $relPath);
                
                $f = [
                    'name' => $file->getFilename(),
                    'relPath' => "assets/{$relPath}",
                    'kind' => self::detectKind($file->getFilename()),
                ];
                
                $e = $entries[$file->getFilename()] ?? null;
                if ($e) {
                    if (isset($e['description'])) $f['description'] = $e['description'];
                    if (isset($e['colorGrade'])) $f['colorGrade'] = $e['colorGrade'];
                    if (isset($e['colorAdjust'])) $f['colorAdjust'] = $e['colorAdjust'];
                }
                $files[] = $f;
            }
        }
        return $files;
    }

    private static function detectKind(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($ext, ['mp4', 'mov', 'webm'])) return 'video';
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'svg', 'webp'])) return 'image';
        if (in_array($ext, ['mp3', 'wav', 'm4a'])) return 'audio';
        return 'other';
    }

    public static function readSfxLibrary(): array
    {
        $path = config('aiev.repo_root') . '/assets/sound-effects/library.json';
        if (!File::exists($path)) return [];
        $data = json_decode(File::get($path), true);
        return is_array($data) ? $data : [];
    }

    public static function readMusicLibrary(): array
    {
        $path = config('aiev.repo_root') . '/assets/music/library.json';
        if (!File::exists($path)) return [];
        $data = json_decode(File::get($path), true);
        return is_array($data) ? $data : [];
    }

    public static function countBrandLogos(): int
    {
        $path = config('aiev.repo_root') . '/assets/brand-logos/library.json';
        if (!File::exists($path)) return 0;
        $data = json_decode(File::get($path), true);
        return is_array($data) ? count($data) : 0;
    }

    public static function getStyle(?string $styleId): ?array
    {
        if (!$styleId) return null;
        $path = config('aiev.repo_root') . '/assets/styles/styles.json';
        if (!File::exists($path)) return null;
        $data = json_decode(File::get($path), true);
        if (!is_array($data)) return null;
        foreach ($data as $s) {
            if (($s['id'] ?? '') === $styleId) return $s;
        }
        return null;
    }

    public static function getVideoStyle(?string $id): ?array
    {
        if (!$id) return null;
        // Mocked or hardcoded in videoStyles.ts - we can port it or return null for now.
        // Doing a quick mock
        return null;
    }
}
