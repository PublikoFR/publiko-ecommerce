/* Weklo Ecommerce — Footer */

function Footer() {
  const cols = [
    { title: 'Catalogue', links: ['Fenêtres', 'Portes', 'Portails', 'Volets', 'Motorisation', 'Portes de garage'] },
    { title: 'Espace pro', links: ['Mon compte', 'Mes devis', 'Mes commandes', 'Tarifs négociés', 'Programme fidélité'] },
    { title: 'Services', links: ['Livraison chantier', 'Prise de mesure', 'SAV & garanties', 'Documentation technique', 'Retours'] },
    { title: 'Weklo', links: ['À propos', 'Nos agences', 'Recrutement', 'Contact', 'Mentions légales'] },
  ];
  return (
    <footer style={{ background: 'var(--forest-700)', color: 'var(--neutral-200)', fontFamily: 'var(--font-sans)', marginTop: 64 }}>
      {/* trust strip */}
      <div style={{ borderBottom: '1px solid rgba(255,255,255,0.1)' }}>
        <div style={{ maxWidth: 1280, margin: '0 auto', padding: '28px 24px', display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 24 }}>
          {[
            { icon: 'Truck', t: 'Livraison chantier', s: 'Partout en France sous 48 h' },
            { icon: 'BadgeCheck', t: 'Produits certifiés', s: 'CE, NF & garantie 10 ans' },
            { icon: 'Headset', t: 'Conseil technique', s: 'Une équipe d’experts dédiée' },
            { icon: 'ReceiptText', t: 'Devis pro en 24 h', s: 'Tarifs dégressifs par volume' },
          ].map((f) => (
            <div key={f.t} style={{ display: 'flex', gap: 12, alignItems: 'center' }}>
              <span style={{ width: 44, height: 44, borderRadius: 'var(--radius-md)', background: 'rgba(170,201,50,0.16)', display: 'flex', alignItems: 'center', justifyContent: 'center', flex: 'none' }}>
                <Icon name={f.icon} size={22} color="var(--lime-400)" />
              </span>
              <div>
                <div style={{ fontWeight: 700, color: '#fff', fontSize: 14 }}>{f.t}</div>
                <div style={{ fontSize: 12, color: 'var(--neutral-300)' }}>{f.s}</div>
              </div>
            </div>
          ))}
        </div>
      </div>

      <div style={{ maxWidth: 1280, margin: '0 auto', padding: '40px 24px', display: 'grid', gridTemplateColumns: '1.4fr repeat(4, 1fr)', gap: 32 }}>
        <div>
          <img src="../../assets/logos/weklo-mark-light.png" alt="weklo" style={{ width: 52, height: 52, marginBottom: 14 }} />
          <p style={{ fontSize: 13, lineHeight: 1.6, color: 'var(--neutral-300)', margin: 0, maxWidth: 240 }}>
            Le distributeur des professionnels de la fermeture. Menuiseries, portails, volets &amp; automatismes.
          </p>
        </div>
        {cols.map((col) => (
          <div key={col.title}>
            <div style={{ fontFamily: 'var(--font-display)', fontWeight: 700, color: '#fff', fontSize: 15, marginBottom: 12 }}>{col.title}</div>
            <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: 8 }}>
              {col.links.map((l) => (
                <li key={l}><a href="#" style={{ fontSize: 13, color: 'var(--neutral-300)', textDecoration: 'none' }}>{l}</a></li>
              ))}
            </ul>
          </div>
        ))}
      </div>
      <div style={{ borderTop: '1px solid rgba(255,255,255,0.1)' }}>
        <div style={{ maxWidth: 1280, margin: '0 auto', padding: '18px 24px', display: 'flex', justifyContent: 'space-between', fontSize: 12, color: 'var(--neutral-400)' }}>
          <span>© 2026 Weklo — Tous droits réservés</span>
          <span>CGV · Confidentialité · Cookies</span>
        </div>
      </div>
    </footer>
  );
}

Object.assign(window, { Footer });
