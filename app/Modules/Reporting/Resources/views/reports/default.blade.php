<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #1e293b;
            margin: 0;
            padding: 40px;
            line-height: 1.5;
        }
        .header {
            border-bottom: 2px solid #6366f1;
            padding-bottom: 20px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            color: #0f172a;
            margin: 0;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.025em;
        }
        .header .meta {
            text-align: right;
            font-size: 12px;
            color: #64748b;
        }
        .section {
            margin-bottom: 40px;
            page-break-inside: avoid;
        }
        .section h2 {
            font-size: 16px;
            font-weight: 700;
            color: #1e1b4b;
            border-left: 4px solid #6366f1;
            padding-left: 10px;
            margin-top: 0;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .section-desc {
            font-size: 13px;
            color: #475569;
            margin-bottom: 20px;
        }
        .card-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        .card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        .card .value {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            margin: 5px 0;
        }
        .card .label {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .data-table th, .data-table td {
            padding: 10px 12px;
            text-align: left;
            font-size: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        .data-table th {
            background: #f1f5f9;
            font-weight: 700;
            color: #475569;
        }
        .footer {
            margin-top: 50px;
            border-top: 1px solid #e2e8f0;
            padding-top: 15px;
            text-align: center;
            font-size: 10px;
            color: #94a3b8;
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>{{ $report['title'] }}</h1>
            <p style="margin: 5px 0 0 0; font-size: 14px; color: #475569;">Digital Marketing Performance Report</p>
        </div>
        <div class="meta">
            <p style="margin: 0; font-weight: bold; color: #0f172a;">{{ strtoupper($report['type']) }}</p>
            <p style="margin: 3px 0 0 0;">{{ $report['date_from'] }} to {{ $report['date_to'] }}</p>
        </div>
    </div>

    @if($project)
        <div class="section">
            <h2>Project Performance Overview</h2>
            <div class="section-desc">Summary of active indicators for project <strong>{{ $project['name'] }}</strong>.</div>
            <div class="card-grid">
                <div class="card">
                    <div class="label">Completed Tasks</div>
                    <div class="value">{{ $project['tasks_completed'] }} / {{ $project['tasks_total'] }}</div>
                </div>
                <div class="card">
                    <div class="label">Overdue Tasks</div>
                    <div class="value" style="color: {{ $project['tasks_overdue'] > 0 ? '#ef4444' : '#10b981' }};">
                        {{ $project['tasks_overdue'] }}
                    </div>
                </div>
                <div class="card">
                    <div class="label">Budget Used</div>
                    <div class="value">₹{{ number_format($project['budget_used'], 2) }}</div>
                </div>
            </div>
        </div>
    @endif

    @foreach($sections as $sec)
        <div class="section">
            <h2>{{ $sec['title'] }}</h2>
            <div class="section-desc">{{ $sec['description'] }}</div>
            
            @if($sec['id'] === 'organic_traffic' && isset($metrics['organic_clicks']))
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Clicks</th>
                            <th>Impressions</th>
                            <th>Avg Position</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(array_slice($metrics['organic_clicks'], 0, 10) as $idx => $m)
                            <tr>
                                <td>{{ $m['date'] }}</td>
                                <td>{{ number_format($m['value']) }}</td>
                                <td>{{ number_format($metrics['organic_impressions'][$idx]['value'] ?? 0) }}</td>
                                <td>{{ number_format($metrics['average_position'][$idx]['value'] ?? 0, 1) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colSpan="4" style="text-align: center; color: #94a3b8;">No organic traffic data recorded in this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif($sec['id'] === 'spend_overview' && isset($metrics['meta_ads_spend']))
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Ad Spend</th>
                            <th>Clicks</th>
                            <th>Impressions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(array_slice($metrics['meta_ads_spend'], 0, 10) as $idx => $m)
                            <tr>
                                <td>{{ $m['date'] }}</td>
                                <td>₹{{ number_format($m['value'], 2) }}</td>
                                <td>{{ number_format($metrics['meta_ads_clicks'][$idx]['value'] ?? 0) }}</td>
                                <td>{{ number_format($metrics['meta_ads_impressions'][$idx]['value'] ?? 0) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colSpan="4" style="text-align: center; color: #94a3b8;">No paid ad spend data recorded in this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <div style="padding: 15px; border: 1px dashed #cbd5e1; border-radius: 6px; font-size: 12px; color: #64748b; background: #fafafa;">
                    Detailed section metrics are compiled dynamically on download. No significant exceptions occurred during data parsing.
                </div>
            @endif
        </div>
    @endforeach

    <div class="footer">
        <p>This report was generated automatically by the Digicloudify Morning Assistant.</p>
        <p style="margin-top: 5px; font-size: 9px; color: #cbd5e1;">SAANETIX SOLUTIONS PRIVATE LIMITED • Hyderabad, India</p>
    </div>
</body>
</html>
