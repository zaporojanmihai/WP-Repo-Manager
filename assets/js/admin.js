jQuery(document).ready(function($) {
    // Load branches for a repository
    function loadBranches(repoUrl, token, $select) {
        if (!repoUrl) {
            $select.html('<option value="">Enter repository URL</option>').prop('disabled', true);
            return;
        }

        if (!repoUrl.match(/github\.com\/[^\/]+\/[^\/\s]+/)) {
            $select.html('<option value="">Invalid GitHub URL format</option>').prop('disabled', true);
            return;
        }

        $select.prop('disabled', true).html('<option value="">Loading branches...</option>');
        
        $.ajax({
            url: wprm_admin.ajaxurl,
            type: 'POST',
            data: {
                action: 'wprm_get_branches',
                repo_url: repoUrl,
                token: token,
                _ajax_nonce: wprm_admin.nonce
            },
            success: function(response) {
                if (response.success && response.data.branches) {
                    var options = '<option value="">Select a branch</option>';
                    response.data.branches.forEach(function(branch) {
                        options += '<option value="' + branch + '">' + branch + '</option>';
                    });
                    $select.html(options).prop('disabled', false);
                } else {
                    var error = response.data || 'Failed to load branches';
                    
                    // Display SAML error in a more readable format
                    if (error.includes('SAML SSO enabled')) {
                        error = error.replace(/\\n/g, '<br>');
                        var $message = $('#wprm-add-repo-form .wprm-message');
                        $message.html('<div class="notice notice-error"><p>' + error + '</p></div>');
                        $select.html('<option value="">Configure SSO access first</option>').prop('disabled', true);
                    } else {
                        $select.html('<option value="">' + error + '</option>').prop('disabled', true);
                    }
                }
            },
            error: function(xhr, status, error) {
                var errorMsg = xhr.responseJSON && xhr.responseJSON.data 
                    ? xhr.responseJSON.data 
                    : 'Failed to connect to server';
                $select.html('<option value="">' + errorMsg + '</option>').prop('disabled', true);
            }
        });
    }

    // Handle repository URL or token changes in add form
    $('#repo_url, #token').on('change', function() {
        var repoUrl = $('#repo_url').val().trim();
        var token = $('#token').val().trim();
        var $branchSelect = $('#branch');
        var $typeSelect = $('#type');
        
        // Reset branch selection
        $branchSelect.html('<option value="">Select a branch</option>').prop('disabled', true);
        
        // Only load branches if we have a repo URL
        if (repoUrl) {
            loadBranches(repoUrl, token, $branchSelect);
        }
    });

    // Handle form submission
    $('#wprm-add-repo-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submitButton = $form.find('button[type="submit"]');
        var $message = $form.find('.wprm-message');
        
        var repoUrl = $('#repo_url').val().trim();
        var branch = $('#branch').val();
        var token = $('#token').val().trim();
        var type = $('#type').val();
        
        if (!repoUrl || !branch || !type) {
            $message.html('<div class="notice notice-error"><p>Please fill in all required fields</p></div>');
            return;
        }
        
        $submitButton.prop('disabled', true);
        $message.html('<div class="notice notice-info"><p>Saving repository...</p></div>');
        
        $.ajax({
            url: wprm_admin.ajaxurl,
            type: 'POST',
            data: {
                action: 'wprm_save_repository',
                repo_url: repoUrl,
                branch: branch,
                token: token,
                type: type,
                _ajax_nonce: wprm_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $message.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                    $form[0].reset();
                    $('#branch').html('<option value="">Select a branch</option>').prop('disabled', true);
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    $message.html('<div class="notice notice-error"><p>' + (response.data || 'Failed to save repository') + '</p></div>');
                }
            },
            error: function(xhr, status, error) {
                var errorMsg = xhr.responseJSON && xhr.responseJSON.data 
                    ? xhr.responseJSON.data 
                    : 'Failed to save repository';
                $message.html('<div class="notice notice-error"><p>' + errorMsg + '</p></div>');
            },
            complete: function() {
                $submitButton.prop('disabled', false);
            }
        });
    });

    // Function to add new history item
    function addHistoryItem(historyItem) {
        var $historyTable = $('.wprm-pull-history tbody');
        var $noHistory = $('.wprm-no-history');
        
        // Create new row
        var $newRow = $('<tr></tr>');
        $newRow.append('<td>' + historyItem.repo_url + '</td>');
        $newRow.append('<td>' + historyItem.type + '</td>');
        $newRow.append('<td>' + historyItem.branch + '</td>');
        $newRow.append('<td>' + historyItem.timestamp + '</td>');
        $newRow.append('<td>' + (historyItem.status ? '<span style="color: #46b450;">Success</span>' : '<span style="color: #dc3232;">Failed</span>') + '</td>');
        
        // Add to top of table
        $historyTable.prepend($newRow);
        
        // Hide "no history" message if present
        if ($noHistory.length) {
            $noHistory.hide();
        }
    }

    // Handle repository pull
    $('.wprm-pull-repo').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var repoId = $button.data('repo-id');
        var $row = $button.closest('tr');
        var $message = $row.find('.wprm-repo-message');
        
        // Show waiting message and disable button while pulling
        $message.text('Waiting...').css('color', '#555');
        $button.prop('disabled', true);
        
        $.ajax({
            url: wprm_admin.ajaxurl,
            type: 'POST',
            data: {
                action: 'wprm_pull_repository',
                repo_id: repoId,
                _ajax_nonce: wprm_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $message.text('Success!').css('color', '#46b450');
                    // Update last pull time
                    $row.find('td:eq(3)').text(response.data.last_pull || 'Just now');
                    
                    // Add to pull history if provided
                    if (response.data.history_item) {
                        addHistoryItem(response.data.history_item);
                    }
                    
                    // Clear message after 5 seconds
                    setTimeout(function() {
                        $message.fadeOut(400, function() {
                            $(this).text('').css('color', '').show();
                        });
                    }, 5000);
                } else {
                    $message.text(response.data || 'Failed to pull repository').css('color', '#dc3232');
                }
            },
            error: function(xhr) {
                var errorMsg = xhr.responseJSON && xhr.responseJSON.data 
                    ? xhr.responseJSON.data 
                    : 'Failed to pull repository';
                $message.text(errorMsg).css('color', '#dc3232');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });

    // Handle branch change button click using event delegation
    $(document).on('click', '.wprm-change-branch', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $branchCell = $button.closest('td');
        var $row = $button.closest('tr');
        var repoId = $row.find('.wprm-pull-repo').data('repo-id');
        
        // Get repository details from data attributes
        var repoUrl = $row.data('repo-url');
        var token = $row.data('repo-token');
        var currentBranch = $button.parent().text().trim().replace('Change', '').trim();
        
        // Create select element
        var $select = $('<select></select>').addClass('wprm-branch-select');
        $select.data('original-branch', currentBranch);
        $select.data('repo-id', repoId);
        
        // Add cancel button
        var $cancelBtn = $('<button type="button" class="button button-small wprm-cancel-branch-change">Cancel</button>');
        var $saveBtn = $('<button type="button" class="button button-small button-primary wprm-save-branch-change">Save</button>');
        
        // Replace content with select and buttons
        $branchCell.html('').append($select, ' ', $saveBtn, ' ', $cancelBtn);
        
        // Load branches
        loadBranches(repoUrl, token, $select);
        
        // Pre-select current branch once loaded
        var checkInterval = setInterval(function() {
            if ($select.find('option').length > 1) {  // More than just the placeholder
                $select.val(currentBranch);
                clearInterval(checkInterval);
            }
        }, 100);
    });

    // Handle cancel button click using event delegation
    $(document).on('click', '.wprm-cancel-branch-change', function(e) {
        e.preventDefault();
        var $button = $(this);
        var $branchCell = $button.closest('td');
        var originalBranch = $branchCell.find('select').data('original-branch');
        $branchCell.html(originalBranch + ' <button type="button" class="button button-small wprm-change-branch">Change</button>');
    });

    // Handle save button click using event delegation
    $(document).on('click', '.wprm-save-branch-change', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $select = $button.siblings('select');
        var repoId = $select.data('repo-id');
        var newBranch = $select.val();
        var originalBranch = $select.data('original-branch');
        
        if (!newBranch || newBranch === originalBranch) {
            // No change or invalid selection, just cancel
            $button.siblings('.wprm-cancel-branch-change').click();
            return;
        }
        
        $button.prop('disabled', true).text('Saving...');
        
        $.ajax({
            url: wprm_admin.ajaxurl,
            type: 'POST',
            data: {
                action: 'wprm_update_branch',
                repo_id: repoId,
                branch: newBranch,
                _ajax_nonce: wprm_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    var $branchCell = $button.closest('td');
                    $branchCell.html(newBranch + ' <button type="button" class="button button-small wprm-change-branch">Change</button>');
                } else {
                    alert(response.data || 'Failed to update branch');
                    $button.siblings('.wprm-cancel-branch-change').click();
                }
            },
            error: function() {
                alert('Failed to update branch');
                $button.siblings('.wprm-cancel-branch-change').click();
            }
        });
    });

    // Handle repository deletion
    $('.wprm-delete-repo').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var repoId = $button.data('repo-id');
        var $row = $button.closest('tr');
        
        if (!confirm('Are you sure you want to delete this repository? This action cannot be undone.')) {
            return;
        }
        
        $button.prop('disabled', true);
        
        $.ajax({
            url: wprm_admin.ajaxurl,
            type: 'POST',
            data: {
                action: 'wprm_delete_repository',
                repo_id: repoId,
                _ajax_nonce: wprm_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Remove the row with animation
                    $row.fadeOut(400, function() {
                        $(this).remove();
                        // If no repositories left, refresh to show empty state
                        if ($('.wprm-repo-list tbody tr').length === 0) {
                            window.location.reload();
                        }
                    });
                } else {
                    alert(response.data || 'Failed to delete repository');
                    $button.prop('disabled', false);
                }
            },
            error: function() {
                alert('Failed to delete repository');
                $button.prop('disabled', false);
            }
        });
    });
});