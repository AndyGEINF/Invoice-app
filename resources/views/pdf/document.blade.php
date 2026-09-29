{{--
    Plantilla única del documento: vista previa en el navegador y PDF (Browsershot).
    Recibe $doc (App\Http\Presenters\PrintableDocument) con todo ya formateado en
    español; aquí no se calcula nada. No muestra el marcador de cobro (FR-033).
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $doc->title }} {{ $doc->number ?? 'borrador' }}</title>
    <style>
        @page { size: A4; margin: 16mm 14mm 18mm; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            color: #1f2328;
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
        }

        .page { max-width: 182mm; margin: 0 auto; position: relative; }

        /* En pantalla (vista previa) se ve como un folio; al imprimir manda @page. */
        @media screen {
            body { background: #eaeef2; padding: 8mm 0; }
            .page {
                max-width: 210mm;
                min-height: 297mm;
                padding: 16mm 14mm 18mm;
                background: #fff;
                box-shadow: 0 1mm 4mm rgba(31, 35, 40, 0.15);
            }
        }

        .watermark {
            position: fixed;
            top: 40%;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 64pt;
            font-weight: 700;
            color: rgba(200, 30, 30, 0.12);
            transform: rotate(-24deg);
            pointer-events: none;
        }

        .header { display: flex; justify-content: space-between; gap: 12mm; margin-bottom: 8mm; }
        .issuer { max-width: 55%; }
        .logo { display: block; max-height: 22mm; max-width: 60mm; margin-bottom: 3mm; }
        .issuer-name { font-size: 12pt; font-weight: 700; }
        .muted { color: #59636e; }

        .doc-meta { text-align: right; }
        .doc-meta h1 { margin: 0; font-size: 18pt; letter-spacing: 0.02em; }
        .number { font-size: 12pt; font-weight: 700; margin: 1mm 0 3mm; }
        .draft-note { color: #b42318; font-weight: 700; }
        .dates { margin-left: auto; border-collapse: collapse; }
        .dates td { padding: 0.5mm 0 0.5mm 4mm; }
        .dates td:first-child { color: #59636e; }

        .box { border: 1px solid #d0d7de; border-radius: 2mm; padding: 3mm 4mm; margin-bottom: 6mm; }
        .box h2, .notes h2 { margin: 0 0 1mm; font-size: 8pt; text-transform: uppercase; letter-spacing: 0.06em; color: #59636e; }
        .customer-name { font-weight: 700; }

        .rectifies { margin: 0 0 6mm; padding: 2mm 4mm; background: #fff8e1; border-left: 1mm solid #d4a72c; }

        table.lines { width: 100%; border-collapse: collapse; margin-bottom: 6mm; }
        table.lines th {
            text-align: left;
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #59636e;
            border-bottom: 1px solid #1f2328;
            padding: 1.5mm 1.5mm;
        }
        table.lines td { padding: 1.8mm 1.5mm; border-bottom: 1px solid #eaeef2; vertical-align: top; }
        table.lines tr { page-break-inside: avoid; }
        .num { text-align: right !important; white-space: nowrap; }

        .summary { display: flex; justify-content: space-between; align-items: flex-start; gap: 10mm; page-break-inside: avoid; }
        table.taxes, table.totals { border-collapse: collapse; }
        table.taxes { flex: 1; }
        table.taxes th { text-align: left; font-size: 8pt; text-transform: uppercase; color: #59636e; padding: 1mm 2mm; border-bottom: 1px solid #d0d7de; }
        table.taxes td { padding: 1mm 2mm; }
        table.totals { min-width: 65mm; }
        table.totals td { padding: 1mm 0 1mm 4mm; }
        table.totals tr.grand td { border-top: 1px solid #1f2328; font-size: 13pt; font-weight: 700; padding-top: 2mm; }

        .global-discount { margin: -4mm 0 6mm; color: #59636e; }
        .exemptions { margin: 6mm 0 0; padding-left: 4mm; color: #59636e; font-size: 9pt; }
        .notes { margin-top: 6mm; }
        .footer { margin-top: 10mm; padding-top: 3mm; border-top: 1px solid #d0d7de; color: #59636e; font-size: 8pt; }
    </style>
</head>
<body>
@if ($doc->isDraft)
    <div class="watermark">BORRADOR</div>
@endif

<div class="page">
    <header class="header">
        <div class="issuer">
            @if ($doc->issuer['logo'])
                <img class="logo" src="{{ $doc->issuer['logo'] }}" alt="Logotipo de {{ $doc->issuer['name'] }}">
            @endif
            <div class="issuer-name">{{ $doc->issuer['name'] }}</div>
            @if ($doc->issuer['contact_name'])
                <div>{{ $doc->issuer['contact_name'] }}</div>
            @endif
            @if ($doc->issuer['tax_id'])
                <div>NIF {{ $doc->issuer['tax_id'] }}</div>
            @endif
            @foreach ($doc->issuer['address_lines'] as $line)
                <div>{{ $line }}</div>
            @endforeach
            @foreach ($doc->issuer['contact_lines'] as $line)
                <div class="muted">{{ $line }}</div>
            @endforeach
        </div>

        <div class="doc-meta">
            <h1>{{ $doc->title }}</h1>
            @if ($doc->number)
                <div class="number">{{ $doc->number }}</div>
            @else
                <div class="number draft-note">Borrador · sin validez fiscal</div>
            @endif
            @if ($doc->dates !== [])
                <table class="dates">
                    @foreach ($doc->dates as $date)
                        <tr><td>{{ $date['label'] }}</td><td>{{ $date['value'] }}</td></tr>
                    @endforeach
                </table>
            @endif
        </div>
    </header>

    @if ($doc->customer)
        <section class="box">
            <h2>Cliente</h2>
            <div class="customer-name">{{ $doc->customer['name'] }}</div>
            @if ($doc->customer['trade_name'])
                <div>{{ $doc->customer['trade_name'] }}</div>
            @endif
            @if ($doc->customer['tax_id'])
                <div>NIF {{ $doc->customer['tax_id'] }}</div>
            @endif
            @foreach ($doc->customer['address_lines'] as $line)
                <div>{{ $line }}</div>
            @endforeach
        </section>
    @endif

    @if ($doc->rectifies)
        <p class="rectifies">{{ $doc->rectifies }}</p>
    @endif

    <table class="lines">
        <thead>
            <tr>
                <th>Descripción</th>
                <th class="num">Cant.</th>
                <th>Ud.</th>
                <th class="num">Precio</th>
                <th class="num">Dto.</th>
                <th class="num">IVA</th>
                <th class="num">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($doc->lines as $line)
                <tr>
                    <td>{!! nl2br(e($line['description'])) !!}</td>
                    <td class="num">{{ $line['quantity'] }}</td>
                    <td>{{ $line['unit'] }}</td>
                    <td class="num">{{ $line['unit_price'] }}</td>
                    <td class="num">{{ $line['discount'] }}</td>
                    <td class="num">{{ $line['vat'] }}</td>
                    <td class="num">{{ $line['amount'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($doc->globalDiscount)
        <p class="global-discount">Importes con un descuento global del {{ $doc->globalDiscount }} aplicado.</p>
    @endif

    <div class="summary">
        <table class="taxes">
            <thead>
                <tr><th>Impuesto</th><th class="num">Base</th><th class="num">Cuota</th></tr>
            </thead>
            <tbody>
                @foreach ($doc->taxes as $tax)
                    <tr>
                        <td>{{ $tax['label'] }}</td>
                        <td class="num">{{ $tax['base'] }}</td>
                        <td class="num">{{ $tax['amount'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals">
            @foreach ($doc->totals as $row)
                <tr><td>{{ $row['label'] }}</td><td class="num">{{ $row['value'] }}</td></tr>
            @endforeach
            <tr class="grand"><td>Total</td><td class="num">{{ $doc->total }}</td></tr>
        </table>
    </div>

    @if ($doc->exemptions !== [])
        <ul class="exemptions">
            @foreach ($doc->exemptions as $exemption)
                <li>{{ $exemption }}</li>
            @endforeach
        </ul>
    @endif

    @if ($doc->notes)
        <section class="notes">
            <h2>Notas</h2>
            <div>{!! nl2br(e($doc->notes)) !!}</div>
        </section>
    @endif

    @if ($doc->footer)
        <footer class="footer">{!! nl2br(e($doc->footer)) !!}</footer>
    @endif
</div>
</body>
</html>
