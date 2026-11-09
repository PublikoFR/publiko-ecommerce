"""Générateur du schéma isométrique « À chaque installation, sa solution » (FEAT-009).

Usage : python3 scripts/home-solutions-scene.py /tmp/scene.svgfrag
  - écrit le fragment SVG (à recopier dans
    packages/pko/storefront-cms/resources/views/livewire/partials/home-solutions-scene.blade.php,
    une balise par ligne, sous le commentaire d'en-tête) ;
  - affiche sur stderr la bounding box et les ancres des pastilles (x, y) à reporter
    dans Pko\StorefrontCms\Support\HomeSolutionZones::all().

Toute la scène est décrite en coordonnées 3D (1 unité ≈ 1 m) et projetée en
isométrie stricte à 30° : la maison et le bâtiment partagent donc la même
perspective. Couleurs = classes Tailwind du Design System uniquement.
"""
import math, json, sys
K = 18.0
C = K * math.cos(math.radians(30))
S = K * math.sin(math.radians(30))
OX, OY = 19 * C + 14, 60.0
BB = [1e9, 1e9, -1e9, -1e9]

def P(x, y, z=0.0):
    a, b = OX + (x - y) * C, OY + (x + y) * S - z * K
    BB[0] = min(BB[0], a); BB[1] = min(BB[1], b); BB[2] = max(BB[2], a); BB[3] = max(BB[3], b)
    return (a, b)

def pts(*p3):
    return ' '.join(f'{a:.1f},{b:.1f}' for a, b in (P(*q) for q in p3))

out = []
def g(cls, body, extra=''):
    out.append(f'<g class="{cls}"{extra}>' + ''.join(body) + '</g>')

def poly(*p3, cls=None):
    c = f' class="{cls}"' if cls else ''
    return f'<polygon{c} points="{pts(*p3)}"/>'

def line(a, b, cls=None):
    c = f' class="{cls}"' if cls else ''
    (x1, y1), (x2, y2) = P(*a), P(*b)
    return f'<line{c} x1="{x1:.1f}" y1="{y1:.1f}" x2="{x2:.1f}" y2="{y2:.1f}"/>'

# Faces of an axis aligned box (only the three visible ones: top, front-left y=y1, front-right x=x1)
def box(x0, x1, y0, y1, z0, z1, top=None, left=None, right=None):
    r = []
    r.append(poly((x0, y1, z0), (x1, y1, z0), (x1, y1, z1), (x0, y1, z1), cls=left))
    r.append(poly((x1, y0, z0), (x1, y1, z0), (x1, y1, z1), (x1, y0, z1), cls=right))
    r.append(poly((x0, y0, z1), (x1, y0, z1), (x1, y1, z1), (x0, y1, z1), cls=top))
    return r

# ---------------------------------------------------------------- ground
g('fill-primary-50 stroke-primary-200', [poly((0, 0), (33, 0), (33, 16.2), (0, 16.2))])
# road strip in front of the fence
g('fill-neutral-100 stroke-neutral-300', [poly((0, 16.8), (33, 16.8), (33, 19), (0, 19))])
g('stroke-neutral-300', [line((2 + i * 3.2, 17.9), (3.6 + i * 3.2, 17.9)) for i in range(10)], ' stroke-dasharray="0" stroke-linecap="round"')
# driveway (house) + yard (building)
g('fill-neutral-50 stroke-none', [
    poly((6.4, 10.0), (10.6, 10.0), (10.6, 15.2), (6.4, 15.2)),
    poly((13.6, 11.0), (31.4, 11.0), (31.4, 15.2), (13.6, 15.2)),
])

# ---------------------------------------------------------------- back fence (x = 0 side), drawn first
back = []
for i in range(0, 16):
    y = i * 1.0
    back.append(line((0, y, 0), (0, y, 1.2)))
back.append(line((0, 0, 1.2), (0, 16.2, 1.2)))
back.append(line((0, 0, 0.2), (0, 16.2, 0.2)))
g('stroke-primary-300', back)

# ---------------------------------------------------------------- house
hx0, hx1, hy0, hy1, hz = 2.0, 11.0, 3.0, 10.0, 3.0
ridge_y, ridge_z, ov = 6.5, 5.6, 0.35
house = []
# back roof slope (seen from above, behind the ridge)
house.append(poly((hx0 - ov, hy0 - ov, hz - 0.25), (hx1 + ov, hy0 - ov, hz - 0.25), (hx1 + ov, ridge_y, ridge_z), (hx0 - ov, ridge_y, ridge_z), cls='fill-primary-200'))
# right gable wall (x = hx1)
house.append(poly((hx1, hy0, 0), (hx1, hy1, 0), (hx1, hy1, hz), (hx1, ridge_y, ridge_z), (hx1, hy0, hz), cls='fill-neutral-100'))
# front facade (y = hy1)
house.append(poly((hx0, hy1, 0), (hx1, hy1, 0), (hx1, hy1, hz), (hx0, hy1, hz), cls='fill-white'))
# front roof slope
house.append(poly((hx0 - ov, ridge_y, ridge_z), (hx1 + ov, ridge_y, ridge_z), (hx1 + ov, hy1 + ov, hz - 0.25), (hx0 - ov, hy1 + ov, hz - 0.25), cls='fill-primary-100'))
# roof tiles lines on front slope
for t in [0.25, 0.5, 0.75]:
    yy = ridge_y + (hy1 + ov - ridge_y) * t
    zz = ridge_z + (hz - 0.25 - ridge_z) * t
    house.append(line((hx0 - ov, yy, zz), (hx1 + ov, yy, zz), cls='stroke-primary-300'))
# chimney
house += box(4.0, 4.7, 4.4, 5.1, 4.5, 6.4, top='fill-primary-200', left='fill-neutral-100', right='fill-neutral-200')
g('fill-white stroke-primary-600', house)

def wall_window_front(x0, x1, z0, z1, y, shutter_drop):
    """Window on a y = const facade with a roller shutter partly lowered."""
    r = []
    r.append(poly((x0, y, z0), (x1, y, z0), (x1, y, z1), (x0, y, z1), cls='fill-primary-100'))
    r.append(line(((x0 + x1) / 2, y, z0), ((x0 + x1) / 2, y, z1), cls='stroke-primary-400'))
    zs = z1 - (z1 - z0) * shutter_drop
    r.append(poly((x0, y, zs), (x1, y, zs), (x1, y, z1), (x0, y, z1), cls='fill-accent-200'))
    n = 4
    for i in range(1, n):
        zz = z1 - (z1 - zs) * i / n
        r.append(line((x0, y, zz), (x1, y, zz), cls='stroke-accent-600'))
    # shutter box
    r += [poly((x0 - 0.1, y, z1), (x1 + 0.1, y, z1), (x1 + 0.1, y, z1 + 0.3), (x0 - 0.1, y, z1 + 0.3), cls='fill-neutral-200')]
    return r

def wall_window_right(y0, y1, z0, z1, x, shutter_drop):
    r = []
    r.append(poly((x, y0, z0), (x, y1, z0), (x, y1, z1), (x, y0, z1), cls='fill-primary-100'))
    zs = z1 - (z1 - z0) * shutter_drop
    r.append(poly((x, y0, zs), (x, y1, zs), (x, y1, z1), (x, y0, z1), cls='fill-accent-200'))
    for i in range(1, 3):
        zz = z1 - (z1 - zs) * i / 3
        r.append(line((x, y0, zz), (x, y1, zz), cls='stroke-accent-600'))
    r.append(poly((x, y0 - 0.1, z1), (x, y1 + 0.1, z1), (x, y1 + 0.1, z1 + 0.3), (x, y0 - 0.1, z1 + 0.3), cls='fill-neutral-200'))
    return r

hd = []
hd += wall_window_front(2.8, 4.9, 0.9, 2.3, hy1, 0.55)
hd += wall_window_front(8.4, 10.3, 0.9, 2.3, hy1, 0.25)
hd += wall_window_right(4.6, 6.6, 0.9, 2.2, hx1, 0.35)
# door
hd.append(poly((5.7, hy1, 0), (6.9, hy1, 0), (6.9, hy1, 2.2), (5.7, hy1, 2.2), cls='fill-primary-500'))
hd.append(poly((6.55, hy1, 1.0), (6.7, hy1, 1.0), (6.7, hy1, 1.15), (6.55, hy1, 1.15), cls='fill-accent-400'))
# smart home hub (wall module + signal arcs)
hd.append(poly((7.35, hy1, 1.25), (7.85, hy1, 1.25), (7.85, hy1, 1.85), (7.35, hy1, 1.85), cls='fill-neutral-50'))
hd.append(poly((7.5, hy1, 1.45), (7.7, hy1, 1.45), (7.7, hy1, 1.65), (7.5, hy1, 1.65), cls='fill-accent-500 stroke-none'))
g('fill-white stroke-primary-600', hd)

# ---------------------------------------------------------------- professional building
bx0, bx1, by0, by1, bz = 15.0, 30.0, 2.0, 11.0, 5.0
bd = []
bd.append(poly((bx1, by0, 0), (bx1, by1, 0), (bx1, by1, bz), (bx1, by0, bz), cls='fill-neutral-100'))
bd.append(poly((bx0, by1, 0), (bx1, by1, 0), (bx1, by1, bz), (bx0, by1, bz), cls='fill-white'))
bd.append(poly((bx0, by0, bz), (bx1, by0, bz), (bx1, by1, bz), (bx0, by1, bz), cls='fill-primary-100'))
# parapet inner line + rooftop units
i = 0.35
bd.append(poly((bx0 + i, by0 + i, bz), (bx1 - i, by0 + i, bz), (bx1 - i, by1 - i, bz), (bx0 + i, by1 - i, bz), cls='fill-primary-50 stroke-primary-300'))
bd += box(18.0, 20.0, 4.0, 5.6, bz, bz + 0.8, top='fill-neutral-50', left='fill-neutral-100', right='fill-neutral-200')
bd += box(23.0, 24.4, 3.8, 5.0, bz, bz + 0.6, top='fill-neutral-50', left='fill-neutral-100', right='fill-neutral-200')
# fascia band
bd.append(line((bx0, by1, 4.35), (bx1, by1, 4.35), cls='stroke-primary-300'))
bd.append(line((bx1, by0, 4.35), (bx1, by1, 4.35), cls='stroke-primary-300'))
# sectional industrial door
d0, d1, dz = 16.8, 21.8, 3.7
bd.append(poly((d0 - 0.15, by1, 0), (d1 + 0.15, by1, 0), (d1 + 0.15, by1, dz + 0.15), (d0 - 0.15, by1, dz + 0.15), cls='fill-neutral-200'))
bd.append(poly((d0, by1, 0), (d1, by1, 0), (d1, by1, dz), (d0, by1, dz), cls='fill-accent-200'))
for k in range(1, 6):
    zz = dz * k / 6
    bd.append(line((d0, by1, zz), (d1, by1, zz), cls='stroke-accent-600'))
bd.append(poly((d0 + 1.2, by1, 2.3), (d1 - 1.2, by1, 2.3), (d1 - 1.2, by1, 2.8), (d0 + 1.2, by1, 2.8), cls='fill-primary-100 stroke-accent-600'))
# pedestrian door + access keypad
bd.append(poly((23.6, by1, 0), (24.8, by1, 0), (24.8, by1, 2.3), (23.6, by1, 2.3), cls='fill-primary-500'))
bd.append(poly((25.2, by1, 1.0), (25.65, by1, 1.0), (25.65, by1, 1.6), (25.2, by1, 1.6), cls='fill-neutral-50'))
for zz in (1.15, 1.3, 1.45):
    bd.append(line((25.3, by1, zz), (25.55, by1, zz), cls='stroke-primary-400'))
# window band on front + right facade
bd.append(poly((26.4, by1, 2.0), (29.4, by1, 2.0), (29.4, by1, 3.5), (26.4, by1, 3.5), cls='fill-primary-100'))
bd.append(line((27.9, by1, 2.0), (27.9, by1, 3.5), cls='stroke-primary-400'))
for y0, y1 in ((3.0, 5.6), (7.2, 9.8)):
    bd.append(poly((bx1, y0, 2.0), (bx1, y1, 2.0), (bx1, y1, 3.5), (bx1, y0, 3.5), cls='fill-primary-100'))
    bd.append(line((bx1, (y0 + y1) / 2, 2.0), (bx1, (y0 + y1) / 2, 3.5), cls='stroke-primary-400'))
# alarm siren on facade
bd.append(poly((26.6, by1, 3.75), (27.3, by1, 3.75), (27.3, by1, 4.2), (26.6, by1, 4.2), cls='fill-accent-400'))
# CCTV camera at the corner: bracket + body
bd.append(line((bx1, by1, 4.2), (bx1 + 0.5, by1 + 0.5, 4.2), cls='stroke-primary-600'))
bd += box(bx1 + 0.3, bx1 + 0.75, by1 + 0.35, by1 + 1.05, 3.95, 4.3, top='fill-neutral-50', left='fill-neutral-100', right='fill-neutral-200')
bd.append(poly((bx1 + 0.38, by1 + 1.05, 4.02), (bx1 + 0.67, by1 + 1.05, 4.02), (bx1 + 0.67, by1 + 1.05, 4.23), (bx1 + 0.38, by1 + 1.05, 4.23), cls='fill-primary-600'))
g('fill-white stroke-primary-600', bd)

# ---------------------------------------------------------------- consumables pallet + spare parts crate
yard = []
yard += box(14.6, 16.4, 12.0, 13.4, 0, 0.2, top='fill-neutral-200', left='fill-neutral-300', right='fill-neutral-300')
yard += box(14.7, 15.5, 12.1, 12.9, 0.2, 0.95, top='fill-accent-100', left='fill-accent-200', right='fill-accent-300')
yard += box(15.6, 16.3, 12.2, 13.3, 0.2, 0.8, top='fill-accent-100', left='fill-accent-200', right='fill-accent-300')
yard += box(14.8, 15.4, 12.3, 12.9, 0.95, 1.5, top='fill-accent-100', left='fill-accent-200', right='fill-accent-300')
yard.append(line((14.7, 12.9, 0.6), (15.5, 12.9, 0.6), cls='stroke-accent-600'))
# spare parts crate (open, with a gear on its side)
yard += box(25.8, 27.4, 12.4, 13.5, 0, 0.9, top='fill-primary-200', left='fill-white', right='fill-neutral-100')
gx, gy = P(26.6, 13.5, 0.45)
teeth = []
for k in range(8):
    a = math.radians(k * 45)
    teeth.append(f'<line x1="{gx + 4.5 * math.cos(a):.1f}" y1="{gy + 4.5 * math.sin(a):.1f}" x2="{gx + 6.8 * math.cos(a):.1f}" y2="{gy + 6.8 * math.sin(a):.1f}"/>')
yard.append('<g class="stroke-primary-600">' + ''.join(teeth) + f'<circle cx="{gx:.1f}" cy="{gy:.1f}" r="4.5" class="fill-white"/><circle cx="{gx:.1f}" cy="{gy:.1f}" r="1.6" class="fill-primary-600"/></g>')
g('fill-white stroke-primary-600', yard)

# ---------------------------------------------------------------- front fence + sliding gate + motor + intercom
fy = 15.4
fence = []
# gate motor (behind the fence line, inside the property, next to the left pillar)
fence += box(4.3, 5.1, 14.3, 14.9, 0, 0.55, top='fill-accent-300', left='fill-accent-400', right='fill-accent-500')
fence.append(line((5.1, 14.6, 0.3), (5.9, 14.6, 0.3), cls='stroke-primary-400'))
fg = []
def fence_run(x0, x1):
    r = []
    n = int(round((x1 - x0) / 0.5))
    for k in range(n + 1):
        x = x0 + (x1 - x0) * k / n
        r.append(line((x, fy, 0.05), (x, fy, 1.15), cls='stroke-primary-400'))
    r.append(line((x0, fy, 1.15), (x1, fy, 1.15)))
    r.append(line((x0, fy, 0.1), (x1, fy, 0.1)))
    for k in range(0, int(x1 - x0) + 1, 3):
        x = x0 + k
        r.append(line((x, fy, 0), (x, fy, 1.35)))
    return r
fg += fence_run(0.0, 5.4)
fg += fence_run(11.6, 33.0)
g('stroke-primary-600', fence + fg)
# pillars + gate leaf
gate = []
gate += box(5.4, 6.1, fy - 0.35, fy + 0.35, 0, 1.9, top='fill-neutral-50', left='fill-neutral-100', right='fill-neutral-200')
gate.append(poly((6.1, fy, 0.08), (10.9, fy, 0.08), (10.9, fy, 1.55), (6.1, fy, 1.55), cls='fill-primary-100'))
for k in range(1, 12):
    x = 6.1 + 4.8 * k / 12
    gate.append(line((x, fy, 0.08), (x, fy, 1.55), cls='stroke-primary-400'))
gate.append(line((6.1, fy, 0.8), (10.9, fy, 0.8), cls='stroke-primary-400'))
gate += box(10.9, 11.6, fy - 0.35, fy + 0.35, 0, 1.9, top='fill-neutral-50', left='fill-neutral-100', right='fill-neutral-200')
# video intercom plate on the right pillar
gate.append(poly((11.05, fy + 0.35, 1.05), (11.45, fy + 0.35, 1.05), (11.45, fy + 0.35, 1.6), (11.05, fy + 0.35, 1.6), cls='fill-primary-600'))
cx_, cy_ = P(11.25, fy + 0.35, 1.45)
gate.append(f'<circle cx="{cx_:.1f}" cy="{cy_:.1f}" r="1.6" class="fill-accent-400 stroke-none"/>')
g('fill-white stroke-primary-600', gate)

# pins anchors (3D) -> 2D
anchors = {
    'gates': (8.5, fy, 1.6),
    'garage': (19.3, 11.0, 3.0),
    'shutters': (3.85, 10.0, 2.4),
    'motors': (4.7, 14.6, 0.9),
    'intercom': (11.25, fy + 0.35, 2.2),
    'access': (25.4, 11.0, 2.0),
    'security': (30.5, 11.7, 5.0),
    'smart': (7.6, 10.0, 2.25),
    'supplies': (15.4, 12.6, 2.0),
    'parts': (26.6, 13.0, 1.6),
}
pins = {k: [round(v, 1) for v in P(*a)] for k, a in anchors.items()}

xs = []
svg = '\n'.join(out)
w, h = 33 * C + 16 * C + 2 * 18, OY + 19 * 2 * S + 30
print(json.dumps({'bbox': [round(v) for v in BB], 'pins': pins}), file=sys.stderr)
open(sys.argv[1], 'w').write(svg)
