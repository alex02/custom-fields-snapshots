jQuery(function($) {
    'use strict';

    var $selectAllFieldGroups = $('.custom-fields-snapshots .select-all-field-groups');
    var $fieldGroupCheckboxes = $('.custom-fields-snapshots .field-group-checkbox');
    var $exportValidationMessage = $('.custom-fields-snapshots .export-validation-message');

    // Handle 'All Field Groups' checkbox
    $selectAllFieldGroups.on('change', function() {
        $fieldGroupCheckboxes.prop('checked', this.checked);
    });

    $fieldGroupCheckboxes.on('change', function() {
        $selectAllFieldGroups.prop('checked', $fieldGroupCheckboxes.length === $fieldGroupCheckboxes.filter(':checked').length);
    });

    // Handle 'All' checkboxes for post types and options
    function handleAllCheckbox(allSelector, itemSelectors) {
        var $allCheckbox = $(allSelector);
        var $itemCheckboxes = $(itemSelectors);

        $allCheckbox.on('change', function() {
            $itemCheckboxes.prop('checked', this.checked).trigger('change');
        });

        $itemCheckboxes.on('change', function() {
            $allCheckbox.prop('checked', $itemCheckboxes.length === $itemCheckboxes.filter(':checked').length);
        });
    }

    handleAllCheckbox('.select-all-public-post-types', '.public-post-type-checkbox');
    handleAllCheckbox('.select-all-private-post-types', '.private-post-type-checkbox');
    handleAllCheckbox('.select-all-site-wide-data', '.options-checkbox,.users-checkbox,.user-roles-checkbox');

    // Handle post type checkboxes
    $('.post-type-checkbox').on('change', function() {
        var $postIdsSelection = $(this).closest('.post-type-selection,.user-selection').find('.post-ids-selection,.user-ids-selection,.user-roles-selection');
        $postIdsSelection.toggle(this.checked);
        $postIdsSelection.find('.post-id-checkbox,.select-all-posts,.select-all-users').prop('checked', this.checked);
    });

    // Handle 'All' checkbox for post IDs
    $('.select-all-posts,.select-all-users').on('change', function() {
        $(this).closest('.post-ids-selection,.user-ids-selection,.user-roles-selection').find('.post-id-checkbox').prop('checked', this.checked);
    });

    // Handle individual post ID checkboxes
    $('.post-id-checkbox').on('change', function() {
        var $postIdsSelection = $(this).closest('.post-ids-selection,.user-ids-selection,.user-roles-selection');
        var $selectAllPosts = $postIdsSelection.find('.select-all-posts,.select-all-users');
        var $postTypeCheckbox = $postIdsSelection.closest('.post-type-selection,.user-selection').find('.post-type-checkbox');
        
        var allChecked = $postIdsSelection.find('.post-id-checkbox:checked').length === $postIdsSelection.find('.post-id-checkbox').length;
        var anyChecked = $postIdsSelection.find('.post-id-checkbox:checked').length > 0;
        
        $selectAllPosts.prop('checked', allChecked);
        $postTypeCheckbox.prop('checked', anyChecked);

        // If no items are checked, hide the post IDs selection
        if (!anyChecked) {
            $postIdsSelection.hide();
        }
    });

    // Validation function
    function validateExportForm() {
        var errors = [];
        
        if (!$('input[name="field_groups[]"]:checked').length) {
            errors.push(customFieldsSnapshots.L10n.selectFieldGroup);
        }
    
        var $selectedPostTypes = $('input[name="post_types[]"]:checked');
        var $selectedOptions = $('input[name="options"]:checked');
        var $selectedUsers = $('input[name="users[]"]:checked');
        var $selectedUserRoles = $('input[name="user_roles[]"]:checked');

        if (!$selectedOptions.length && 
            !$selectedPostTypes.length && 
            !$selectedUsers.length && 
            !$selectedUserRoles.length) {
            errors.push(customFieldsSnapshots.L10n.selectDataTypes);
        }

        $selectedPostTypes.each(function() {
            var $this = $(this);
            var postType = $this.val();
            var postTypeLabel = $this.parent().text().trim();
            var $postIds = $('input[name="post_ids[' + postType + '][]"]');
            
            if (postType !== 'options' && !$postIds.filter(':checked').length && !$('.select-all-posts[data-post-type="' + postType + '"]').is(':checked')) {
                errors.push(customFieldsSnapshots.L10n.selectPostId.replace('%s', postTypeLabel));
            }
        });
    
        return errors;
    }

    // Display validation errors
    function displayValidationErrors(errors) {
        var errorHtml = errors.length ? '<div class="notice notice-error"><p>' + errors.join('</p><p>') + '</p></div>' : '';
        $exportValidationMessage.html(errorHtml);
    }

    // Handle form submission
    $('form').on('submit', function(e) {
        var errors = validateExportForm();
        if (errors.length) {
            e.preventDefault();
            displayValidationErrors(errors);
            $('html, body').animate({ scrollTop: $exportValidationMessage.offset().top - 100 }, 'slow');
        } else {
            $exportValidationMessage.html('');
        }
    });
});