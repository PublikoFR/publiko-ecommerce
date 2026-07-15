<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ brand_name() }} — Maintenance</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: #f6f8f7;
            color: #16201d;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 24px;
        }
        .card {
            background: #fff;
            border: 1px solid #e0e4e2;
            border-radius: 20px;
            padding: 48px 40px;
            max-width: 480px;
            width: 100%;
            text-align: center;
        }
        .icon {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            background: #fef3c7;
            color: #92400e;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        h1 { font-size: 22px; font-weight: 700; color: #16201d; margin-bottom: 10px; }
        p { font-size: 15px; color: #3f4a46; line-height: 1.6; }
        .brand { font-size: 13px; font-weight: 600; color: #00453e; margin-top: 32px; opacity: .7; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/>
                <path d="M12 8v4M12 16h.01"/>
            </svg>
        </div>
        <h1>Site en maintenance</h1>
        <p>Notre boutique est temporairement indisponible. Nous effectuons des améliorations et serons de retour très prochainement.</p>
        <p class="brand">{{ brand_name() }}</p>
    </div>
</body>
</html>
