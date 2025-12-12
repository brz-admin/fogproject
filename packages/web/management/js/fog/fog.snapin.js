(function($) {
    checkboxToggleSearchListPages();
    validatorOpts = {
        submitHandler: submithandlerfunc,
        rules: {
            name: {
                required: true,
                minlength: 1,
                maxlength: 255,
                regex: /^[-\w!@#$%^()'{}\\\.~ ]{1,255}$/
            }
        }
    };
    if ($_GET['sub'] == 'membership') return;
    $('input[type=file]').on('change', function(event) {
        files = event.target.files;
    });
    setupTimeoutElement('#add, #update, #updategroups, #primarysel, #groupdel', '', 1000);
    $('.snapinname-input').each(function(e) {
        if ($(this).is(':visible')) {
            $(this).on('keyup change blur', function(e) {
                validator = $(this).parents('form').validate({
                    rules: {
                        name: {
                            required: true,
                            minlength: 1,
                            maxlength: 255,
                            regex: /^[-\w!@#$%^()'{}\\\.~ ]{1,255}$/
                        }
                    }
                });
                return validator.element(this);
            }).trigger('change');
        }
    });
    $('#argTypes').on('change', function() {
        if ($('option:selected',this).attr('value')) $("input[name=rw]").val($('option:selected',this).attr('value'));
        $("input[name=rwa]").val($('option:selected',this).attr('rwargs'));
        $("input[name=args]").val($('option:selected',this).attr('args'));
        updateCmdStore();
    });
    $('#packTypes').on('change', function() {
        $("input[name=rw]").val($('option:selected',this).attr('file'));
        $("input[name=rwa]").val($('option:selected',this).attr('args'));
    });
    updateCmdStore();
    $('.cmdlet1,.cmdlet2,.cmdlet3,.cmdlet4').on('change keyup',function(e) {
        updateCmdStore();
    });
    $('.cmdlet3').on('change', function(e) {
        updateCmdStore();
    })
    $('.snapinpack-input').on('change blur',function(e) {
        if (this.value == 1) {
            $('.packnotemplate, .packnochangerw, .packnochangerwa, .packhide').hide();
            $('.packtemplate, .packchangerw, .packchangerwa').show();
        } else {
            $('.packnotemplate, .packnochangerw, .packnochangerwa, .packhide').show();
            $('.packtemplate, .packchangerw, .packchangerwa').hide();
        }
        updateCmdStore();
    });
    $('.snapinpack-input').trigger('change');
    // Handle snapin source type toggle (file vs URL)
    $('.snapintype-input').on('change blur', function(e) {
        var snapinType = $(this).val();
        if (snapinType === 'url') {
            $('.snapinfile-section').hide();
            $('.snapinurl-section').show();
            // Clear file input when switching to URL
            $('.cmdlet3').val('');
            $('.filedisp').val('');
        } else {
            $('.snapinfile-section').show();
            $('.snapinurl-section').hide();
            // Clear URL inputs when switching to file
            $('.snapinurl-input').val('');
        }
    });
    // Initialize based on current selection
    $('.snapintype-input').trigger('change');
    
    // Handle gitea server field - update URL placeholder when gitea server changes
    $('.snapingiteaserver-input').on('change blur', function(e) {
        var giteaServer = $(this).val();
        var urlInput = $('.snapinurl-input');
        if (giteaServer && urlInput.val().startsWith('https://gitea.company.com')) {
            // If URL is the default placeholder, update it with the new gitea server
            urlInput.val(giteaServer + '/user/repo/raw/branch/script.ps1');
        }
    });
})(jQuery);
function updateCmdStore() {
    if (typeof $('.cmdlet3').val() === 'undefined') return;
    cmd1 = $('.cmdlet1').val();
    cmd2 = $('.cmdlet2').val();
    cmd3 = $('.cmdlet3').val();
    cmd4 = $('.cmdlet4').val();
    test = $('[type="file"]')
    if (test.length < 1) {
        cmd3 = $('select.cmdlet3').val();
    } else {
        test = test[0].files.length;
        if (test < 1) cmd3 = $('select.cmdlet3').val();
        else cmd3 = $('[type="file"]')[0].files[0].name;
    }
    if ($('.snapinpack-input').val() == 1) {
        cmd3 = '';
    }
    var snapCMD = [cmd1,cmd2,cmd3,cmd4];
    $('.snapincmd').val(snapCMD.join(' ')).css({width: '100%',border: 0,resize: 'none'});
}
