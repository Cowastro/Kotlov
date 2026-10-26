<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title>@yield('title') — KOTLOV</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Golos+Text:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap">
<style>
@verbatim
*{box-sizing:border-box}
body{margin:0}
/* Layout: one reading column, sections as plain headed blocks; a summary strip, then detail; tables for numbers. */
:root {
  --bg: #f6f7f8;
  --surface: #ffffff;
  --fg: #1c2024;
  --muted: #5d6670;
  --line: #dde1e5;
  --accent: #c8373b;
  --accent-soft: #fbe9e9;
  --ok: #1f7a4d;
  --ok-soft: #e4f3eb;
  --font-body: "Golos Text", "Segoe UI", Roboto, Arial, sans-serif;
  --font-mono: "JetBrains Mono", Consolas, "Courier New", monospace;
}
@media (prefers-color-scheme: dark) {
  :root:not([data-theme="light"]) {
    --bg: #15181b; --surface: #1d2125; --fg: #e8eaec; --muted: #9aa3ac; --line: #30363c;
    --accent: #ff7377; --accent-soft: #3a2224; --ok: #5fd39a; --ok-soft: #1c3127; color-scheme: dark;
  }
}
:root[data-theme="dark"] {
  --bg: #15181b; --surface: #1d2125; --fg: #e8eaec; --muted: #9aa3ac; --line: #30363c;
  --accent: #ff7377; --accent-soft: #3a2224; --ok: #5fd39a; --ok-soft: #1c3127; color-scheme: dark;
}
body { background: var(--bg); color: var(--fg); font-family: var(--font-body); font-size: 16px; line-height: 1.55; }
.wrap { max-width: 860px; margin: 0 auto; padding-block: 40px 64px; padding-left: max(16px, env(safe-area-inset-left, 0px)); padding-right: max(16px, env(safe-area-inset-right, 0px)); display: grid; gap: 36px; }
header { display: grid; gap: 10px; }
.eyebrow { font-family: var(--font-mono); font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: var(--accent); }
h1 { margin: 0; font-size: clamp(28px, 4vw, 38px); line-height: 1.15; font-weight: 700; text-wrap: balance; }
.lead { margin: 0; color: var(--muted); max-width: 65ch; }
.summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; }
.summary div { background: var(--surface); border: 1px solid var(--line); border-radius: 10px; padding: 14px 16px; display: grid; gap: 2px; }
.summary b { font-size: 26px; font-variant-numeric: tabular-nums; }
.summary span { color: var(--muted); font-size: 14px; }
section { display: grid; gap: 14px; }
h2 { margin: 0; font-size: 22px; font-weight: 700; text-wrap: balance; padding-bottom: 8px; border-bottom: 2px solid var(--fg); }
h3 { margin: 6px 0 0; font-size: 17px; font-weight: 600; }
p { margin: 0; max-width: 68ch; }
ul, ol { margin: 0; padding-left: 22px; display: grid; gap: 6px; max-width: 68ch; }
.where { display: inline-block; font-family: var(--font-mono); font-size: 12.5px; background: var(--accent-soft); color: var(--accent); border-radius: 6px; padding: 1px 7px; }
.note { background: var(--surface); border: 1px solid var(--line); border-left: 3px solid var(--accent); border-radius: 8px; padding: 12px 16px; display: grid; gap: 6px; }
.note strong { font-weight: 600; }
.table-wrap { overflow-x: auto; border: 1px solid var(--line); border-radius: 10px; background: var(--surface); }
table { border-collapse: collapse; width: 100%; min-width: 560px; font-size: 15px; }
th, td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); vertical-align: top; }
th { font-size: 12px; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); font-weight: 600; }
tr:last-child td { border-bottom: 0; }
td.num { font-variant-numeric: tabular-nums; white-space: nowrap; }
.pill { display: inline-block; font-size: 12.5px; border-radius: 999px; padding: 1px 9px; background: var(--ok-soft); color: var(--ok); white-space: nowrap; }
.pill.wait { background: var(--accent-soft); color: var(--accent); }
footer { color: var(--muted); font-size: 14px; border-top: 1px solid var(--line); padding-top: 14px; }
@media (max-width: 480px) { .summary b { font-size: 22px; } }
.topbar{display:flex;flex-wrap:wrap;gap:8px 18px;align-items:center;font-size:14px}
.topbar a{color:var(--muted);text-decoration:none}
.topbar a:hover{color:var(--accent)}
.release-list{display:grid;gap:12px}
.release-list a{display:grid;gap:4px;background:var(--surface);border:1px solid var(--line);border-radius:10px;padding:14px 16px;color:var(--fg);text-decoration:none}
.release-list a:hover{border-color:var(--accent)}
.release-list time{font-family:var(--font-mono);font-size:12.5px;color:var(--accent)}
.release-list span{color:var(--muted);font-size:14.5px}
.release{background:var(--surface);border:1px solid var(--line);border-radius:10px;padding:14px 16px;display:grid;gap:8px}
.release a.title{color:var(--fg);font-weight:600;text-decoration:none}
.release a.title:hover{color:var(--accent)}
.release time{font-family:var(--font-mono);font-size:12.5px;color:var(--accent)}
.release p{color:var(--muted);font-size:14.5px}
.share{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.share input{flex:1 1 260px;min-width:0;font:inherit;font-size:13px;padding:8px 10px;border:1px solid var(--line);border-radius:8px;background:var(--bg);color:var(--fg)}
.share button{font:inherit;font-size:14px;font-weight:600;padding:8px 14px;border:0;border-radius:8px;background:var(--accent);color:#fff;cursor:pointer}
.share button:focus-visible,.share input:focus-visible{outline:2px solid var(--accent);outline-offset:2px}
.share small{color:var(--muted);font-size:13px;flex-basis:100%}
a{color:var(--accent)}
.toc{display:flex;flex-wrap:wrap;gap:8px}
.toc a{font-size:14px;padding:6px 12px;border:1px solid var(--line);border-radius:999px;background:var(--surface);color:var(--fg);text-decoration:none}
.toc a:hover{border-color:var(--accent);color:var(--accent)}
.link-groups{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px}
.link-groups>div{background:var(--surface);border:1px solid var(--line);border-radius:10px;padding:14px 16px;display:grid;gap:8px;align-content:start}
.link-groups h3{margin:0;font-size:15px}
.link-groups h3 small{font-weight:400;color:var(--muted);font-size:12.5px;margin-left:6px}
.link-list{list-style:none;padding:0;gap:6px}
.item{display:grid;gap:8px}
.item-head{display:flex;flex-wrap:wrap;gap:8px 14px;align-items:baseline;justify-content:space-between}
.item-head h3{margin:0}
a.open{font-size:13.5px;font-weight:600;padding:5px 12px;border-radius:8px;background:var(--accent-soft);color:var(--accent);text-decoration:none;white-space:nowrap}
a.open:hover{background:var(--accent);color:#fff}
a.sub{font-size:12.5px;color:var(--muted)}
a:focus-visible{outline:2px solid var(--accent);outline-offset:2px}
section{scroll-margin-top:16px}
@endverbatim
</style>
</head>
<body>
<div class="wrap">
  @if ($canNavigate ?? false)
  <nav class="topbar" aria-label="Навигация">
    <a href="{{ url('/admin') }}">← В админку</a>
    <a href="{{ route('admin.releases.index') }}">Все релизы</a>
  </nav>
  @endif
@yield('content')
</div>
</body>
</html>
