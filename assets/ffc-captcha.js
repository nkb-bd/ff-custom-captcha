(function ($) {
    'use strict';

    function refresh($widget) {
        return $.get(window.ffcCaptcha.ajaxUrl, {
            action: 'ffc_refresh_captcha',
            type: $widget.data('type'),
            bg: $widget.data('bg'),
            color: $widget.data('color')
        }).done(function (res) {
            if (!res || !res.success || !res.data) {
                return;
            }
            if (res.data.ts) {
                $widget.find('.ffc-captcha-ts').val(res.data.ts);
            }
            if (res.data.token) {
                $widget.find('.ffc-captcha-token').val(res.data.token);
            }
            if (res.data.image) {
                $widget.find('.ffc-captcha-image').attr('src', res.data.image);
            } else if (res.data.text) {
                $widget.find('.ffc-captcha-text').text(res.data.text);
            }
        });
    }

    function refreshAll() {
        $('.ffc-captcha').each(function () {
            refresh($(this));
        });
    }

    $(refreshAll);

    $(document).on('click', '.ffc-captcha-refresh', function (e) {
        e.preventDefault();
        refresh($(this).closest('.ffc-captcha'));
    });

    $(document.body).on('fluentform_submission_success', refreshAll);
})(jQuery);
