import { cpSync, existsSync, mkdirSync, readdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const root = process.cwd();
const out = join(root, 'out');

if (!existsSync(out)) {
  mkdirSync(out, { recursive: true });
}

const copies = [
  ['files', 'files'],
  ['js', 'js'],
  ['css', 'css'],
  ['favicon.svg', 'favicon.svg'],
  ['api.php', 'api.php'],
  ['backend', 'backend'],
  ['.htaccess', '.htaccess'],
  ['sitemap.xml', 'sitemap.xml'],
];

// config.local.php is intentionally NOT copied. It holds DB, Telegram, mail
// and IMAP secrets and must be placed in the web root manually during deploy
// (mode 600), never shipped inside the build artifact. run_local.sh /
// run_server.sh copy it into out/ for local previews only.
const forbidden = ['config.local.php', 'config.local.js'];
for (const name of forbidden) {
  const stale = join(out, name);
  if (existsSync(stale)) {
    rmSync(stale, { force: true });
    console.warn(`[copy-static] removed stale out/${name}`);
  }
}

for (const [from, to] of copies) {
  const source = join(root, from);
  if (!existsSync(source)) {
    console.warn(`[copy-static] skip missing ${from}`);
    continue;
  }
  cpSync(source, join(out, to), { recursive: true });
  console.log(`[copy-static] ${from} -> out/${to}`);
}

// Legacy Sitejet assets are kept in the repository for history, but the active
// React pages never reference them. Do not ship them: the old CSS alone is
// almost 900 KB.
const cssOut = join(out, 'css');
if (existsSync(cssOut)) {
  for (const name of readdirSync(cssOut)) {
    if (/^custom\..*\.css$/.test(name)) {
      rmSync(join(cssOut, name), { force: true });
      console.log(`[copy-static] skipped legacy css/${name}`);
    }
  }
}
const legacyJs = join(out, 'js', 'portfolio.js');
if (existsSync(legacyJs)) {
  rmSync(legacyJs, { force: true });
  console.log('[copy-static] skipped legacy js/portfolio.js');
}

// Keep the sitemap lastmod honest for the index and listing pages. Project
// detail dates stay as they are because the admin panel maintains them from
// the database.
const sitemapOut = join(out, 'sitemap.xml');
if (existsSync(sitemapOut)) {
  const today = new Date().toISOString().slice(0, 10);
  const source = readFileSync(sitemapOut, 'utf8');
  const stamped = source.replace(
    /<url>\s*<loc>([^<]+)<\/loc>\s*<lastmod>[^<]+<\/lastmod>/g,
    (block, loc) => {
      const isIndex = /^https:\/\/[^/]+\/(uz|en|ru)\/$/.test(loc);
      const isListing = /^https:\/\/[^/]+\/(uz|en|ru)\/projects\/$/.test(loc);
      return isIndex || isListing
        ? block.replace(/<lastmod>[^<]+<\/lastmod>/, `<lastmod>${today}</lastmod>`)
        : block;
    }
  );
  writeFileSync(sitemapOut, stamped);
  console.log(`[copy-static] sitemap lastmod stamped ${today}`);
}
