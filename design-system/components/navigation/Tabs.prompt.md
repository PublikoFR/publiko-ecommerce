Underline tab switcher with a lime active indicator. Works controlled or uncontrolled.

```jsx
<Tabs
  defaultValue="overview"
  onChange={setView}
  tabs={[
    { id: 'overview', label: 'Overview' },
    { id: 'fields', label: 'Fields', count: 12 },
    { id: 'alerts', label: 'Alerts', count: 3 },
    { id: 'settings', label: 'Settings', disabled: true },
  ]}
/>
```

Each tab supports `icon`, `count`, and `disabled`.
