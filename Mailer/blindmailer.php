<script>
// Import CSV
document.getElementById('csvImport').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(event) {
        const text = event.target.result;
        const lines = text.split(/\r?\n/).map(l => l.trim()).filter(l => l && l.includes('@'));
        const emails = lines.map(line => {
            // Take first column if comma-separated
            const parts = line.split(/[,;]/);
            return parts[0].trim();
        }).filter(email => email && email.includes('@'));

        const current = document.getElementById('emails').value.trim();
        document.getElementById('emails').value = current ? current + '\n' + emails.join('\n') : emails.join('\n');
    };
    reader.readAsText(file);
});

// Export CSV
document.getElementById('exportCsvBtn').addEventListener('click', function() {
    const text = document.getElementById('emails').value.trim();
    if (!text) {
        alert('No recipients to export');
        return;
    }

    const blob = new Blob([text], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'recipients_' + new Date().toISOString().slice(0,10) + '.csv';
    a.click();
    URL.revokeObjectURL(url);
});
</script>
