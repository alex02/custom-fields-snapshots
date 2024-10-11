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
    function handleAllCheckbox(allSelector, itemSelector) {
        var $allCheckbox = $(allSelector);
        var $itemCheckboxes = $(itemSelector);

        $allCheckbox.on('change', function() {
            $itemCheckboxes.prop('checked', this.checked).trigger('change');
        });

        $itemCheckboxes.on('change', function() {
            $allCheckbox.prop('checked', $itemCheckboxes.length === $itemCheckboxes.filter(':checked').length);
        });
    }

    handleAllCheckbox('.select-all-public-post-types', '.public-post-type-checkbox');
    handleAllCheckbox('.select-all-private-post-types', '.private-post-type-checkbox');

    // Handle post type checkboxes
    $('.post-type-checkbox').on('change', function() {
        var $postIdsSelection = $(this).closest('.post-type-selection').find('.post-ids-selection');
        $postIdsSelection.toggle(this.checked);
        $postIdsSelection.find('.post-id-checkbox, .select-all-posts').prop('checked', this.checked);
    });

    // Handle 'All' checkbox for post IDs
    $('.select-all-posts').on('change', function() {
        $(this).closest('.post-ids-selection').find('.post-id-checkbox').prop('checked', this.checked);
    });

    // Handle individual post ID checkboxes
    $('.post-id-checkbox').on('change', function() {
        var $postIdsSelection = $(this).closest('.post-ids-selection');
        var $selectAllPosts = $postIdsSelection.find('.select-all-posts');
        $selectAllPosts.prop('checked', $postIdsSelection.find('.post-id-checkbox:checked').length === $postIdsSelection.find('.post-id-checkbox').length);
    });

    // Validation function
    function validateExportForm() {
        var errors = [];
        
        if (!$('input[name="field_groups[]"]:checked').length) {
            errors.push(customFieldsSnapshots.L10n.selectFieldGroup);
        }
    
        var $selectedPostTypes = $('input[name="post_types[]"]:checked');
        var $selectedOptionsPages = $('.options-page-checkbox:checked');
        
        if (!$selectedPostTypes.length && !$selectedOptionsPages.length) {
            errors.push(customFieldsSnapshots.L10n.selectPostTypeOrOptions);
        } else {
            $selectedPostTypes.each(function() {
                var $this = $(this);
                var postType = $this.val();
                var postTypeLabel = $this.parent().text().trim();
                var $postIds = $('input[name="post_ids[' + postType + '][]"]');
                
                if (postType !== 'options' && !$postIds.filter(':checked').length && !$('.select-all-posts[data-post-type="' + postType + '"]').is(':checked')) {
                    errors.push(customFieldsSnapshots.L10n.selectPostId.replace('%s', postTypeLabel));
                }
            });
        }
    
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