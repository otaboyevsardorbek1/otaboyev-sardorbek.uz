/** @type {import('next').NextConfig} */
const nextConfig = {
  output: 'export',
  trailingSlash: true,
  images: { unoptimized: true },
  // cPanel LVE memory limits can kill Next's parallel page workers.
  experimental: { cpus: 1 },
};

module.exports = nextConfig;
