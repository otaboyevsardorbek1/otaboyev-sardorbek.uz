import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';

const root = process.cwd();
const out = join(root, 'out');
const failures = [];

function fail(message) {
  failures.push(message);
}

function requireFile(relative) {
  const path = join(out, relative);
  if (!existsSync(path) || !statSync(path).isFile()) {
    fail(`missing out/${relative}`);
  }
  return path;
}

requireFile('index.html');
requireFile('404.html');
requireFile('api.php');
requireFile('.htaccess');
requireFile('robots.txt');
requireFile('sitemap.xml');
requireFile('admin/index.html');
requireFile('og/og-uz.png');
requireFile('og/og-en.png');
requireFile('og/og-ru.png');

for (const forbidden of ['config.local.php', 'config.local.js', 'github_keys.txt', 'server_keys.txt']) {
  if (existsSync(join(out, forbidden))) {
    fail(`secret leaked into out/${forbidden}`);
  }
}
if (existsSync(join(out, 'database'))) {
  fail('database/ must not be copied into out/');
}

const cssOut = join(out, 'css');
if (existsSync(cssOut)) {
  for (const name of readdirSync(cssOut)) {
    if (/^custom\..*\.css$/.test(name)) {
      fail(`legacy CSS in out/css/${name}`);
    }
  }
}
if (existsSync(join(out, 'js', 'portfolio.js'))) {
  fail('legacy js/portfolio.js must not be copied into out/');
}

const robots = requireFile('robots.txt');
if (existsSync(robots)) {
  const body = readFileSync(robots, 'utf8');
  if (!/^Sitemap:\s+https:\/\/otaboyev-sardorbek\.uz\/sitemap\.xml$/m.test(body)) {
    fail('robots.txt must point at the canonical sitemap');
  }
}

const sitemap = requireFile('sitemap.xml');
if (existsSync(sitemap)) {
  const body = readFileSync(sitemap, 'utf8');
  const urls = (body.match(/<loc>/g) || []).length;
  if (urls < 18) {
    fail(`sitemap.xml has ${urls} URLs, expected at least 18`);
  }
}

const home = requireFile('uz/index.html');
if (existsSync(home)) {
  const body = readFileSync(home, 'utf8');
  for (const marker of [
    'rel="canonical"',
    'hrefLang="en"',
    'property="og:image"',
    'name="twitter:card"',
    'application/ld+json',
    '/og/og-uz.png',
  ]) {
    if (!body.includes(marker)) {
      fail(`out/uz/index.html missing ${marker}`);
    }
  }
}

if (failures.length) {
  for (const failure of failures) {
    console.error(`[check-export] ${failure}`);
  }
  process.exit(1);
}

console.log('[check-export] OK: required files, metadata, robots and sitemap');
