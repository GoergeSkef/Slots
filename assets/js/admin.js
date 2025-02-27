jQuery(document).ready(function($) {
    // Initialize Select2
    $('#sync-domain, .post-select').select2({
        width: '100%'
    });

    // Handle domain selection change
    $('#sync-domain').on('change', function() {
        const domain = $(this).val();
        if (!domain) return;

        // Get remote categories
        $.ajax({
            url: hubAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'hub_get_remote_categories',
                domain: domain,
                nonce: hubAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    const container = $('#category-select .category-checkboxes');
                    container.empty();
                    response.data.forEach(category => {
                        container.append(`
                            <label class="category-option">
                                <input type="checkbox" 
                                    name="categories[]" 
                                    value="${category.slug}">
                                ${category.name}
                            </label>
                        `);
                    });
                }
            }
        });

        // Get remote posts
        $.ajax({
            url: hubAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'hub_get_remote_posts',
                domain: domain,
                nonce: hubAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    const select = $('.post-select');
                    select.empty();
                    response.data.forEach(post => {
                        select.append(
                            new Option(post.title, post.id, false, false)
                        );
                    });
                    select.trigger('change');
                }
            }
        });
    });

    // Handle scope change
    $('#sync-scope').on('change', function() {
        $('#category-select, #post-select').hide();
        if (this.value === 'category') {
            $('#category-select').show();
        } else if (this.value === 'specific') {
            $('#post-select').show();
        }
    });

    // Add to the document ready function
    $('#hub-sync-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $progress = $('#sync-progress');
        const $log = $progress.find('.sync-log');
        const $progressBar = $progress.find('.progress');
        
        $progress.show();
        $log.empty();
        
        const formData = {
            action: 'hub_process_sync',
            nonce: hubAdmin.nonce,
            domain: $('#sync-domain').val(),
            action_type: $form.find('[name="action_type"]:checked').val(),
            sync_scope: $('#sync-scope').val(),
            categories: $form.find('[name="categories[]"]:checked').map(function() {
                return this.value;
            }).get(),
            posts: $form.find('[name="posts[]"]').val() || []
        };

        $.ajax({
            url: hubAdmin.ajaxUrl,
            method: 'POST',
            data: formData,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percent = (e.loaded / e.total) * 100;
                        $progressBar.css('width', percent + '%');
                    }
                });
                return xhr;
            },
            success: function(response) {
                if (response.success) {
                    $log.append(`<p class="success">Sync completed: ${response.data.processed} items processed</p>`);
                } else {
                    $log.append(`<p class="error">Error: ${response.data}</p>`);
                }
            },
            error: function(xhr) {
                $log.append(`<p class="error">Request failed: ${xhr.statusText}</p>`);
            }
        });
    });

    // Add to document ready function
    $(document).on('click', '.delete-key', function() {
        const domain = $(this).data('domain');
        if (confirm(`Delete API key for ${domain}?`)) {
            $.ajax({
                url: hubAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'hub_delete_api_key',
                    domain: domain,
                    nonce: hubAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                }
            });
        }
    });

    $(document).on('click', '.copy-key', function() {
        navigator.clipboard.writeText($(this).data('key'))
            .then(() => alert('API key copied!'))
            .catch(err => console.error('Copy failed:', err));
    });

    $(document).on('click', '.push-updates', function() {
        const domain = $(this).data('domain');
        const confirmed = confirm(`Force push all content to ${domain}?`);
        if (!confirmed) return;

        $.ajax({
            url: hubAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'hub_force_push',
                domain: domain,
                nonce: hubAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Push completed successfully');
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function(xhr) {
                alert('Request failed: ' + xhr.statusText);
            }
        });
    });

    // Add verify connection handler
    $(document).on('click', '.verify-connection', function() {
        const domain = $(this).data('domain');
        const $row = $(this).closest('tr');
        const $status = $row.find('.connection-status');
        
        $status.html('<span class="spinner is-active"></span>');
        
        $.ajax({
            url: hubAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'hub_verify_connection',
                domain: domain,
                nonce: hubAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $status.html('<span class="status-success">✓ Connected</span>');
                } else {
                    $status.html(`<span class="status-error">✗ ${response.data}</span>`);
                }
            },
            error: function(xhr) {
                $status.html(`<span class="status-error">✗ ${xhr.statusText}</span>`);
            }
        });
    });
}); 