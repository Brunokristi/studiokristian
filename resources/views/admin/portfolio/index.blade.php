<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Portfolio Admin</title>
    <style>
        :root {
            --bg: #0d1117;
            --surface: #161b22;
            --muted: #8b949e;
            --text: #e6edf3;
            --accent: #2f81f7;
            --danger: #da3633;
            --border: #30363d;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Inter, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif;
            background: var(--bg);
            color: var(--text);
            padding: 24px;
        }
        .container { max-width: 1100px; margin: 0 auto; }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
        }
        .title { font-size: 28px; margin: 0; }
        .subtle { color: var(--muted); margin-top: 6px; }
        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid var(--border);
            color: var(--text);
            text-decoration: none;
            background: transparent;
            cursor: pointer;
            font-weight: 600;
        }
        .button.primary { background: var(--accent); border-color: var(--accent); }
        .button.danger { background: transparent; border-color: var(--danger); color: #ff938f; }
        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td {
            text-align: left;
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }
        th { color: var(--muted); font-size: 13px; text-transform: uppercase; letter-spacing: .05em; }
        tr:last-child td { border-bottom: none; }
        .actions { display: flex; gap: 8px; align-items: center; }
        .flash {
            margin-bottom: 16px;
            padding: 10px 12px;
            background: rgba(47,129,247,.12);
            border: 1px solid rgba(47,129,247,.35);
            border-radius: 10px;
        }
        .empty { padding: 18px 14px; color: var(--muted); }
        form { margin: 0; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1 class="title">Portfolio Admin</h1>
            <p class="subtle">Manage projects, feature blocks, and gallery images.</p>
        </div>
        <a class="button primary" href="{{ route('admin.portfolio.create') }}">Add project</a>
    </div>

    @if (session('status'))
        <div class="flash">{{ session('status') }}</div>
    @endif

    <div class="panel">
        <table>
            <thead>
            <tr>
                <th>Name</th>
                <th>URL slug</th>
                <th>Live URL</th>
                <th>Images</th>
                <th>Features</th>
                <th>Updated</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($projects as $project)
                <tr>
                    <td>{{ $project->name }}</td>
                    <td>/portfolio/{{ $project->url }}</td>
                    <td>{{ $project->live_url ?: '—' }}</td>
                    <td>{{ $project->images_count }}</td>
                    <td>{{ $project->features_count }}</td>
                    <td>{{ optional($project->updated_at)->format('Y-m-d H:i') }}</td>
                    <td>
                        <div class="actions">
                            <a class="button" href="{{ route('admin.portfolio.edit', $project) }}">Edit</a>

                            <form method="POST" action="{{ route('admin.portfolio.destroy', $project) }}" onsubmit="return confirm('Delete this project?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="button danger">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="empty">No projects yet. Create your first one.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
