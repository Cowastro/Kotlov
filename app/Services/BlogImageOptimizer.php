<?php

namespace App\Services;

/**
 * Resizes/compresses blog images server-side so nothing ever ships at raw
 * upload resolution again.
 *
 * Root cause this fixes: blog cover images render in a fixed 1600×900 (hero)
 * / 900×675 (listing card) box via CSS, but nothing ever resized the source
 * file to match — a portrait phone photo (e.g. 1536×2048, ~1MB) was shipped
 * as-is and just visually cropped client-side by object-fit, so every visitor
 * downloaded the full multi-megapixel original to render a small box. Same
 * problem, smaller scale, for in-article photos (RichEditor attachments).
 *
 * Used from two places:
 *  - BlogPostForm (Filament admin): wired into FileUpload::saveUploadedFileUsing()
 *    and RichEditor::saveUploadedFileAttachmentUsing(), so every future upload
 *    through the admin is optimized automatically — no manual step required.
 *  - blog:optimize-images artisan command: retroactive cleanup for existing
 *    files, and for images placed directly on disk (e.g. by a migration that
 *    seeds an article, as this project's older articles do).
 */
class BlogImageOptimizer
{
    // Matches the site's actual display boxes: blog-single.blade.php hero
    // (width=1600 height=900) and blog.blade.php listing card (900×675,
    // same 4:3-ish crop tolerated fine inside a 16:9 source).
    public const COVER_WIDTH = 1600;
    public const COVER_HEIGHT = 900;

    // In-article photos display well under this in the content column;
    // 1200px keeps them sharp on retina without shipping full phone-camera
    // resolution (routinely 3000px+ / several MB).
    public const CONTENT_MAX_WIDTH = 1200;

    public const JPEG_QUALITY = 82;
    public const PNG_COMPRESSION = 6; // 0 (none) – 9 (max), 6 is GD's own default

    /**
     * Crop-to-16:9 + resize to exactly COVER_WIDTH×COVER_HEIGHT, in place.
     * Always re-encoded as JPEG — a cover/hero image never needs transparency,
     * and JPEG is smaller than PNG for a photo.
     *
     * @return string|null Absolute path of the resulting file (may differ from
     *                      the input if the extension changed, e.g. .png -> .jpg),
     *                      or null if the file couldn't be processed (left as-is).
     */
    public function optimizeCover(string $absolutePath): ?string
    {
        return $this->process($absolutePath, function ($src, int $w, int $h) {
            $targetRatio = self::COVER_WIDTH / self::COVER_HEIGHT;
            $currentRatio = $w / $h;

            if ($currentRatio > $targetRatio) {
                // Wider than 16:9 — crop the sides, keep full height.
                $cropH = $h;
                $cropW = (int) round($h * $targetRatio);
            } else {
                // Taller/narrower than 16:9 (typical portrait phone photo) —
                // crop top/bottom, keep full width.
                $cropW = $w;
                $cropH = (int) round($w / $targetRatio);
            }

            $srcX = (int) round(($w - $cropW) / 2);
            $srcY = (int) round(($h - $cropH) / 2);

            $dst = imagecreatetruecolor(self::COVER_WIDTH, self::COVER_HEIGHT);
            imagecopyresampled(
                $dst, $src,
                0, 0, $srcX, $srcY,
                self::COVER_WIDTH, self::COVER_HEIGHT, $cropW, $cropH
            );

            return $dst;
        }, forceJpeg: true);
    }

    /**
     * Cap max width (preserving aspect ratio), no crop — for in-article
     * photos. Leaves images already at/under the cap untouched.
     *
     * @return string|null Absolute path of the resulting file, or null if
     *                      nothing was done (already small enough, or unreadable).
     */
    public function optimizeContentImage(string $absolutePath, int $maxWidth = self::CONTENT_MAX_WIDTH): ?string
    {
        return $this->process($absolutePath, function ($src, int $w, int $h) use ($maxWidth) {
            if ($w <= $maxWidth) {
                return null; // already small enough — process() will skip re-saving
            }

            $newW = $maxWidth;
            $newH = (int) round($h * ($maxWidth / $w));

            $dst = imagecreatetruecolor($newW, $newH);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

            return $dst;
        });
    }

    /**
     * @param  callable(\GdImage, int, int): (\GdImage|null)  $transform  Returns
     *         null to signal "nothing to do" (image already fits).
     */
    private function process(string $absolutePath, callable $transform, bool $forceJpeg = false): ?string
    {
        if (! is_file($absolutePath)) {
            return null;
        }

        $info = @getimagesize($absolutePath);
        if ($info === false) {
            return null;
        }
        [$w, $h] = $info;
        $mime = $info['mime'];

        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($absolutePath),
            'image/png' => @imagecreatefrompng($absolutePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absolutePath) : false,
            default => false,
        };

        if (! $src) {
            return null;
        }

        $dst = $transform($src, $w, $h);

        if ($dst === null) {
            imagedestroy($src);
            return null; // nothing to do, left untouched
        }

        $outputAsPng = ($mime === 'image/png') && ! $forceJpeg;
        $outPath = $outputAsPng
            ? preg_replace('/\.(jpe?g|webp)$/i', '.png', $absolutePath)
            : preg_replace('/\.(png|webp)$/i', '.jpg', $absolutePath);
        $outPath ??= $absolutePath;

        if ($outputAsPng) {
            imagesavealpha($dst, true);
            imagepng($dst, $outPath, self::PNG_COMPRESSION);
        } else {
            imagejpeg($dst, $outPath, self::JPEG_QUALITY);
        }

        // Extension changed (e.g. .png -> .jpg for a forced-JPEG cover) — drop
        // the stale original so callers referencing the new extension find it.
        if ($outPath !== $absolutePath && is_file($absolutePath)) {
            @unlink($absolutePath);
        }

        imagedestroy($src);
        imagedestroy($dst);

        return $outPath;
    }
}
