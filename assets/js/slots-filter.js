document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.slots-filter-controls form');
    const searchInput = document.getElementById('slots_search');
    const limitSelect = document.getElementById('slots_limit');
    const sortSelect = document.getElementById('slots_sort');

    // Function to submit the form
    function submitForm() {
        // Get current URL
        let url = new URL(window.location.href);
        let params = new URLSearchParams(url.search);

        // Update parameters
        if (searchInput.value) {
            params.set('slots_search', searchInput.value);
        } else {
            params.delete('slots_search');
        }
        params.set('slots_limit', limitSelect.value);
        params.set('slots_sort', sortSelect.value);

        // Update URL and reload
        url.search = params.toString();
        window.location.href = url.toString();
    }

    // Add event listeners
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                submitForm();
            }
        });
    }

    if (limitSelect) {
        limitSelect.addEventListener('change', submitForm);
    }

    if (sortSelect) {
        sortSelect.addEventListener('change', submitForm);
    }
});
