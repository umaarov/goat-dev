<?php
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referral Analytics</title>
    <style>
        body { font-family: sans-serif; padding: 2rem; }
        table { border-collapse: collapse; width: 100%; max-width: 600px; }
        th, td { border: 1px solid #ccc; padding: 0.5rem; text-align: left; }
        th { background: #f5f5f5; }
    </style>
</head>
<body>
    <h1>Referral Analytics</h1>
    @if($clicks->isEmpty())
        <p>No referral clicks recorded yet.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Referrer</th>
                    <th>Clicks</th>
                </tr>
            </thead>
            <tbody>
                @foreach($clicks as $click)
                    <tr>
                        <td>{{ $click->referrer }}</td>
                        <td>{{ $click->total }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
