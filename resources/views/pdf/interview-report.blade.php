<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Compte rendu d'entretien</title>
    <style>
        @page { margin: 24mm 18mm; }
        body {
            color: #1f2937;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 13px;
            line-height: 1.5;
        }
        .header {
            border-bottom: 2px solid #1d4ed8;
            margin-bottom: 22px;
            padding-bottom: 12px;
        }
        .brand {
            color: #1d4ed8;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: .08em;
        }
        h1 {
            font-size: 20px;
            margin: 8px 0 0;
        }
        h2 {
            border-bottom: 1px solid #d1d5db;
            color: #111827;
            font-size: 15px;
            margin: 22px 0 10px;
            padding-bottom: 5px;
        }
        .meta {
            display: table;
            width: 100%;
        }
        .meta-row {
            display: table-row;
        }
        .meta-label,
        .meta-value {
            display: table-cell;
            padding: 5px 0;
            vertical-align: top;
        }
        .meta-label {
            color: #6b7280;
            font-weight: 700;
            width: 150px;
        }
        .block {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 12px;
            white-space: pre-line;
        }
        .footer {
            border-top: 1px solid #d1d5db;
            color: #6b7280;
            font-size: 11px;
            margin-top: 28px;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">AKANJO</div>
        <h1>Compte rendu d'entretien</h1>
        <div>Généré le {{ $generatedAt->format('d/m/Y H:i') }}</div>
    </div>

    <h2>Entretien</h2>
    <div class="meta">
        <div class="meta-row">
            <div class="meta-label">Titre</div>
            <div class="meta-value">{{ $eventTitle }}</div>
        </div>
        <div class="meta-row">
            <div class="meta-label">Candidat</div>
            <div class="meta-value">{{ $candidateName }}</div>
        </div>
        <div class="meta-row">
            <div class="meta-label">Poste</div>
            <div class="meta-value">{{ $postTitle }}</div>
        </div>
        <div class="meta-row">
            <div class="meta-label">Date</div>
            <div class="meta-value">{{ optional($event->start_datetime)->format('d/m/Y H:i') }}</div>
        </div>
        <div class="meta-row">
            <div class="meta-label">Type</div>
            <div class="meta-value">{{ ucfirst($event->event_type) }}</div>
        </div>
        <div class="meta-row">
            <div class="meta-label">Participants</div>
            <div class="meta-value">{{ $participants }}</div>
        </div>
    </div>

    <h2>Résumé / Évaluation</h2>
    <div class="block">{{ $report->evaluation_notes ?: 'Non renseigné' }}</div>

    <h2>Recommandation</h2>
    <div class="block">{{ $report->recommendation ?: 'Non renseigné' }}</div>

    <h2>Points positifs</h2>
    <div class="block">{{ $report->strengths ?: 'Non renseigné' }}</div>

    <h2>Points d'amélioration</h2>
    <div class="block">{{ $report->weaknesses ?: 'Non renseigné' }}</div>

    <h2>Commentaires supplémentaires</h2>
    <div class="block">{{ $report->next_steps ?: 'Non renseigné' }}</div>

    <div class="footer">
        Rédigé par {{ $authorName }}.
        @if ($report->validated_at)
            Validé le {{ $report->validated_at }}.
        @else
            Non validé.
        @endif
        Document confidentiel - Usage interne Akanjo.
    </div>
</body>
</html>
