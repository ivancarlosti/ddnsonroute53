document.addEventListener('DOMContentLoaded', function() {
    const table = document.getElementById('ddnsTable');
    if (!table) return;

    const headers = table.querySelectorAll('th.sortable');
    const tbody = table.querySelector('tbody');

    headers.forEach((header, index) => {
        header.addEventListener('click', () => {
            const type = header.dataset.type || 'string';
            const isAscending = header.classList.contains('asc');
            
            // Reset all headers
            headers.forEach(h => h.classList.remove('asc', 'desc'));
            
            // Toggle sort order
            if (!isAscending) {
                header.classList.add('asc');
            } else {
                header.classList.add('desc');
            }

            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            const sortedRows = rows.sort((a, b) => {
                // Find the cell corresponding to the header index
                // Note: We need to account for the fact that headers might not match columns 1:1 if there are colspans, 
                // but here it seems straightforward. However, we should find the index of the header among all ths in the thead
                // to match the td index.
                const allHeaders = Array.from(header.parentElement.children);
                const colIndex = allHeaders.indexOf(header);

                const aText = a.children[colIndex].textContent.trim();
                const bText = b.children[colIndex].textContent.trim();

                if (type === 'number') {
                    return isAscending ? bText - aText : aText - bText;
                } else {
                    return isAscending ? bText.localeCompare(aText) : aText.localeCompare(bText);
                }
            });

            // Re-append rows
            tbody.innerHTML = '';
            sortedRows.forEach(row => tbody.appendChild(row));
        });
    });
});
