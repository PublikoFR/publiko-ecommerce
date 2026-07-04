On/off toggle. Controlled — pass `checked` and handle `onChange(next)`.

```jsx
const [on, setOn] = React.useState(true);
<Switch checked={on} onChange={setOn} label="Email alerts" />
```

Sizes: `sm` · `md`. Track turns lime when on.
