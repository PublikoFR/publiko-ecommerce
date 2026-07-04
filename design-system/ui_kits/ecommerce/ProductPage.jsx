/* Weklo Ecommerce — Product detail page */

function ProductPage({ product, onAdd, onNav, onHome, onOpen }) {
  const { Button, Badge, Tabs } = window.WekloDesignSystem_23c47b;
  const [qty, setQty] = React.useState(1);
  const [tab, setTab] = React.useState('desc');
  const [thumb, setThumb] = React.useState(0);
  const [color, setColor] = React.useState('Blanc');
  const stock = STOCK[product.stock];
  const cat = CATEGORIES.find((c) => c.id === product.cat);
  const related = PRODUCTS.filter((p) => p.cat === product.cat && p.id !== product.id).slice(0, 4);
  const colors = [['Blanc', '#f3f4f2'], ['Gris anthracite', '#3b3f3d'], ['Beige', '#d8cdb6'], ['Noir', '#1a1c1b']];

  return (
    <div style={{ fontFamily: 'var(--font-sans)', background: 'var(--surface-page)' }}>
      <div style={{ maxWidth: 1280, margin: '0 auto', padding: '20px 24px 56px' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 13, color: 'var(--text-muted)', marginBottom: 20 }}>
          <button onClick={onHome} style={crumb}>Accueil</button>
          <Icon name="ChevronRight" size={14} />
          <button onClick={() => onNav(product.cat)} style={crumb}>{cat.label}</button>
          <Icon name="ChevronRight" size={14} />
          <span style={{ color: 'var(--text-primary)', fontWeight: 600 }}>{product.name}</span>
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 40, alignItems: 'start' }}>
          {/* Gallery */}
          <div style={{ display: 'flex', gap: 16, position: 'sticky', top: 176 }}>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
              {[0, 1, 2, 3].map((i) => (
                <button key={i} onClick={() => setThumb(i)} style={{
                  width: 64, height: 64, borderRadius: 'var(--radius-md)', overflow: 'hidden', cursor: 'pointer',
                  border: thumb === i ? '2px solid var(--forest-600)' : '1px solid var(--border-subtle)', padding: 0, background: 'none',
                }}><ProductViz cat={product.cat} size="sm" /></button>
              ))}
            </div>
            <div style={{ flex: 1, aspectRatio: '1 / 1', borderRadius: 'var(--radius-2xl)', overflow: 'hidden', border: '1px solid var(--border-subtle)', position: 'relative' }}>
              <ProductViz cat={product.cat} size="lg" />
              {product.badge && <span style={{ position: 'absolute', top: 16, left: 16 }}><Badge tone="lime" variant="solid">{product.badge}</Badge></span>}
            </div>
          </div>

          {/* Info */}
          <div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 10 }}>
              <span style={{ fontFamily: 'var(--font-mono)', fontSize: 12, color: 'var(--text-muted)' }}>{product.brand}</span>
              <Badge tone={stock.tone} size="sm" dot>{stock.label}</Badge>
            </div>
            <h1 style={{ fontFamily: 'var(--font-display)', fontWeight: 700, fontSize: 32, margin: '0 0 12px', lineHeight: 1.15 }}>{product.name}</h1>
            <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 20 }}>
              <Stars value={product.rating} size={16} />
              <span style={{ fontSize: 14, color: 'var(--text-secondary)' }}>{product.rating.toFixed(1)} · 47 avis</span>
              <span style={{ fontFamily: 'var(--font-mono)', fontSize: 12, color: 'var(--text-muted)' }}>Réf. {product.ref}</span>
            </div>

            <div style={{ background: 'var(--surface-card)', border: '1px solid var(--border-subtle)', borderRadius: 'var(--radius-xl)', padding: 24 }}>
              <div style={{ display: 'flex', alignItems: 'baseline', gap: 12 }}>
                <span style={{ fontFamily: 'var(--font-display)', fontWeight: 700, fontSize: 40, color: 'var(--forest-600)' }}>{euro(product.price)}</span>
                <span style={{ fontSize: 14, color: 'var(--text-muted)' }}>HT</span>
                <span style={{ fontSize: 14, color: 'var(--text-secondary)', marginLeft: 'auto' }}>{euro(ttc(product.price))} TTC</span>
              </div>
              <div style={{ display: 'inline-flex', alignItems: 'center', gap: 6, marginTop: 8, color: 'var(--success-700)', fontSize: 13, fontWeight: 600 }}>
                <Icon name="Tag" size={14} color="var(--success-500)" /> Tarif pro · remises dégressives dès 5 unités
              </div>

              {/* Color */}
              <div style={{ marginTop: 22 }}>
                <div style={{ fontSize: 13, fontWeight: 700, marginBottom: 10 }}>Coloris · <span style={{ fontWeight: 400, color: 'var(--text-secondary)' }}>{color}</span></div>
                <div style={{ display: 'flex', gap: 10 }}>
                  {colors.map(([name, hex]) => (
                    <button key={name} title={name} onClick={() => setColor(name)} style={{
                      width: 38, height: 38, borderRadius: 'var(--radius-full)', background: hex, cursor: 'pointer',
                      border: color === name ? '2px solid var(--forest-600)' : '1px solid var(--border-default)',
                      boxShadow: color === name ? '0 0 0 3px var(--lime-100)' : 'none',
                    }} />
                  ))}
                </div>
              </div>

              {/* Qty + add */}
              <div style={{ display: 'flex', gap: 12, marginTop: 24 }}>
                <Stepper qty={qty} onChange={setQty} size="lg" />
                <Button variant="accent" size="lg" style={{ flex: 1 }} iconLeft={<Icon name="ShoppingCart" size={20} />} onClick={() => onAdd(product, qty)}>Ajouter au panier</Button>
              </div>
              <div style={{ display: 'flex', gap: 12, marginTop: 12 }}>
                <Button variant="secondary" size="md" style={{ flex: 1 }} iconLeft={<Icon name="FileText" size={18} />}>Devis</Button>
                <Button variant="ghost" size="md" style={{ flex: 1 }} iconLeft={<Icon name="Heart" size={18} />}>Favori</Button>
              </div>
            </div>

            {/* delivery reassurance */}
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12, marginTop: 16 }}>
              {[['Truck', 'Livraison chantier 48 h'], ['ShieldCheck', 'Garantie 10 ans'], ['RotateCcw', 'Retour sous 30 j'], ['Headset', 'Conseil technique']].map(([ic, t]) => (
                <div key={t} style={{ display: 'flex', alignItems: 'center', gap: 10, fontSize: 13, color: 'var(--text-secondary)' }}>
                  <Icon name={ic} size={18} color="var(--forest-600)" /> {t}
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Tabs */}
        <div style={{ marginTop: 48 }}>
          <Tabs value={tab} onChange={setTab} tabs={[
            { id: 'desc', label: 'Description' },
            { id: 'spec', label: 'Caractéristiques' },
            { id: 'doc', label: 'Documents', count: 3 },
          ]} />
          <div style={{ background: 'var(--surface-card)', border: '1px solid var(--border-subtle)', borderTop: 'none', borderRadius: '0 0 var(--radius-xl) var(--radius-xl)', padding: 28 }}>
            {tab === 'desc' && (
              <p style={{ maxWidth: 760, lineHeight: 1.7, color: 'var(--text-secondary)', margin: 0 }}>
                {product.name} en {product.material.toLowerCase()}, conçu pour les professionnels exigeants. Performances thermiques et acoustiques optimisées, finition soignée et pose facilitée. Compatible avec l'ensemble de nos accessoires et motorisations. Fabrication certifiée CE &amp; NF.
              </p>
            )}
            {tab === 'spec' && (
              <div style={{ maxWidth: 640 }}>
                {[['Marque', product.brand], ['Matière', product.material], ['Référence', product.ref], ['Coloris disponibles', '4'], ['Garantie', '10 ans'], ['Certification', 'CE · NF']].map(([k, v], i) => (
                  <div key={k} style={{ display: 'flex', justifyContent: 'space-between', padding: '12px 0', borderBottom: i < 5 ? '1px solid var(--border-subtle)' : 'none' }}>
                    <span style={{ color: 'var(--text-secondary)' }}>{k}</span>
                    <span style={{ fontWeight: 600, color: 'var(--text-primary)' }}>{v}</span>
                  </div>
                ))}
              </div>
            )}
            {tab === 'doc' && (
              <div style={{ display: 'flex', flexDirection: 'column', gap: 10, maxWidth: 460 }}>
                {['Fiche technique.pdf', 'Notice de pose.pdf', 'Déclaration de performance.pdf'].map((d) => (
                  <a key={d} href="#" style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '14px 16px', borderRadius: 'var(--radius-md)', border: '1px solid var(--border-subtle)', textDecoration: 'none', color: 'var(--text-primary)' }}>
                    <Icon name="FileText" size={20} color="var(--danger-500)" />
                    <span style={{ flex: 1, fontWeight: 600, fontSize: 14 }}>{d}</span>
                    <Icon name="Download" size={18} color="var(--text-muted)" />
                  </a>
                ))}
              </div>
            )}
          </div>
        </div>

        {/* Related */}
        <div style={{ marginTop: 48 }}>
          <h2 style={{ fontFamily: 'var(--font-display)', fontWeight: 700, fontSize: 26, marginBottom: 20 }}>Dans le même univers</h2>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 20 }}>
            {related.map((p) => <ProductCard key={p.id} product={p} onOpen={onOpen} onAdd={(pr) => onAdd(pr, 1)} />)}
          </div>
        </div>
      </div>
    </div>
  );
}

const crumb = { border: 'none', background: 'transparent', cursor: 'pointer', color: 'var(--text-muted)', padding: 0, fontFamily: 'var(--font-sans)', fontSize: 13 };

Object.assign(window, { ProductPage });
