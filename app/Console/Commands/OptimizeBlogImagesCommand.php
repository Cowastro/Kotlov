<?php

namespace App\Console\Commands;

use App\Services\BlogImageOptimizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Retroactive / manual counterpart to the auto-optimization wired into
 * BlogPostForm (Filament admin). Two uses:
 *
 *  - `--all`   : sweep every existing blog post's cover + in-article images
 *                (covers the backlog of already-published articles, and any
 *                image dropped straight onto disk by a migration-seeded
 *                article rather than uploaded through the admin).
 *  - `--file=` : optimize one specific file — the manual-run path for a
 *                migration-seeded article (write the migration referencing
 *                img/blog/works/whatever.jpg as usual, then run this once
 *                on each file instead of hand-rolling a resize script).
 *
 * Dry-run by default; --apply writes.
 */
class OptimizeBlogImagesCommand extends Command
{
    protected $signature = 'blog:optimize-images
        {--file= : Optimize a single file (path relative to public/, or absolute)}
        {--cover : With --file, treat it as a cover image (crop to 1600x900) instead of an in-article photo}
        {--all   : Sweep every blog post cover_image + images[] on disk}
        {--apply : Write changes (default: dry-run, reports what would change)}';

    protected $description = 'Resize/compress blog cover and in-article images to match their actual display size';

    public function handle(BlogImageOptimizer $optimizer): int
    {
        $apply = (bool) $this->option('apply');

        if ($file = $this->option('file')) {
            $absolute = str_starts_with($file, '/') || preg_match('/^[A-Za-z]:\\\\/', $file)
                ? $file
                : public_path(ltrim($file, '/'));

            if (! is_file($absolute)) {
                $this->error("File not found: {$absolute}");
                return self::FAILURE;
            }

            $before = filesize($absolute);

            if (! $apply) {
                $this->warn('DRY RUN — pass --apply to write.');
                $this->line(($this->option('cover') ? '[cover] ' : '[content] ') . $absolute . " ({$before} bytes)");
                return self::SUCCESS;
            }

            $result = $this->option('cover')
                ? $optimizer->optimizeCover($absolute)
                : $optimizer->optimizeContentImage($absolute);

            if ($result === null) {
                $this->line('No change needed (already optimized or unreadable).');
                return self::SUCCESS;
            }

            $after = filesize($result);
            $this->info(sprintf('%s: %d -> %d bytes (%s)', basename($result), $before, $after, $result));
            return self::SUCCESS;
        }

        if ($this->option('all')) {
            return $this->handleAll($optimizer, $apply);
        }

        $this->error('Pass --file=... or --all');
        return self::FAILURE;
    }

    private function handleAll(BlogImageOptimizer $optimizer, bool $apply): int
    {
        $this->line($apply
            ? '<fg=red;options=bold>APPLY — files will be overwritten.</>'
            : '<fg=yellow;options=bold>DRY RUN — no files will be changed.</>');

        $posts = DB::table('blog_posts')->get(['id', 'cover_image', 'images']);

        $coversDone = 0;
        $contentDone = 0;
        $skipped = 0;

        foreach ($posts as $post) {
            $images = json_decode((string) ($post->images ?? '[]'), true) ?: [];
            $coverRelative = $post->cover_image;

            if ($coverRelative) {
                $absolute = public_path(ltrim($coverRelative, '/'));
                if (is_file($absolute)) {
                    $before = filesize($absolute);
                    if ($apply) {
                        $result = $optimizer->optimizeCover($absolute);
                        if ($result !== null) {
                            $this->line(sprintf('  [cover] post #%d: %d -> %d bytes', $post->id, $before, filesize($result)));
                            $coversDone++;
                        } else {
                            $skipped++;
                        }
                    } else {
                        $this->line(sprintf('  [cover] post #%d: %s (%d bytes)', $post->id, $coverRelative, $before));
                        $coversDone++;
                    }
                }
            }

            foreach ($images as $imgRelative) {
                if ($imgRelative === $coverRelative) {
                    continue; // already handled above as the cover
                }
                $absolute = public_path(ltrim((string) $imgRelative, '/'));
                if (! is_file($absolute)) {
                    continue;
                }
                $before = filesize($absolute);
                if ($apply) {
                    $result = $optimizer->optimizeContentImage($absolute);
                    if ($result !== null) {
                        $this->line(sprintf('  [content] post #%d: %d -> %d bytes', $post->id, $before, filesize($result)));
                        $contentDone++;
                    } else {
                        $skipped++;
                    }
                } else {
                    $this->line(sprintf('  [content] post #%d: %s (%d bytes)', $post->id, $imgRelative, $before));
                    $contentDone++;
                }
            }
        }

        $this->newLine();
        $this->table(['metric', 'count'], [
            ['posts scanned', $posts->count()],
            ['covers ' . ($apply ? 'optimized' : 'to optimize'), $coversDone],
            ['content images ' . ($apply ? 'optimized' : 'to optimize'), $contentDone],
            ['skipped (already fine / unreadable)', $skipped],
        ]);

        return self::SUCCESS;
    }
}
