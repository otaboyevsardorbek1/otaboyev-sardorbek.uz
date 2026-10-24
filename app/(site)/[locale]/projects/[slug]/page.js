import { notFound } from 'next/navigation';
import ProjectDetail from '../../../../../components/ProjectDetail';
import JsonLd from '../../../../../components/JsonLd';
import {
  getProjectDetail,
  getProjectDetailLabels,
  LOCALES,
  projectDetails,
  SITE_URL,
} from '../../../../../content/locales';

const OG_LOCALES = { uz: 'uz_UZ', en: 'en_US', ru: 'ru_RU' };

export const dynamicParams = false;

export function generateStaticParams() {
  return LOCALES.flatMap((locale) =>
    Object.keys(projectDetails[locale] || {}).map((slug) => ({ locale, slug }))
  );
}

function ogImage(locale, title, description) {
  return {
    url: `${SITE_URL}/og/og-${locale}.png`,
    width: 1200,
    height: 630,
    alt: title,
    type: 'image/png',
  };
}

export async function generateMetadata({ params }) {
  const { locale, slug } = await params;
  if (!LOCALES.includes(locale)) return {};
  const project = getProjectDetail(locale, slug);
  if (!project) return {};

  const path = `${SITE_URL}/${locale}/projects/${slug}/`;
  const title = `${project.title} | Sardorbek Otaboyev`;
  const image = ogImage(locale, title, project.summary);

  return {
    title,
    description: project.summary,
    alternates: {
      canonical: path,
      languages: {
        uz: `${SITE_URL}/uz/projects/${slug}/`,
        en: `${SITE_URL}/en/projects/${slug}/`,
        ru: `${SITE_URL}/ru/projects/${slug}/`,
        'x-default': `${SITE_URL}/uz/projects/${slug}/`,
      },
    },
    openGraph: {
      title,
      description: project.summary,
      type: 'article',
      url: path,
      siteName: 'Sardorbek Otaboyev',
      locale: OG_LOCALES[locale],
      images: [image],
    },
    twitter: {
      card: 'summary_large_image',
      title,
      description: project.summary,
      images: [image.url],
    },
  };
}

export default async function ProjectDetailPage({ params }) {
  const { locale, slug } = await params;
  if (!LOCALES.includes(locale)) notFound();
  const project = getProjectDetail(locale, slug);
  if (!project) notFound();

  const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'CreativeWork',
    name: project.title,
    description: project.summary,
    url: `${SITE_URL}/${locale}/projects/${slug}/`,
    inLanguage: locale,
    keywords: Array.isArray(project.tech) ? project.tech.join(', ') : '',
    author: {
      '@type': 'Person',
      name: 'Sardorbek Otaboyev',
      url: SITE_URL,
    },
    creator: {
      '@type': 'Person',
      name: 'Sardorbek Otaboyev',
      url: SITE_URL,
    },
  };

  return (
    <>
      <JsonLd data={jsonLd} />
      <ProjectDetail
        locale={locale}
        slug={slug}
        fallback={project}
        labels={getProjectDetailLabels(locale)}
      />
    </>
  );
}
