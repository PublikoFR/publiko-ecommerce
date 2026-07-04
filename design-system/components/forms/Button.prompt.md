Primary action control — use for any clickable action; `primary` (forest) for the main action on a view, `accent` (lime) for high-energy CTAs, `secondary`/`ghost` for lower emphasis.

```jsx
<Button variant="primary" size="md" onClick={save}>Save changes</Button>
<Button variant="accent" iconRight={<ArrowIcon/>}>Get started</Button>
<Button variant="secondary">Cancel</Button>
<Button variant="ghost" size="sm">Skip</Button>
<Button variant="danger" loading>Deleting…</Button>
```

Variants: `primary` · `accent` · `secondary` · `ghost` · `danger`. Sizes: `sm` (36) · `md` (44) · `lg` (54). Props: `iconLeft`, `iconRight`, `loading`, `disabled`, `fullWidth`. Reserve `accent` (lime) for one CTA per view — it is the loudest color in the system.
