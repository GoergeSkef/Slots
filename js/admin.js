jQuery(document).ready(function($) {
    // Add form submission handling if needed
    $('#generate-api-key-form').on('submit', function(e) {
        var domain = $('#website_domain').val();
        if (!domain) {
            e.preventDefault();
            alert('Please enter a website domain');
            return false;
        }
    });

    // Copy API key
    $('.copy-key').on('click', function() {
        const key = $(this).data('key');
        navigator.clipboard.writeText(key).then(function() {
            alert('API key copied to clipboard!');
        });
    });

    // Add debugging
    function logError(message, error = null) {
        console.error(message);
        if (error) {
            console.error(error);
        }
    }

    // Verify connection with error logging
    $('.verify-connection').on('click', function() {
        const button = $(this);
        const domain = button.data('domain');
        const statusCell = button.closest('tr').find('.connection-status');
        
        console.log('Verifying connection for domain:', domain);
        statusCell.html('<span class="status-checking">Checking...</span>');
        
        $.ajax({
            url: hubAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'hub_verify_connection',
                domain: domain,
                nonce: hubAdmin.nonce
            },
            success: function(response) {
                console.log('Verification response:', response);
                if (response.success) {
                    statusCell.html('<span class="status-success">Connected</span>');
                } else {
                    const errorMessage = response.data || 'Unknown error';
                    console.error('Verification failed:', errorMessage);
                    statusCell.html('<span class="status-error">Failed: ' + errorMessage + '</span>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', {xhr, status, error});
                statusCell.html('<span class="status-error">Error: ' + error + '</span>');
            }
        });
    });

    // Push/Pull Updates
    $('.push-updates, .pull-updates').on('click', function() {
        const button = $(this);
        const domain = button.data('domain');
        const action = button.hasClass('push-updates') ? 'push' : 'pull';
        
        showModal(domain, action);
    });

    // Modal functions
    function showModal(domain, action) {
        const modal = $('#hub-modal');
        const title = action === 'push' ? 'Push Updates to ' : 'Pull Updates from ';
        
        $('#hub-modal-title').text(title + domain);
        loadModalContent(domain, action);
        
        modal.show();
    }

    $('.hub-modal-close').on('click', function() {
        $('#hub-modal').hide();
    });

    // Close modal when clicking outside
    $(window).on('click', function(event) {
        const modal = $('#hub-modal');
        if (event.target === modal[0]) {
            modal.hide();
        }
    });

    // Load modal content for push/pull operations
    function loadModalContent(domain, action) {
        const modalBody = $('#hub-modal-body');
        modalBody.html(`
            <form id="sync-form" class="hub-sync-form">
                <input type="hidden" name="domain" value="${domain}">
                <input type="hidden" name="action_type" value="${action}">
                
                <div class="sync-options">
                    <h3>Select Sync Options</h3>
                    
                    <div class="sync-option">
                        <label>
                            <input type="radio" name="sync_scope" value="all" checked>
                            All Posts
                        </label>
                    </div>
                    
                    <div class="sync-option">
                        <label>
                            <input type="radio" name="sync_scope" value="category">
                            By Category
                        </label>
                        <div class="category-select" style="display:none; margin-left: 20px;">
                            <select name="category" multiple>
                                <option value="">Loading categories...</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="sync-option">
                        <label>
                            <input type="radio" name="sync_scope" value="specific">
                            Specific Posts
                        </label>
                        <div class="posts-select" style="display:none; margin-left: 20px;">
                            <select name="posts[]" multiple>
                                <option value="">Loading posts...</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="sync-actions">
                    <button type="submit" class="button button-primary">
                        ${action === 'push' ? 'Push Updates' : 'Pull Updates'}
                    </button>
                    <div class="sync-status"></div>
                </div>
            </form>
        `);

        // Handle radio button changes
        modalBody.find('input[name="sync_scope"]').on('change', function() {
            const scope = $(this).val();
            const categorySelect = modalBody.find('.category-select');
            const postsSelect = modalBody.find('.posts-select');
            
            categorySelect.hide();
            postsSelect.hide();
            
            if (scope === 'category') {
                categorySelect.show();
                loadCategories(domain);
            } else if (scope === 'specific') {
                postsSelect.show();
                loadPosts(domain);
            }
        });

        // Handle form submission
        modalBody.find('#sync-form').on('submit', function(e) {
            e.preventDefault();
            performSync($(this));
        });
    }

    // Load categories from the selected site
    function loadCategories(domain) {
        $.ajax({
            url: hubAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'hub_get_categories',
                domain: domain,
                nonce: hubAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    const select = $('.category-select select');
                    select.empty();
                    response.data.forEach(function(category) {
                        select.append(`<option value="${category.slug}">${category.name}</option>`);
                    });
                } else {
                    logError('Failed to load categories:', response.data);
                }
            },
            error: function(xhr, status, error) {
                logError('Error loading categories', error);
            }
        });
    }

    // Load posts from the selected site
    function loadPosts(domain) {
        $.ajax({
            url: hubAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'hub_get_posts',
                domain: domain,
                nonce: hubAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    const select = $('.posts-select select');
                    select.empty();
                    response.data.forEach(function(post) {
                        select.append(`<option value="${post.ID}">${post.post_title}</option>`);
                    });
                } else {
                    logError('Failed to load posts:', response.data);
                }
            },
            error: function(xhr, status, error) {
                logError('Error loading posts', error);
            }
        });
    }

    // Perform the actual sync operation
    function performSync(form) {
        const formData = form.serializeArray();
        const statusDiv = form.find('.sync-status');
        
        statusDiv.html('<span class="status-checking">Processing...</span>');
        
        $.ajax({
            url: hubAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'hub_perform_sync',
                sync_data: formData,
                nonce: hubAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    statusDiv.html('<span class="status-success">Success: ' + response.data + '</span>');
                } else {
                    statusDiv.html('<span class="status-error">Failed: ' + response.data + '</span>');
                }
            },
            error: function(xhr, status, error) {
                logError('Sync operation failed', error);
                statusDiv.html('<span class="status-error">Error: ' + error + '</span>');
            }
        });
    }
}); 