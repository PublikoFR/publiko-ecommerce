/* Weklo Ecommerce — Header (utility bar + main header + category nav) */

function Header({ cartCount = 0, onCart, onNav, onHome, active, query, onQuery }) {
  const { Badge } = window.WekloDesignSystem_23c47b;
  return (
    <header style={{ position: 'sticky', top: 0, zIndex: 30, fontFamily: 'var(--font-sans)' }}>
      {/* Utility bar */}
      <div style={{ background: 'var(--forest-600)', color: 'var(--lime-300)' }}>
        <div style={{ maxWidth: 1280, margin: '0 auto', padding: '0 24px', height: 38, display: 'flex', alignItems: 'center', justifyContent: 'space-between', fontSize: 13 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, whiteSpace: 'nowrap' }}>
            <Icon name="Truck" size={15} color="var(--lime-400)" />
            <span style={{ color: '#fff' }}>Livraison chantier 48 h · Franco dès 500 € HT</span>
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 20, whiteSpace: 'nowrap' }}>
            <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6, color: '#fff' }}><Icon name="Phone" size={14} color="var(--lime-400)" /> 04 78 00 00 00</span>
            <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6, color: '#fff' }}><Icon name="MapPin" size={14} color="var(--lime-400)" /> Trouver une agence</span>
          </div>
        </div>
      </div>

      {/* Main header */}
      <div style={{ background: 'var(--surface-card)', borderBottom: '1px solid var(--border-subtle)' }}>
        <div style={{ maxWidth: 1280, margin: '0 auto', padding: '0 24px', height: 78, display: 'flex', alignItems: 'center', gap: 28 }}>
          <button onClick={onHome} style={{ border: 'none', background: 'transparent', cursor: 'pointer', flex: 'none', display: 'flex', alignItems: 'center' }}>
            <img src="../../assets/logos/weklo-lockup.png" alt="weklo" style={{ height: 38 }} />
          </button>

          {/* Search */}
          <div style={{ flex: 1, display: 'flex', alignItems: 'center', height: 48, background: 'var(--surface-page)', border: '1.5px solid var(--border-default)', borderRadius: 'var(--radius-full)', padding: '0 6px 0 18px' }}>
            <Icon name="Search" size={20} color="var(--text-muted)" />
            <input value={query} onChange={(e) => onQuery && onQuery(e.target.value)}
              placeholder="Rechercher un produit, une référence…"
              style={{ flex: 1, border: 'none', outline: 'none', background: 'transparent', fontSize: 15, fontFamily: 'var(--font-sans)', color: 'var(--text-primary)', padding: '0 12px', minWidth: 0 }} />
            <button style={{ height: 38, padding: '0 18px', border: 'none', borderRadius: 'var(--radius-full)', background: 'var(--forest-600)', color: '#fff', fontWeight: 600, fontSize: 14, cursor: 'pointer', fontFamily: 'var(--font-sans)' }}>Rechercher</button>
          </div>

          {/* Account + cart */}
          <div style={{ display: 'flex', alignItems: 'center', gap: 6, flex: 'none' }}>
            <button style={btnIcon}>
              <Icon name="User" size={22} color="var(--forest-600)" />
              <span style={btnIconLabel}>Compte pro</span>
            </button>
            <button style={btnIcon}>
              <Icon name="Heart" size={22} color="var(--forest-600)" />
              <span style={btnIconLabel}>Favoris</span>
            </button>
            <button onClick={onCart} style={{ ...btnIcon, position: 'relative' }}>
              <span style={{ position: 'relative' }}>
                <Icon name="ShoppingCart" size={22} color="var(--forest-600)" />
                {cartCount > 0 && (
                  <span style={{ position: 'absolute', top: -8, right: -10, minWidth: 18, height: 18, padding: '0 5px', borderRadius: 9, background: 'var(--lime-500)', color: 'var(--forest-700)', fontSize: 11, fontWeight: 700, display: 'flex', alignItems: 'center', justifyContent: 'center', fontFamily: 'var(--font-mono)' }}>{cartCount}</span>
                )}
              </span>
              <span style={btnIconLabel}>Panier</span>
            </button>
          </div>
        </div>

        {/* Category nav */}
        <div style={{ maxWidth: 1280, margin: '0 auto', padding: '0 24px' }}>
          <nav style={{ display: 'flex', gap: 2, height: 48, alignItems: 'stretch' }}>
            {CATEGORIES.map((c) => {
              const on = active === c.id;
              return (
                <button key={c.id} onClick={() => onNav && onNav(c.id)}
                  style={{
                    display: 'flex', alignItems: 'center', gap: 8, padding: '0 16px', border: 'none',
                    borderBottom: on ? '3px solid var(--lime-500)' : '3px solid transparent',
                    background: 'transparent', cursor: 'pointer', fontFamily: 'var(--font-sans)',
                    fontWeight: 600, fontSize: 14, color: on ? 'var(--forest-600)' : 'var(--text-secondary)',
                  }}
                  onMouseEnter={(e) => { if (!on) e.currentTarget.style.color = 'var(--text-primary)'; }}
                  onMouseLeave={(e) => { if (!on) e.currentTarget.style.color = 'var(--text-secondary)'; }}>
                  <Icon name={c.icon} size={18} color={on ? 'var(--forest-600)' : 'var(--text-muted)'} />
                  {c.label}
                </button>
              );
            })}
            <button style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: 8, padding: '0 16px', border: 'none', background: 'transparent', cursor: 'pointer', fontFamily: 'var(--font-sans)', fontWeight: 600, fontSize: 14, color: 'var(--lime-700)' }}>
              <Icon name="FileText" size={18} color="var(--lime-700)" /> Demander un devis
            </button>
          </nav>
        </div>
      </div>
    </header>
  );
}

const btnIcon = { display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 2, border: 'none', background: 'transparent', cursor: 'pointer', padding: '6px 10px', borderRadius: 'var(--radius-md)' };
const btnIconLabel = { fontSize: 11, color: 'var(--text-secondary)', fontFamily: 'var(--font-sans)', fontWeight: 500 };

Object.assign(window, { Header });
