/* Form section components reused across layouts */

const PhotoUploader = ({ compact = false }) => {
  const [images, setImages] = React.useState([
    { id: 1, label: 'face avant', primary: true },
    { id: 2, label: 'détail cadre' },
    { id: 3, label: '3/4 ouvert' },
    { id: 4, label: 'vue de nuit' },
  ]);
  return (
    <div className="stack-sm">
      <div className="image-grid" style={{ gridTemplateColumns: compact ? 'repeat(4, 1fr)' : 'repeat(auto-fill, minmax(100px, 1fr))' }}>
        {images.map((im, i) => (
          <div key={im.id} className={`image-tile ${im.primary ? 'primary' : ''}`}>
            {im.primary && <span className="primary-tag">PRINCIPALE</span>}
            <button className="remove" aria-label="Supprimer"
              onClick={() => setImages(imgs => imgs.filter(x => x.id !== im.id))}>
              <Icon name="x" size={12}/>
            </button>
            <span style={{ padding: 4 }}>{im.label}</span>
          </div>
        ))}
        <div className="image-tile add"
          onClick={() => setImages(imgs => [...imgs, { id: Date.now(), label: 'nouvelle image' }])}
          title="Ajouter une image">
          <Icon name="plus" size={20}/>
        </div>
      </div>
      <div className="hstack" style={{ fontSize: 12, color: 'var(--text-muted)' }}>
        <Icon name="info" size={13}/>
        <span>Glissez-déposez pour réorganiser. La première image est utilisée comme vignette.</span>
      </div>
    </div>
  );
};

const BasicFields = ({ state, set }) => (
  <div className="stack">
    <Field label="Titre du produit" required>
      <input className="input" value={state.title} onChange={e => set('title', e.target.value)}/>
    </Field>
    <div className="row row-2">
      <Field label="SKU / Référence interne" help="Identifiant unique, utilisé en interne.">
        <div className="input-group">
          <span className="addon"><Icon name="hash" size={13}/></span>
          <input value={state.sku} onChange={e => set('sku', e.target.value)} />
        </div>
      </Field>
      <Field label="Code-barres (EAN/UPC)">
        <input className="input" value={state.ean} onChange={e => set('ean', e.target.value)}/>
      </Field>
    </div>
    <Field label="Description courte" help="Affichée sur la fiche produit, en haut. 1-2 phrases.">
      <textarea className="textarea" rows="3"
        value={state.shortDesc}
        onChange={e => set('shortDesc', e.target.value)}/>
    </Field>
  </div>
);

const PricingFields = ({ state, set, showTiers = true }) => {
  const margin = state.price && state.cost ?
    (((parseFloat(state.price) - parseFloat(state.cost)) / parseFloat(state.price)) * 100).toFixed(1) : '—';
  return (
    <div className="stack">
      <div className="row row-3">
        <Field label="Prix de vente" required help="Prix public TTC.">
          <div className="input-group">
            <input type="text" value={state.price} onChange={e => set('price', e.target.value)}/>
            <span className="addon suffix">€</span>
          </div>
        </Field>
        <Field label="Prix de comparaison" help="Prix barré (PDSF).">
          <div className="input-group">
            <input type="text" value={state.comparePrice} onChange={e => set('comparePrice', e.target.value)}/>
            <span className="addon suffix">€</span>
          </div>
        </Field>
        <Field label="Prix d'achat" help="Coût unitaire, non public.">
          <div className="input-group">
            <input type="text" value={state.cost} onChange={e => set('cost', e.target.value)}/>
            <span className="addon suffix">€</span>
          </div>
        </Field>
      </div>
      <div className="row row-2">
        <Field label="Classe de taxe" required>
          <select className="select" value={state.tax} onChange={e => set('tax', e.target.value)}>
            <option>TVA 20%</option>
            <option>TVA 10%</option>
            <option>TVA 5,5%</option>
            <option>TVA 0% (export)</option>
          </select>
        </Field>
        <Field label="Marge estimée">
          <div className="input-group">
            <input readOnly value={margin} style={{ color: 'var(--green)' }}/>
            <span className="addon suffix">%</span>
          </div>
        </Field>
      </div>
      {showTiers && (
        <div className="card" style={{ boxShadow: 'none' }}>
          <div className="card-header" style={{ padding: '10px 14px' }}>
            <h3 style={{ fontSize: 13 }}>Tarification B2B / dégressive</h3>
            <button className="btn btn-link"><Icon name="plus" size={13}/>Ajouter un palier</button>
          </div>
          <div className="card-body" style={{ padding: 0 }}>
            <table className="table">
              <thead>
                <tr>
                  <th>Groupe client</th>
                  <th>À partir de</th>
                  <th className="num">Prix unitaire</th>
                  <th className="num">Remise</th>
                  <th style={{ width: 40 }}></th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>Installateurs</td>
                  <td>1 unité</td>
                  <td className="num mono">836,84 €</td>
                  <td className="num"><span className="badge green">−15%</span></td>
                  <td><button className="btn btn-ghost" style={{ padding: 4 }}><Icon name="more" size={14}/></button></td>
                </tr>
                <tr>
                  <td>Installateurs</td>
                  <td>10 unités</td>
                  <td className="num mono">788,64 €</td>
                  <td className="num"><span className="badge green">−20%</span></td>
                  <td><button className="btn btn-ghost" style={{ padding: 4 }}><Icon name="more" size={14}/></button></td>
                </tr>
                <tr>
                  <td>Revendeurs</td>
                  <td>50 unités</td>
                  <td className="num mono">738,39 €</td>
                  <td className="num"><span className="badge green">−25%</span></td>
                  <td><button className="btn btn-ghost" style={{ padding: 4 }}><Icon name="more" size={14}/></button></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
};

const InventoryFields = ({ state, set }) => (
  <div className="stack">
    <div className="switch-row">
      <label>
        Suivre le stock de ce produit
        <span className="sub">Désactivez pour les services, dropshipping ou produits illimités.</span>
      </label>
      <Switch on={state.trackStock} onToggle={() => set('trackStock', !state.trackStock)}/>
    </div>
    <div className="row row-3">
      <Field label="Stock actuel" help="Mise à jour automatique à chaque commande.">
        <div className="input-group">
          <input type="number" value={state.stock} onChange={e => set('stock', e.target.value)}/>
          <span className="addon suffix">u.</span>
        </div>
      </Field>
      <Field label="Seuil d'alerte" help="Notification si stock ≤ seuil.">
        <input className="input" type="number" value={state.lowStock} onChange={e => set('lowStock', e.target.value)}/>
      </Field>
      <Field label="Stock de sécurité" help="Non vendable (réserve).">
        <input className="input" type="number" value={state.safetyStock} onChange={e => set('safetyStock', e.target.value)}/>
      </Field>
    </div>
    <div className="switch-row">
      <label>
        Autoriser les commandes en rupture
        <span className="sub">Backorder — livraison différée.</span>
      </label>
      <Switch on={state.allowBackorder} onToggle={() => set('allowBackorder', !state.allowBackorder)}/>
    </div>
    <Field label="Délai de réapprovisionnement" help="Estimation affichée aux clients en cas de rupture.">
      <select className="select">
        <option>2 à 3 jours ouvrés</option>
        <option>5 à 7 jours ouvrés</option>
        <option>2 semaines</option>
        <option>Sur commande</option>
      </select>
    </Field>
  </div>
);

const ShippingFields = ({ state, set }) => (
  <div className="stack">
    <div className="row row-2">
      <Field label="Poids">
        <div className="input-group">
          <input type="text" value={state.weight} onChange={e => set('weight', e.target.value)}/>
          <span className="addon suffix">kg</span>
        </div>
      </Field>
      <Field label="Classe d'expédition">
        <select className="select">
          <option>Standard</option>
          <option>Encombrant</option>
          <option>Palette</option>
        </select>
      </Field>
    </div>
    <Field label="Dimensions (L × l × H)">
      <div className="hstack-sm" style={{ gap: 8 }}>
        <div className="input-group"><input type="text" defaultValue="215"/><span className="addon suffix">cm</span></div>
        <div className="input-group"><input type="text" defaultValue="8"/><span className="addon suffix">cm</span></div>
        <div className="input-group"><input type="text" defaultValue="95"/><span className="addon suffix">cm</span></div>
      </div>
    </Field>
  </div>
);

const CategoryField = ({ compact = false }) => {
  const [cats, setCats] = React.useState(['Menuiserie extérieure', 'Portails aluminium', 'Coulissants']);
  return (
    <Field label="Catégories" help="Le produit apparaîtra dans toutes les catégories sélectionnées.">
      <div style={{
        border: '1px solid var(--border-strong)',
        borderRadius: 'var(--radius)',
        padding: '6px 8px',
        background: '#fff',
        display: 'flex', flexWrap: 'wrap', gap: 5, alignItems: 'center',
        minHeight: 38
      }}>
        {cats.map((c, i) => (
          <span key={i} className="chip blue">
            {c}
            <button onClick={() => setCats(xs => xs.filter((_, j) => j !== i))}><Icon name="x" size={11}/></button>
          </span>
        ))}
        <input placeholder="Rechercher ou créer..." style={{
          border: 0, outline: 'none', background: 'transparent',
          flex: 1, minWidth: 140, padding: '4px 6px', fontSize: 13
        }}/>
      </div>
    </Field>
  );
};

const TagsField = () => {
  const [tags, setTags] = React.useState(['RAL 7016', 'Anthracite', 'Motorisable', 'Sur-mesure']);
  return (
    <Field label="Tags" help="Libellés libres pour le filtrage boutique.">
      <div style={{
        border: '1px solid var(--border-strong)',
        borderRadius: 'var(--radius)',
        padding: '6px 8px',
        background: '#fff',
        display: 'flex', flexWrap: 'wrap', gap: 5, alignItems: 'center',
        minHeight: 38
      }}>
        {tags.map((t, i) => (
          <span key={i} className="chip">
            {t}
            <button onClick={() => setTags(xs => xs.filter((_, j) => j !== i))}><Icon name="x" size={11}/></button>
          </span>
        ))}
        <input placeholder="Ajouter un tag..." style={{
          border: 0, outline: 'none', background: 'transparent',
          flex: 1, minWidth: 120, padding: '4px 6px', fontSize: 13
        }}/>
      </div>
    </Field>
  );
};

const AttributesField = () => {
  const [rows, setRows] = React.useState([
    { k: 'Matériau', v: 'Aluminium laqué' },
    { k: 'Finition', v: 'RAL 7016 Gris anthracite structuré' },
    { k: 'Vitrage', v: 'Double vitrage 4/16/4 Argon' },
    { k: 'Ouverture', v: 'Coulissant 2 vantaux' },
    { k: 'Uw', v: '1,4 W/m².K' },
  ]);
  return (
    <div className="stack-sm">
      <div style={{ border: '1px solid var(--border)', borderRadius: 'var(--radius)', overflow: 'hidden' }}>
        {rows.map((r, i) => (
          <div key={i} style={{
            display: 'grid', gridTemplateColumns: '1fr 2fr 32px',
            borderBottom: i < rows.length - 1 ? '1px solid var(--border)' : 0,
            background: '#fff'
          }}>
            <input style={{ border: 0, padding: '8px 12px', fontWeight: 500, background: '#fafbfc', borderRight: '1px solid var(--border)', outline: 'none' }}
              value={r.k} onChange={e => setRows(xs => xs.map((x, j) => j === i ? { ...x, k: e.target.value } : x))}/>
            <input style={{ border: 0, padding: '8px 12px', outline: 'none' }}
              value={r.v} onChange={e => setRows(xs => xs.map((x, j) => j === i ? { ...x, v: e.target.value } : x))}/>
            <button className="btn btn-ghost" style={{ padding: 4, justifyContent: 'center' }}
              onClick={() => setRows(xs => xs.filter((_, j) => j !== i))}>
              <Icon name="x" size={13}/>
            </button>
          </div>
        ))}
      </div>
      <button className="btn btn-link" style={{ alignSelf: 'flex-start' }}
        onClick={() => setRows(xs => [...xs, { k: '', v: '' }])}>
        <Icon name="plus" size={13}/>Ajouter une caractéristique
      </button>
    </div>
  );
};

const SEOFields = ({ state, set }) => {
  const titleLen = state.seoTitle.length;
  const descLen = state.seoDesc.length;
  const titleClass = titleLen > 60 ? 'over' : titleLen > 55 ? 'warn' : '';
  const descClass = descLen > 160 ? 'over' : descLen > 150 ? 'warn' : '';
  return (
    <div className="stack">
      <div className="card" style={{ background: '#fafbfc', boxShadow: 'none', border: '1px solid var(--border)' }}>
        <div className="card-body" style={{ padding: 14 }}>
          <div style={{ fontSize: 11, color: 'var(--text-muted)', marginBottom: 8, display: 'flex', alignItems: 'center', gap: 6 }}>
            <Icon name="eye" size={12}/> APERÇU GOOGLE
          </div>
          <div className="seo-preview">
            <div className="url">
              <span className="site">mon-site.com</span>
              <span>› portails › </span>
              <span>{state.slug}</span>
            </div>
            <div className="title">{state.seoTitle || state.title}</div>
            <div className="desc">{state.seoDesc || 'Aucune méta-description définie.'}</div>
          </div>
        </div>
      </div>
      <Field label="Titre SEO (balise <title>)"
        help="Ce qui apparaît dans l'onglet du navigateur et en titre Google."
        charCounter={<span className={`char-counter ${titleClass}`}>{titleLen} / 60</span>}>
        <input className="input" value={state.seoTitle} onChange={e => set('seoTitle', e.target.value)}/>
      </Field>
      <Field label="Méta-description"
        help="160 caractères max pour un affichage optimal."
        charCounter={<span className={`char-counter ${descClass}`}>{descLen} / 160</span>}>
        <textarea className="textarea" rows="3" value={state.seoDesc} onChange={e => set('seoDesc', e.target.value)}/>
      </Field>
      <Field label="URL de la page" help="Slug utilisé dans l'URL publique.">
        <div className="input-group">
          <span className="addon">mon-site.com/portails/</span>
          <input value={state.slug} onChange={e => set('slug', e.target.value)}/>
        </div>
      </Field>
      <div className="row row-2">
        <Field label="Balise canonical" help="Laissez vide pour auto-génération.">
          <input className="input" placeholder="Auto" value={state.canonical} onChange={e => set('canonical', e.target.value)}/>
        </Field>
        <Field label="Indexation">
          <select className="select">
            <option>index, follow (par défaut)</option>
            <option>noindex, follow</option>
            <option>noindex, nofollow</option>
          </select>
        </Field>
      </div>
    </div>
  );
};

const LongDescription = ({ state, set }) => (
  <div className="wysiwyg">
    <div className="wysiwyg-toolbar">
      <select style={{
        border: 0, background: 'transparent', padding: '3px 6px',
        fontSize: 12.5, color: '#374151'
      }}>
        <option>Paragraphe</option>
        <option>Titre H2</option>
        <option>Titre H3</option>
      </select>
      <span className="sep"/>
      <button title="Gras"><Icon name="bold" size={13}/></button>
      <button title="Italique"><Icon name="italic" size={13}/></button>
      <button title="Souligné"><Icon name="underline" size={13}/></button>
      <span className="sep"/>
      <button title="Liste"><Icon name="list" size={13}/></button>
      <button title="Lien"><Icon name="link" size={13}/></button>
      <button title="Image"><Icon name="image" size={13}/></button>
      <span className="grow" style={{ flex: 1 }}/>
      <button title="Générer avec IA" style={{ color: 'var(--blue)' }}>
        <Icon name="sparkle" size={13}/> IA
      </button>
    </div>
    <div className="wysiwyg-content" contentEditable suppressContentEditableWarning
      dangerouslySetInnerHTML={{ __html: state.longDesc }}/>
  </div>
);

const VariantsTable = () => {
  const [variants, setVariants] = React.useState([
    { name: 'Gris anthracite / 2,40m', sku: 'PCA-7016-240', price: '984,52', stock: 12, active: true },
    { name: 'Gris anthracite / 3,00m', sku: 'PCA-7016-300', price: '1 184,00', stock: 7, active: true },
    { name: 'Noir / 2,40m',            sku: 'PCA-9005-240', price: '984,52', stock: 0, active: true },
    { name: 'Noir / 3,00m',            sku: 'PCA-9005-300', price: '1 184,00', stock: 3, active: false },
  ]);
  return (
    <div className="stack-sm">
      <div className="variant-matrix">
        <div className="variant-matrix-head">
          <div>Variante</div>
          <div>SKU</div>
          <div>Prix (€)</div>
          <div>Stock</div>
          <div>Statut</div>
          <div></div>
        </div>
        {variants.map((v, i) => (
          <div key={i} className="variant-row">
            <div className="variant-name">
              <span className="swatch"/>
              <input value={v.name} onChange={e => setVariants(xs => xs.map((x, j) => j === i ? { ...x, name: e.target.value } : x))}/>
            </div>
            <div><input className="mono" value={v.sku} onChange={e => setVariants(xs => xs.map((x, j) => j === i ? { ...x, sku: e.target.value } : x))}/></div>
            <div><input value={v.price} onChange={e => setVariants(xs => xs.map((x, j) => j === i ? { ...x, price: e.target.value } : x))}/></div>
            <div><input type="number" value={v.stock} onChange={e => setVariants(xs => xs.map((x, j) => j === i ? { ...x, stock: e.target.value } : x))}/></div>
            <div style={{ padding: '8px 12px' }}>
              <Switch on={v.active} onToggle={() => setVariants(xs => xs.map((x, j) => j === i ? { ...x, active: !x.active } : x))}/>
            </div>
            <div style={{ padding: '8px 4px' }}>
              <button className="btn btn-ghost" style={{ padding: 4 }}><Icon name="more" size={14}/></button>
            </div>
          </div>
        ))}
      </div>
      <div className="hstack" style={{ justifyContent: 'space-between' }}>
        <button className="btn btn-secondary"><Icon name="plus" size={13}/>Ajouter une variante</button>
        <button className="btn btn-link">Générer à partir des options →</button>
      </div>
    </div>
  );
};

const RelatedProducts = () => {
  const items = [
    { name: 'Poignée encastrée inox brossé', sku: 'POI-INX-001' },
    { name: 'Kit motorisation coulissant 300W', sku: 'MOT-COU-300' },
    { name: 'Seuil aluminium PMR encastrable', sku: 'SEU-ALU-PMR' },
  ];
  return (
    <div className="stack-sm">
      <div className="stack-sm">
        {items.map((it, i) => (
          <div key={i} className="related-product">
            <div className="thumb"/>
            <div className="info">
              <div className="name">{it.name}</div>
              <div className="sku">{it.sku}</div>
            </div>
            <button className="btn btn-ghost" style={{ padding: 6 }}><Icon name="x" size={13}/></button>
          </div>
        ))}
      </div>
      <button className="btn btn-secondary" style={{ alignSelf: 'flex-start' }}>
        <Icon name="plus" size={13}/>Lier un produit
      </button>
    </div>
  );
};

const HistoryList = () => {
  const items = [
    { user: 'Olivier M.', what: 'Prix mis à jour : 972,00 € → 984,52 €', when: 'il y a 12 min' },
    { user: 'Sophie L.', what: '3 images ajoutées', when: 'hier, 17:42' },
    { user: 'Olivier M.', what: 'Méta-description modifiée', when: 'hier, 14:15' },
    { user: 'Système',   what: 'Synchronisation stock depuis ERP (+8 unités)', when: '15 avr. 09:00' },
    { user: 'Olivier M.', what: 'Produit créé', when: '12 avr. 11:28' },
  ];
  return (
    <div>
      {items.map((it, i) => (
        <div key={i} className="history-item">
          <span className="dot"/>
          <div style={{ flex: 1 }}>
            <div>{it.what}</div>
            <div className="meta">{it.user} · {it.when}</div>
          </div>
          <button className="btn btn-link" style={{ padding: '2px 4px', fontSize: 12 }}>Détails</button>
        </div>
      ))}
    </div>
  );
};

const StatusPanel = ({ state, set }) => (
  <div className="stack">
    <Field label="Statut">
      <select className="select" value={state.status} onChange={e => set('status', e.target.value)}>
        <option value="published">Publié</option>
        <option value="draft">Brouillon</option>
        <option value="scheduled">Programmé</option>
        <option value="archived">Archivé</option>
      </select>
    </Field>
    <div className="switch-row">
      <label>
        Visible sur la boutique
        <span className="sub">Apparaît dans les listings et recherches.</span>
      </label>
      <Switch on={state.visible} onToggle={() => set('visible', !state.visible)}/>
    </div>
    <div className="switch-row">
      <label>
        Mis en avant
        <span className="sub">Poussé en page d'accueil.</span>
      </label>
      <Switch on={state.featured} onToggle={() => set('featured', !state.featured)}/>
    </div>
    <Field label="Date de publication">
      <input className="input" type="datetime-local" value={state.publishAt} onChange={e => set('publishAt', e.target.value)}/>
    </Field>
  </div>
);

Object.assign(window, {
  PhotoUploader, BasicFields, PricingFields, InventoryFields, ShippingFields,
  CategoryField, TagsField, AttributesField, SEOFields, LongDescription,
  VariantsTable, RelatedProducts, HistoryList, StatusPanel
});
