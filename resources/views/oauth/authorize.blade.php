<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Autoriser l'accès — {{ config('app.name') }}</title>
    <style>
        :root { --forest:#00453e; --lime:#aac932; --ink:#16201d; --muted:#586460; --border:#e0e4e2; --bg:#f6f8f7; }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; background:var(--bg); font-family:'Hanken Grotesk',system-ui,-apple-system,sans-serif; color:var(--ink); padding:24px; }
        .card { background:#fff; border:1px solid var(--border); border-radius:16px; box-shadow:0 12px 28px rgba(0,33,30,.1); max-width:440px; width:100%; padding:32px; }
        h1 { font-size:20px; margin:0 0 8px; }
        p { color:var(--muted); line-height:1.6; margin:0 0 16px; font-size:15px; }
        .client { font-weight:700; color:var(--forest); }
        ul { margin:0 0 20px; padding-left:18px; color:var(--muted); font-size:14px; }
        .who { font-size:13px; color:var(--muted); margin-bottom:20px; }
        .actions { display:flex; gap:12px; }
        .actions form { flex:1; margin:0; }
        button { width:100%; height:46px; border-radius:10px; border:1.5px solid transparent; font:600 15px inherit; cursor:pointer; }
        .approve { background:var(--forest); color:#fff; }
        .deny { background:#fff; color:var(--forest); border-color:var(--border); }
    </style>
</head>
<body>
    <div class="card">
        <h1>Autoriser l'accès</h1>
        <p><span class="client">{{ $client->name }}</span> demande l'autorisation d'accéder à la gestion de contenu (page-builder) de {{ config('app.name') }}.</p>

        @if (count($scopes) > 0)
            <ul>
                @foreach ($scopes as $scope)
                    <li>{{ $scope->description ?? $scope->id }}</li>
                @endforeach
            </ul>
        @endif

        @if (! empty($user))
            <div class="who">Connecté en tant que <strong>{{ $user->name ?? $user->email ?? ('#'.$user->getKey()) }}</strong></div>
        @endif

        <div class="actions">
            <form method="post" action="{{ route('passport.authorizations.approve') }}">
                @csrf
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="approve">Autoriser</button>
            </form>

            <form method="post" action="{{ route('passport.authorizations.deny') }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="deny">Refuser</button>
            </form>
        </div>
    </div>
</body>
</html>
