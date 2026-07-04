/* Weklo Ecommerce — Home page (with hero carousel) */

const HERO_SLIDES = [
  {
    id: 'catalogue',
    over: 'Réservé aux professionnels',
    title: ['Tout pour la ', 'fermeture', ' du professionnel'],
    text: 'Menuiseries, portails, volets et automatismes des plus grandes marques. Tarifs pro, stock permanent et livraison chantier en 48 h.',
    cta: { label: 'Explorer le catalogue', cat: 'fenetres' },
    secondary: { label: 'Demander un devis', icon: 'FileText' },
    icon: 'Warehouse',
    stats: [['12 000+', 'références'], ['48 h', 'livraison chantier'], ['28', 'agences en France']],
  },
  {
    id: 'portails',
    over: 'Univers portails & accès',
    title: ['Portails & ', 'motorisation', ', prêts à poser'],
    text: 'Battant ou coulissant, alu ou acier, du portail nu au kit motorisé Somfy et Nice. Configurés, livrés et prêts pour vos chantiers.',
    cta: { label: 'Voir les portails', cat: 'portails' },
    secondary: { label: 'Motorisation', icon: 'Cog', cat: 'motorisation' },
    icon: 'Fence',
    stats: [['3,5 m', "jusqu'à 6 m sur mesure"], ['600 kg', 'moteurs coulissants'], ['-35 %', 'tarif pro']],
  },
  {
    id: 'volets',
    over: 'Sélection éco',
    title: ['Le volet roulant ', 'solaire', ', sans travaux'],
    text: 'Pose sans raccordement électrique, pilotage domotique et jusqu’à 40 % d’économie de chauffage. Le confort qui s’installe en une journée.',
    cta: { label: 'Voir les volets', cat: 'volets' },
    secondary: { label: 'Nos univers', icon: 'LayoutGrid' },
    icon: 'Blinds',
    stats: [['0', 'travaux électriques'], ['-40 %', 'sur le chauffage'], ['Éco', 'sélection Bubendorff']],
  },
];

function HeroCarousel({ onNav }) {
  const { Button, Badge } = window.WekloDesignSystem_23c47b;
  const [i, setI] = React.useState(0);
  const [paused, setPaused] = React.useState(false);
  const n = HERO_SLIDES.length;
  const go = (k) => setI((k + n) % n);

  React.useEffect(() => {
    if (paused) return;
    const t = setInterval(() => setI((c) => (c + 1) % n), 6000);
    return () => clearInterval(t);
  }, [paused, n]);

  return (
    <section
      onMouseEnter={() => setPaused(true)}
      onMouseLeave={() => setPaused(false)}
      style={{ background: 'var(--forest-600)', color: '#fff', position: 'relative', overflow: 'hidden' }}
    >
      <Decor corner="tr" tone="on-dark" size={560} />
      <Decor corner="bl" tone="on-dark" size={420} opacity={0.08} />

      {/* Sliding track */}
      <div style={{ display: 'flex', transform: `translateX(-${i * 100}%)`, transition: 'transform 620ms var(--ease-standard)' }}>
        {HERO_SLIDES.map((s) => (
          <div key={s.id} style={{ flex: '0 0 100%', minWidth: 0 }}>
            <div style={{ maxWidth: 1280, margin: '0 auto', padding: '60px 88px 72px', position: 'relative', display: 'grid', gridTemplateColumns: '1.08fr 0.92fr', gap: 40, alignItems: 'center' }}>
              <div>
                <span style={{ display: 'inline-flex', alignItems: 'center', gap: 8, background: 'rgba(170,201,50,0.18)', color: 'var(--lime-300)', padding: '6px 14px', borderRadius: 'var(--radius-full)', fontSize: 13, fontWeight: 600, marginBottom: 20 }}>
                  <Icon name="Building2" size={15} color="var(--lime-400)" /> {s.over}
                </span>
                <h1 style={{ fontFamily: 'var(--font-display)', fontWeight: 700, fontSize: 52, lineHeight: 1.05, letterSpacing: '-0.02em', margin: 0, color: '#fff' }}>
                  {s.title[0]}<span style={{ color: 'var(--lime-400)' }}>{s.title[1]}</span>{s.title[2]}
                </h1>
                <p style={{ fontSize: 18, lineHeight: 1.6, color: 'var(--neutral-200)', margin: '20px 0 32px', maxWidth: 480 }}>{s.text}</p>
                <div style={{ display: 'flex', gap: 12 }}>
                  <Button variant="accent" size="lg" iconRight={<Icon name="ArrowRight" size={18} />} onClick={() => onNav(s.cta.cat)}>{s.cta.label}</Button>
                  <Button variant="secondary" size="lg" style={{ background: 'transparent', color: '#fff', borderColor: 'rgba(255,255,255,0.4)' }} iconLeft={<Icon name={s.secondary.icon} size={18} />} onClick={() => s.secondary.cat && onNav(s.secondary.cat)}>{s.secondary.label}</Button>
                </div>
                <div style={{ display: 'flex', gap: 28, marginTop: 36 }}>
                  {s.stats.map(([v, l]) => (
                    <div key={l}>
                      <div style={{ fontFamily: 'var(--font-display)', fontWeight: 700, fontSize: 26, color: 'var(--lime-400)' }}>{v}</div>
                      <div style={{ fontSize: 13, color: 'var(--neutral-300)' }}>{l}</div>
                    </div>
                  ))}
                </div>
              </div>

              {/* Visual panel */}
              <div style={{ position: 'relative', height: 300, borderRadius: 'var(--radius-2xl)', background: 'linear-gradient(155deg, var(--forest-500) 0%, var(--forest-700) 100%)', border: '1px solid rgba(255,255,255,0.12)', overflow: 'hidden', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                <Decor corner="c" size={360} color="var(--lime-400)" opacity={0.16} />
                <Icon name={s.icon} size={150} color="#fff" strokeWidth={1.1} style={{ position: 'relative', opacity: 0.95 }} />
                <span style={{ position: 'absolute', left: 18, bottom: 14, fontFamily: 'var(--font-display)', fontWeight: 700, fontSize: 18, color: 'rgba(255,255,255,0.85)' }}>weklo</span>
              </div>
            </div>
          </div>
        ))}
      </div>

      {/* Arrows */}
      {[['ChevronLeft', () => go(i - 1), { left: 22 }], ['ChevronRight', () => go(i + 1), { right: 22 }]].map(([ic, fn, pos]) => (
        <button key={ic} onClick={fn} aria-label={ic}
          style={{ position: 'absolute', top: '50%', transform: 'translateY(-50%)', ...pos, width: 46, height: 46, borderRadius: 'var(--radius-full)', border: '1px solid rgba(255,255,255,0.28)', background: 'rgba(0,33,30,0.35)', backdropFilter: 'blur(6px)', color: '#fff', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 2, transition: 'background var(--duration-base) var(--ease-standard)' }}
          onMouseEnter={(e) => { e.currentTarget.style.background = 'rgba(170,201,50,0.85)'; }}
          onMouseLeave={(e) => { e.currentTarget.style.background = 'rgba(0,33,30,0.35)'; }}>
          <Icon name={ic} size={22} />
        </button>
      ))}

      {/* Dots */}
      <div style={{ position: 'absolute', bottom: 22, left: 0, right: 0, display: 'flex', justifyContent: 'center', gap: 10, zIndex: 2 }}>
        {HERO_SLIDES.map((s, k) => (
          <button key={s.id} onClick={() => go(k)} aria-label={`Slide ${k + 1}`}
            style={{ height: 8, width: k === i ? 30 : 8, borderRadius: 'var(--radius-full)', border: 'none', padding: 0, cursor: 'pointer', background: k === i ? 'var(--lime-400)' : 'rgba(255,255,255,0.4)', transition: 'all var(--duration-base) var(--ease-standard)' }} />
        ))}
      </div>
    </section>
  );
}

function HomePage({ onNav, onOpen, onAdd }) {
  const { Button } = window.WekloDesignSystem_23c47b;
  const featured = PRODUCTS.filter((p) => p.badge).slice(0, 4);
  return (
    <div style={{ fontFamily: 'var(--font-sans)' }}>
      <HeroCarousel onNav={onNav} />

      <div style={{ maxWidth: 1280, margin: '0 auto', padding: '0 24px' }}>
        {/* Categories */}
        <section style={{ padding: '56px 0 8px' }}>
          <SectionHead over="Catalogue" title="Nos univers" action={{ label: 'Voir tout', onClick: () => onNav('fenetres') }} />
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 20 }}>
            {CATEGORIES.map((c) => (
              <button key={c.id} onClick={() => onNav(c.id)} style={{
                display: 'flex', alignItems: 'center', gap: 18, padding: 22, borderRadius: 'var(--radius-xl)',
                border: '1px solid var(--border-subtle)', background: 'var(--surface-card)', cursor: 'pointer', textAlign: 'left',
                transition: 'all var(--duration-base) var(--ease-standard)',
              }}
                onMouseEnter={(e) => { e.currentTarget.style.transform = 'translateY(-3px)'; e.currentTarget.style.boxShadow = 'var(--shadow-md)'; }}
                onMouseLeave={(e) => { e.currentTarget.style.transform = 'translateY(0)'; e.currentTarget.style.boxShadow = 'none'; }}>
                <span style={{ width: 64, height: 64, borderRadius: 'var(--radius-lg)', background: 'var(--forest-50)', display: 'flex', alignItems: 'center', justifyContent: 'center', flex: 'none' }}>
                  <Icon name={c.icon} size={32} color="var(--forest-600)" strokeWidth={1.6} />
                </span>
                <div>
                  <div style={{ fontFamily: 'var(--font-display)', fontWeight: 700, fontSize: 19, color: 'var(--text-primary)' }}>{c.label}</div>
                  <div style={{ fontSize: 13, color: 'var(--text-secondary)', marginTop: 2 }}>{c.blurb}</div>
                </div>
                <Icon name="ArrowRight" size={20} color="var(--lime-600)" style={{ marginLeft: 'auto' }} />
              </button>
            ))}
          </div>
        </section>

        {/* Featured */}
        <section style={{ padding: '48px 0 8px' }}>
          <SectionHead over="Sélection" title="Produits à la une" action={{ label: 'Tout le catalogue', onClick: () => onNav('fenetres') }} />
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 20 }}>
            {featured.map((p) => <ProductCard key={p.id} product={p} onOpen={onOpen} onAdd={onAdd} />)}
          </div>
        </section>

        {/* Pro banner */}
        <section style={{ padding: '48px 0 8px' }}>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 0, borderRadius: 'var(--radius-2xl)', overflow: 'hidden', border: '1px solid var(--border-subtle)' }}>
            <div style={{ position: 'relative', background: 'var(--lime-500)', padding: 40, display: 'flex', flexDirection: 'column', justifyContent: 'center', overflow: 'hidden' }}>
              <Decor corner="br" tone="accent" size={320} />
              <div style={{ position: 'relative', fontFamily: 'var(--font-display)', fontWeight: 700, fontSize: 28, color: 'var(--forest-700)', lineHeight: 1.1 }}>Ouvrez votre compte pro</div>
              <p style={{ position: 'relative', fontSize: 15, color: 'var(--forest-700)', margin: '12px 0 24px', maxWidth: 360, lineHeight: 1.5 }}>Tarifs dégressifs, encours dédié, devis en 24 h et un interlocuteur unique pour vos chantiers.</p>
              <div style={{ position: 'relative' }}><Button variant="primary" size="lg" iconRight={<Icon name="ArrowRight" size={18} />}>Créer mon compte</Button></div>
            </div>
            <div style={{ position: 'relative', background: 'var(--forest-600)', padding: 40, color: '#fff', display: 'flex', flexDirection: 'column', justifyContent: 'center', gap: 14, overflow: 'hidden' }}>
              <Decor corner="tr" tone="on-dark" size={300} />
              {[['Percent', 'Jusqu’à -35 % sur le tarif catalogue'], ['CalendarClock', 'Devis chiffré sous 24 h ouvrées'], ['Wallet', 'Paiement à 30 / 45 jours fin de mois']].map(([ic, t]) => (
                <div key={t} style={{ position: 'relative', display: 'flex', alignItems: 'center', gap: 12 }}>
                  <Icon name={ic} size={22} color="var(--lime-400)" />
                  <span style={{ fontSize: 15 }}>{t}</span>
                </div>
              ))}
            </div>
          </div>
        </section>
      </div>
    </div>
  );
}

function SectionHead({ over, title, action }) {
  return (
    <div style={{ display: 'flex', alignItems: 'flex-end', justifyContent: 'space-between', marginBottom: 24 }}>
      <div>
        <div style={{ fontFamily: 'var(--font-sans)', fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.08em', fontSize: 12, color: 'var(--lime-700)', marginBottom: 6 }}>{over}</div>
        <h2 style={{ fontFamily: 'var(--font-display)', fontWeight: 700, fontSize: 34, margin: 0, color: 'var(--text-primary)' }}>{title}</h2>
      </div>
      {action && (
        <button onClick={action.onClick} style={{ display: 'inline-flex', alignItems: 'center', gap: 6, border: 'none', background: 'transparent', cursor: 'pointer', color: 'var(--text-brand)', fontWeight: 600, fontFamily: 'var(--font-sans)', fontSize: 14 }}>
          {action.label} <Icon name="ArrowRight" size={16} />
        </button>
      )}
    </div>
  );
}

Object.assign(window, { HomePage, SectionHead, HeroCarousel });
