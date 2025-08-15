<!DOCTYPE html>
<html>

<head>
    <title>Document Dashboard</title>
</head>

<body>
    <h1>Document Dashboard</h1>
    <table border="1" cellpadding="5">
        <tr>
            <th>File</th>
            <th>Invoice</th>
            <th>Vendor</th>
            <th>Total</th>
            <th>PO Match</th>
            <th>Status</th>
        </tr>
        @foreach ($documents as $doc)
            <tr>
                <td>{{ $doc->formatted_file_name }}</td>
                <td>{{ $doc->invoice_number }}</td>
                <td>{{ $doc->vendor }}</td>
                <td>{{ $doc->total_amount }}</td>
                <td>{{ $doc->po_number }}</td>
                <td>{{ $doc->status }}</td>
            </tr>
        @endforeach
    </table>
</body>

</html>
