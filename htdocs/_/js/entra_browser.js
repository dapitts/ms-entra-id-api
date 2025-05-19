(function($) 
{
    $(document).ready(function() 
    {
        $(document).on('change', '#entra-client-list', function() 
        {
            let selected_value = $(this).val();

            if (selected_value)
            {
                $('.entra-search-options').show();
            }
            else
            {
                reset_search_area();
            }
        });

        $(document).on('change', '#entra-search-type', function() 
        {
            let selected_value      = $(this).val(),
                search_term         = $('#entra-search-term'),
                search_term_visible = search_term.is(':visible'),
                search_value        = $('#entra-search-value'),
                search_value_len    = search_value.val().length,
                submit_btn          = $('#entra-search-submit-btn button'),
                submit_btn_visible  = submit_btn.is(':visible'),
                submit_btn_enabled  = submit_btn.is(':enabled');

            if (selected_value)
            {
                switch (selected_value)
                {
                    case 'list':
                    case 'enabled':
                    case 'disabled':
                        if (search_term_visible)
                        {
                            if (search_value_len)
                            {
                                search_value.val('');
                            }

                            search_term.hide();
                        }

                        if (submit_btn_visible)
                        {
                            if (submit_btn_enabled)
                            {
                                reset_search_results();
                                reset_data_display_area();
                            }
                            else
                            {
                                submit_btn.prop('disabled', false);
                            }
                        }
                        else
                        {
                            submit_btn.parent().show();
                            submit_btn.prop('disabled', false);
                        }

                        break;
                    case 'email_contains':
                    case 'email_equals':
                    case 'name_contains':
                    case 'name_starts_with':
                        if (search_term_visible)
                        {
                            if (search_value_len)
                            {
                                search_value.val('');
                            }
                        }
                        else
                        {
                            search_term.show();
                        }

                        if (submit_btn_visible)
                        {
                            if (submit_btn_enabled)
                            {
                                submit_btn.prop('disabled', true);
                                reset_search_results();
                                reset_data_display_area();
                            }
                        }
                        else
                        {
                            submit_btn.parent().show();
                        }

                        break;
                }
            }
            else
            {
                if (search_term_visible)
                {
                    if (search_value_len)
                    {
                        search_value.val('');
                    }

                    search_term.hide();
                }

                if (submit_btn_visible)
                {
                    if (submit_btn_enabled)
                    {
                        submit_btn.prop('disabled', true);
                        reset_search_results();
                        reset_data_display_area();
                    }

                    submit_btn.parent().hide();
                }
            }
        });

        $(document).on('keyup change input', '#entra-search-value', function() 
        {
            let search_value_len    = $(this).val().length,
                submit_btn          = $('#entra-search-submit-btn button'),
                submit_btn_disabled = submit_btn.is(':disabled');

            if (!submit_btn_disabled)
            {
                reset_search_results();
                reset_data_display_area();
            }

            if (search_value_len)
            {
                if (submit_btn_disabled)
                {
                    submit_btn.prop('disabled', false);
                }
            }
            else
            {
                submit_btn.prop('disabled', true);
            }
        });

        $(document).on('submit', '#entra-search-form', function() 
        {  			
            reset_search_results();
            reset_data_display_area();

            $(this).ajaxSubmit({ 
                beforeSubmit:  showGeneralRequest,
                success:       show_search_results,
                error:         showGeneralError,
                type:          'POST',
                timeout:       30000 
            });

            return false; 
        });

        $(document).on('submit', '#entra-action-form', function() 
        {
            $(this).ajaxSubmit({ 
                beforeSubmit:  showGeneralRequest,
                success:       show_action_results,
                error:         showGeneralError,
                type:          'POST',
                timeout:       30000 
            });

            return false; 
        });

        $(document).on('click', '.entra-search-results button[data-user-id]', function () 
        {
            let btn     = $(this),
                user_id = btn.data('user-id'),
                client 	= btn.data('client'),
                url     = '/ms-entra-browser/get-details/'+client+'/'+user_id;

            reset_data_display_area();

            btn.button('loading');

            $.get(url, function(data, status) 
            {
                if (status) 
                {
                    let response_obj = jQuery.parseJSON(data);

                    if (response_obj.success)
                    {
                        let results         = response_obj.results,
                            rendered_html   = results.map(get_entra_html).join('');

                        $('#entra-tab-contents').html(rendered_html);

                        $('.entra-browser-placeholder').removeClass('show');
                        $('.entra-data-display-area').show();
                        $('#entra-tabs > li:first-child > a').tab('show');

                        $('#entra-actions').selectpicker({
                            style: 'btn-select',
                            showTick: true,
                            noneSelectedText: '- - - Select - - -',
                        });

                        btn.removeClass('btn-default').addClass('btn-success').button('reset');
                    }
                    else
                    {
                        show_message(response_obj.message);

                        if (btn.hasClass('btn-success'))
                        {
                            btn.removeClass('btn-success').addClass('btn-default').button('reset');
                        }
                        else
                        {
                            btn.button('reset');
                        }
                    }
                }
            });
        });

        $(document).on('change', '#entra-actions', function() 
        {
            let selected_value  = $(this).val(),
                submit_btn      = $('#entra-action-submit-btn button');

            if (selected_value)
            {
                submit_btn.prop('disabled', false);
            }
            else
            {
                submit_btn.prop('disabled', true);
            }
        });
    });

    // =================================================
    // BLOCK Functions - Start Here
    // =================================================

    get_entra_html = function(data) 
    {
        let entra_detail_template   = $('#entra-detail-template').html();
        return Mustache.render(entra_detail_template, data);
    };

    get_entra_row_template = function(data) 
    {
        let entra_row_template  = $('#entra-row-template').html();		
        return Mustache.render(entra_row_template, data);
    };

    show_search_results = function(response) 
    {
        let response_obj    = jQuery.parseJSON(response),
            success         = response_obj.success,
            results_element = $('.entra-search-results');

        if (success) 
        {			
            results_element.find('tbody').html('');

            if (response_obj.result_count)
            {
                let results         = response_obj.results,
                    rendered_html   = results.map(get_entra_row_template).join('');

                results_element.find('tbody').append(rendered_html);
            }
            else
            {
                results_element.find('tbody').append('<tr><td colspan="3" class="text-left">No Results Found</td></tr>');
            }										

            results_element.show();
        }
        else
        {
            if (response_obj.csrf_name)
            {
                $('input[name="'+response_obj.csrf_name+'"]').val(response_obj.csrf_value);
            }

            show_message(response_obj.message);
        }

        $('#entra-search-form button[type="submit"]').button('reset');
    };

    show_action_results = function(response) 
    {
        let response_obj    = jQuery.parseJSON(response),
            success         = response_obj.success,
            message         = response_obj.message,
            user_id         = response_obj.user_id,
            btn_selector    = '.entra-search-results button[data-user-id="'+user_id+'"]';

        if (success) 
        {			
            show_message(message, 'success');
            $(btn_selector).trigger('click');
        }
        else
        {
            if (response_obj.csrf_name)
            {
                $('input[name="'+response_obj.csrf_name+'"]').val(response_obj.csrf_value);
            }

            show_message(message);
        }

        $('#entra-action-form button[type="submit"]').button('reset');
    };

    reset_search_area = function() 
    {
        let search_options      = $('.entra-search-options'),
            search_type         = $('#entra-search-type'),
            search_type_len     = search_type.val().length,
            search_term         = $('#entra-search-term'),
            search_term_visible = search_term.is(':visible'),
            search_value        = $('#entra-search-value'),
            search_value_len    = search_value.val().length,
            submit_btn          = $('#entra-search-submit-btn button'),
            submit_btn_visible  = submit_btn.is(':visible'),
            submit_btn_enabled  = submit_btn.is(':enabled');

        if (search_type_len)
        {
            search_type.val('');
            search_type.selectpicker('refresh');

            if (search_term_visible)
            {
                if (search_value_len)
                {
                    search_value.val('');
                }

                search_term.hide();
            }

            if (submit_btn_visible)
            {
                if (submit_btn_enabled)
                {
                    submit_btn.prop('disabled', true);
                    reset_search_results();
                    reset_data_display_area();
                }

                submit_btn.parent().hide();
            }
        }

        search_options.hide();
    };

    show_message = function(message, type = 'error') 
    {
        let message_type = type[0].toUpperCase() + type.slice(1).toLowerCase(),
            modal_alert  = $('#modal-alert-container > div');

        if (type === 'error')
        {
            if (modal_alert.hasClass('alert-success'))
            {
                modal_alert.removeClass('alert-success').addClass('alert-error');
            }
        }
        else if (type === 'success')
        {
            if (modal_alert.hasClass('alert-error'))
            {
                modal_alert.removeClass('alert-error').addClass('alert-success');
            }
        }

        $('.alert-heading').text(message_type);
        $('.modal-alert-content').html(message);
        $('#modal-alert-container').show();

        setTimeout(function(){closeModalAlertBox();}, 3000);
    }

    reset_search_results = function() 
    {
        let entra_search_results    = $('.entra-search-results'),
            search_results_visible  = entra_search_results.is(':visible');

        if (search_results_visible)
        {
            entra_search_results.hide();
            entra_search_results.find('tbody').html('');
        }
    };

    reset_data_display_area = function() 
    {
        let entra_browser_placeholder   = $('.entra-browser-placeholder'),
            browser_placeholder_hidden  = entra_browser_placeholder.is(':hidden');

        if (browser_placeholder_hidden)
        {
            $('.entra-data-display-area').hide();
            $('#entra-tab-contents').html('');
            entra_browser_placeholder.addClass('show');
        }
    };

})(jQuery);