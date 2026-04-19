/* 3 layout variants for the product edit page */

const SECTIONS = [
  { id: 'info',      label: 'Informations de base', icon: 'edit' },
  { id: 'media',     label: 'Médias',              icon: 'image' },
  { id: 'pricing',   label: 'Tarification',        icon: 'dollar' },
  { id: 'inventory', label: 'Inventaire',          icon: 'box' },
  { id: 'seo',       label: 'SEO & URLs',          icon: 'globe' },
  { id: 'variants',  label: 'Variantes',           icon: 'grid' },
  { id: 'related',   label: 'Produits liés',       icon: 'link' },
  { id: 'shipping',  label: 'Expédition',          icon: 'truck' },
  { id: 'history',   label: 'Historique',          icon: 'history' },
];

/* ============ LAYOUT 1 — Contenu + sidebar contextuelle (style Shopify-like) ============ */
const LayoutTwoCol = ({ state, set }) => (
  <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0, 1fr) 320px', gap: 20, alignItems: 'start' }}>
    <div className="stack">
      <div className="card">
        <div className="card-header">
          <h3><Icon name="edit" size={14}/> Informations générales</h3>
        </div>
        <div className="card-body">
          <BasicFields state={state} set={set}/>
        </div>
      </div>

      <div className="card">
        <div className="card-header">
          <h3><Icon name="image" size={14}/> Médias</h3>
          <span className="hint">4 images · 0 vidéo</span>
        </div>
        <div className="card-body">
          <PhotoUploader/>
        </div>
      </div>

      <div className="card">
        <div className="card-header">
          <h3><Icon name="edit" size={14}/> Description longue</h3>
          <button className="btn btn-link"><Icon name="sparkle" size={13}/> Générer avec IA</button>
        </div>
        <div className="card-body">
          <LongDescription state={state} set={set}/>
        </div>
      </div>

      <div className="card">
        <div className="card-header">
          <h3><Icon name="feature" size={14}/> Caractéristiques techniques</h3>
        </div>
        <div className="card-body">
          <AttributesField/>
        </div>
      </div>

      <div className="card">
        <div className="card-header">
          <h3><Icon name="dollar" size={14}/> Tarification</h3>
        </div>
        <div className="card-body">
          <PricingFields state={state} set={set}/>
        </div>
      </div>

      <div className="card">
        <div className="card-header">
          <h3><Icon name="box" size={14}/> Inventaire & expédition</h3>
        </div>
        <div className="card-body">
          <InventoryFields state={state} set={set}/>
          <hr style={{ border: 0, borderTop: '1px solid var(--border)', margin: '4px 0' }}/>
          <ShippingFields state={state} set={set}/>
        </div>
      </div>

      <div className="card">
        <div className="card-header">
          <h3><Icon name="grid" size={14}/> Variantes</h3>
          <span className="hint">4 variantes actives</span>
        </div>
        <div className="card-body">
          <VariantsTable/>
        </div>
      </div>

      <div className="card">
        <div className="card-header">
          <h3><Icon name="globe" size={14}/> Référencement (SEO)</h3>
        </div>
        <div className="card-body">
          <SEOFields state={state} set={set}/>
        </div>
      </div>
    </div>

    <div className="stack" style={{ position: 'sticky', top: 70 }}>
      <div className="card">
        <div className="card-header">
          <h3>Statut & visibilité</h3>
          <span className={`badge ${state.status === 'published' ? 'green' : 'gray'}`}>
            <span className="dot"/>
            {state.status === 'published' ? 'Publié' : state.status === 'draft' ? 'Brouillon' : state.status}
          </span>
        </div>
        <div className="card-body">
          <StatusPanel state={state} set={set}/>
        </div>
      </div>

      <div className="card">
        <div className="card-header">
          <h3>Organisation</h3>
        </div>
        <div className="card-body">
          <CategoryField/>
          <Field label="Marque">
            <select className="select">
              <option>Lunar Pro</option>
              <option>Atlantem</option>
              <option>K-Line</option>
            </select>
          </Field>
          <TagsField/>
        </div>
      </div>

      <div className="card">
        <div className="card-header">
          <h3><Icon name="link" size={13}/> Produits liés</h3>
        </div>
        <div className="card-body">
          <RelatedProducts/>
        </div>
      </div>

      <div className="card">
        <div className="card-header">
          <h3><Icon name="history" size={13}/> Dernières modifications</h3>
          <button className="btn btn-link" style={{ padding: 0, fontSize: 12 }}>Tout voir</button>
        </div>
        <div className="card-body" style={{ paddingTop: 6 }}>
          <HistoryList/>
        </div>
      </div>
    </div>
  </div>
);

/* ============ LAYOUT 2 — 1 colonne avec 3 onglets ============ */
const LayoutTabbed = ({ state, set }) => {
  const [tab, setTab] = React.useState('general');
  const tabs = [
    { id: 'general',   label: 'Général' },
    { id: 'content',   label: 'Contenu & SEO' },
    { id: 'stock',     label: 'Stock & variantes' },
  ];
  return (
    <div style={{ maxWidth: 900, margin: '0 auto' }}>
      <div style={{ background: '#fff', border: '1px solid var(--border)', borderRadius: 'var(--radius)', boxShadow: 'var(--shadow-sm)' }}>
        <div className="tabs" style={{ padding: '0 18px' }}>
          {tabs.map(t => (
            <button key={t.id} className={`tab ${tab === t.id ? 'active' : ''}`} onClick={() => setTab(t.id)}>
              {t.label}
            </button>
          ))}
        </div>

        {tab === 'general' && (
          <div style={{ padding: '20px 24px' }} className="stack">
            <div style={{ display: 'grid', gridTemplateColumns: '220px 1fr', gap: 24, alignItems: 'start' }}>
              <div>
                <div style={{ fontSize: 12.5, fontWeight: 500, color: '#374151', marginBottom: 5 }}>Photo principale</div>
                <div className="image-tile" style={{ aspectRatio: '1', gridColumn: 'span 1' }}>
                  <span className="primary-tag">PRINCIPALE</span>
                  <span>face avant</span>
                </div>
                <button className="btn btn-link" style={{ padding: '8px 0' }}>
                  <Icon name="upload" size={13}/> Gérer les médias (4)
                </button>
              </div>
              <div className="stack">
                <BasicFields state={state} set={set}/>
              </div>
            </div>

            <hr style={{ border: 0, borderTop: '1px solid var(--border)', margin: '6px 0' }}/>

            <div className="stack-sm">
              <div style={{ fontSize: 14, fontWeight: 600, color: '#0f172a' }}>Statut & organisation</div>
              <div className="row row-2">
                <Field label="Statut">
                  <select className="select" value={state.status} onChange={e => set('status', e.target.value)}>
                    <option value="published">Publié</option>
                    <option value="draft">Brouillon</option>
                    <option value="scheduled">Programmé</option>
                  </select>
                </Field>
                <Field label="Marque">
                  <select className="select">
                    <option>Lunar Pro</option>
                    <option>Atlantem</option>
                  </select>
                </Field>
              </div>
              <CategoryField/>
              <TagsField/>
              <div className="switch-row">
                <label>Visible sur la boutique<span className="sub">Apparaît dans les listings.</span></label>
                <Switch on={state.visible} onToggle={() => set('visible', !state.visible)}/>
              </div>
            </div>

            <hr style={{ border: 0, borderTop: '1px solid var(--border)', margin: '6px 0' }}/>

            <div className="stack-sm">
              <div style={{ fontSize: 14, fontWeight: 600, color: '#0f172a' }}>Tarification</div>
              <PricingFields state={state} set={set}/>
            </div>
          </div>
        )}

        {tab === 'content' && (
          <div style={{ padding: '20px 24px' }} className="stack">
            <div className="stack-sm">
              <div style={{ fontSize: 14, fontWeight: 600, color: '#0f172a' }}>Description longue</div>
              <LongDescription state={state} set={set}/>
            </div>

            <hr style={{ border: 0, borderTop: '1px solid var(--border)', margin: '6px 0' }}/>

            <div className="stack-sm">
              <div style={{ fontSize: 14, fontWeight: 600, color: '#0f172a' }}>Caractéristiques techniques</div>
              <AttributesField/>
            </div>

            <hr style={{ border: 0, borderTop: '1px solid var(--border)', margin: '6px 0' }}/>

            <div className="stack-sm">
              <div style={{ fontSize: 14, fontWeight: 600, color: '#0f172a' }}>Référencement (SEO)</div>
              <SEOFields state={state} set={set}/>
            </div>

            <hr style={{ border: 0, borderTop: '1px solid var(--border)', margin: '6px 0' }}/>

            <div className="stack-sm">
              <div style={{ fontSize: 14, fontWeight: 600, color: '#0f172a' }}>Produits liés / cross-sell</div>
              <RelatedProducts/>
            </div>
          </div>
        )}

        {tab === 'stock' && (
          <div style={{ padding: '20px 24px' }} className="stack">
            <div className="stack-sm">
              <div style={{ fontSize: 14, fontWeight: 600, color: '#0f172a' }}>Inventaire</div>
              <InventoryFields state={state} set={set}/>
            </div>

            <hr style={{ border: 0, borderTop: '1px solid var(--border)', margin: '6px 0' }}/>

            <div className="stack-sm">
              <div style={{ fontSize: 14, fontWeight: 600, color: '#0f172a' }}>Variantes</div>
              <VariantsTable/>
            </div>

            <hr style={{ border: 0, borderTop: '1px solid var(--border)', margin: '6px 0' }}/>

            <div className="stack-sm">
              <div style={{ fontSize: 14, fontWeight: 600, color: '#0f172a' }}>Expédition</div>
              <ShippingFields state={state} set={set}/>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

/* ============ LAYOUT 3 — 3 colonnes denses (power user) ============ */
const LayoutThreeCol = ({ state, set }) => (
  <div style={{
    display: 'grid',
    gridTemplateColumns: '300px minmax(0, 1fr) 320px',
    gap: 18,
    alignItems: 'start'
  }}>
    {/* LEFT — Médias + statut rapide */}
    <div className="stack" style={{ position: 'sticky', top: 70 }}>
      <div className="card">
        <div className="card-header" style={{ padding: '10px 14px' }}>
          <h3 style={{ fontSize: 13 }}>Médias</h3>
        </div>
        <div className="card-body" style={{ padding: 12 }}>
          <PhotoUploader compact/>
        </div>
      </div>

      <div className="card">
        <div className="card-header" style={{ padding: '10px 14px' }}>
          <h3 style={{ fontSize: 13 }}>Statut</h3>
          <span className={`badge ${state.status === 'published' ? 'green' : 'gray'}`}>
            <span className="dot"/>
            {state.status === 'published' ? 'Publié' : 'Brouillon'}
          </span>
        </div>
        <div className="card-body" style={{ padding: 14 }}>
          <StatusPanel state={state} set={set}/>
        </div>
      </div>

      <div className="card">
        <div className="card-header" style={{ padding: '10px 14px' }}>
          <h3 style={{ fontSize: 13 }}><Icon name="history" size={12}/> Activité</h3>
        </div>
        <div className="card-body" style={{ padding: '6px 14px 14px' }}>
          <HistoryList/>
        </div>
      </div>
    </div>

    {/* CENTER — Formulaire principal */}
    <div className="stack">
      <div className="card">
        <div className="card-header">
          <h3><Icon name="edit" size={14}/> Informations générales</h3>
        </div>
        <div className="card-body">
          <BasicFields state={state} set={set}/>
        </div>
      </div>

      <div className="card">
        <div className="card-header">
          <h3><Icon name="dollar" size={14}/> Tarification</h3>
        </div>
        <div className="card-body">
          <PricingFields state={state} set={set}/>
        </div>
      </div>

      <div className="card">
        <div className="card-header">
          <h3><Icon name="box" size={14}/> Stock & expédition</h3>
        </div>
        <div className="card-body">
          <InventoryFields state={state} set={set}/>
          <hr style={{ border: 0, borderTop: '1px solid var(--border)', margin: '4px 0' }}/>
          <ShippingFields state={state} set={set}/>
        </div>
      </div>

      <div className="card">
        <div className="card-header">
          <h3><Icon name="grid" size={14}/> Variantes</h3>
        </div>
        <div className="card-body">
          <VariantsTable/>
        </div>
      </div>

      <div className="card">
        <div className="card-header">
          <h3><Icon name="edit" size={14}/> Description longue</h3>
        </div>
        <div className="card-body">
          <LongDescription state={state} set={set}/>
        </div>
      </div>

      <div className="card">
        <div className="card-header">
          <h3><Icon name="feature" size={14}/> Caractéristiques</h3>
        </div>
        <div className="card-body">
          <AttributesField/>
        </div>
      </div>
    </div>

    {/* RIGHT — SEO + catégorisation + liés */}
    <div className="stack" style={{ position: 'sticky', top: 70 }}>
      <div className="card">
        <div className="card-header" style={{ padding: '10px 14px' }}>
          <h3 style={{ fontSize: 13 }}><Icon name="grid" size={12}/> Organisation</h3>
        </div>
        <div className="card-body" style={{ padding: 14 }}>
          <CategoryField/>
          <Field label="Marque">
            <select className="select">
              <option>Lunar Pro</option>
            </select>
          </Field>
          <TagsField/>
        </div>
      </div>

      <div className="card">
        <div className="card-header" style={{ padding: '10px 14px' }}>
          <h3 style={{ fontSize: 13 }}><Icon name="globe" size={12}/> SEO</h3>
        </div>
        <div className="card-body" style={{ padding: 14 }}>
          <SEOFields state={state} set={set}/>
        </div>
      </div>

      <div className="card">
        <div className="card-header" style={{ padding: '10px 14px' }}>
          <h3 style={{ fontSize: 13 }}><Icon name="link" size={12}/> Produits liés</h3>
        </div>
        <div className="card-body" style={{ padding: 14 }}>
          <RelatedProducts/>
        </div>
      </div>
    </div>
  </div>
);

Object.assign(window, { LayoutTwoCol, LayoutTabbed, LayoutThreeCol, SECTIONS });
