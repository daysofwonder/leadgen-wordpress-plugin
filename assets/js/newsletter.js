jQuery(function ($) {
  $(".newsletter-subscribe").click(function (e) {
    e.preventDefault();
    var response_placeholder = $("div.asmodee-newsletter-response");
    response_placeholder.html("");
    var input_email = $("#asmodee-newsletter-email");
    var button = $(this),
      data = {
        action: "asmodee_newsletter_subscribe",
        assets_url: asmodee_newsletter.assets_url,
        nonce_newsletter: asmodee_newsletter.nonce_newsletter,
        email: input_email.val(),
      };
    $.ajax({
      url: asmodee_newsletter.ajaxurl,
      data: data,
      type: "POST",
      beforeSend: function (xhr) {
        response_placeholder.html(
          '<p class="asmodee-newsletter-loading">' +
            asmodee_newsletter.txt_loading +
            '<img src="' +
            asmodee_newsletter.assets_url +
            'loader.svg" width="30" alt="" class="loader"></p>'
        );
        button.prop("disabled", true);
      },
      complete: function (xhr) {
        button.prop("disabled", false);
      },
      success: function (response) {
        if (response) {
          if (response.success === false && response.data.error_description) {
            response_placeholder.html(
              '<p class="asmodee-newsletter-error">' +
                response.data.error_description +
                "</p>"
            );
          }
          if (response.success === false && response.data.status === 409) {
            response_placeholder.html(
              '<p class="asmodee-newsletter-error">' +
                asmodee_newsletter.txt_user_already_registred +
                "</p>"
            );
          }
          if (
            response.success === false &&
            response.data.status !== 409 &&
            response.data.error_description === undefined
          ) {
            response_placeholder.html(
              '<p class="asmodee-newsletter-error">' +
                asmodee_newsletter.txt_user_error +
                "</p>"
            );
          }
          if (response.success === true) {
            input_email.hide();
            button.hide();
            response_placeholder.html(
              '<div class="asmodee-newsletter-success"><span class="icon"></span><p>' +
                asmodee_newsletter.txt_user_subscribed +
                '</p><p class="asmodee-newsletter-success">' +
                asmodee_newsletter.txt_user_subscribed_confirm +
                "</p></div>"
            );
            const print_and_play = $(
              ".asmodee-print-and-play-newsletter-offers p.latest-news"
            );
            const print_and_play_skip = $("span.newsletter-skip");
            if (
              print_and_play !== undefined &&
              print_and_play_skip !== undefined
            ) {
              print_and_play.hide();
              print_and_play_skip.hide();
            }
          }
        }
      },
    });
  });
});
