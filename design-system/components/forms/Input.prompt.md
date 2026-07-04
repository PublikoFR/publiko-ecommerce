Text field with label, helper text, error state and optional left icon / suffix. Focus ring uses the lime accent.

```jsx
<Input label="Email" type="email" placeholder="you@farm.co" required />
<Input label="Field area" suffix="ha" iconLeft={<MapIcon/>} />
<Input label="Password" type="password" error="At least 8 characters" />
```

Sizes: `sm` (38) · `md` (44) · `lg` (52). Pass `error` to show the danger state; `helper` for neutral guidance.
