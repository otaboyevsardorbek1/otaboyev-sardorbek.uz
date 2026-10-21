import '../../css/admin.css';

export const metadata = {
  title: 'Admin | Sardorbek Otaboyev',
  description: 'Private content administration.',
  robots: { index: false, follow: false, nocache: true },
};

export default function AdminLayout({ children }) {
  return (
    <html lang="uz">
      <body>{children}</body>
    </html>
  );
}
