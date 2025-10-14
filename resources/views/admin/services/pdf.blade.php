<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice {{ $service->invoice_no }}</title>
    <style>
        /* Kunci margin & orientasi kertas */
        @page {
            size: 24cm 14cm;
            margin: 0 !important;
        }
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
        }
        .invoice-box {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            line-height: 1.1;
            text-align: left;
        }

        table td, table th {
            padding: 1px 2px;
            vertical-align: top;
        }

        .header-main { font-size: 14px; font-weight: bold; }
        .header-sub { font-size: 12px; font-weight: bold; }

        .items-table th, .items-table td {
            border: 1px solid #000;
            padding: 2px;
        }
        .items-table th { text-align: center; }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }

        .terbilang-box {
            padding: 3px;
            font-style: italic;
            border: 1px solid #000;
            margin-top: 2px;
        }

        .signature-box { margin-top: 5px; }

        hr {
            border: none;
            border-top: 1px solid #000;
            margin: 3px 0;
        }

        .dotted-hr {
            border: none;
            border-top: 1px dotted #000;
            margin: 2px 0;
        }
    </style>
</head>
<body>
    <div class="invoice-box">
        {{-- Konten invoice tetap sama --}}
        @include('admin.service.pdf_content', ['service' => $service])
    </div>
</body>
</html>
