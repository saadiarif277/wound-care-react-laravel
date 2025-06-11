<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>CSRF Test Form</title>
</head>
<body>
    <h1>CSRF Token Test</h1>
    <p>Current CSRF Token: <code>{{ csrf_token() }}</code></p>
    <p>Session ID: <code>{{ session()->getId() }}</code></p>

    <form method="POST" action="/test-form">
        @csrf
        <input type="text" name="test_field" value="test" required>
        <button type="submit">Submit Test</button>
    </form>

    <script>
        // Test AJAX request with CSRF token
        fetch('/test-form', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            },
            body: JSON.stringify({test_field: 'ajax_test'})
        })
        .then(response => response.json())
        .then(data => {
            console.log('AJAX test result:', data);
        })
        .catch(error => {
            console.error('AJAX test error:', error);
        });
    </script>
</body>
</html>
