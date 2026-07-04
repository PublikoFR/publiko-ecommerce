/* Weklo Ecommerce — Category / listing page (with layout options) */

function CategoryPage({ catId, onOpen, onAdd, onHome }) {
  const { Badge, Button } = window.WekloDesignSystem_23c47b;
  const cat = CATEGORIES.find((c) => c.id === catId) || CATEGORIES[0];
  const all = PRODUCTS.filter((p) => p.cat === cat.id);
  const [view, setView] = React.useState('grid');       // grid | list
  const [filterPos, setFilterPos] = React.useState('side'); // side | top  (LAYOUT OPTION)
  const [sort, setSort] = React.useState('Popularité');
  const [mats, setMats] = React.useState([]);

  const materials = [...new Set(all.map((p) => p.material))];
  let list = mats.length ? all.filter((p) => mats.includes(p.material)) : all;
  if (sort === 'Prix croissant') list = [...list].sort((a, b) => a.price - b.price);
  if (sort === 'Prix décroissant') list = [...list].sort((a, b) => b.price - a.price);
  if (sort === 'Mieux notés') list = [...list].sort((a, b) => b.rating - a.rating);
  const toggleMat = (m) => setMats((s) => s.includes(m) ? s.filter((x) => x !== m) : [...s, m]);

  const Filters = ({ horizontal }) => (
    <div style={{ display: 'flex', flexDirection: horizontal ? 'row' : 'column', gap: horizontal ? 20 : 26, alignItems: horizontal ? 'center' : 'stretch', flexWrap: 'wrap' }}>
      <FilterBlock title="Matière" horizontal={horizontal}>
        {materials.map((m) => {
          const { Checkbox } = window.WekloDesignSystem_23c47b;
          return <Checkbox key={m} label={m} checked={mats.includes(m)} onChange={() => toggleMat(m)} />;
        })}
      </FilterBlock>
      {!horizontal && (
        <>
          <FilterBlock title="Disponibilité">
            {['En stock', 'Sur commande'].map((m) => {
              const { Checkbox } = window.WekloDesignSystem_23c47b;
              return <Checkbox key={m} label={m} />;
            })}
          </FilterBlock>
          <FilterBlock title="Prix HT">
            <input type="range" min="0" max="2000" defaultValue="2000" style={{ width: '100%', accentColor: 'var(--lime-600)' }} />
            <div style={{ display: 'flex', justifyContent: 'space-between', fontFamily: 'var(--font-mono)', fontSize: 12, color: 'var(--text-muted)' }}><span>0 €</span><span>2 000 €</span></div>
          </FilterBlock>
        </>
      )}
    </div>
  );

  return (
    <div style={{ fontFamily: 'var(--font-sans)', background: 'var(--surface-page)', minHeight: '60vh' }}>
      <div style={{ maxWidth: 1280, margin: '0 auto', padding: '20px 24px 64px' }}>
        {/* breadcrumb */}
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 13, color: 'var(--text-muted)', marginBottom: 16 }}>
          <button onClick={onHome} style={{ border: 'none', background: 'transparent', cursor: 'pointer', color: 'var(--text-muted)', padding: 0 }}>Accueil</button>
          <Icon name="ChevronRight" size={14} />
          <span style={{ color: 'var(--text-primary)', fontWeight: 600 }}>{cat.label}</span>
        </div>

        <div style={{ display: 'flex', alignItems: 'flex-end', justifyContent: 'space-between', marginBottom: 20, gap: 16, flexWrap: 'wrap' }}>
          <div>
            <h1 style={{ fontFamily: 'var(--font-display)', fontWeight: 700, fontSize: 36, margin: 0 }}>{cat.label}</h1>
            <div style={{ fontSize: 14, color: 'var(--text-secondary)', marginTop: 4 }}>{list.length} produits · {cat.blurb}</div>
          </div>
          {/* Layout option controls */}
          <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
            <Seg value={filterPos} onChange={setFilterPos} options={[{ v: 'side', icon: 'PanelLeft', t: 'Filtres latéraux' }, { v: 'top', icon: 'PanelTop', t: 'Filtres en haut' }]} />
            <Seg value={view} onChange={setView} options={[{ v: 'grid', icon: 'LayoutGrid', t: 'Grille' }, { v: 'list', icon: 'List', t: 'Liste' }]} />
            <SortSelect value={sort} onChange={setSort} />
          </div>
        </div>

        {filterPos === 'top' && (
          <div style={{ background: 'var(--surface-card)', border: '1px solid var(--border-subtle)', borderRadius: 'var(--radius-xl)', padding: '16px 20px', marginBottom: 20 }}>
            <Filters horizontal />
          </div>
        )}

        <div style={{ display: 'grid', gridTemplateColumns: filterPos === 'side' ? '248px 1fr' : '1fr', gap: 28, alignItems: 'start' }}>
          {filterPos === 'side' && (
            <aside style={{ background: 'var(--surface-card)', border: '1px solid var(--border-subtle)', borderRadius: 'var(--radius-xl)', padding: 22, position: 'sticky', top: 176 }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 18 }}>
                <Icon name="SlidersHorizontal" size={18} color="var(--forest-600)" />
                <span style={{ fontFamily: 'var(--font-display)', fontWeight: 700, fontSize: 17 }}>Filtres</span>
              </div>
              <Filters />
            </aside>
          )}

          <div>
            {/* active filter tags */}
            {mats.length > 0 && (
              <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 16 }}>
                {mats.map((m) => {
                  const { Tag } = window.WekloDesignSystem_23c47b;
                  return <Tag key={m} leadingDot color="forest" onRemove={() => toggleMat(m)}>{m}</Tag>;
                })}
                <button onClick={() => setMats([])} style={{ border: 'none', background: 'transparent', color: 'var(--text-link)', fontSize: 13, fontWeight: 600, cursor: 'pointer', fontFamily: 'var(--font-sans)' }}>Tout effacer</button>
              </div>
            )}
            <div style={{
              display: 'grid',
              gridTemplateColumns: view === 'list' ? '1fr' : `repeat(${filterPos === 'side' ? 3 : 4}, 1fr)`,
              gap: 20,
            }}>
              {list.map((p) => <ProductCard key={p.id} product={p} onOpen={onOpen} onAdd={onAdd} view={view} />)}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

function FilterBlock({ title, children, horizontal }) {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
      <div style={{ fontWeight: 700, fontSize: 13, color: 'var(--text-primary)', textTransform: horizontal ? 'none' : 'none' }}>{title}</div>
      <div style={{ display: 'flex', flexDirection: horizontal ? 'row' : 'column', gap: horizontal ? 16 : 10 }}>{children}</div>
    </div>
  );
}

function Seg({ value, onChange, options }) {
  return (
    <div style={{ display: 'inline-flex', background: 'var(--surface-sunken)', borderRadius: 'var(--radius-md)', padding: 3, gap: 2 }}>
      {options.map((o) => {
        const on = value === o.v;
        return (
          <button key={o.v} onClick={() => onChange(o.v)} title={o.t} style={{
            display: 'flex', alignItems: 'center', justifyContent: 'center', width: 36, height: 32, border: 'none', cursor: 'pointer',
            borderRadius: 'var(--radius-sm)', background: on ? 'var(--surface-card)' : 'transparent',
            boxShadow: on ? 'var(--shadow-xs)' : 'none',
          }}>
            <Icon name={o.icon} size={18} color={on ? 'var(--forest-600)' : 'var(--text-muted)'} />
          </button>
        );
      })}
    </div>
  );
}

function SortSelect({ value, onChange }) {
  const opts = ['Popularité', 'Prix croissant', 'Prix décroissant', 'Mieux notés'];
  return (
    <div style={{ position: 'relative' }}>
      <select value={value} onChange={(e) => onChange(e.target.value)} style={{
        appearance: 'none', WebkitAppearance: 'none', height: 38, padding: '0 36px 0 14px', borderRadius: 'var(--radius-md)',
        border: '1.5px solid var(--border-default)', background: 'var(--surface-card)', fontFamily: 'var(--font-sans)',
        fontSize: 14, fontWeight: 600, color: 'var(--text-primary)', cursor: 'pointer', outline: 'none',
      }}>
        {opts.map((o) => <option key={o}>{o}</option>)}
      </select>
      <Icon name="ChevronDown" size={16} color="var(--text-muted)" style={{ position: 'absolute', right: 12, top: 11, pointerEvents: 'none' }} />
    </div>
  );
}

Object.assign(window, { CategoryPage });
