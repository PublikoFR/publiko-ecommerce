/* Weklo Ecommerce — Cart drawer (slide-in) */

function CartDrawer({ open, items, onClose, onQty, onRemove }) {
  const { Button, Badge } = window.WekloDesignSystem_23c47b;
  const lines = Object.values(items);
  const subtotal = lines.reduce((s, l) => s + l.product.price * l.qty, 0);
  const count = lines.reduce((s, l) => s + l.qty, 0);
  return (
    <>
      <div onClick={onClose} style={{
        position: 'fixed', inset: 0, background: 'rgba(0,33,30,0.4)', backdropFilter: 'blur(2px)',
        opacity: open ? 1 : 0, pointerEvents: open ? 'auto' : 'none',
        transition: 'opacity var(--duration-base) var(--ease-standard)', zIndex: 40,
      }} />
      <aside style={{
        position: 'fixed', top: 0, right: 0, bottom: 0, width: 420, maxWidth: '92vw', zIndex: 50,
        background: 'var(--surface-card)', boxShadow: 'var(--shadow-xl)', display: 'flex', flexDirection: 'column',
        transform: open ? 'translateX(0)' : 'translateX(100%)',
        transition: 'transform var(--duration-slow) var(--ease-standard)', fontFamily: 'var(--font-sans)',
      }}>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '20px 24px', borderBottom: '1px solid var(--border-subtle)' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
            <Icon name="ShoppingCart" size={22} color="var(--forest-600)" />
            <span style={{ fontFamily: 'var(--font-display)', fontWeight: 700, fontSize: 20 }}>Mon panier</span>
            <Badge tone="neutral" size="sm">{count}</Badge>
          </div>
          <button onClick={onClose} style={{ border: 'none', background: 'transparent', cursor: 'pointer', padding: 6 }}><Icon name="X" size={22} color="var(--text-secondary)" /></button>
        </div>

        <div style={{ flex: 1, overflowY: 'auto', padding: lines.length ? '12px 24px' : 24 }}>
          {lines.length === 0 && (
            <div style={{ height: '100%', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 12, color: 'var(--text-muted)', textAlign: 'center' }}>
              <Icon name="PackageOpen" size={56} color="var(--neutral-300)" strokeWidth={1.4} />
              <div style={{ fontWeight: 600, color: 'var(--text-secondary)' }}>Votre panier est vide</div>
              <div style={{ fontSize: 13 }}>Ajoutez des produits depuis le catalogue.</div>
            </div>
          )}
          {lines.map((l) => (
            <div key={l.product.id} style={{ display: 'flex', gap: 14, padding: '14px 0', borderBottom: '1px solid var(--border-subtle)' }}>
              <div style={{ width: 72, height: 72, borderRadius: 'var(--radius-md)', overflow: 'hidden', flex: 'none', border: '1px solid var(--border-subtle)' }}>
                <ProductViz cat={l.product.cat} size="sm" />
              </div>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontWeight: 600, fontSize: 14, lineHeight: 1.3 }}>{l.product.name}</div>
                <div style={{ fontFamily: 'var(--font-mono)', fontSize: 11, color: 'var(--text-muted)', margin: '2px 0 8px' }}>Réf. {l.product.ref}</div>
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                  <Stepper qty={l.qty} onChange={(q) => onQty(l.product.id, q)} />
                  <span style={{ fontFamily: 'var(--font-display)', fontWeight: 700, color: 'var(--forest-600)' }}>{euro(l.product.price * l.qty)}</span>
                </div>
              </div>
              <button onClick={() => onRemove(l.product.id)} style={{ border: 'none', background: 'transparent', cursor: 'pointer', alignSelf: 'flex-start', padding: 4, color: 'var(--text-muted)' }}><Icon name="Trash2" size={16} /></button>
            </div>
          ))}
        </div>

        {lines.length > 0 && (
          <div style={{ borderTop: '1px solid var(--border-subtle)', padding: 24, background: 'var(--surface-page)' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 14, color: 'var(--text-secondary)', marginBottom: 6 }}>
              <span>Sous-total HT</span><span style={{ fontWeight: 600, color: 'var(--text-primary)' }}>{euro(subtotal)}</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 14, color: 'var(--text-secondary)', marginBottom: 14 }}>
              <span>TVA 20 %</span><span>{euro(subtotal * 0.2)}</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', marginBottom: 16 }}>
              <span style={{ fontWeight: 700 }}>Total TTC</span>
              <span style={{ fontFamily: 'var(--font-display)', fontWeight: 700, fontSize: 26, color: 'var(--forest-600)' }}>{euro(subtotal * 1.2)}</span>
            </div>
            <Button variant="accent" fullWidth size="lg" iconRight={<Icon name="ArrowRight" size={18} />}>Valider la commande</Button>
            <button style={{ width: '100%', marginTop: 10, height: 40, border: 'none', background: 'transparent', color: 'var(--text-brand)', fontWeight: 600, fontFamily: 'var(--font-sans)', fontSize: 14, cursor: 'pointer' }}>Convertir en devis</button>
          </div>
        )}
      </aside>
    </>
  );
}

function Stepper({ qty, onChange, size = 'sm' }) {
  const h = size === 'lg' ? 44 : 32;
  const btn = { width: h, height: h, border: 'none', background: 'transparent', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--forest-600)' };
  return (
    <div style={{ display: 'inline-flex', alignItems: 'center', border: '1.5px solid var(--border-default)', borderRadius: 'var(--radius-md)', height: h }}>
      <button style={btn} onClick={(e) => { e.stopPropagation(); onChange(Math.max(1, qty - 1)); }}><Icon name="Minus" size={16} /></button>
      <span style={{ minWidth: 30, textAlign: 'center', fontFamily: 'var(--font-mono)', fontWeight: 600, fontSize: size === 'lg' ? 16 : 14 }}>{qty}</span>
      <button style={btn} onClick={(e) => { e.stopPropagation(); onChange(qty + 1); }}><Icon name="Plus" size={16} /></button>
    </div>
  );
}

Object.assign(window, { CartDrawer, Stepper });
