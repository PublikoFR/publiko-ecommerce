/* Weklo Ecommerce kit — shared helpers, catalog data, Icon, ProductCard */

/* ---- Lucide icon → React SVG ------------------------------------------- */
function Icon({ name, size = 20, color = 'currentColor', strokeWidth = 2, style = {} }) {
  const node = window.lucide && window.lucide.icons && window.lucide.icons[name];
  if (!node) return <span style={{ display: 'inline-block', width: size, height: size, flex: 'none', ...style }} />;
  const children = (node[2] || []).map((c, i) => React.createElement(c[0], { key: i, ...c[1] }));
  return React.createElement('svg', {
    xmlns: 'http://www.w3.org/2000/svg', width: size, height: size, viewBox: '0 0 24 24',
    fill: 'none', stroke: color, strokeWidth, strokeLinecap: 'round', strokeLinejoin: 'round',
    style: { display: 'block', flex: 'none', ...style },
  }, children);
}

/* ---- Formatting -------------------------------------------------------- */
const euro = (n) => n.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
const ttc = (ht) => ht * 1.2;

/* ---- Catalog ----------------------------------------------------------- */
const CATEGORIES = [
  { id: 'fenetres', label: 'Fenêtres', icon: 'Grid2x2', blurb: 'PVC, alu & bois' },
  { id: 'portes', label: 'Portes', icon: 'DoorClosed', blurb: "Entrée & service" },
  { id: 'portails', label: 'Portails', icon: 'Fence', blurb: 'Battant & coulissant' },
  { id: 'volets', label: 'Volets', icon: 'Blinds', blurb: 'Roulants & battants' },
  { id: 'motorisation', label: 'Motorisation', icon: 'Cog', blurb: 'Moteurs & automatismes' },
  { id: 'garage', label: 'Portes de garage', icon: 'Warehouse', blurb: 'Sectionnelle & enroulable' },
];

const CAT_ICON = Object.fromEntries(CATEGORIES.map((c) => [c.id, c.icon]));

const PRODUCTS = [
  { id: 'p1', cat: 'fenetres', name: 'Fenêtre PVC 2 vantaux blanc', ref: 'FEN-PVC-1240', brand: 'Weklo Line', price: 189.9, stock: 'in', rating: 4.6, badge: 'Best-seller', material: 'PVC' },
  { id: 'p2', cat: 'fenetres', name: 'Fenêtre alu oscillo-battant', ref: 'FEN-ALU-0810', brand: 'Technal', price: 349.0, stock: 'in', rating: 4.8, material: 'Aluminium' },
  { id: 'p3', cat: 'fenetres', name: 'Fenêtre bois chêne 1 vantail', ref: 'FEN-BOI-0612', brand: 'Weklo Line', price: 279.0, stock: 'low', rating: 4.4, material: 'Bois' },
  { id: 'p4', cat: 'portes', name: "Porte d'entrée alu « Lyon »", ref: 'POR-ALU-LYON', brand: 'Technal', price: 1290.0, stock: 'in', rating: 4.9, badge: 'Nouveau', material: 'Aluminium' },
  { id: 'p5', cat: 'portes', name: 'Porte de service PVC blanche', ref: 'POR-PVC-SERV', brand: 'Weklo Line', price: 459.0, stock: 'in', rating: 4.3, material: 'PVC' },
  { id: 'p6', cat: 'portails', name: 'Portail battant alu 3,5 m', ref: 'PTL-BAT-350', brand: 'Cadiou', price: 1490.0, stock: 'in', rating: 4.7, material: 'Aluminium' },
  { id: 'p7', cat: 'portails', name: 'Portail coulissant alu 4 m', ref: 'PTL-COU-400', brand: 'Cadiou', price: 1890.0, stock: 'order', rating: 4.8, badge: 'Best-seller', material: 'Aluminium' },
  { id: 'p8', cat: 'volets', name: 'Volet roulant solaire', ref: 'VLT-ROU-SOL', brand: 'Bubendorff', price: 539.0, stock: 'in', rating: 4.9, badge: 'Éco', material: 'Aluminium' },
  { id: 'p9', cat: 'volets', name: 'Volet battant alu 2 vantaux', ref: 'VLT-BAT-ALU', brand: 'Weklo Line', price: 219.0, stock: 'in', rating: 4.2, material: 'Aluminium' },
  { id: 'p10', cat: 'motorisation', name: 'Motorisation portail battant 24 V', ref: 'MOT-BAT-24V', brand: 'Somfy', price: 379.0, stock: 'in', rating: 4.6, material: 'Kit' },
  { id: 'p11', cat: 'motorisation', name: 'Motorisation coulissant 600 kg', ref: 'MOT-COU-600', brand: 'Nice', price: 429.0, stock: 'low', rating: 4.5, material: 'Kit' },
  { id: 'p12', cat: 'motorisation', name: 'Moteur volet roulant filaire', ref: 'MOT-VR-FIL', brand: 'Somfy', price: 79.9, stock: 'in', rating: 4.7, badge: 'Best-seller', material: 'Moteur' },
  { id: 'p13', cat: 'garage', name: 'Porte de garage sectionnelle 2,4 m', ref: 'GAR-SEC-2400', brand: 'Hörmann', price: 899.0, stock: 'in', rating: 4.8, material: 'Acier' },
  { id: 'p14', cat: 'garage', name: 'Porte de garage enroulable', ref: 'GAR-ENR-2500', brand: 'Hörmann', price: 1150.0, stock: 'order', rating: 4.6, material: 'Aluminium' },
];

const STOCK = {
  in: { label: 'En stock', tone: 'success' },
  low: { label: 'Stock faible', tone: 'warning' },
  order: { label: 'Sur commande', tone: 'neutral' },
};

/* ---- Product image placeholder (branded tile, lucide product icon) ----- */
function ProductViz({ cat, size = 'md', style = {} }) {
  const pads = { sm: 18, md: 30, lg: 54 };
  const icon = CAT_ICON[cat] || 'Package';
  const iconSize = { sm: 46, md: 88, lg: 150 }[size] || 88;
  return (
    <div style={{
      position: 'relative', width: '100%', height: '100%', minHeight: 0,
      display: 'flex', alignItems: 'center', justifyContent: 'center',
      background: 'radial-gradient(120% 120% at 30% 20%, var(--neutral-0) 0%, var(--forest-50) 90%)',
      padding: pads[size], overflow: 'hidden', ...style,
    }}>
      <Icon name={icon} size={iconSize} color="var(--forest-300)" strokeWidth={1.4} />
      <span style={{ position: 'absolute', right: 12, bottom: 10, fontFamily: 'var(--font-mono)', fontSize: 10, color: 'var(--neutral-400)', letterSpacing: '0.04em' }}>weklo</span>
    </div>
  );
}

/* ---- Star rating ------------------------------------------------------- */
function Stars({ value = 0, size = 13 }) {
  return (
    <span style={{ display: 'inline-flex', gap: 1, alignItems: 'center' }}>
      {[0, 1, 2, 3, 4].map((i) => (
        <Icon key={i} name="Star" size={size}
          color={i < Math.round(value) ? 'var(--lime-600)' : 'var(--neutral-300)'}
          strokeWidth={i < Math.round(value) ? 0 : 1.6}
          style={{ fill: i < Math.round(value) ? 'var(--lime-500)' : 'none' }} />
      ))}
    </span>
  );
}

/* ---- Product card ------------------------------------------------------ */
function ProductCard({ product, onOpen, onAdd, view = 'grid' }) {
  const { Badge, Button } = window.WekloDesignSystem_23c47b;
  const stock = STOCK[product.stock];
  const row = view === 'list';
  return (
    <div
      onClick={() => onOpen && onOpen(product)}
      style={{
        display: 'flex', flexDirection: row ? 'row' : 'column',
        background: 'var(--surface-card)', border: '1px solid var(--border-subtle)',
        borderRadius: 'var(--radius-xl)', overflow: 'hidden', cursor: 'pointer',
        transition: 'transform var(--duration-base) var(--ease-standard), box-shadow var(--duration-base) var(--ease-standard)',
      }}
      onMouseEnter={(e) => { e.currentTarget.style.transform = 'translateY(-3px)'; e.currentTarget.style.boxShadow = 'var(--shadow-lg)'; }}
      onMouseLeave={(e) => { e.currentTarget.style.transform = 'translateY(0)'; e.currentTarget.style.boxShadow = 'none'; }}
    >
      <div style={{ position: 'relative', flex: row ? '0 0 200px' : 'none', aspectRatio: row ? 'auto' : '4 / 3', minHeight: row ? 150 : 0 }}>
        <ProductViz cat={product.cat} />
        {product.badge && (
          <span style={{ position: 'absolute', top: 12, left: 12 }}>
            <Badge tone="lime" variant="solid" size="sm">{product.badge}</Badge>
          </span>
        )}
      </div>
      <div style={{ padding: 'var(--space-4)', display: 'flex', flexDirection: 'column', gap: 6, flex: 1 }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <span style={{ fontFamily: 'var(--font-mono)', fontSize: 11, color: 'var(--text-muted)' }}>{product.brand}</span>
          <Badge tone={stock.tone} size="sm" dot>{stock.label}</Badge>
        </div>
        <div style={{ fontFamily: 'var(--font-sans)', fontWeight: 600, fontSize: 'var(--text-base)', color: 'var(--text-primary)', lineHeight: 1.3 }}>{product.name}</div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
          <Stars value={product.rating} />
          <span style={{ fontSize: 'var(--text-xs)', color: 'var(--text-muted)' }}>{product.rating.toFixed(1)}</span>
        </div>
        <div style={{ fontFamily: 'var(--font-mono)', fontSize: 11, color: 'var(--text-muted)' }}>Réf. {product.ref}</div>
        <div style={{ marginTop: 'auto', display: 'flex', alignItems: 'flex-end', justifyContent: 'space-between', gap: 10, paddingTop: 8 }}>
          <div style={{ lineHeight: 1.1 }}>
            <div style={{ fontFamily: 'var(--font-display)', fontWeight: 700, fontSize: 'var(--text-2xl)', color: 'var(--forest-600)' }}>{euro(product.price)}</div>
            <div style={{ fontSize: 10, color: 'var(--text-muted)' }}>HT · {euro(ttc(product.price))} TTC</div>
          </div>
          <Button variant="primary" size="sm" iconLeft={<Icon name="Plus" size={16} />}
            onClick={(e) => { e.stopPropagation(); onAdd && onAdd(product); }}>Ajouter</Button>
        </div>
      </div>
    </div>
  );
}

/* ---- Habillage decoration (concentric-ring motif, tint-able mask) ------ */
function Decor({ corner = 'tr', tone = 'on-dark', size = 440, opacity, color, style = {} }) {
  const anchors = {
    tl: { top: 0, left: 0, transform: 'translate(-42%,-42%)' },
    tr: { top: 0, right: 0, transform: 'translate(42%,-42%)' },
    bl: { bottom: 0, left: 0, transform: 'translate(-42%,42%)' },
    br: { bottom: 0, right: 0, transform: 'translate(42%,42%)' },
    c:  { top: '50%', left: '50%', transform: 'translate(-50%,-50%)' },
  };
  const tones = {
    'on-dark':  { color: 'var(--lime-400)', opacity: 0.12 },
    'on-light': { color: 'var(--forest-600)', opacity: 0.05 },
    'accent':   { color: 'var(--forest-600)', opacity: 0.14 },
  };
  const t = tones[tone] || tones['on-dark'];
  return (
    <div aria-hidden="true" style={{
      position: 'absolute', width: size, height: size,
      backgroundColor: color || t.color,
      WebkitMaskImage: 'url(../../assets/habillage.svg)', maskImage: 'url(../../assets/habillage.svg)',
      WebkitMaskSize: 'contain', maskSize: 'contain',
      WebkitMaskRepeat: 'no-repeat', maskRepeat: 'no-repeat',
      WebkitMaskPosition: 'center', maskPosition: 'center',
      opacity: opacity != null ? opacity : t.opacity,
      pointerEvents: 'none', zIndex: 0, ...anchors[corner], ...style,
    }} />
  );
}

Object.assign(window, { Icon, euro, ttc, CATEGORIES, CAT_ICON, PRODUCTS, STOCK, ProductViz, Stars, ProductCard, Decor });
