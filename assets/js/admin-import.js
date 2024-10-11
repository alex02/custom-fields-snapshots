jQuery(function($) {
    'use strict';

    var $uploadArea = $('.custom-fields-snapshots .upload-area');
    var $fileInput = $('.custom-fields-snapshots .import-file');
    var $fileInfo = $('.custom-fields-snapshots .file-info');
    var $fileName = $('.custom-fields-snapshots .file-name');
    var $removeFile = $('.custom-fields-snapshots .remove-file');
    var $importForm = $('.custom-fields-snapshots .import-form');
    var $importValidationMessage = $('.custom-fields-snapshots .import-validation-message');
    var $importResult = $('.custom-fields-snapshots .import-result');
    var $eventLog = $('.custom-fields-snapshots .event-log');

    function handleFiles(files) {
        if (files && files.length > 0) {
            var file = files[0];
            if (file.type === 'application/json') {
                $fileName.text(file.name);
                $fileInfo.show();
                $uploadArea.hide();
                $fileInput[0].files = files;
            } else {
                alert(customFieldsSnapshots.L10n.invalidFileType);
            }
        }
    }

    function displayValidationErrors(errors) {
        var errorHtml = errors.length ? '<div class="notice notice-error"><p>' + errors.join('</p><p>') + '</p></div>' : '';
        $importValidationMessage.html(errorHtml).show();
    }

    $uploadArea.on({
        'click': function(e) {
            e.preventDefault();
            e.stopPropagation();
            $fileInput.click();
        },
        'dragover dragenter': function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).css('border-color', '#0073aa');
        },
        'dragleave dragend drop': function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).css('border-color', '#b4b9be');
        },
        'drop': function(e) {
            e.preventDefault();
            e.stopPropagation();
            handleFiles(e.originalEvent.dataTransfer.files);
        }
    });

    $fileInput.on({
        'click': function(e) {
            e.stopPropagation();
        },
        'change': function(e) {
            e.stopPropagation();
            handleFiles(this.files);
        }
    });

    $removeFile.on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $fileInput.val('');
        $fileInfo.hide();
        $uploadArea.show();
        $importValidationMessage.add($importResult).add($eventLog).html('').hide();
    });

    $importForm.on('submit', function(e) {
        e.preventDefault();

        if (!$('.custom-fields-snapshots #rollback-changes-input').is(':checked') && !confirm(customFieldsSnapshots.L10n.rollbackDisabledConfirmation)) {
            return false;
        }

        var formData = new FormData(this);
        var file = $fileInput[0].files[0];
        var errors = [];

        if (!file) {
            errors.push(customFieldsSnapshots.L10n.noFileSelected);
        } else if (file.type !== 'application/json') {
            errors.push(customFieldsSnapshots.L10n.invalidFileType);
        }

        if (errors.length) {
            displayValidationErrors(errors);
            $importResult.add($eventLog).hide();
            return;
        }

        var $submitButton = $(this).find('input[type="submit"]');
        $submitButton.prop('disabled', true).val(customFieldsSnapshots.L10n.importingText);

        formData.append('action', 'custom_fields_snapshots_import');
        formData.append('nonce', customFieldsSnapshots.nonce);

        $.ajax({
            url: customFieldsSnapshots.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $importValidationMessage.html('');
                if (response.success) {
                    $importResult.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>').show();
                    $fileInput.val('');
                    $fileInfo.hide();
                    $uploadArea.show();
                } else {
                    $importResult.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>').show();
                }
                
                if (response.data.log) {
                    $eventLog.html('<h4>' + customFieldsSnapshots.L10n.eventLogText + '</h4><pre>' + response.data.log + '</pre>').show();
                } else {
                    $eventLog.hide();
                }
            },
            error: function() {
                $importValidationMessage.html('');
                $importResult.html('<div class="notice notice-error"><p>' + customFieldsSnapshots.L10n.ajaxError + '</p></div>').show();
                $eventLog.hide();
            },
            complete: function() {
                $submitButton.prop('disabled', false).val(customFieldsSnapshots.L10n.importText);
            }
        });
    });
});