# Repository workflow

- Treat the primary checkout at `C:/laragon/www/kotlov-new2026` on `main` as the local mirror of published code. Implement tasks in a separate branch and worktree; use the `codex/` branch prefix for Codex work. Do not commit task changes directly to `main`.
- Before starting, fetch `origin` and inspect the branch, working-tree status and active worktrees. Do not assume that local `main` is current or that uncommitted files belong to the current task.
- Preserve unpublished commits and modified or untracked source files before changing a divergent checkout. Do not use force-push, destructive reset, blanket clean or automatic stash restoration as the normal synchronization workflow.
- Keep worktrees with active tests independent. Do not modify another task's files, dependencies or test database. Unmerged feature branches are expected to differ from `main`; do not reset them to synchronize the release checkout.
- Publish completed changes through a pull request and the relevant checks. After merging, fetch `origin/main` and update the clean primary `main` checkout with `git merge --ff-only origin/main` (or the locally configured `git sync-main` alias). If it is dirty or diverged, preserve the work and resolve ownership before proceeding.
- After an authorized deployment, compare the deployed commit with `origin/main` and the primary local `main`. Report any outstanding unpublished branch separately. Never describe unmerged work as deployed merely because it exists locally.
