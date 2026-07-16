<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $brand }} — {{ $periodLabel }}</title>
    <style>
        /*
         * dompdf only ships DejaVu, which has no Khmer glyphs — a Khmer report
         * rendered as rows of tofu boxes. Noto Sans Khmer is registered here and
         * listed FIRST so Khmer resolves to it, with DejaVu behind it for the
         * Latin and currency glyphs Noto Khmer does not carry.
         *
         * The file ships in the repo rather than relying on the host having the
         * font: dompdf reads it off disk at render time, and a server without it
         * would silently go back to boxes.
         */
        @font-face {
            font-family: 'Noto Sans Khmer';
            font-style: normal;
            font-weight: 400;
            src: url('{{ resource_path('fonts/NotoSansKhmer-Regular.ttf') }}') format('truetype');
        }

        @font-face {
            font-family: 'Noto Sans Khmer';
            font-style: normal;
            font-weight: 700;
            src: url('{{ resource_path('fonts/NotoSansKhmer-Bold.ttf') }}') format('truetype');
        }

        * { font-family: 'Noto Sans Khmer', 'DejaVu Sans', sans-serif; }
        body { margin: 0; color: #171717; font-size: 11px; }

        .head { border-bottom: 2px solid #171717; padding-bottom: 10px; margin-bottom: 16px; }
        .brand { font-size: 16px; font-weight: bold; }
        .period { color: #737373; font-size: 11px; margin-top: 2px; }
        .generated { color: #a3a3a3; font-size: 9px; margin-top: 2px; }

        .stats { width: 100%; margin-bottom: 18px; }
        .stats td { width: 25%; padding: 8px 10px; background: #f5f5f4; }
        .stat-label { color: #737373; font-size: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-value { font-size: 14px; font-weight: bold; padding-top: 3px; }

        h2 { font-size: 12px; margin: 18px 0 6px; }

        table.data { width: 100%; border-collapse: collapse; }
        table.data th {
            text-align: left; font-size: 9px; color: #737373; text-transform: uppercase;
            border-bottom: 1px solid #d4d4d4; padding: 5px 6px;
        }
        table.data td { padding: 5px 6px; border-bottom: 1px solid #f5f5f4; }
        table.data tr:nth-child(even) td { background: #fafafa; }
        .num { text-align: right; }
        /* Repeat the header on every page rather than orphaning columns. */
        thead { display: table-header-group; }
        tfoot td { border-top: 2px solid #171717; font-weight: bold; padding-top: 6px; }
        .swatch { display: inline-block; width: 7px; height: 7px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="head">
        <div class="brand">{{ $brand }}</div>
        <div class="period">{{ __('Reports') }} — {{ $periodLabel }} · {{ $userName }}</div>
        <div class="generated">{{ __('Generated :date', ['date' => $generatedAt]) }}</div>
    </div>

    @unless ($listOnly ?? false)
    <table class="stats">
        <tr>
            <td>
                <div class="stat-label">{{ __('Total spent') }}</div>
                <div class="stat-value">{{ $money($stats['total']) }}</div>
            </td>
            <td>
                <div class="stat-label">{{ __('Daily average') }}</div>
                <div class="stat-value">{{ $money($stats['daily_average']) }}</div>
            </td>
            <td>
                <div class="stat-label">{{ __('Transactions') }}</div>
                <div class="stat-value">{{ $stats['count'] }}</div>
            </td>
            <td>
                <div class="stat-label">{{ __('Top category') }}</div>
                <div class="stat-value">{{ $breakdown[0]['name'] ?? '—' }}</div>
            </td>
        </tr>
    </table>
    @endunless

    @if (count($breakdown))
        <h2>{{ __('By category') }}</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>{{ __('Category') }}</th>
                    <th class="num">{{ __('Transactions') }}</th>
                    <th class="num">{{ __('Average') }}</th>
                    <th class="num">{{ __('Share') }}</th>
                    <th class="num">{{ __('Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($breakdown as $row)
                    <tr>
                        <td>
                            <span class="swatch" style="background: {{ $swatch($row['color']) }}"></span>
                            {{ $row['name'] }}
                        </td>
                        <td class="num">{{ $row['count'] }}</td>
                        <td class="num">{{ $money($row['average']) }}</td>
                        <td class="num">{{ $row['share'] }}%</td>
                        <td class="num">{{ $money($row['total']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td>{{ __('Total spent') }}</td>
                    <td class="num">{{ $stats['count'] }}</td>
                    <td></td>
                    <td></td>
                    <td class="num">{{ $money($stats['total']) }}</td>
                </tr>
            </tfoot>
        </table>
    @endif

    <h2>{{ __('Expenses') }}</h2>
    @if (count($expenses))
        <table class="data">
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Item') }}</th>
                    <th>{{ __('Category') }}</th>
                    <th class="num">{{ __('Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($expenses as $expense)
                    <tr>
                        <td>{{ $expense->spent_on->toDateString() }}</td>
                        <td>{{ $expense->item }}</td>
                        <td>{{ $expense->category?->name }}</td>
                        <td class="num">{{ $money((float) $expense->price) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3">{{ __('Total spent') }}</td>
                    <td class="num">{{ $money($stats['total']) }}</td>
                </tr>
            </tfoot>
        </table>
    @else
        <p style="color:#737373">{{ __('Nothing logged in this period.') }}</p>
    @endif
</body>
</html>
