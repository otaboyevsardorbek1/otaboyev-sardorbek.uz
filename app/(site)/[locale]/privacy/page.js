import { notFound } from 'next/navigation';
import { getContent, LOCALES, SITE_URL } from '../../../../content/locales';
import { getProjectDetailLabels } from '../../../../content/locales';

const OG_LOCALES = { uz: 'uz_UZ', en: 'en_US', ru: 'ru_RU' };

export async function generateMetadata({ params }) {
  const { locale } = await params;
  if (!LOCALES.includes(locale)) return {};
  const copy = getContent(locale);

  return {
    title: `${copy.privacy.title} | Sardorbek Otaboyev`,
    description: copy.privacy.description,
    alternates: {
      canonical: `${SITE_URL}/${locale}/privacy/`,
      languages: {
        uz: `${SITE_URL}/uz/privacy/`,
        en: `${SITE_URL}/en/privacy/`,
        ru: `${SITE_URL}/ru/privacy/`,
        'x-default': `${SITE_URL}/uz/privacy/`,
      },
    },
    openGraph: {
      title: `${copy.privacy.title} | Sardorbek Otaboyev`,
      description: copy.privacy.description,
      type: 'website',
      url: `${SITE_URL}/${locale}/privacy/`,
      siteName: 'Sardorbek Otaboyev',
      locale: OG_LOCALES[locale],
      images: [
        {
          url: `${SITE_URL}/og/og-${locale}.png`,
          width: 1200,
          height: 630,
          alt: copy.privacy.title,
          type: 'image/png',
        },
      ],
    },
  };
}

export default async function PrivacyPage({ params }) {
  const { locale } = await params;
  if (!LOCALES.includes(locale)) notFound();
  const copy = getContent(locale);
  const labels = getProjectDetailLabels(locale);
  const privacy = copy.privacy;

  return (
    <main className="legal-page">
      <article className="container legal-inner">
        <nav className="legal-nav">
          <a href={`/${locale}/`}>{labels.home}</a>
          <span aria-hidden="true">/</span>
          <span>{privacy.title}</span>
        </nav>
        <h1>{privacy.title}</h1>
        <p className="legal-updated">{privacy.updated}</p>
        <p className="legal-intro">{privacy.description}</p>
        {privacy.sections.map((section) => (
          <section className="legal-section" key={section.title}>
            <h2>{section.title}</h2>
            <p>{section.body}</p>
          </section>
        ))}
        <a className="legal-back" href={`/${locale}/`}>
          {privacy.back}
        </a>
      </article>
    </main>
  );
}
